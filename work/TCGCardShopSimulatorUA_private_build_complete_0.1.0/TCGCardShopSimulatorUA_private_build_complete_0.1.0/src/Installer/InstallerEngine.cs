using System.Diagnostics;
using System.Text.Json;

namespace TCGCardShopSimulatorUA.Installer;

internal sealed class InstallerEngine
{
    private const string ManifestRelative = "BepInEx/config/TCGCardShopSimulatorUA/ownership-manifest.json";
    private const string StateRootRelative = "BepInEx/config/TCGCardShopSimulatorUA";
    private const string BaselineBepInExVersion = "5.4.23.5";
    private static readonly JsonSerializerOptions JsonOptions = new() { WriteIndented = true };

    public string ManifestPath(string gameRoot) => PathSafety.SafeCombine(gameRoot, ManifestRelative);

    public InstallPlan BuildPlan(string gameRoot, PayloadExtractor payload)
    {
        EnsureGameRoot(gameRoot);
        var plan = new InstallPlan();
        var old = LoadManifest(gameRoot);
        var oldByPath = old?.Files.ToDictionary(x => x.RelativePath, StringComparer.OrdinalIgnoreCase)
                        ?? new Dictionary<string, ManagedFile>(StringComparer.OrdinalIgnoreCase);

        var payloadFiles = payload.EnumerateFiles().ToList();
        var payloadPaths = new HashSet<string>(payloadFiles.Select(x => x.RelativePath), StringComparer.OrdinalIgnoreCase);
        var hasBepInEx = File.Exists(Path.Combine(gameRoot, "BepInEx", "core", "BepInEx.dll"));
        var version = DetectBepInExVersion(gameRoot);
        var exactBaseline = hasBepInEx && version?.StartsWith(BaselineBepInExVersion, StringComparison.OrdinalIgnoreCase) == true;

        if (hasBepInEx && !exactBaseline)
            plan.Warnings.Add($"Знайдено наявний BepInEx ({version ?? "версію не визначено"}), який відрізняється від перевіреного {BaselineBepInExVersion}. Спільні runtime-файли не видаляються без backup; перед записом перегляньте план.");

        foreach (var (rel, source) in payloadFiles)
        {
            var dest = PathSafety.SafeCombine(gameRoot, rel);
            var newHash = Hashing.Sha256File(source);
            if (exactBaseline && IsSharedRuntimePath(rel) && !oldByPath.ContainsKey(rel) && File.Exists(dest))
            {
                var currentSharedHash = Hashing.Sha256File(dest);
                if (currentSharedHash.Equals(newHash, StringComparison.OrdinalIgnoreCase))
                {
                    plan.Actions.Add(new(PlanActionKind.SkipSame, rel, "Наявний shared runtime ідентичний payload; файл не береться у власність інсталятора."));
                    continue;
                }
                plan.Warnings.Add($"Shared runtime має версію BepInEx {BaselineBepInExVersion}, але файл відрізняється від перевіреного payload: {rel}. Перед заміною буде створено backup.");
            }
            if (oldByPath.TryGetValue(rel, out var previous))
            {
                if (previous.OriginalExisted && !IsValidOriginalBackup(gameRoot, previous))
                {
                    plan.HasBlockingConflict = true;
                    plan.Warnings.Add($"Початковий backup відсутній або пошкоджений: {rel}. Оновлення зупинено, щоб не втратити можливість безпечного uninstall.");
                    continue;
                }
                if (!File.Exists(dest))
                {
                    plan.HasBlockingConflict = true;
                    plan.Warnings.Add($"Керований файл зник після попередньої інсталяції: {rel}. Оновлення зупинено, щоб не втратити стан.");
                    continue;
                }
                var currentHash = Hashing.Sha256File(dest);
                if (!currentHash.Equals(previous.InstalledSha256, StringComparison.OrdinalIgnoreCase))
                {
                    plan.HasBlockingConflict = true;
                    plan.Warnings.Add($"Керований файл змінено після інсталяції: {rel}. Його не буде перезаписано автоматично.");
                    continue;
                }
                plan.Actions.Add(currentHash.Equals(newHash, StringComparison.OrdinalIgnoreCase)
                    ? new(PlanActionKind.SkipSame, rel, "Вже актуальний.")
                    : new(PlanActionKind.UpdateOwned, rel, "Оновити файл, зберігши початковий backup із попереднього manifest."));
            }
            else if (!File.Exists(dest))
            {
                plan.Actions.Add(new(PlanActionKind.Add, rel, "Додати новий файл."));
            }
            else
            {
                var currentHash = Hashing.Sha256File(dest);
                plan.Actions.Add(currentHash.Equals(newHash, StringComparison.OrdinalIgnoreCase)
                    ? new(PlanActionKind.SkipSame, rel, "Такий самий файл уже існує; інсталятор не бере його у власність.")
                    : new(PlanActionKind.Replace, rel, "Замінити після timestamped backup оригіналу."));
            }
        }

        foreach (var previous in oldByPath.Values.Where(x => !payloadPaths.Contains(x.RelativePath)))
        {
            var dest = PathSafety.SafeCombine(gameRoot, previous.RelativePath);
            if (previous.OriginalExisted && !IsValidOriginalBackup(gameRoot, previous))
            {
                plan.HasBlockingConflict = true;
                plan.Warnings.Add($"Для застарілого керованого файла відсутній або пошкоджений початковий backup: {previous.RelativePath}. Оновлення зупинено.");
                continue;
            }
            if (!File.Exists(dest))
            {
                plan.Actions.Add(new(PlanActionKind.RemoveObsoleteOwned, previous.RelativePath,
                    previous.OriginalExisted
                        ? "Керований файл відсутній; під час оновлення буде відновлено його початковий backup."
                        : "Керований файл уже відсутній; запис буде прибрано з manifest."));
                continue;
            }
            var currentHash = Hashing.Sha256File(dest);
            if (!currentHash.Equals(previous.InstalledSha256, StringComparison.OrdinalIgnoreCase))
            {
                plan.HasBlockingConflict = true;
                plan.Warnings.Add($"Застарілий керований файл змінено користувачем: {previous.RelativePath}. Оновлення зупинено для ручного вирішення.");
            }
            else
            {
                plan.Actions.Add(new(PlanActionKind.RemoveObsoleteOwned, previous.RelativePath, "Файл більше не входить у payload: відновити початковий стан/видалити."));
            }
        }

        return plan;
    }

