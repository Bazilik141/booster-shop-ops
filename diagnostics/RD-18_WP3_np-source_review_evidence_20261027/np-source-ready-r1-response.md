# Review OK: RD-18-WP3 NP source-only collector + owner packet

**Verdict: Review OK.** There are no mandatory findings. The optional findings below do not block the owner run.

## Hash binding

| Artifact | SHA256 | Bytes (as supplied) |
|---|---|---|
| `diagnostics\RD-18-WP3_np-source_collect_20261027.sh` | `d52a1b416ec9eedf8624ef1b241d9ac487193bbe6fc3271470bbb56f436c053f` | 15172 |
| `diagnostics\RD-18-WP3_np-source_owner_20261027.ps1` | `108578c6ea554b588b8964a3c3293ea99589fd0c96003507e2e49f38721dff3f` | 7024 |

I reviewed the exact bytes as supplied. I could not recompute the hashes because I had no tools. The supplied hashes agree with each other in four places:
- The packet's `$CollectorSha` equals the collector hash.
- `source-facts.json` matches the candidate.
- Both PS5.1 and PS7 result files record the same pair.

Any change to either file voids this verdict.

**Scope of this verdict:**
- **Covered:** collector and command code, source privacy, receipt and manifest binding, and private cleanup.
- **Not covered:** UI, the NP tracker, NP API behaviour, real Unix/MySQL/SSH runs, deployment, and whether the source contains secrets.

## What I checked

**Collector: holds**
- **Fixed paths only:** the seven fixed source paths are used, with no wildcards or recursion.
- **Symlink checks:** each path component is checked for symlinks, plus file type, 2 MB per-file and 8 MB total caps.
- **Secret screens:**
  - The obvious-secret and literal-key screens handle grep exit codes correctly. Inside the `else` branch, `$?` is grep's status, and an exit code of 2 stops the run.
  - The secret screen runs on the "before" bytes. Those same bytes are then bound through the pre/post hash comparison and the archived-entry hash check, so the screened bytes are the archived bytes.
- **Artifact handling:**
  - The no-replay check runs, then `set -C`, so both exports are written only once. With `umask 077` they are created as 0600.
  - Tar stores symlinks as links rather than following them. If a component is swapped for a symlink mid-run, the archived hash will not match and the run fails closed.
- **Stdin safety under `bash -s`:** no command reads ssh stdin. PHP gets the quoted heredoc. `php -r`, `tar -f`, `sha256sum` and `grep` with file arguments don't read stdin.
- **Database access:**
  - The `DB_PREFIX` is allowlisted.
  - Queries are fixed: `SHOW COLUMNS` → `START TRANSACTION READ ONLY` → a single `SELECT trigger,action WHERE status=1 LIMIT 1025`.
  - Rollback runs in `finally`.
  - Caps are 1024 rows and 64 relevant rows, with null/type/length guards.
  - Output is a safe string or a redacted SHA.
  - No business, customer or catalogue tables are touched.
- **Hook predicate:** the five-hook reviewed predicate is unchanged. The output says `admission:none`, and no new allowlist was added.
- **Current-file hashes:** these cover the five deployed files plus the order model and the acc007 map, with a component-symlink walk and size caps. Hashes only, no bodies.
- **Config handling:** config is `require`d only inside the owner-run PHP and only constants are used. Output buffers are discarded, and errors are suppressed with no details printed.
- **Receipt:** it is exactly five lines, and the manifest hash is computed after the manifest is closed.

**Owner packet: holds**
- **Before transport:**
  - The collector's bytes are hash-checked.
  - An existing local drop is refused before any transport.
- **SSH call:**
  - The script is sent as LF-only bytes over stdin.
  - Native arguments are allowlisted, and ssh options are strict (BatchMode, StrictHostKeyChecking, key-only).
  - Stdout is read asynchronously, so it cannot deadlock.
  - A send failure cannot be reported as success.
