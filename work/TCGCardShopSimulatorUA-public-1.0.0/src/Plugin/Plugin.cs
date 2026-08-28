using BepInEx;
using HarmonyLib;
using I2.Loc;
using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Reflection;
using System.Text;
using UnityEngine;

namespace TCGCardShopSimulatorUA;

[BepInPlugin(PluginGuid, PluginName, PluginVersion)]
public sealed class Plugin : BaseUnityPlugin
{
    public const string PluginGuid = "freshraccoon.arcania.tcgcardshopsimulatorua";
    public const string PluginName = "TCG Card Shop Simulator Ukrainian Localization";
    public const string PluginVersion = "2.3.0";

    private const string CarrierLanguage = "Korean";
    private const string CarrierNativeLabel = "한국어";
    private const string Ukrainian = "Українська";

    private readonly Dictionary<string, string> _translations = new(StringComparer.Ordinal);
    private readonly HashSet<string> _missingTerms = new(StringComparer.Ordinal);
    private bool _dictionaryFaulted;
    private bool _registrationInProgress;
    private bool _settingText;
    private bool _reportedFirstUpdate;
    private string? _lastRegistrationSummary;
    private float _nextRegistrationRefresh;
    private float _nextUiRefresh;

    internal static Plugin? Instance { get; private set; }

    private void Awake()
    {
        Instance = this;
        try
        {
            var directory = Path.GetDirectoryName(Info.Location)!;
            var masterCount = LoadMappings(Path.Combine(directory, "localization_data.txt"));
            var overrideCount = LoadMappings(Path.Combine(directory, "dynamic_ui_overrides.txt"));
            Logger.LogInfo($"Loaded {masterCount} master mappings and {overrideCount} exact dynamic UI mappings.");
            Logger.LogInfo("Using the game's existing Korean I2 slot as the Ukrainian carrier language.");
        }
        catch (Exception ex)
        {
            _dictionaryFaulted = true;
            Logger.LogError($"Localization dictionary could not be loaded: {ex}");
            return;
        }

        RefreshI2SourcesSynchronously();
        InstallRuntimeHooks();
        RegisterCarrierTranslations("plugin Awake");
    }

    private void Start()
    {
        Logger.LogInfo("Unity Start callback received; frame fallback is available.");
        RegisterCarrierTranslations("Unity Start");
    }

    private void Update()
    {
        if (!_reportedFirstUpdate)
        {
            _reportedFirstUpdate = true;
            Logger.LogInfo("Unity Update callback received; frame fallback is active.");
        }
        if (_dictionaryFaulted) return;

        if (Time.unscaledTime >= _nextRegistrationRefresh)
        {
            _nextRegistrationRefresh = Time.unscaledTime + 1f;
            RegisterCarrierTranslations("Unity Update fallback");
        }
        if (Time.unscaledTime < _nextUiRefresh) return;
        _nextUiRefresh = Time.unscaledTime + 0.5f;
        TranslateExistingLiveText();
    }

    private int LoadMappings(string path)
    {
        if (!File.Exists(path)) throw new FileNotFoundException("Localization mapping file is missing.", path);
        var added = 0;
        foreach (var line in File.ReadLines(path, Encoding.UTF8))
        {
            if (string.IsNullOrWhiteSpace(line)) continue;
            var separator = line.IndexOf('|');
            if (separator <= 0 || separator != line.LastIndexOf('|'))
                throw new InvalidDataException("Dictionary contains a malformed mapping.");

            var source = line.Substring(0, separator);
            if (_translations.ContainsKey(source))
                throw new InvalidDataException($"Dictionary contains an exact duplicate key: {source}");

            _translations.Add(source, line.Substring(separator + 1));
            added++;
        }
        return added;
    }

    private void RefreshI2SourcesSynchronously()
    {
        try
        {
            var found = LocalizationManager.UpdateSources();
            Logger.LogInfo($"Synchronous I2 source refresh completed: found={found}, sources={LocalizationManager.Sources?.Count ?? 0}.");
        }
        catch (Exception ex)
        {
            Logger.LogWarning($"Synchronous I2 source refresh was not ready yet: {ex.Message}");
        }
    }

    private void InstallRuntimeHooks()
    {
        try
        {
            var harmony = new Harmony(PluginGuid);
            PatchRequired(harmony, AccessTools.Method(typeof(LocalizationManager), nameof(LocalizationManager.UpdateSources)), postfix: nameof(RuntimeHooks.AfterUpdateSources));
            PatchRequired(harmony, AccessTools.Method(typeof(LocalizationManager), nameof(LocalizationManager.LocalizeAll)), prefix: nameof(RuntimeHooks.BeforeLocalizeAll));
            PatchRequired(harmony, AccessTools.Method(typeof(LocalizationManager), "DoLocalizeAll"), postfix: nameof(RuntimeHooks.AfterDoLocalizeAll));

            PatchTextSetter(harmony, "TMPro.TMP_Text");
            PatchTextSetter(harmony, "UnityEngine.UI.Text");
            Logger.LogInfo("Installed verified I2 source/localization hooks and live-text hooks.");
        }
        catch (Exception ex)
        {
            Logger.LogError($"Runtime hooks could not be installed: {ex}");
        }
    }

    private void PatchRequired(Harmony harmony, MethodInfo? original, string? prefix = null, string? postfix = null)
    {
        if (original is null) throw new MissingMethodException("A required I2 localization method was not found.");
        var prefixMethod = prefix is null ? null : new HarmonyMethod(AccessTools.Method(typeof(RuntimeHooks), prefix));
        var postfixMethod = postfix is null ? null : new HarmonyMethod(AccessTools.Method(typeof(RuntimeHooks), postfix));
        harmony.Patch(original, prefix: prefixMethod, postfix: postfixMethod);

        var patchInfo = Harmony.GetPatchInfo(original);
        if (patchInfo is null || !patchInfo.Owners.Contains(PluginGuid))
            throw new InvalidOperationException($"Harmony did not register the expected hook on {original.DeclaringType?.FullName}.{original.Name}.");
    }