    public void InstallOrUpdate(string gameRoot, PayloadExtractor payload, string modVersion, Action<string>? log = null)
    {
        EnsureWritableState(gameRoot);
        var plan = BuildPlan(gameRoot, payload);
        if (plan.HasBlockingConflict)
            throw new InvalidOperationException("Є конфлікти або пошкоджені backup-файли. Автоматичне оновлення скасовано.");

        var old = LoadManifest(gameRoot);
        var oldByPath = old?.Files.ToDictionary(x => x.RelativePath, StringComparer.OrdinalIgnoreCase)
                        ?? new Dictionary<string, ManagedFile>(StringComparer.OrdinalIgnoreCase);
        var now = DateTimeOffset.Now;
        var stamp = now.ToString("yyyyMMdd-HHmmss") + "-" + Guid.NewGuid().ToString("N")[..8];
        var stateRoot = PathSafety.SafeCombine(gameRoot, StateRootRelative);
        var backupRoot = Path.Combine(stateRoot, "backups", stamp);
        var transactionRoot = Path.Combine(stateRoot, "transactions", stamp);
        var transactionFilesRoot = Path.Combine(transactionRoot, "files");
        Directory.CreateDirectory(stateRoot);

        var payloadFiles = payload.EnumerateFiles().ToDictionary(x => x.RelativePath, x => x.FullPath, StringComparer.OrdinalIgnoreCase);
        var newManifest = new OwnershipManifest { ModVersion = modVersion, InstalledAt = now };
        var exactBaseline = DetectBepInExVersion(gameRoot)?.StartsWith(BaselineBepInExVersion, StringComparison.OrdinalIgnoreCase) == true;
        var mutationPaths = plan.Actions
            .Where(x => x.Kind != PlanActionKind.SkipSame)
            .Select(x => x.RelativePath)
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .ToList();

        // Snapshot every destination before the first mutation. If any later write fails,
        // restore this snapshot so a failed install does not leave unmanaged partial state.
        var snapshot = CreateMutationSnapshot(gameRoot, mutationPaths, transactionFilesRoot);
        var committed = false;
        var rollbackSucceeded = false;

        try
        {
            // First retire files no longer in payload. Keep their original backups until commit.
            foreach (var previous in oldByPath.Values.Where(x => !payloadFiles.ContainsKey(x.RelativePath)))
                RestoreOrDeleteManagedFile(gameRoot, previous, log, deleteBackup: false);

            foreach (var kv in payloadFiles)
            {
                var rel = kv.Key;
                var source = kv.Value;
                var dest = PathSafety.SafeCombine(gameRoot, rel);
                var installedHash = Hashing.Sha256File(source);
                if (exactBaseline && IsSharedRuntimePath(rel) && !oldByPath.ContainsKey(rel) && File.Exists(dest))
                {
                    var currentSharedHash = Hashing.Sha256File(dest);
                    if (currentSharedHash.Equals(installedHash, StringComparison.OrdinalIgnoreCase))
                    {
                        log?.Invoke($"Пропущено ідентичний shared runtime, який не належить цьому інсталятору: {rel}");
                        continue;
                    }
                }

                if (oldByPath.TryGetValue(rel, out var previous))
                {
                    var currentHash = File.Exists(dest) ? Hashing.Sha256File(dest) : "";
                    if (!currentHash.Equals(previous.InstalledSha256, StringComparison.OrdinalIgnoreCase))
                        throw new InvalidOperationException($"Файл змінено після попередньої інсталяції: {rel}");
                    if (previous.OriginalExisted && !IsValidOriginalBackup(gameRoot, previous))
                        throw new InvalidOperationException($"Початковий backup відсутній або пошкоджений: {rel}");

                    if (currentHash.Equals(installedHash, StringComparison.OrdinalIgnoreCase))
                    {
                        newManifest.Files.Add(new ManagedFile
                        {
                            RelativePath = rel,
                            OriginalExisted = previous.OriginalExisted,
                            OriginalSha256 = previous.OriginalSha256,
                            BackupRelativePath = previous.BackupRelativePath,
                            InstalledSha256 = installedHash
                        });
                        log?.Invoke($"Без змін: {rel}");
                        continue;
                    }

                    Directory.CreateDirectory(Path.GetDirectoryName(dest)!);
                    File.Copy(source, dest, overwrite: true);
                    newManifest.Files.Add(new ManagedFile
                    {
                        RelativePath = rel,
                        OriginalExisted = previous.OriginalExisted,
                        OriginalSha256 = previous.OriginalSha256,
                        BackupRelativePath = previous.BackupRelativePath,
                        InstalledSha256 = installedHash
                    });
                    log?.Invoke($"Оновлено: {rel}");
                    continue;
                }

                if (File.Exists(dest))
                {
                    var currentHash = Hashing.Sha256File(dest);
                    if (currentHash.Equals(installedHash, StringComparison.OrdinalIgnoreCase))
                    {
                        log?.Invoke($"Вже існує ідентичний, не взято у власність: {rel}");
                        continue;
                    }

                    var backupDest = Path.Combine(backupRoot, rel.Replace('/', Path.DirectorySeparatorChar));
                    Directory.CreateDirectory(Path.GetDirectoryName(backupDest)!);
                    File.Copy(dest, backupDest, overwrite: false);
                    Directory.CreateDirectory(Path.GetDirectoryName(dest)!);
                    File.Copy(source, dest, overwrite: true);
                    newManifest.Files.Add(new ManagedFile
                    {
                        RelativePath = rel,
                        OriginalExisted = true,
                        OriginalSha256 = currentHash,
                        BackupRelativePath = PathSafety.NormalizeRelative(Path.GetRelativePath(gameRoot, backupDest)),
                        InstalledSha256 = installedHash
                    });
                    log?.Invoke($"Замінено з backup: {rel}");
                }
                else
                {
                    Directory.CreateDirectory(Path.GetDirectoryName(dest)!);
                    File.Copy(source, dest, overwrite: false);
                    newManifest.Files.Add(new ManagedFile
                    {
                        RelativePath = rel,
                        OriginalExisted = false,
                        InstalledSha256 = installedHash
                    });
                    log?.Invoke($"Додано: {rel}");
                }
            }

            WriteManifestAtomic(gameRoot, newManifest);
            committed = true;
        }
        catch (Exception ex)
        {
            var rollbackErrors = RollbackMutationSnapshot(gameRoot, snapshot, transactionFilesRoot);
            if (rollbackErrors.Count > 0)
            {
                throw new InvalidOperationException(
                    "Встановлення перервано, а автоматичний rollback завершився не повністю. " +
                    $"Snapshot залишено для ручного відновлення: {transactionRoot}. " +
                    string.Join(" | ", rollbackErrors), ex);
            }

            rollbackSucceeded = true;
            TryDeleteDirectory(backupRoot);
            throw;
        }
        finally
        {
            if (committed || rollbackSucceeded)
                TryDeleteDirectory(transactionRoot);
        }

        // Commit succeeded. Backups belonging only to files retired by this update are no longer needed.
        foreach (var previous in oldByPath.Values.Where(x => !payloadFiles.ContainsKey(x.RelativePath) && x.OriginalExisted))
        {
            if (!string.IsNullOrWhiteSpace(previous.BackupRelativePath))
                TryDelete(PathSafety.SafeCombine(gameRoot, previous.BackupRelativePath));
        }
        CleanupEmptyStateDirs(gameRoot);
    }

