using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.IO.Compression;
using System.Linq;
using System.Net.Sockets;
using System.Reflection;
using System.Security.Cryptography;
using System.Text;
using System.Windows.Forms;

namespace BoosterShop.SerhiyUpdater
{
    internal static class Program
    {
        private const string PayloadResource = "Booster3DP.Payload.zip";
        private const string ManifestResource = "Booster3DP.Manifest.sha256";
        private const string ProductTitle = "Оновлення Booster 3D — Сергій";

        [STAThread]
        private static int Main(string[] args)
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);
            bool quiet = args.Any(delegate(string value) { return String.Equals(value, "--quiet", StringComparison.OrdinalIgnoreCase); });
            bool noRemember = args.Any(delegate(string value) { return String.Equals(value, "--no-remember", StringComparison.OrdinalIgnoreCase); });
            try
            {
                string explicitTarget = ArgumentValue(args, "--target");
                string target = ResolveTarget(explicitTarget, quiet);
                if (String.IsNullOrEmpty(target)) return 2;
                if (PortInUse(3107)) throw new InvalidOperationException("Дашборд ще запущений. Закрий його, зачекай до 5 хвилин і знову відкрий оновлювач.");

                string backup = ApplyUpdate(target);
                if (!noRemember) RememberTarget(target);
                if (!quiet)
                {
                    DialogResult result = MessageBox.Show(
                        "Оновлення встановлено. URL і токен не змінювалися.\r\n\r\nРезервна копія: " + backup + "\r\n\r\nЗапустити дашборд зараз?",
                        ProductTitle,
                        MessageBoxButtons.YesNo,
                        MessageBoxIcon.Information);
                    if (result == DialogResult.Yes) LaunchDashboard(target);
                }
                return 0;
            }
            catch (Exception error)
            {
                if (!quiet) MessageBox.Show(error.Message, ProductTitle, MessageBoxButtons.OK, MessageBoxIcon.Error);
                return 1;
            }
        }

        private static string ArgumentValue(string[] args, string name)
        {
            for (int index = 0; index + 1 < args.Length; index += 1)
            {
                if (String.Equals(args[index], name, StringComparison.OrdinalIgnoreCase)) return args[index + 1];
            }
            return null;
        }

        private static string ResolveTarget(string explicitTarget, bool quiet)
        {
            if (!String.IsNullOrWhiteSpace(explicitTarget)) return ValidateTarget(explicitTarget);
            string executableFolder = AppDomain.CurrentDomain.BaseDirectory;
            if (IsInstallRoot(executableFolder)) return Path.GetFullPath(executableFolder);

            string remembered = ReadRememberedTarget();
            if (!String.IsNullOrEmpty(remembered) && IsInstallRoot(remembered)) return Path.GetFullPath(remembered);
            if (quiet) throw new InvalidOperationException("Valid --target installation folder is required in quiet mode.");

            using (FolderBrowserDialog dialog = new FolderBrowserDialog())
            {
                dialog.Description = "Оберіть папку Booster 3D — ту, де лежить «Запустити.bat».";
                dialog.ShowNewFolderButton = false;
                if (dialog.ShowDialog() != DialogResult.OK) return null;
                return ValidateTarget(dialog.SelectedPath);
            }
        }

        private static string ValidateTarget(string path)
        {
            string full = Path.GetFullPath(path);
            if (!IsInstallRoot(full)) throw new InvalidOperationException("Це не папка встановленого дашборда. Обери папку, де разом лежать «Запустити.bat», папки app і runtime.");
            return full;
        }

        private static bool IsInstallRoot(string path)
        {
            if (String.IsNullOrWhiteSpace(path)) return false;
            return File.Exists(Path.Combine(path, "Запустити.bat"))
                && File.Exists(Path.Combine(path, "app", "server.mjs"))
                && File.Exists(Path.Combine(path, "runtime", "node.exe"));
        }

        private static bool PortInUse(int port)
        {
            using (TcpClient client = new TcpClient())
            {
                try
                {
                    IAsyncResult attempt = client.BeginConnect("127.0.0.1", port, null, null);
                    if (!attempt.AsyncWaitHandle.WaitOne(350)) return false;
                    client.EndConnect(attempt);
                    return client.Connected;
                }
                catch
                {
                    return false;
                }
            }
        }

        private static string ApplyUpdate(string target)
        {
            string tempRoot = Path.Combine(Path.GetTempPath(), "Booster3DP-update-" + Guid.NewGuid().ToString("N"));
            string payloadZip = Path.Combine(tempRoot, "payload.zip");
            string staging = Path.Combine(tempRoot, "staging");
            Directory.CreateDirectory(staging);
            try
            {
                CopyResourceToFile(PayloadResource, payloadZip);
                ExtractSafely(payloadZip, staging);
                Dictionary<string, string> manifest = ReadManifest();
                ValidateStaging(staging, manifest);

                string backup = Path.Combine(target, "_update_backups", "update-" + DateTime.Now.ToString("yyyyMMdd-HHmmss", CultureInfo.InvariantCulture));
                List<string> newTargets = new List<string>();
                List<string> backedUp = new List<string>();
                Directory.CreateDirectory(backup);
                try
                {
                    foreach (KeyValuePair<string, string> item in manifest)
                    {
                        string relative = item.Key.Replace('/', Path.DirectorySeparatorChar);
                        string source = SafeCombine(staging, relative);
                        string destination = SafeCombine(target, relative);
                        string destinationFolder = Path.GetDirectoryName(destination);
                        if (!Directory.Exists(destinationFolder)) Directory.CreateDirectory(destinationFolder);
                        if (File.Exists(destination))
                        {
                            string backupFile = SafeCombine(backup, relative);
                            string backupFolder = Path.GetDirectoryName(backupFile);
                            if (!Directory.Exists(backupFolder)) Directory.CreateDirectory(backupFolder);
                            File.Copy(destination, backupFile, true);
                            backedUp.Add(relative);
                        }
                        else
                        {
                            newTargets.Add(destination);
                        }
                        File.Copy(source, destination, true);
                    }
                    ValidateInstalled(target, manifest);
                }
                catch
                {
                    foreach (string destination in newTargets)
                    {
                        try { if (File.Exists(destination)) File.Delete(destination); } catch { }
                    }
                    foreach (string relative in backedUp)
                    {
                        try { File.Copy(SafeCombine(backup, relative), SafeCombine(target, relative), true); } catch { }
                    }
                    throw new InvalidOperationException("Оновлення не завершилося. Попередні файли відновлено з резервної копії.");
                }
                return backup;
            }
            finally
            {
                try { if (Directory.Exists(tempRoot)) Directory.Delete(tempRoot, true); } catch { }
            }
        }

        private static void CopyResourceToFile(string name, string destination)
        {
            using (Stream input = Assembly.GetExecutingAssembly().GetManifestResourceStream(name))
            {
                if (input == null) throw new InvalidOperationException("В оновлювачі відсутній пакет файлів.");
                using (FileStream output = new FileStream(destination, FileMode.Create, FileAccess.Write, FileShare.None)) input.CopyTo(output);
            }
        }

        private static Dictionary<string, string> ReadManifest()
        {
            Dictionary<string, string> result = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
            using (Stream stream = Assembly.GetExecutingAssembly().GetManifestResourceStream(ManifestResource))
            {
                if (stream == null) throw new InvalidOperationException("В оновлювачі відсутній список перевірки.");
                using (StreamReader reader = new StreamReader(stream, Encoding.UTF8, true))
                {
                    string line;
                    while ((line = reader.ReadLine()) != null)
                    {
                        if (String.IsNullOrWhiteSpace(line)) continue;
                        string[] parts = line.Split(new char[] { '\t' }, 2);
                        if (parts.Length != 2 || parts[0].Length != 64) throw new InvalidOperationException("Пошкоджено список перевірки оновлення.");
                        string relative = NormalizedRelativePath(parts[1]);
                        if (result.ContainsKey(relative)) throw new InvalidOperationException("Повторений файл у списку оновлення.");
                        result.Add(relative, parts[0].ToLowerInvariant());
                    }
                }
            }
            if (result.Count == 0) throw new InvalidOperationException("Пакет оновлення порожній.");
            return result;
        }

        private static void ExtractSafely(string zipPath, string staging)
        {
            using (ZipArchive archive = ZipFile.OpenRead(zipPath))
            {
                foreach (ZipArchiveEntry entry in archive.Entries)
                {
                    if (String.IsNullOrEmpty(entry.Name)) continue;
                    string relative = NormalizedRelativePath(entry.FullName);
                    string destination = SafeCombine(staging, relative.Replace('/', Path.DirectorySeparatorChar));
                    string folder = Path.GetDirectoryName(destination);
                    if (!Directory.Exists(folder)) Directory.CreateDirectory(folder);
                    using (Stream input = entry.Open())
                    using (FileStream output = new FileStream(destination, FileMode.CreateNew, FileAccess.Write, FileShare.None)) input.CopyTo(output);
                }
            }
        }

        private static string NormalizedRelativePath(string path)
        {
            string relative = String.Concat(path ?? String.Empty).Replace('\\', '/').TrimStart('/');
            if (relative.Length == 0 || relative.Contains(":")) throw new InvalidOperationException("Некоректний шлях у пакеті оновлення.");
            string[] segments = relative.Split('/');
            if (segments.Any(delegate(string segment) { return segment.Length == 0 || segment == "." || segment == ".."; })) throw new InvalidOperationException("Небезпечний шлях у пакеті оновлення.");
            return String.Join("/", segments);
        }

        private static string SafeCombine(string root, string relative)
        {
            string fullRoot = Path.GetFullPath(root).TrimEnd(Path.DirectorySeparatorChar) + Path.DirectorySeparatorChar;
            string combined = Path.GetFullPath(Path.Combine(fullRoot, relative));
            if (!combined.StartsWith(fullRoot, StringComparison.OrdinalIgnoreCase)) throw new InvalidOperationException("Файл оновлення виходить за межі папки дашборда.");
            return combined;
        }

        private static void ValidateStaging(string staging, Dictionary<string, string> manifest)
        {
            List<string> files = Directory.GetFiles(staging, "*", SearchOption.AllDirectories)
                .Select(delegate(string path) { return path.Substring(staging.TrimEnd(Path.DirectorySeparatorChar).Length + 1).Replace('\\', '/'); })
                .OrderBy(delegate(string path) { return path; }, StringComparer.OrdinalIgnoreCase).ToList();
            List<string> expected = manifest.Keys.OrderBy(delegate(string path) { return path; }, StringComparer.OrdinalIgnoreCase).ToList();
            if (!files.SequenceEqual(expected, StringComparer.OrdinalIgnoreCase)) throw new InvalidOperationException("Вміст оновлення не відповідає списку перевірки.");
            ValidateInstalled(staging, manifest);
        }

        private static void ValidateInstalled(string root, Dictionary<string, string> manifest)
        {
            foreach (KeyValuePair<string, string> item in manifest)
            {
                string file = SafeCombine(root, item.Key.Replace('/', Path.DirectorySeparatorChar));
                if (!File.Exists(file) || !String.Equals(Sha256(file), item.Value, StringComparison.OrdinalIgnoreCase)) throw new InvalidOperationException("Не пройшла перевірка файлу: " + item.Key);
            }
        }

        private static string Sha256(string file)
        {
            using (SHA256 algorithm = SHA256.Create())
            using (FileStream stream = File.OpenRead(file))
            {
                return BitConverter.ToString(algorithm.ComputeHash(stream)).Replace("-", String.Empty).ToLowerInvariant();
            }
        }

        private static string StateFile()
        {
            return Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "BoosterShop3DP", "install-path.txt");
        }

        private static string ReadRememberedTarget()
        {
            try { return File.Exists(StateFile()) ? File.ReadAllText(StateFile(), Encoding.UTF8).Trim() : null; }
            catch { return null; }
        }

        private static void RememberTarget(string target)
        {
            string file = StateFile();
            string folder = Path.GetDirectoryName(file);
            if (!Directory.Exists(folder)) Directory.CreateDirectory(folder);
            File.WriteAllText(file, target, new UTF8Encoding(false));
        }

        private static void LaunchDashboard(string target)
        {
            Process.Start(new ProcessStartInfo { FileName = Path.Combine(target, "Запустити.bat"), WorkingDirectory = target, UseShellExecute = true });
        }
    }
}
