using Microsoft.Win32;
using System.Text.RegularExpressions;

namespace TCGCardShopSimulatorUA.Installer;

internal static class GameDetection
{
    public const string GameExe = "Card Shop Simulator.exe";
    public const string GameData = "Card Shop Simulator_Data";

    public static bool IsValidGameRoot(string? root)
    {
        if (string.IsNullOrWhiteSpace(root)) return false;
        return File.Exists(Path.Combine(root, GameExe)) && Directory.Exists(Path.Combine(root, GameData));
    }

    public static IEnumerable<string> FindSteamCandidates()
    {
        var steamRoots = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
        foreach (var keyPath in new[] { @"Software\Valve\Steam", @"Software\WOW6432Node\Valve\Steam" })
        {
            try
            {
                using var key = Registry.CurrentUser.OpenSubKey(keyPath);
                var p = key?.GetValue("SteamPath") as string;
                if (!string.IsNullOrWhiteSpace(p)) steamRoots.Add(p);
            }
            catch { }
        }

        foreach (var hive in new[] { Registry.LocalMachine })
        {
            foreach (var keyPath in new[] { @"SOFTWARE\WOW6432Node\Valve\Steam", @"SOFTWARE\Valve\Steam" })
            {
                try
                {
                    using var key = hive.OpenSubKey(keyPath);
                    var p = key?.GetValue("InstallPath") as string;
                    if (!string.IsNullOrWhiteSpace(p)) steamRoots.Add(p);
                }
                catch { }
            }
        }

        var libraries = new HashSet<string>(steamRoots, StringComparer.OrdinalIgnoreCase);
        foreach (var steam in steamRoots)
        {
            var vdf = Path.Combine(steam, "steamapps", "libraryfolders.vdf");
            if (!File.Exists(vdf)) continue;
            try
            {
                var text = File.ReadAllText(vdf);
                foreach (Match m in Regex.Matches(text, "\\\"path\\\"\\s+\\\"(?<p>[^\\\"]+)\\\"", RegexOptions.IgnoreCase))
                {
                    var p = m.Groups["p"].Value.Replace("\\\\", "\\");
                    if (!string.IsNullOrWhiteSpace(p)) libraries.Add(p);
                }
            }
            catch { }
        }

        foreach (var lib in libraries)
        {
            var candidate = Path.Combine(lib, "steamapps", "common", "TCG Card Shop Simulator");
            if (IsValidGameRoot(candidate)) yield return candidate;
        }
    }
}
