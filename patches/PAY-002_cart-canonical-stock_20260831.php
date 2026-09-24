<?php
/**
 * PAY-002 canonical stock/minimum feedback. PHP 8.0 compatible. No DB writes.
 * Targets: cart controller, checkout entry controller, cart list template.
 * Requires exact 2026-08-31 owner archive state, including the canonical cart library.
 * Rollback: restore the three files from the printed backup, then clear template cache.
 * Source logic: stock is a QUANTITY; stock_status is availability for the requested quantity.
 * This runner uses Cart::hasStock()/hasMinimum() rather than duplicating that logic.
 */
declare(strict_types=1);
const PATCH_ID = 'PAY-002_cart-canonical-stock_20260831';
function need(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function out(string $message): void { echo $message . PHP_EOL; }
function copyChecked(string $from, string $to, string $hash): void {
    need(copy($from, $to), 'copy failed: ' . $to);
    need(hash_file('sha256', $to) === $hash, 'copy verification failed: ' . $to);
}
try {
    $root = getcwd() ?: '';
    need(PHP_SAPI === 'cli' && realpath($root) === realpath(__DIR__) && is_file($root . '/config.php'), 'Run uploaded copy in public_html');
    $spec = json_decode(base64_decode('eyJmaWxlcyI6eyJjYXRhbG9nL2NvbnRyb2xsZXIvY2hlY2tvdXQvY2FydC5waHAiOnsiYmVmb3JlIjoiOWQ1OTAwZWFiM2VkNDU5MGFlYjEzZjI4MTliYTQ3MTJiZTFlYjU1YmM5ZGQ4YWFjOGRiYjU3MmEyZGEwYzY2NyIsImFmdGVyIjoiOWQwYmQ5YjJmZmUyNzFjZTc3NGRhYzI3OTc3ODNjODBjMTUwOTFmZjAyOTFhMGU1M2Q1Nzk0Y2YwNjllODRkNSIsImVkaXRzIjpbeyJsYWJlbCI6ImNhbm9uaWNhbCBzdG9jayBjaGVjayIsImJlZm9yZSI6Ilx0XHRcdFx0JHByZW9yZGVyX3N0b2NrX3N0YXR1c19pZCA9IDg7XG5cdFx0JGhhc19zdG9ja19lcnJvciA9IGZhbHNlO1xuXG5cdFx0JHRoaXMtPmxvYWQtPm1vZGVsKCdjaGVja291dC9jYXJ0Jyk7XG5cblx0XHQkcHJvZHVjdHMgPSAkdGhpcy0+bW9kZWxfY2hlY2tvdXRfY2FydC0+Z2V0UHJvZHVjdHMoKTtcblxuXHRcdGZvcmVhY2ggKCRwcm9kdWN0cyBhcyAkY2FydF9wcm9kdWN0KSB7XG5cdFx0XHQkaXNfcHJlb3JkZXIgPSBpc3NldCgkY2FydF9wcm9kdWN0WydzdG9ja19zdGF0dXNfaWQnXSkgJiYgKGludCkkY2FydF9wcm9kdWN0WydzdG9ja19zdGF0dXNfaWQnXSA9PT0gJHByZW9yZGVyX3N0b2NrX3N0YXR1c19pZDtcblxuXHRcdFx0aWYgKCEoaW50KSRjYXJ0X3Byb2R1Y3RbJ3N0b2NrJ10gJiYgISRpc19wcmVvcmRlcikge1xuXHRcdFx0XHQkaGFzX3N0b2NrX2Vycm9yID0gdHJ1ZTtcblx0XHRcdFx0YnJlYWs7XG5cdFx0XHR9XG5cdFx0fVxuIiwiYWZ0ZXIiOiJcdFx0Ly8gUEFZLTAwMi1DQU5PTklDQUwtU1RPQ0s6IHVzZSB0aGUgc2FtZSBxdWFudGl0eS9vcHRpb24vcHJlb3JkZXIgZ2F0ZSBhcyBjb25maXJtLlxuXHRcdCRoYXNfc3RvY2tfZXJyb3IgPSAhJHRoaXMtPmNhcnQtPmhhc1N0b2NrKCk7XG4ifSx7ImxhYmVsIjoiY2Fub25pY2FsIG1pbmltdW0gY2hlY2siLCJiZWZvcmUiOiJcdFx0JGRhdGFbJ3BheTAwMl9jYXJ0X3N0b2NrX2NoZWNrb3V0X2Jsb2NrZWQnXSA9ICRoYXNfc3RvY2tfZXJyb3IgJiYgISR0aGlzLT5jb25maWctPmdldCgnY29uZmlnX3N0b2NrX2NoZWNrb3V0Jyk7IiwiYWZ0ZXIiOiJcdFx0JGRhdGFbJ3BheTAwMl9jYXJ0X3N0b2NrX2NoZWNrb3V0X2Jsb2NrZWQnXSA9ICRoYXNfc3RvY2tfZXJyb3IgJiYgISR0aGlzLT5jb25maWctPmdldCgnY29uZmlnX3N0b2NrX2NoZWNrb3V0Jyk7XG5cdFx0JGRhdGFbJ3BheTAwMl9jYXJ0X21pbmltdW1fY2hlY2tvdXRfYmxvY2tlZCddID0gISR0aGlzLT5jYXJ0LT5oYXNNaW5pbXVtKCk7In0seyJsYWJlbCI6ImNvcnJlY3QgZXhpc3RpbmcgbWluaW11bS1zdGF0dXMgcG9sYXJpdHkiLCJiZWZvcmUiOiInbWluaW11bV9lcnJvcicgPT4gJHByb2R1Y3RbJ21pbmltdW1fc3RhdHVzJ10gPyBzcHJpbnRmKCIsImFmdGVyIjoiJ21pbmltdW1fZXJyb3InID0+ICEkcHJvZHVjdFsnbWluaW11bV9zdGF0dXMnXSA/IHNwcmludGYoIn1dfSwiY2F0YWxvZy9jb250cm9sbGVyL2NoZWNrb3V0L2NoZWNrb3V0LnBocCI6eyJiZWZvcmUiOiI5NmVmMzRhZDNjMGMyYjNjOTgwZDZmZmU1NDk1M2RhM2Y2NmUyY2QxMzIwZjlmNTQ5MzA1ZjVjM2Y5ODI4OWM5IiwiYWZ0ZXIiOiJkYmY0YjhiOThjNWJlMjUyODZjN2U0NDI1NDQ4NGJiNzg4MzAxNzkwOTJjMjM0MGM1MGYzYTgxYjI5NDljM2EyIiwiZWRpdHMiOlt7ImxhYmVsIjoiYWxpZ24gZW50cnkgc3RvY2sgZ2F0ZSB3aXRoIGNhbm9uaWNhbCBjYXJ0IGdhdGUiLCJiZWZvcmUiOiJcdFx0XHRcdC8vIFZhbGlkYXRlIGNhcnQgdG8gc2VlIGlmIGl0IGhhcyBwcm9kdWN0cyBhbmQgaGFzIHN0b2NrLlxuXHRcdCRwcmVvcmRlcl9zdG9ja19zdGF0dXNfaWQgPSA4O1xuXHRcdCRoYXNfc3RvY2tfZXJyb3IgPSBmYWxzZTtcblxuXHRcdGZvcmVhY2ggKCR0aGlzLT5jYXJ0LT5nZXRQcm9kdWN0cygpIGFzICRjYXJ0X3Byb2R1Y3QpIHtcblx0XHRcdCRpc19wcmVvcmRlciA9IGlzc2V0KCRjYXJ0X3Byb2R1Y3RbJ3N0b2NrX3N0YXR1c19pZCddKSAmJiAoaW50KSRjYXJ0X3Byb2R1Y3RbJ3N0b2NrX3N0YXR1c19pZCddID09PSAkcHJlb3JkZXJfc3RvY2tfc3RhdHVzX2lkO1xuXG5cdFx0XHRpZiAoIShpbnQpJGNhcnRfcHJvZHVjdFsnc3RvY2snXSAmJiAhJGlzX3ByZW9yZGVyKSB7XG5cdFx0XHRcdCRoYXNfc3RvY2tfZXJyb3IgPSB0cnVlO1xuXHRcdFx0XHRicmVhaztcblx0XHRcdH1cblx0XHR9XG4iLCJhZnRlciI6Ilx0XHQvLyBQQVktMDAyLUNBTk9OSUNBTC1TVE9DSzoga2VlcCBlbnRyeSwgY2FydCBmZWVkYmFjaywgYW5kIGNvbmZpcm0gb24gb25lIGdhdGUuXG5cdFx0JGhhc19zdG9ja19lcnJvciA9ICEkdGhpcy0+Y2FydC0+aGFzU3RvY2soKTtcbiJ9XX0sImNhdGFsb2cvdmlldy90ZW1wbGF0ZS9jaGVja291dC9jYXJ0X2xpc3QudHdpZyI6eyJiZWZvcmUiOiIwYmQ3ZmNmZDU5Nzc0MmJiMjgzOGNmOTIxMWRlYjRiM2Y3MzJhNjM4Y2RmNTgyYzQ4ZThmNzI1MzNiYmNiM2FiIiwiYWZ0ZXIiOiIwYzI2NGI4Njc2ZThkN2U4Zjk3ZTU5Y2FiOWQwMmIyMDQwMDVkMWIxY2JmNGQzYzg2NTlhMWRmMWQxNjBmYmVjIiwiZWRpdHMiOlt7ImxhYmVsIjoiYXZvaWQgZHVwbGljYXRlIGdlbmVyaWMgc3RvY2sgbWVzc2FnZSIsImJlZm9yZSI6InslIGlmIGVycm9yX3N0b2NrICV9IiwiYWZ0ZXIiOiJ7JSBpZiBlcnJvcl9zdG9jayBhbmQgbm90IHBheTAwMl9jYXJ0X3N0b2NrX3dhcm5pbmcgJX0ifSx7ImxhYmVsIjoiYWNjdXJhdGUgc3RvY2sgY29weSBpbmNsdWRpbmcgemVybyBzdG9jayBhbmQgb3B0aW9ucyIsImJlZm9yZSI6ItCU0LvRjyDQvtC00L3QvtCz0L4g0LDQsdC+INC60ZbQu9GM0LrQvtGFINGC0L7QstCw0YDRltCyINC+0LHRgNCw0L3QsCDQutGW0LvRjNC60ZbRgdGC0Ywg0L/QtdGA0LXQstC40YnRg9GUINC00L7RgdGC0YPQv9C90LjQuSDQt9Cw0LvQuNGI0L7QuiDigJQg0LfQvNC10L3RiNGC0LUg0LrRltC70YzQutGW0YHRgtGMINCw0LHQviDQv9GA0LjQsdC10YDRltGC0Ywg0L/QvtC30LjRhtGW0Y4uIiwiYWZ0ZXIiOiLQntC00L3QvtCz0L4g0LDQsdC+INC60ZbQu9GM0LrQvtGFINGC0L7QstCw0YDRltCyINC90LXQvNCw0ZQg0LIg0L3QsNGP0LLQvdC+0YHRgtGWINCyINC+0LHRgNCw0L3RltC5INC60ZbQu9GM0LrQvtGB0YLRliDigJQg0LfQvNC10L3RiNGC0LUg0LrRltC70YzQutGW0YHRgtGMINCw0LHQviDQv9GA0LjQsdC10YDRltGC0Ywg0L/QvtC30LjRhtGW0Y4uIn0seyJsYWJlbCI6InNob3cgZ2VudWluZSBtaW5pbXVtIGJsb2NrIHNlcGFyYXRlbHkiLCJiZWZvcmUiOiIgIHslIGlmIHBheTAwMl9jYXJ0X3N0b2NrX3dhcm5pbmcgJX0iLCJhZnRlciI6IiAgeyUgaWYgcGF5MDAyX2NhcnRfbWluaW11bV9jaGVja291dF9ibG9ja2VkICV9XG4gICAgPGRpdiBjbGFzcz1cImFsZXJ0IGFsZXJ0LXdhcm5pbmdcIiByb2xlPVwiYWxlcnRcIiBkYXRhLXBheTAwMi1jYXJ0LW1pbmltdW0taGludD7QlNC70Y8g0L7QtNC90L7Qs9C+INCw0LHQviDQutGW0LvRjNC60L7RhSDRgtC+0LLQsNGA0ZbQsiDQvdC1INC00L7RgdGP0LPQvdGD0YLQviDQvNGW0L3RltC80LDQu9GM0L3RgyDQutGW0LvRjNC60ZbRgdGC0Ywg0LfQsNC80L7QstC70LXQvdC90Y8uINCX0LHRltC70YzRiNGW0YLRjCDQutGW0LvRjNC60ZbRgdGC0Ywg0LDQsdC+INC/0YDQuNCx0LXRgNGW0YLRjCDQv9C+0LfQuNGG0ZbRji48L2Rpdj5cbiAgeyUgZW5kaWYgJX1cbiAgeyUgaWYgcGF5MDAyX2NhcnRfc3RvY2tfd2FybmluZyAlfSJ9LHsibGFiZWwiOiJkaXNhYmxlIGxpbmsgb25seSBmb3IgYWN0dWFsIGNhbm9uaWNhbCBibG9ja2VycyIsImJlZm9yZSI6InslIGlmIHBheTAwMl9jYXJ0X3N0b2NrX2NoZWNrb3V0X2Jsb2NrZWQgJX0iLCJhZnRlciI6InslIGlmIHBheTAwMl9jYXJ0X3N0b2NrX2NoZWNrb3V0X2Jsb2NrZWQgb3IgcGF5MDAyX2NhcnRfbWluaW11bV9jaGVja291dF9ibG9ja2VkICV9In1dfX0sImd1YXJkcyI6eyJzeXN0ZW0vbGlicmFyeS9jYXJ0L2NhcnQucGhwIjoiY2UwODFjNGY0NmQ5Y2RiZWE3MmRmNTQ3Zjk0MmU3MzI0ZjQ2MWYyODlmMWM3YzVhYTJhZGE4ZmYzZjZmOTIwMyJ9fQ==', true), true, 512, JSON_THROW_ON_ERROR);
    $files = $spec['files'];
    out('cwd=' . $root);
    out('time=' . date('c'));
    foreach ($spec['guards'] as $file => $hash) {
        need(is_file($root . '/' . $file) && hash_file('sha256', $root . '/' . $file) === $hash, 'canonical library differs: ' . $file . '; write skipped');
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
    out('done=ok');
    out('self_delete=' . (@unlink(__FILE__) ? 'ok' : 'failed remove_uploaded_patch_manually=yes'));
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