    public List<string> Uninstall(string gameRoot, Action<string>? log = null)
    {
        EnsureWritableState(gameRoot);
        var manifest = LoadManifest(gameRoot) ?? throw new InvalidOperationException("Manifest цієї локалізації не знайдено. Без нього безпечний uninstall неможливий.");
        var unresolved = new List<ManagedFile>();
        var messages = new List<string>();
        var backupsToDelete = new List<string>();
        var stamp = DateTimeOffset.Now.ToString("yyyyMMdd-HHmmss") + "-" + Guid.NewGuid().ToString("N")[..8];
        var stateRoot = PathSafety.SafeCombine(gameRoot, StateRootRelative);
        var transactionRoot = Path.Combine(stateRoot, "transactions", "uninstall-" + stamp);
        var transactionFilesRoot = Path.Combine(transactionRoot, "files");
        Directory.CreateDirectory(stateRoot);
        var snapshot = CreateMutationSnapshot(gameRoot, manifest.Files.Select(x => x.RelativePath), transactionFilesRoot);
        var committed = false;
        var rollbackSucceeded = false;

        try
        {
            foreach (var file in manifest.Files)
            {
                var dest = PathSafety.SafeCombine(gameRoot, file.RelativePath);
                if (!File.Exists(dest))
                {
                    if (file.OriginalExisted)
                    {
                        unresolved.Add(file);
                        messages.Add($"Потрібне ручне вирішення: {file.RelativePath} відсутній, але до інсталяції існував.");
                    }
                    continue;
                }

                var currentHash = Hashing.Sha256File(dest);
                if (!currentHash.Equals(file.InstalledSha256, StringComparison.OrdinalIgnoreCase))
                {
                    unresolved.Add(file);
                    messages.Add($"Не змінено: {file.RelativePath} — файл редагувався після інсталяції.");
                    continue;
                }

                if (file.OriginalExisted)
                {
                    if (string.IsNullOrWhiteSpace(file.BackupRelativePath))
                    {
                        unresolved.Add(file);
                        messages.Add($"Не вдалося відновити {file.RelativePath}: у manifest немає backup path.");
                        continue;
                    }
                    var backup = PathSafety.SafeCombine(gameRoot, file.BackupRelativePath);
                    if (!File.Exists(backup) || (file.OriginalSha256 is not null && !Hashing.Sha256File(backup).Equals(file.OriginalSha256, StringComparison.OrdinalIgnoreCase)))
                    {
                        unresolved.Add(file);
                        messages.Add($"Не вдалося відновити {file.RelativePath}: backup відсутній або пошкоджений.");
                        continue;
                    }
                    File.Copy(backup, dest, overwrite: true);
                    backupsToDelete.Add(backup);
                    log?.Invoke($"Відновлено: {file.RelativePath}");
                }
                else
                {
                    File.Delete(dest);
                    log?.Invoke($"Видалено: {file.RelativePath}");
                }
            }

            if (unresolved.Count == 0)
            {
                var manifestPath = ManifestPath(gameRoot);
                if (File.Exists(manifestPath)) File.Delete(manifestPath);
                messages.Add("Локалізацію видалено; початкові файли відновлено там, де вони існували.");
            }
            else
            {
                manifest.Files = unresolved;
                WriteManifestAtomic(gameRoot, manifest);
                messages.Add("Частину файлів залишено без змін для безпеки. Manifest збережено тільки для невирішених записів.");
            }
            committed = true;
        }
        catch (Exception ex)
        {
            var rollbackErrors = RollbackMutationSnapshot(gameRoot, snapshot, transactionFilesRoot);
            if (rollbackErrors.Count > 0)
            {
                throw new InvalidOperationException(
                    "Видалення перервано, а автоматичний rollback завершився не повністю. " +
                    $"Snapshot залишено для ручного відновлення: {transactionRoot}. " +
                    string.Join(" | ", rollbackErrors), ex);
            }
            rollbackSucceeded = true;
            throw;
        }
        finally
        {
            if (committed || rollbackSucceeded)
                TryDeleteDirectory(transactionRoot);
        }

        foreach (var backup in backupsToDelete)
            TryDelete(backup);
        CleanupEmptyStateDirs(gameRoot);
        return messages;
    }

