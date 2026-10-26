# RD-16 WP0 Menu-Supplement Review: Three-File Collector and Owner Packet

## Verdict: Review OK

I found no mandatory issue. This verdict comes from static source analysis only. I did not execute anything and make no claim about how the host, MySQL, OpenSSH or Windows PowerShell 5.1 will behave at runtime.

### Bound executable identities

These are taken from the package; I did not recompute them.

| Item | Path | SHA-256 |
|---|---|---|
| Menu collector | `diagnostics/RD-16_wp0_menu-collect_20261025.sh` | `64d8ecfc5844f78661ab9a3fd5de26ac4219b0671d738cfc1f136804a64f9d39` |
| Menu owner packet | `diagnostics/RD-16_wp0_owner-menu-pull_20261025.ps1` | `e194f34fb1b2d939faefeb23f2674c96d6c3cf9e7eb3c6e92ca044e49f334cbc` |
| Report reviewed | `diagnostics/RD-16_wp0_source-request_report_20261025.md` | `dd376478790f56fb9af4c498a610e0cfd90a21732ccc14b7d1bf40b2c125caf3` |
| Fixture (Codex evidence) | `work/rd16-wp0-20261025/check-menu-transport.ps1` | `c8dec9752fae4dfd44bb19b076b5640340ba807bfa34eb81b6b14e6bc025a5f7` |
| Metadata lint copy | `work/rd16-wp0-20261025/menu-metadata-lint.php` | `491219c1af2e991eb94202f76ec1f78d15ac506ac6f9ee8eca41dffbd8442a1d` |

- The packet's `$CollectorSha` equals the menu collector hash above.
- The report's two new executable identities match both values above.
- By my reading, the lint copy is identical to the collector's `RD16_PHP` here-doc.

### Launcher adoption

I explicitly adopt this exact launcher as my narrow pull instruction. It is bound to packet `e194f34f…f334cbc` and collector `64d8ecfc…f9d39`:

```powershell
& 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\RD-16_wp0_owner-menu-pull_20261025.ps1'
```

The adoption holds only under these conditions:
- The owner runs it once.
- There is no retry on any `STOP`; the result and artifacts are sent as they are.
- Codex checks delivery and intake.
- Any byte change to either executable voids this adoption.

The scope is read-only WP0 collection of the three named files only. It grants nothing for G0, WP1, WP2, status, Git or deployment.

---

## Findings

### 1. Source path provenance: sound

The three paths follow from the historical controller's own references, not from a wildcard:

- **Controller.** The layout code `ciaccountsidebar.account_sidebar` maps to route `extension/ciaccountsidebar/module/account_sidebar`. That is `extension/ciaccountsidebar/catalog/controller/module/account_sidebar.php`.
- **View.** `load->view('extension/ciaccountsidebar/module/account_sidebar')` gives `…/catalog/view/template/module/account_sidebar.twig`.
- **Stylesheet.** `addStyle(HTTP_SERVER . 'extension/ciaccountsidebar/catalog/view/stylesheet/accountdashboard/sidebar.css')` is webroot-relative and exactly the third path.

The report correctly frames the historical snippets as path locators only, not as current content.

### 2. Context pins: sound

- **Values.** I compared all 34 pinned hashes line by line against the accepted R1 manifest (`3636e66e…`). Paths, order and values are identical, and they include all 7 ACC-009 after-hashes.
- **Per-entry checks.** Each entry is walked for symlinked components, then checked with `-f` and SHA-256. Any failure stops the script.
- **Timing.** The check runs before any export work and again after the archive is created. A drift after collection stops before the manifest is written, so no `done=ok` receipt is possible.
- **Stdin safety.** The `while read` loop reads only its own here-doc. `sha256sum` takes a file argument. Nothing inside the loop can consume the `bash -s` script stream, which preserves the R2 property that no command reads the script's stdin.
- **Exit behaviour.** `stop` inside the function exits the whole script, because the loop is not in a subshell.
- **Export boundary.** Context files are hashed only. Nothing in the tar invocation or the manifest re-exports them.

### 3. Collector body: unchanged accepted mechanics

The following are carried over, retargeted only to the new artifact names:
- the webroot and private-directory guards;
- no-replay existence checks followed by `set -C`;
- the size and total caps;
- the obvious-secret screen, including the `$?` handling in the `else` branch;
- the before/after source hashes;
- per-member archive verification without extraction;
- the five-line receipt shape.

The new names `RD-16-menu-20261025-r1.*` cannot collide with the consumed `RD-16-source-*` artifacts.

### 4. Metadata: read-only and bounded

