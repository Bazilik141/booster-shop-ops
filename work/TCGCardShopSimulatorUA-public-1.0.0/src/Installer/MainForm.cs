namespace TCGCardShopSimulatorUA.Installer;

internal sealed class MainForm : Form
{
    private readonly TextBox _path = new() { Dock = DockStyle.Fill };
    private readonly RichTextBox _log = new() { Dock = DockStyle.Fill, ReadOnly = true, WordWrap = false };
    private readonly Button _install = new() { Text = "Встановити / Оновити", AutoSize = true };
    private readonly Button _uninstall = new() { Text = "Видалити", AutoSize = true };
    private readonly Button _browse = new() { Text = "Обрати папку…", AutoSize = true };
    private readonly Button _detect = new() { Text = "Знайти у Steam", AutoSize = true };
    private readonly InstallerEngine _engine = new();
    private const string ModVersion = "2.3.0";

    public MainForm()
    {
        Text = "Українська локалізація — TCG Card Shop Simulator";
        Width = 820;
        Height = 540;
        MinimumSize = new Size(700, 440);
        StartPosition = FormStartPosition.CenterScreen;

        var header = new Label
        {
            Text = "TCG Card Shop Simulator — українська локалізація",
            AutoSize = true,
            Font = new Font(Font, FontStyle.Bold),
            Margin = new Padding(8, 8, 8, 4)
        };
        var help = new Label
        {
            Text = "Оберіть кореневу папку встановленої Steam-гри. Інсталятор не змінює збереження або оригінальні Unity-асети.",
            AutoSize = true,
            MaximumSize = new Size(760, 0),
            Margin = new Padding(8, 0, 8, 8)
        };

        var pathRow = new TableLayoutPanel { Dock = DockStyle.Top, AutoSize = true, ColumnCount = 3, Padding = new Padding(8) };
        pathRow.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100));
        pathRow.ColumnStyles.Add(new ColumnStyle(SizeType.AutoSize));
        pathRow.ColumnStyles.Add(new ColumnStyle(SizeType.AutoSize));
        pathRow.Controls.Add(_path, 0, 0);
        pathRow.Controls.Add(_browse, 1, 0);
        pathRow.Controls.Add(_detect, 2, 0);

        var buttons = new FlowLayoutPanel { Dock = DockStyle.Top, AutoSize = true, Padding = new Padding(8), FlowDirection = FlowDirection.LeftToRight };
        buttons.Controls.Add(_install);
        buttons.Controls.Add(_uninstall);

        var top = new FlowLayoutPanel { Dock = DockStyle.Top, AutoSize = true, FlowDirection = FlowDirection.TopDown, WrapContents = false };
        top.Controls.Add(header);
        top.Controls.Add(help);

        Controls.Add(_log);
        Controls.Add(buttons);
        Controls.Add(pathRow);
        Controls.Add(top);

        _browse.Click += (_, _) => Browse();
        _detect.Click += (_, _) => DetectSteam();
        _install.Click += (_, _) => RunInstall();
        _uninstall.Click += (_, _) => RunUninstall();

        Shown += (_, _) => DetectSteam(silent: true);
    }

    private void Browse()
    {
        using var dialog = new FolderBrowserDialog { Description = "Оберіть папку TCG Card Shop Simulator" };
        if (dialog.ShowDialog(this) == DialogResult.OK) _path.Text = dialog.SelectedPath;
    }

    private void DetectSteam(bool silent = false)
    {
        var candidate = GameDetection.FindSteamCandidates().FirstOrDefault();
        if (candidate is not null)
        {
            _path.Text = candidate;
            Log($"Знайдено гру: {candidate}");
        }
        else if (!silent)
        {
            MessageBox.Show(this, "Автоматично знайти гру не вдалося. Оберіть її папку вручну.", "Steam", MessageBoxButtons.OK, MessageBoxIcon.Information);
        }
    }

    private void RunInstall()
    {
        try
        {
            Toggle(false);
            var root = _path.Text.Trim();
            using var payload = new PayloadExtractor();
            var plan = _engine.BuildPlan(root, payload);
            var text = BuildPlanText(plan);
            if (plan.HasBlockingConflict)
            {
                MessageBox.Show(this, text, "Оновлення заблоковано", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                Log(text);
                return;
            }

            var result = MessageBox.Show(this, text + "\n\nПродовжити?", "План змін", MessageBoxButtons.YesNo, MessageBoxIcon.Question);
            if (result != DialogResult.Yes) return;

            _engine.InstallOrUpdate(root, payload, ModVersion, Log);
            var logPath = Path.Combine(root, "BepInEx", "LogOutput.log");
            MessageBox.Show(this,
                $"Готово. Запустіть гру один раз.\n\nЯкщо переклад не з’явиться, надішліть файл:\n{logPath}",
                "Встановлення завершено", MessageBoxButtons.OK, MessageBoxIcon.Information);
        }
        catch (Exception ex)
        {
            Log("ПОМИЛКА: " + ex.Message);
            MessageBox.Show(this, ex.Message, "Помилка", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
        finally { Toggle(true); }
    }

    private void RunUninstall()
    {
        try
        {
            Toggle(false);
            var root = _path.Text.Trim();
            var confirm = MessageBox.Show(this,
                "Інсталятор видалить лише файли зі свого ownership manifest і відновить backup там, де це безпечно. Продовжити?",
                "Видалення", MessageBoxButtons.YesNo, MessageBoxIcon.Question);
            if (confirm != DialogResult.Yes) return;

            var messages = _engine.Uninstall(root, Log);
            var text = string.Join(Environment.NewLine, messages);
            MessageBox.Show(this, text, "Результат видалення", MessageBoxButtons.OK,
                messages.Any(x => x.Contains("ручн", StringComparison.OrdinalIgnoreCase) || x.Contains("залишено", StringComparison.OrdinalIgnoreCase))
                    ? MessageBoxIcon.Warning : MessageBoxIcon.Information);
        }
        catch (Exception ex)
        {
            Log("ПОМИЛКА: " + ex.Message);
            MessageBox.Show(this, ex.Message, "Помилка", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
        finally { Toggle(true); }
    }

    private static string BuildPlanText(InstallPlan plan)
    {
        var lines = new List<string>();
        if (plan.Warnings.Count > 0)
        {
            lines.Add("ПОПЕРЕДЖЕННЯ:");
            lines.AddRange(plan.Warnings.Select(x => "• " + x));
            lines.Add("");
        }
        lines.Add("ЗМІНИ:");
        foreach (var a in plan.Actions.Where(x => x.Kind != PlanActionKind.SkipSame))
            lines.Add($"• {a.Kind}: {a.RelativePath} — {a.Detail}");
        var skipped = plan.Actions.Count(x => x.Kind == PlanActionKind.SkipSame);
        if (skipped > 0) lines.Add($"• Без змін: {skipped} файл(ів).");
        return string.Join(Environment.NewLine, lines);
    }

    private void Toggle(bool enabled)
    {
        _install.Enabled = enabled;
        _uninstall.Enabled = enabled;
        _browse.Enabled = enabled;
        _detect.Enabled = enabled;
    }

    private void Log(string message)
    {
        if (InvokeRequired) { BeginInvoke(new Action(() => Log(message))); return; }
        _log.AppendText($"[{DateTime.Now:HH:mm:ss}] {message}{Environment.NewLine}");
        _log.ScrollToCaret();
    }
}
