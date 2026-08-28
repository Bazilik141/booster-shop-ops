using System.Text.Json.Serialization;

namespace TCGCardShopSimulatorUA.Installer;

internal sealed class OwnershipManifest
{
    public int SchemaVersion { get; set; } = 1;
    public string ModVersion { get; set; } = "0.1.0";
    public DateTimeOffset InstalledAt { get; set; }
    public List<ManagedFile> Files { get; set; } = new();
}

internal sealed class ManagedFile
{
    public string RelativePath { get; set; } = "";
    public bool OriginalExisted { get; set; }
    public string? OriginalSha256 { get; set; }
    public string? BackupRelativePath { get; set; }
    public string InstalledSha256 { get; set; } = "";
}

internal enum PlanActionKind
{
    Add,
    Replace,
    UpdateOwned,
    RemoveObsoleteOwned,
    SkipSame
}

internal sealed record PlanAction(PlanActionKind Kind, string RelativePath, string Detail);

internal sealed class InstallPlan
{
    public List<PlanAction> Actions { get; } = new();
    public List<string> Warnings { get; } = new();
    public bool HasBlockingConflict { get; set; }
}