    private static void RestoreOrDeleteManagedFile(string gameRoot, ManagedFile previous, Action<string>? log, bool deleteBackup)
    {
        var dest = PathSafety.SafeCombine(gameRoot, previous.RelativePath);
        if (File.Exists(dest))
        {
            var current = Hashing.Sha256File(dest);
            if (!current.Equals(previous.InstalledSha256, StringComparison.OrdinalIgnoreCase))
                throw new InvalidOperationException($"Не можна прибрати застарілий файл, бо його змінено: {previous.RelativePath}");
        }

        if (previous.OriginalExisted)
        {
            var backup = PathSafety.SafeCombine(gameRoot, previous.BackupRelativePath ?? throw new InvalidDataException("Missing backup path."));
            if (!File.Exists(backup)) throw new FileNotFoundException("Backup missing.", backup);
            if (previous.OriginalSha256 is not null && !Hashing.Sha256File(backup).Equals(previous.OriginalSha256, StringComparison.OrdinalIgnoreCase))
                throw new InvalidDataException($"Backup hash mismatch: {previous.RelativePath}");
            Directory.CreateDirectory(Path.GetDirectoryName(dest)!);
            File.Copy(backup, dest, overwrite: true);
            if (deleteBackup) TryDelete(backup);
            log?.Invoke($"Відновлено застарілий файл: {previous.RelativePath}");
        }
        else if (File.Exists(dest))
        {
            File.Delete(dest);
            log?.Invoke($"Прибрано застарілий файл: {previous.RelativePath}");
        }
    }

