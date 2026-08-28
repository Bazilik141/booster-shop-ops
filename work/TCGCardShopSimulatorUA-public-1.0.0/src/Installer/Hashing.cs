using System.Security.Cryptography;

namespace TCGCardShopSimulatorUA.Installer;

internal static class Hashing
{
    public static string Sha256File(string path)
    {
        using var stream = File.OpenRead(path);
        var hash = SHA256.HashData(stream);
        return Convert.ToHexString(hash).ToLowerInvariant();
    }
}