- **Download checks:**
  - The receipt must match an anchored strict regex requiring `source_files=7`.
  - Each `scp` download must return a zero exit code.
  - Both local files must match the receipt hashes, and the manifest must contain the matching `archive_sha256` line and `done=ok`.
- **Remote cleanup:**
  - It runs only after both downloads are verified.
  - It re-checks that the private directories and both files are regular, non-symlink files, and re-checks both hashes.
  - It removes exactly two files by name, with no `-r` and no wildcards.
  - It requires an exact cleanup receipt.
- **Failure handling:** every failure preserves the artifacts and tells the owner not to retry. Neither the WP2 Apply patch nor a cache cleanup can be triggered.

## Mandatory findings
None.

## Optional findings and how to close them

1. **The literal-key screen is narrow, not "conservative".**
   - **Problem:** it only matches quoted-key forms (`'apiKey' => '…'`). It misses `private $apiKey = '<32hex>'`, `$this->api_key = '…'`, `define('…','<32hex>')` and keys in comments.
   - **Why it's low cost to fix:** NP Refs are dashed UUIDs, so a quoted bare 32-hex literal should be rare in this module, and a hit simply stops the run.
   - **Closure:** either add a screen for any `["'][[:xdigit:]]{32}["']`, or reword the claim to "keyed-form literal screen". The disclaimer that the screen is not exhaustive already stands.
2. **`fwrite(STDERR, …)` in the PHP catch block.**
   - **Problem:** the PHP CLI does not define `STDERR` when the script is read from stdin. The catch block would therefore hit an undefined-constant Error, which becomes a silent fatal exit 255.
   - **Impact:** the run still fails closed with bash's `STOP=metadata_failed`, so this is not a safety gap. The intended STOP marker is simply lost, and this path is untested.
   - **Closure:** use `file_put_contents('php://stderr', …)`.
3. **The embedded-PHP test evidence is indirect.**
   - **Problem:** `test-np-metadata.php` evaluates `np-source-metadata.php`, which was supplied by hash only. Its equality to the heredoc embedded in the collector is asserted but not shown.
   - **Closure:** extract the heredoc from the collector itself in the test, or include the builder's equality check in the results.
4. **The missing-source STOP doesn't say which file.**
   - **Problem:** a single wrong path fails the whole one-shot run with no indication of which file.
   - **Closure:** include the list index (e.g. `required_source_missing_3`). This is safe because the index is not data.
5. **Leftover from the photo-task template.**
   - **Problem:** `image_root_is_expected`, the `photo_*` test labels, and the "fixed prepared SELECTs" comment are leftovers. The queries are fixed literal queries, not prepared statements.
   - **Closure:** remove the field and fix the wording. This is cosmetic and has no privacy impact.
6. **No local archive inventory check.**
   - **Closure:** after the hash checks, add a local `tar -tzf` check for exactly seven expected names, before the agent consumes the archive.
7. **The local drop sits inside the repo under `work\…`.**
   - **Closure:** confirm the drop is git-ignored so the third-party source and event metadata cannot be committed or pushed.
8. **The packet's indentation suggests a removed try block.**
   - **Closure:** cosmetic only. Normalise the indentation.

## Evidence assessment
- **Accepted as bounded evidence:**
  - Real Bash/tar runs on seven synthetic files cover success, missing, drift, existing and literal-key cases.
  - Fake-PHP and fake-mysqli native runs cover predicate classification, caps, redaction and rollback.
  - The full-packet AST/mock runs on PS5.1 and PS7 replaced both transports before invocation and showed the replay refusal.
- **Symlink case:** recorded honestly as `NOT_TESTED`. It is acceptable by inspection on Linux, where `[[ -L ]]` works per component.
- **Not established:** real Unix/MySQL/ssh/scp behaviour, server bash and PHP versions, absence of secrets in the source, and the main block (config require, connect).