    private static Dictionary<string, bool> CreateMutationSnapshot(string gameRoot, IEnumerable<string> relativePaths, string snapshotRoot)
    {
        var result = new Dictionary<string, bool>(StringComparer.OrdinalIgnoreCase);
        foreach (var rel in relativePaths)
        {
            var normalized = PathSafety.NormalizeRelative(rel);
            if (result.ContainsKey(normalized)) continue;
            var dest = PathSafety.SafeCombine(gameRoot, normalized);
            var existed = File.Exists(dest);
            result[normalized] = existed;
            if (!existed) continue;

            var snap = Path.Combine(snapshotRoot, normalized.Replace('/', Path.DirectorySeparatorChar));
            Directory.CreateDirectory(Path.GetDirectoryName(snap)!);
            File.Copy(dest, snap, overwrite: false);
        }
        return result;
    }

    private static List<string> RollbackMutationSnapshot(string gameRoot, IReadOnlyDictionary<string, bool> snapshot, string snapshotRoot)
    {
        var errors = new List<string>();
        foreach (var entry in snapshot.Reverse())
        {
            try
            {
                var dest = PathSafety.SafeCombine(gameRoot, entry.Key);
                if (entry.Value)
                {
                    var snap = Path.Combine(snapshotRoot, entry.Key.Replace('/', Path.DirectorySeparatorChar));
                    if (!File.Exists(snap))
                        throw new FileNotFoundException("Transaction snapshot missing.", snap);
                    Directory.CreateDirectory(Path.GetDirectoryName(dest)!);
                    File.Copy(snap, dest, overwrite: true);
                }
                else if (File.Exists(dest))
                {
                    File.Delete(dest);
                }
            }
            catch (Exception rollbackEx)
            {
                errors.Add($"{entry.Key}: {rollbackEx.Message}");
            }
        }
        return errors;
    }