The only delta is one added route string, `extension/ciaccountsidebar/module/account_sidebar`, in the existing fixed theme-count list.
- The schema check, `START TRANSACTION READ ONLY`, prepared SELECTs, row and identifier caps, and output allowlist are unchanged.
- It reads no settings, customer, order or session data, and uses no DB CLI.
- Bind-by-reference through `foreach … as $route` still updates the bound parameter correctly.

### 5. Owner packet: R2 M1–M3 preserved

- **M1 (exact stdin transport).** `Invoke-Rd16LfProcess` keeps the same R2-accepted structure:
  - it writes to `BaseStream` and then closes stdin;
  - stdout is read asynchronously before the write;
  - native arguments are allowlisted;
  - a failed send forces a non-zero code;
  - the function returns a single object.
- **M2 (cleanup EOL guards).** The cleanup body is normalized to LF after placeholder substitution and a remaining CR is refused. The helper refuses CR again before launch. Placeholders accept only `[a-f0-9]{64}` captures. `rm` uses exact absolute paths, gated by the symlink, regular-file and both hash checks.
- **M3 (raw receipt retention).** `owner-receipt-raw.txt` and `cleanup-receipt-raw.txt` are both written before their exit-code and regex checks.
- **Receipt and local path.**
  - The receipt regex requires `task=RD-16-WP0-MENU` and `source_files=3`.
  - The local drop path is new and must be absent beforehand.
  - The success marker is `RD-16_MENU_SOURCE_READY`.

### 6. Fixture boundary: accurately stated

The fixture exercises the following using real Bash, tar and SHA-256:
- the AST-extracted helper, cleanup literal and receipt regex;
- LF and CRLF runs;
- exact cleanup;
- refusal of pre-collection context drift, with no archive created.

PHP is mocked and the three module files are synthetic, as the report says.

---

## Optional notes (not closure conditions)

1. **Extension root is not attested.** The controller and Twig load through `DIR_EXTENSION`; the CSS loads through webroot `HTTP_SERVER`.
   - The collector assumes `DIR_EXTENSION` is `~/public_html/extension/`. The original 34-file collection made the same assumption for `extension/opencart` and `extension/ukrainian`.
   - `DIR_CACHE` is already relocated to `/home2/boosters/ocartdata/storage/`.
   - A future collector could emit a derived `DIR_EXTENSION` alongside the cache path. For now, G0 should state this as a limit rather than a proven fact.
2. **The visible menu depends on unread settings.** The historical controller builds every link, label, icon, visibility rule and color from `module_account_sidebar_*` settings.
   - Column-left also gates the module on its status setting; check this against the accepted `column_left.php`.
   - Source alone therefore cannot establish the rendered link set or order. If G0 needs it, use a separately reviewed bounded read or an owner-captured page, not an inference.
3. **Possible language dependency.** The historical controller loads the language file `extension/module/account_sidebar`, which may resolve under `catalog/language/<lang>/extension/module/`.
   - Historically only `heading_title` was read, and the Twig never rendered it.
   - If the current Twig uses language keys, that file would be a fourth gap, needing another narrow supplement.
4. **Confirm the theme-count key format.** Check that the route string matches the key format `catalog/controller/event/theme.php` uses for extension views, against the accepted fresh source. A zero count is meaningful only if the key format matches.
5. **Count the context lines.** The context check does not assert that exactly 34 lines were processed. The list is a hash-pinned literal, so this is defensive only.
6. **Fixture gaps.** The fixture does not cover a symlinked context component or a context drift after collection. Both paths are correct by reading.
7. **Packet-side manifest checks.** The packet checks only `archive_sha256` and `done=ok` in the manifest.
   - The hash binding already ties the manifest to the remote receipt.
   - Codex intake should still assert `task=RD-16-WP0-MENU`, `source_files=3`, `context_sources=34` and `context_pre_post=ok`.
8. **Still-open R2 optional notes.** These remain non-blocking:
   - **BOM under Windows PowerShell 5.1.** The package reports that the first live run of the same helper succeeded. That is Codex's evidence, not mine.
   - **Stderr visibility.** `STOP=` codes rely on console handle inheritance.
   - **Directory modes.** The modes of existing private directories are not checked.
   - **Secret screen.** It is case-insensitive.
   - **Rollback.** The `$db->rollback()` return value is unchecked.
   - **Indentation.** The post-receipt block in the packet is indented as if nested, with no behavioural effect.

---

## Not claimed

- No proof of host, DB, SSH/SCP or PowerShell 5.1 runtime behaviour.
- No claim about the current content of the three module files, or that the module renders.
- No claim about default layout, active module settings, events or the visible menu.
- No shell-mechanism or G0 decision, and nothing in WP1 or WP2. Those gates stand unchanged.
