<?php
/**
 * PAY-002 NP quote latency: skip free-order tariff calls and reuse exact successful
 * API prices for up to 300 seconds within the current session (maximum 8 entries).
 * Cold paid-shipping quotes still use the original carrier API and timeout.
 * Targets: Pinta shipping model and checkout shipping controller. PHP 8.0.
 * No DB writes at deployment, no bank/order/TTN API changes. Runtime writes a
 * bounded session cache; existing totals/free-shipping/coupon rules are preserved.
 * Server-Timing exposes duration/hit/miss/free only, never customer data.
 * Rollback: restore both files from the printed backup, then clear normal cache.
 * Session cache is inert after rollback and expires in 300 seconds.
 */
declare(strict_types=1);
const PATCH_ID = 'PAY-002_np-quote-cache_20260831';
function need(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function out(string $message): void { echo $message . PHP_EOL; }
function copyChecked(string $from, string $to, string $hash): void {
    need(copy($from, $to), 'copy failed: ' . $to);
    need(hash_file('sha256', $to) === $hash, 'copy verification failed: ' . $to);
}
try {
    $root = getcwd() ?: '';
    need(PHP_SAPI === 'cli' && realpath($root) === realpath(__DIR__) && is_file($root . '/config.php'), 'Run uploaded copy in public_html');
    $spec = json_decode(base64_decode('eyJmaWxlcyI6eyJleHRlbnNpb24vUGludGFOb3ZhUG9zaHRhQ29kL2NhdGFsb2cvbW9kZWwvc2hpcHBpbmcvcGludGFfbm92YV9wb3NodGEucGhwIjp7ImJlZm9yZSI6IjJkY2RjNWQ4MjRiNjYyODAzZjc1MzRjOTJiZDNkZTU2YzRjY2VmNTVlYzJhNGE2MmU2MDM4MmQyMzBlZTZhOTAiLCJhZnRlciI6IjI0OTIzZWYyYzQwYjI4ZmMyMDQ1YzQ4N2RiNDE3MTNhNWU2NTNhZWFjN2NlYjM1ZWJlNDhlY2RkN2NlN2FiMjMiLCJlZGl0cyI6W3sibGFiZWwiOiJza2lwIHVubmVjZXNzYXJ5IHRhcmlmZiBmb3IgZWxpZ2libGUgZnJlZSB3YXJlaG91c2Ugc2hpcHBpbmciLCJiZWZvcmUiOiIkc2hpcHBpbmdfY29zdF91YWggPSAkdGhpcy0+Z2V0RG9jdW1lbnRQcmljZSgkYWRkcmVzcyAsJHNlcnZpY2VfdHlwZSApOyIsImFmdGVyIjoiJHNoaXBwaW5nX2Nvc3RfdWFoID0gJHRoaXMtPnBheTAwMlF1b3RlUHJpY2UoJGFkZHJlc3MsICRzZXJ2aWNlX3R5cGUpOyJ9LHsibGFiZWwiOiJza2lwIHVubmVjZXNzYXJ5IHRhcmlmZiBmb3IgZWxpZ2libGUgZnJlZSBjb3VyaWVyIHNoaXBwaW5nIiwiYmVmb3JlIjoiJHNoaXBwaW5nX2Nvc3RfdWFoID0gJHRoaXMtPmdldERvY3VtZW50UHJpY2UoJGFkZHJlc3MgLCAkc2VydmljZV90eXBlICk7IiwiYWZ0ZXIiOiIkc2hpcHBpbmdfY29zdF91YWggPSAkdGhpcy0+cGF5MDAyUXVvdGVQcmljZSgkYWRkcmVzcywgJHNlcnZpY2VfdHlwZSk7In0seyJsYWJlbCI6ImZyZWUtc2hpcHBpbmcgYnJhbmNoIG9ubHkgYXQgZGlzcGxheSBxdW90ZSBib3VuZGFyeSIsImJlZm9yZSI6IiAgICBwdWJsaWMgZnVuY3Rpb24gZ2V0RG9jdW1lbnRQcmljZSggJGFkZHJlc3MgLCAkc2VydmljZV90eXBlID0gXCJXYXJlaG91c2VXYXJlaG91c2VcIikgeyIsImFmdGVyIjoiICAgIC8vIFBBWS0wMDItTlAtUVVPVEUtQ0FDSEU6IG9ubHkgZGlzcGxheSBxdW90ZXMgbWF5IHNraXAgYSBjYXJyaWVyIHRhcmlmZi5cbiAgICBwcml2YXRlIGZ1bmN0aW9uIHBheTAwMlF1b3RlUHJpY2UoYXJyYXkgJGFkZHJlc3MsIHN0cmluZyAkc2VydmljZV90eXBlKSB7XG4gICAgICAgIGlmICgkdGhpcy0+Z2V0Qm9vc3RlckNhcnRUb3RhbFVhaCgpID49ICR0aGlzLT5nZXRCb29zdGVyRnJlZVNoaXBwaW5nRnJvbVVhaCgpKSB7XG4gICAgICAgICAgICAkdGhpcy0+cmVzcG9uc2UtPmFkZEhlYWRlcignU2VydmVyLVRpbWluZzogcGF5MDAyX25wX3ByaWNlO2R1cj0wO2Rlc2M9XCJmcmVlXCInKTtcbiAgICAgICAgICAgIHJldHVybiAwLjA7XG4gICAgICAgIH1cblxuICAgICAgICByZXR1cm4gJHRoaXMtPmdldERvY3VtZW50UHJpY2UoJGFkZHJlc3MsICRzZXJ2aWNlX3R5cGUpO1xuICAgIH1cblxuICAgIHB1YmxpYyBmdW5jdGlvbiBnZXREb2N1bWVudFByaWNlKCAkYWRkcmVzcyAsICRzZXJ2aWNlX3R5cGUgPSBcIldhcmVob3VzZVdhcmVob3VzZVwiKSB7In0seyJsYWJlbCI6ImRlZmVyIHNlcGFyYXRlIEFQSSBjbGllbnQgREIgY29ubmVjdGlvbiB1bnRpbCBhIGNhY2hlIG1pc3MiLCJiZWZvcmUiOiIgICAgICAgICRQaW50YU5vdmFQb3NodGFBcGkgPSBuZXcgXFxPcGVuY2FydFxcU3lzdGVtXFxMaWJyYXJ5XFxQaW50YW5vdmFwb3NodGFcXFBpbnRhTm92YVBvc2h0YUFwaSgpO1xuIiwiYWZ0ZXIiOiIifSx7ImxhYmVsIjoicmV1c2Ugc3VjY2Vzc2Z1bCBpZGVudGljYWwgQVBJIHByaWNlcyBmb3IgYXQgbW9zdCBmaXZlIG1pbnV0ZXMiLCJiZWZvcmUiOiIgICAgICAgICAgICAkcGludGFfbm92YV9wb3NodGFfYXBpX3Jlc3VsdCA9ICRQaW50YU5vdmFQb3NodGFBcGktPmNhbGxBcGkoJ0ludGVybmV0RG9jdW1lbnQnLCAnZ2V0RG9jdW1lbnRQcmljZScsICRwcm9wZXJ0aWVzKTsiLCJhZnRlciI6IiAgICAgICAgICAgIC8vIFRoZSBrZXkgY292ZXJzIHRoZSBleGFjdCBBUEkgcGF5bG9hZCBhbmQgYWNjb3VudC9zdG9yZSBjb250ZXh0LlxuICAgICAgICAgICAgLy8gU3RvcmUgb25seSBhbiBvcGFxdWUgaGFzaCwgcHJpY2UgYW5kIGV4cGlyeTsgbmV2ZXIgYWRkcmVzc2VzIG9yIGtleXMuXG4gICAgICAgICAgICAkY2FjaGVfa2V5ID0gaGFzaCgnc2hhMjU2Jywgc2VyaWFsaXplKFtcbiAgICAgICAgICAgICAgICAndjEnLCAkcHJvcGVydGllcyxcbiAgICAgICAgICAgICAgICAkdGhpcy0+Y29uZmlnLT5nZXQoJ2NvbmZpZ19zdG9yZV9pZCcpLFxuICAgICAgICAgICAgICAgICR0aGlzLT5jb25maWctPmdldCgnc2hpcHBpbmdfcGludGFfbm92YV9wb3NodGFfYXBpX2tleScpXG4gICAgICAgICAgICBdKSk7XG4gICAgICAgICAgICAkbm93ID0gdGltZSgpO1xuICAgICAgICAgICAgJGNhY2hlID0gJHRoaXMtPnNlc3Npb24tPmRhdGFbJ3BheTAwMl9ucF9wcmljZV9jYWNoZSddID8/IFtdO1xuICAgICAgICAgICAgaWYgKCFpc19hcnJheSgkY2FjaGUpKSAkY2FjaGUgPSBbXTtcbiAgICAgICAgICAgICRjYWNoZSA9IGFycmF5X2ZpbHRlcigkY2FjaGUsIHN0YXRpYyBmdW5jdGlvbiAoJGl0ZW0pIHVzZSAoJG5vdyk6IGJvb2wge1xuICAgICAgICAgICAgICAgIHJldHVybiBpc19hcnJheSgkaXRlbSkgJiYgaXNzZXQoJGl0ZW1bJ2V4cGlyZXMnXSwgJGl0ZW1bJ3ByaWNlJ10pXG4gICAgICAgICAgICAgICAgICAgICYmICRpdGVtWydleHBpcmVzJ10gPiAkbm93ICYmICRpdGVtWydleHBpcmVzJ10gPD0gJG5vdyArIDMwMFxuICAgICAgICAgICAgICAgICAgICAmJiBpc19udW1lcmljKCRpdGVtWydwcmljZSddKSAmJiAoZmxvYXQpJGl0ZW1bJ3ByaWNlJ10gPiAwO1xuICAgICAgICAgICAgfSk7XG4gICAgICAgICAgICAkdGhpcy0+c2Vzc2lvbi0+ZGF0YVsncGF5MDAyX25wX3ByaWNlX2NhY2hlJ10gPSAkY2FjaGU7XG4gICAgICAgICAgICBpZiAoaXNzZXQoJGNhY2hlWyRjYWNoZV9rZXldKSkge1xuICAgICAgICAgICAgICAgICR0aGlzLT5yZXNwb25zZS0+YWRkSGVhZGVyKCdTZXJ2ZXItVGltaW5nOiBwYXkwMDJfbnBfcHJpY2U7ZHVyPTA7ZGVzYz1cImhpdFwiJyk7XG4gICAgICAgICAgICAgICAgcmV0dXJuICRjYWNoZVskY2FjaGVfa2V5XVsncHJpY2UnXTtcbiAgICAgICAgICAgIH1cblxuICAgICAgICAgICAgJHByaWNlX3N0YXJ0ZWQgPSBtaWNyb3RpbWUodHJ1ZSk7XG4gICAgICAgICAgICAkUGludGFOb3ZhUG9zaHRhQXBpID0gbmV3IFxcT3BlbmNhcnRcXFN5c3RlbVxcTGlicmFyeVxcUGludGFub3ZhcG9zaHRhXFxQaW50YU5vdmFQb3NodGFBcGkoKTtcbiAgICAgICAgICAgICRwaW50YV9ub3ZhX3Bvc2h0YV9hcGlfcmVzdWx0ID0gJFBpbnRhTm92YVBvc2h0YUFwaS0+Y2FsbEFwaSgnSW50ZXJuZXREb2N1bWVudCcsICdnZXREb2N1bWVudFByaWNlJywgJHByb3BlcnRpZXMpO1xuICAgICAgICAgICAgJHRoaXMtPnJlc3BvbnNlLT5hZGRIZWFkZXIoJ1NlcnZlci1UaW1pbmc6IHBheTAwMl9ucF9wcmljZTtkdXI9JyAuIG51bWJlcl9mb3JtYXQoKG1pY3JvdGltZSh0cnVlKSAtICRwcmljZV9zdGFydGVkKSAqIDEwMDAsIDIsICcuJywgJycpIC4gJztkZXNjPVwibWlzc1wiJyk7In0seyJsYWJlbCI6ImNhY2hlIG9ubHkgdmFsaWRhdGVkIHN1Y2Nlc3NmdWwgbnVtZXJpYyBwcmljZXMiLCJiZWZvcmUiOiIgICAgICAgICAgICBpZiAoISRoYXNfYXBpX2Vycm9yKSB7XG4gICAgICAgICAgICAgICAgcmV0dXJuICRwaW50YV9ub3ZhX3Bvc2h0YV9hcGlfcmVzdWx0WydhcGlfcmVzcG9uc2UnXVsnZGF0YSddWzBdWydDb3N0J107XG4gICAgICAgICAgICB9IiwiYWZ0ZXIiOiIgICAgICAgICAgICBpZiAoISRoYXNfYXBpX2Vycm9yKSB7XG4gICAgICAgICAgICAgICAgJHByaWNlID0gJHBpbnRhX25vdmFfcG9zaHRhX2FwaV9yZXN1bHRbJ2FwaV9yZXNwb25zZSddWydkYXRhJ11bMF1bJ0Nvc3QnXSA/PyBudWxsO1xuICAgICAgICAgICAgICAgIGlmIChpc19udW1lcmljKCRwcmljZSkgJiYgKGZsb2F0KSRwcmljZSA+IDApIHtcbiAgICAgICAgICAgICAgICAgICAgJGNhY2hlWyRjYWNoZV9rZXldID0gWydwcmljZScgPT4gJHByaWNlLCAnZXhwaXJlcycgPT4gdGltZSgpICsgMzAwXTtcbiAgICAgICAgICAgICAgICAgICAgJHRoaXMtPnNlc3Npb24tPmRhdGFbJ3BheTAwMl9ucF9wcmljZV9jYWNoZSddID0gYXJyYXlfc2xpY2UoJGNhY2hlLCAtOCwgbnVsbCwgdHJ1ZSk7XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiAkcHJpY2U7XG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgIC8vIEludmFsaWQgQVBJIGRhdGEgZm9sbG93cyB0aGUgZXhpc3RpbmcgZml4ZWQtcmF0ZS91bmtub3duIGZhbGxiYWNrLlxuICAgICAgICAgICAgfSJ9XX0sImNhdGFsb2cvY29udHJvbGxlci9jaGVja291dC9zaGlwcGluZ19tZXRob2QucGhwIjp7ImJlZm9yZSI6Ijk4NTNiYTg3ZWYyNzIyYjMyOTYyMWQ4YjUxNzcyNzA0NGVjMDAyZTUyMGJhMjUxZTE4ZDZjMTQ2YWExZDIwM2YiLCJhZnRlciI6ImViMTRjNzRkZTQ1MmFhM2FhMDc0NzgwZmEwNDY3MDQwODRmNTBmNDRkNzMwZmUzODllOWZlNzlhN2QyNjM5ZTkiLCJlZGl0cyI6W3sibGFiZWwiOiJtZWFzdXJlIGNvbXBsZXRlIHF1b3RlIG1vZGVsIGR1cmF0aW9uIHdpdGhvdXQgbG9nZ2luZyByZXF1ZXN0IGRhdGEiLCJiZWZvcmUiOiJcdFx0XHQkc2hpcHBpbmdfbWV0aG9kcyA9ICR0aGlzLT5tb2RlbF9jaGVja291dF9zaGlwcGluZ19tZXRob2QtPmdldE1ldGhvZHMoJHRoaXMtPnNlc3Npb24tPmRhdGFbJ3NoaXBwaW5nX2FkZHJlc3MnXSk7IiwiYWZ0ZXIiOiJcdFx0XHQvLyBQQVktMDAyLU5QLVFVT1RFLUNBQ0hFOiB0aW1pbmcgb25seSwgbm8gYWRkcmVzc2VzIG9yIHNlc3Npb24gaWRlbnRpZmllcnMuXG5cdFx0XHQkcGF5MDAyX3F1b3RlX3N0YXJ0ZWQgPSBtaWNyb3RpbWUodHJ1ZSk7XG5cdFx0XHQkc2hpcHBpbmdfbWV0aG9kcyA9ICR0aGlzLT5tb2RlbF9jaGVja291dF9zaGlwcGluZ19tZXRob2QtPmdldE1ldGhvZHMoJHRoaXMtPnNlc3Npb24tPmRhdGFbJ3NoaXBwaW5nX2FkZHJlc3MnXSk7XG5cdFx0XHQkdGhpcy0+cmVzcG9uc2UtPmFkZEhlYWRlcignU2VydmVyLVRpbWluZzogcGF5MDAyX3NoaXBwaW5nX3F1b3RlcztkdXI9JyAuIG51bWJlcl9mb3JtYXQoKG1pY3JvdGltZSh0cnVlKSAtICRwYXkwMDJfcXVvdGVfc3RhcnRlZCkgKiAxMDAwLCAyLCAnLicsICcnKSk7In1dfX0sImd1YXJkcyI6eyJjYXRhbG9nL21vZGVsL2NoZWNrb3V0L3NoaXBwaW5nX21ldGhvZC5waHAiOiJjYzA4YTNjNzZhOWEyMTAyNTIyZjQ1YWM3NWVhYWJiYWMxMjMxMDE3NmFiMWRmOWVmY2QwYWM3N2Y4MjIwNjViIiwiY2F0YWxvZy9tb2RlbC9jaGVja291dC9ib29zdGVyX2NvdXBvbi5waHAiOiI0YjIwYzQ2YjIwYzM1NDYyNTRmYzJmNDc2ODYxYmI3MjViNzQ0MDBhMWJlMDUwN2M3ODc1Zjk3ZDdjZjgzNjIwIiwiY2F0YWxvZy9tb2RlbC9jaGVja291dC9jYXJ0LnBocCI6ImNhNTFjZThhMzRlNGExMDE5NmVhYTg4ZjU5MGVjNDRiYjRlYzBlOGM2ODQyN2MwMmQxYzI3OTZjMWYzZDY0ZTgiLCJleHRlbnNpb24vUGludGFOb3ZhUG9zaHRhQ29kL3N5c3RlbS9saWJyYXJ5L3BpbnRhbm92YXBvc2h0YS9waW50YW5vdmFwb3NodGFhcGkucGhwIjoiNzI5YzNjNmNlZTZiMDRmOTI1OTY3YTllNDIyNzdiOTcwMmU1MTg3NmIzN2Q2ZGYxMTliYTQwMjU4YjIwYjE5YyJ9fQ==', true), true, 512, JSON_THROW_ON_ERROR);
    $files = $spec['files'];
    out('cwd=' . $root);
    out('time=' . date('c'));
    foreach ($spec['guards'] as $file => $hash) {
        need(is_file($root . '/' . $file) && hash_file('sha256', $root . '/' . $file) === $hash, 'dependency source differs: ' . $file . '; write skipped');
    }
    $allAfter = true;
    foreach ($files as $file => $entry) {
        need(is_file($root . '/' . $file), 'missing target: ' . $file);
        $actual = hash_file('sha256', $root . '/' . $file);
        $allAfter = $allAfter && $actual === $entry['after'];
    }
    if ($allAfter) {
        out('already_applied=yes');
        out('self_delete=' . (@unlink(__FILE__) ? 'ok' : 'failed remove_uploaded_patch_manually=yes'));
        exit(0);
    }
    $candidates = [];
    foreach ($files as $file => $entry) {
        $current = file_get_contents($root . '/' . $file);
        need(is_string($current) && hash('sha256', $current) === $entry['before'], 'source SHA mismatch: ' . $file . '; write skipped');
        foreach ($entry['edits'] as $edit) {
            need(substr_count($current, $edit['before']) === 1, 'anchor mismatch: ' . $edit['label']);
            $current = str_replace($edit['before'], $edit['after'], $current);
        }
        need(hash('sha256', $current) === $entry['after'], 'candidate SHA mismatch: ' . $file . '; write skipped');
        $candidates[$file] = $current;
        out('candidate_sha256=' . $file . ':' . $entry['after']);
    }
    $backup = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    foreach ($files as $file => $entry) {
        $target = $backup . '/' . $file;
        if (!is_dir(dirname($target))) need(mkdir(dirname($target), 0755, true), 'backup mkdir failed');
        copyChecked($root . '/' . $file, $target, $entry['before']);
    }
    out('backup=' . $backup);
    try {
        foreach ($files as $file => $entry) {
            need(hash_file('sha256', $root . '/' . $file) === $entry['before'], 'source changed during preparation: ' . $file);
            need(file_put_contents($root . '/' . $file, $candidates[$file], LOCK_EX) === strlen($candidates[$file]), 'write failed: ' . $file);
            if (substr($file, -4) === '.php') {
                $lines = []; $code = 0;
                exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/' . $file) . ' 2>&1', $lines, $code);
                need($code === 0, 'PHP lint failed: ' . $file);
                out('php_l=ok file=' . $file);
            }
            need(hash_file('sha256', $root . '/' . $file) === $entry['after'], 'written SHA mismatch: ' . $file);
            out('after_sha256=' . $file . ':' . $entry['after']);
        }
    } catch (Throwable $error) {
        foreach ($files as $file => $entry) copyChecked($backup . '/' . $file, $root . '/' . $file, $entry['before']);
        throw new RuntimeException('source restored: ' . $error->getMessage());
    }
    out('changed=' . implode(',', array_keys($files)));
    out('assertions=ok');
    out('database_touched=no');
    out('runtime_session_cache=successful_np_prices_ttl300_max8');
    out('done=ok');
    out('self_delete=' . (@unlink(__FILE__) ? 'ok' : 'failed remove_uploaded_patch_manually=yes'));
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