    private static bool IsValidOriginalBackup(string gameRoot, ManagedFile file)
    {
        if (!file.OriginalExisted) return true;
        if (string.IsNullOrWhiteSpace(file.BackupRelativePath)) return false;
        try
        {
            var backup = PathSafety.SafeCombine(gameRoot, file.BackupRelativePath);
            if (!File.Exists(backup)) return false;
            return file.OriginalSha256 is null || Hashing.Sha256File(backup).Equals(file.OriginalSha256, StringComparison.OrdinalIgnoreCase);
        }
        catch { return false; }
    }

    private static bool IsSharedRuntimePath(string rel)
    {
        rel = rel.Replace('\\', '/');
        return rel.Equals(".doorstop_version", StringComparison.OrdinalIgnoreCase)
            || rel.Equals("doorstop_config.ini", StringComparison.OrdinalIgnoreCase)
            || rel.Equals("winhttp.dll", StringComparison.OrdinalIgnoreCase)
            || rel.Equals("version.dll", StringComparison.OrdinalIgnoreCase)
            || rel.StartsWith("BepInEx/core/", StringComparison.OrdinalIgnoreCase);
    }

    private static string? DetectBepInExVersion(string gameRoot)
    {
        var dll = Path.Combine(gameRoot, "BepInEx", "core", "BepInEx.dll");
        if (!File.Exists(dll)) return null;
        try { return FileVersionInfo.GetVersionInfo(dll).FileVersion; }
        catch { return null; }
    }

    private static OwnershipManifest? LoadManifest(string gameRoot)
    {
        var path = PathSafety.SafeCombine(gameRoot, ManifestRelative);
        if (!File.Exists(path)) return null;
        return JsonSerializer.Deserialize<OwnershipManifest>(File.ReadAllText(path));
    }

    private static void WriteManifestAtomic(string gameRoot, OwnershipManifest manifest)
    {
        var path = PathSafety.SafeCombine(gameRoot, ManifestRelative);
        Directory.CreateDirectory(Path.GetDirectoryName(path)!);
        var tmp = path + ".tmp";
        try
        {
            File.WriteAllText(tmp, JsonSerializer.Serialize(manifest, JsonOptions));
            File.Move(tmp, path, overwrite: true);
        }
        finally
        {
            TryDelete(tmp);
        }
    }

    private static void EnsureGameRoot(string gameRoot)
    {
        if (!GameDetection.IsValidGameRoot(gameRoot))
            throw new InvalidOperationException("Обрана папка не схожа на корінь TCG Card Shop Simulator: потрібні Card Shop Simulator.exe і Card Shop Simulator_Data\\.");
    }

    private static void EnsureWritableState(string gameRoot)
    {
        EnsureGameRoot(gameRoot);
        if (ProcessGuard.IsGameRunning())
            throw new InvalidOperationException("TCG Card Shop Simulator зараз запущено. Закрийте гру і повторіть операцію.");
        try
        {
            var probe = Path.Combine(gameRoot, $".tcg-ua-write-test-{Guid.NewGuid():N}.tmp");
            File.WriteAllText(probe, "test");
            File.Delete(probe);
        }
        catch (UnauthorizedAccessException)
        {
            throw new InvalidOperationException("Windows не дозволяє запис у цю папку Steam. Закрийте інсталятор і запустіть його від імені адміністратора.");
        }
    }

    private static void TryDelete(string path)
    {
        try { if (File.Exists(path)) File.Delete(path); } catch { }
    }

    private static void TryDeleteDirectory(string path)
    {
        try { if (Directory.Exists(path)) Directory.Delete(path, recursive: true); } catch { }
    }

    private static void CleanupEmptyStateDirs(string gameRoot)
    {
        var root = PathSafety.SafeCombine(gameRoot, StateRootRelative);
        try
        {
            if (!Directory.Exists(root)) return;
            foreach (var dir in Directory.EnumerateDirectories(root, "*", SearchOption.AllDirectories).OrderByDescending(x => x.Length))
                if (!Directory.EnumerateFileSystemEntries(dir).Any()) Directory.Delete(dir);
            if (!Directory.EnumerateFileSystemEntries(root).Any()) Directory.Delete(root);
        }
        catch { }
    }
}
