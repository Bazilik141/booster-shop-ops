namespace TCGCardShopSimulatorUA.Installer;

internal static class PathSafety
{
    public static string SafeCombine(string root, string relativePath)
    {
        var normalized = relativePath.Replace('/', Path.DirectorySeparatorChar)
                                     .Replace('\\', Path.DirectorySeparatorChar);
        if (Path.IsPathRooted(normalized))
            throw new InvalidDataException($"Payload contains rooted path: {relativePath}");

        var fullRoot = Path.GetFullPath(root).TrimEnd(Path.DirectorySeparatorChar) + Path.DirectorySeparatorChar;
        var full = Path.GetFullPath(Path.Combine(root, normalized));
        if (!full.StartsWith(fullRoot, StringComparison.OrdinalIgnoreCase))
            throw new InvalidDataException($"Payload path escapes game folder: {relativePath}");
        return full;
    }

    public static string NormalizeRelative(string path)
    {
        if (string.IsNullOrWhiteSpace(path))
            throw new InvalidDataException("Payload contains an empty path.");

        var normalized = path.Replace('\\', '/');
        if (normalized.StartsWith('/') || Path.IsPathRooted(normalized))
            throw new InvalidDataException($"Payload contains rooted path: {path}");

        var parts = normalized.Split('/', StringSplitOptions.RemoveEmptyEntries);
        if (parts.Any(part => part == ".."))
            throw new InvalidDataException($"Payload path contains parent traversal: {path}");

        var clean = string.Join('/', parts.Where(part => part != "."));
        if (string.IsNullOrWhiteSpace(clean))
            throw new InvalidDataException($"Payload path is empty after normalization: {path}");
        return clean;
    }
}
