using System.Diagnostics;

namespace TCGCardShopSimulatorUA.Installer;

internal static class ProcessGuard
{
    public static bool IsGameRunning()
    {
        try
        {
            return Process.GetProcesses().Any(p =>
            {
                try { return p.ProcessName.Equals("Card Shop Simulator", StringComparison.OrdinalIgnoreCase); }
                catch { return false; }
            });
        }
        catch { return false; }
    }
}
