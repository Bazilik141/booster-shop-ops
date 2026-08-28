using System.IO.Compression;
using System.Reflection;

namespace TCGCardShopSimulatorUA.Installer;

internal sealed class PayloadExtractor : IDisposable
{
    public string DirectoryPath { get; }

    public PayloadExtractor()
    {
        DirectoryPath = Path.Combine(Path.GetTempPath(), "TCGCardShopSimulatorUA", Guid.NewGuid().ToString("N"));
        Directory.CreateDirectory(DirectoryPath);

        var asm = Assembly.GetExecutingAssembly();
        var resource = asm.GetManifestResourceNames().FirstOrDefault(n => n.EndsWith("payload.zip", StringComparison.OrdinalIgnoreCase));
        if (resource is null)
            throw new InvalidOperationException("В інсталятор не вбудовано payload.zip. Перезберіть реліз через build.ps1.");

        using var stream = asm.GetManifestResourceStream(resource) ?? throw new InvalidOperationException("Не вдалося відкрити payload.zip.");
        using var zip = new ZipArchive(stream, ZipArchiveMode.Read);
        var seen = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
        foreach (var entry in zip.Entries)
        {
            if (string.IsNullOrEmpty(entry.Name)) continue;
            var rel = PathSafety.NormalizeRelative(entry.FullName);
            if (!seen.Add(rel))
                throw new InvalidDataException($"Payload містить дубльований шлях: {rel}");
            var dest = PathSafety.SafeCombine(DirectoryPath, rel);
            Directory.CreateDirectory(Path.GetDirectoryName(dest)!);
            entry.ExtractToFile(dest, overwrite: false);
        }
    }

    public IEnumerable<(string RelativePath, string FullPath)> EnumerateFiles()
    {
        foreach (var file in Directory.EnumerateFiles(DirectoryPath, "*", SearchOption.AllDirectories))
        {
            var rel = Path.GetRelativePath(DirectoryPath, file);
            yield return (PathSafety.NormalizeRelative(rel), file);
        }
    }

    public void Dispose()
    {
        try { if (Directory.Exists(DirectoryPath)) Directory.Delete(DirectoryPath, recursive: true); }
        catch { /* temp cleanup is best-effort */ }
    }
}