    private void PatchTextSetter(Harmony harmony, string typeName)
    {
        var type = AccessTools.TypeByName(typeName);
        var setter = type is null ? null : AccessTools.PropertySetter(type, "text");
        if (setter is null)
        {
            Logger.LogWarning($"Live-text hook target is unavailable: {typeName}.text");
            return;
        }

        harmony.Patch(setter, postfix: new HarmonyMethod(AccessTools.Method(typeof(RuntimeHooks), nameof(RuntimeHooks.AfterTextSet))));
        var patchInfo = Harmony.GetPatchInfo(setter);
        Logger.LogInfo($"Live-text hook {typeName}.text: installed={patchInfo is not null && patchInfo.Owners.Contains(PluginGuid)}.");
    }

    internal void RegisterCarrierTranslations(string trigger)
    {
        if (_dictionaryFaulted || _registrationInProgress) return;
        _registrationInProgress = true;
        try
        {
            var sources = LocalizationManager.Sources;
            if (sources is null || sources.Count == 0)
            {
                ReportRegistrationSummary($"trigger={trigger}; sources=0; waiting=yes");
                return;
            }

            var foundTerms = new HashSet<string>(StringComparer.Ordinal);
            var touched = 0;
            var carrierSources = 0;
            foreach (var source in sources.ToArray())
            {
                if (source is null) continue;
                source.UpdateDictionary(false);
                if (source.mDictionary is null) continue;

                var languageIndex = source.GetLanguageIndex(CarrierLanguage);
                if (languageIndex < 0) continue;
                carrierSources++;

                foreach (var mapping in _translations)
                {
                    if (!source.mDictionary.TryGetValue(mapping.Key, out var term)) continue;
                    foundTerms.Add(mapping.Key);

                    if (term.Languages is null)
                        term.Languages = new string[languageIndex + 1];
                    else if (term.Languages.Length <= languageIndex)
                        Array.Resize(ref term.Languages, languageIndex + 1);

                    term.Languages[languageIndex] = mapping.Value;
                    touched++;
                }

                source.UpdateDictionary(false);
            }

            _missingTerms.Clear();
            foreach (var key in _translations.Keys)
                if (!foundTerms.Contains(key)) _missingTerms.Add(key);

            var summaryChanged = ReportRegistrationSummary($"trigger={trigger}; sources={sources.Count}; carrierSources={carrierSources}; touched={touched}; missing={_missingTerms.Count}");
            if (summaryChanged && carrierSources > 0 && _missingTerms.Count > 0) WriteMissingTerms();
        }
        catch (Exception ex)
        {
            Logger.LogError($"Carrier-language registration failed at {trigger}: {ex}");
        }
        finally
        {
            _registrationInProgress = false;
        }
    }

    private bool ReportRegistrationSummary(string summary)
    {
        if (string.Equals(_lastRegistrationSummary, summary, StringComparison.Ordinal)) return false;
        _lastRegistrationSummary = summary;
        Logger.LogInfo($"I2 registration: {summary}.");
        return true;
    }

    internal void TranslateAssignedText(object target, string? source)
    {
        if (_dictionaryFaulted || _settingText || string.IsNullOrEmpty(source)) return;

        string? translated = null;
        if (source == CarrierNativeLabel || source == CarrierLanguage)
            translated = Ukrainian;
        else
            _translations.TryGetValue(source!, out translated);

        if (string.IsNullOrEmpty(translated) || string.Equals(source, translated, StringComparison.Ordinal)) return;

        try
        {
            _settingText = true;
            target.GetType().GetProperty("text", BindingFlags.Public | BindingFlags.Instance)?.SetValue(target, translated);
        }
        catch
        {
            // A UI element can disappear while a menu is closing.
        }
        finally
        {
            _settingText = false;
        }
    }

    internal void TranslateExistingLiveText()
    {
        foreach (var typeName in new[] { "UnityEngine.UI.Text", "TMPro.TMP_Text" })
        {
            var type = AccessTools.TypeByName(typeName);
            if (type is null) continue;
            var property = type.GetProperty("text", BindingFlags.Public | BindingFlags.Instance);
            if (property is null || !property.CanRead) continue;

            foreach (var target in Resources.FindObjectsOfTypeAll(type))
            {
                try
                {
                    TranslateAssignedText(target, property.GetValue(target) as string);
                }
                catch
                {
                    // A UI element can disappear while a menu is closing.
                }
            }
        }
    }

    private void WriteMissingTerms()
    {
        var output = Path.Combine(Path.GetDirectoryName(Info.Location)!, "missing-known-terms.txt");
        File.WriteAllLines(output, _missingTerms.OrderBy(x => x), new UTF8Encoding(encoderShouldEmitUTF8Identifier: false));
    }
}

internal static class RuntimeHooks
{
    internal static void AfterUpdateSources()
    {
        Plugin.Instance?.RegisterCarrierTranslations("I2.UpdateSources postfix");
    }

    internal static void BeforeLocalizeAll()
    {
        Plugin.Instance?.RegisterCarrierTranslations("I2.LocalizeAll prefix");
    }

    internal static void AfterDoLocalizeAll()
    {
        Plugin.Instance?.TranslateExistingLiveText();
    }

    internal static void AfterTextSet(object __instance, object[] __args)
    {
        Plugin.Instance?.TranslateAssignedText(__instance, __args.Length > 0 ? __args[0] as string : null);
    }
}
