<?php
/**
 * PAY-002 PUMB order amount reconciliation. PHP 8.0, one controller only.
 * Uses persisted order total (UAH), exact cents, proportional goods allocation.
 * Up to two unit-price buckets per product preserve quantity and exact sum.
 * Invalid/unrepresentable data fails before reservation or bank requests.
 * No deployment DB writes, settings, Mono, delivery, UI or callback changes.
 * Existing applications are not recalculated or recreated.
 * Rollback: restore the target from the printed backup; no DB rollback needed.
 */
declare(strict_types=1);
const PATCH_ID = 'PAY-002_pumb-order-amount_20260831';
function need(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function out(string $message): void { echo $message . PHP_EOL; }
function copyChecked(string $from, string $to, string $hash): void {
    need(copy($from, $to), 'copy failed: ' . $to);
    need(hash_file('sha256', $to) === $hash, 'copy verification failed: ' . $to);
}
try {
    $root = getcwd() ?: '';
    need(PHP_SAPI === 'cli' && realpath($root) === realpath(__DIR__) && is_file($root . '/config.php'), 'Run uploaded copy in public_html');
    $spec = json_decode(base64_decode('eyJmaWxlcyI6eyJleHRlbnNpb24vcHVtYl9jcmVkaXQvY2F0YWxvZy9jb250cm9sbGVyL3BheW1lbnQvcHVtYl9jcmVkaXQucGhwIjp7ImJlZm9yZSI6ImU1MjZiMmYwN2Y5YWUzOTUwYzc3NzM1N2E1YjMwNjM2MjE5ZWFmMDY1MGZmZTQ2NjAyNWJkYWIwZTM2ZTllMTEiLCJhZnRlciI6IjQyMmQxMjY4NTQyNjM0MDUxMTJiZmUyYTcyNjdiMTc4Y2RhZjEyYWFmMzgxZGJjYjhmOTdhMTg0MGUwMWFjNDUiLCJlZGl0cyI6W3sibGFiZWwiOiJidWlsZCBhbmQgdmFsaWRhdGUgaW52b2ljZSBiZWZvcmUgcmVzZXJ2aW5nIGEgbmV3IGFwcGxpY2F0aW9uIiwiYmVmb3JlIjoiICAgICAgICB0cnkge1xuICAgICAgICAgICAgJHJlc2VydmF0aW9uID0gJHRoaXMtPnJlc2VydmVDcmVhdGUoJG9yZGVySWQsICRpc1Rlc3QpOyIsImFmdGVyIjoiICAgICAgICAvLyBQQVktMDAyLU9SREVSLUFNT1VOVDogZmFpbCBiZWZvcmUgcmVzZXJ2YXRpb24vT0F1dGgvYmFuayBvbiBpbnZhbGlkIHRvdGFscy5cbiAgICAgICAgdHJ5IHtcbiAgICAgICAgICAgICRwYXlsb2FkID0gJHRoaXMtPmNyZWF0ZVBheWxvYWQoJG9yZGVyLCAkdGVybSk7XG4gICAgICAgIH0gY2F0Y2ggKFxcVGhyb3dhYmxlICRleGNlcHRpb24pIHtcbiAgICAgICAgICAgICR0aGlzLT5yZXBseShbJ2Vycm9yJyA9PiAn0J3QtSDQstC00LDQu9C+0YHRjyDRg9C30LPQvtC00LjRgtC4INGB0YPQvNGDINC30LDQvNC+0LLQu9C10L3QvdGPINC00LvRjyDQn9Cj0JzQkS4g0JfQstC10YDQvdGW0YLRjNGB0Y8g0LTQviDQv9GW0LTRgtGA0LjQvNC60Lgg0LzQsNCz0LDQt9C40L3Rgy4nXSk7XG4gICAgICAgICAgICByZXR1cm47XG4gICAgICAgIH1cblxuICAgICAgICB0cnkge1xuICAgICAgICAgICAgJHJlc2VydmF0aW9uID0gJHRoaXMtPnJlc2VydmVDcmVhdGUoJG9yZGVySWQsICRpc1Rlc3QpOyJ9LHsibGFiZWwiOiJyZXVzZSB2YWxpZGF0ZWQgaW52b2ljZSBhZnRlciB3aW5uaW5nIHRoZSBleGlzdGluZyByZXNlcnZhdGlvbiBndWFyZCIsImJlZm9yZSI6IiAgICAgICAgJHBheWxvYWQgPSAkdGhpcy0+Y3JlYXRlUGF5bG9hZCgkb3JkZXIsICR0ZXJtKTtcbiAgICAgICAgJHJlc3BvbnNlID0gJHRoaXMtPmFwaSgnUE9TVCcsIHNlbGY6OkFQSV9DUkVBVEUsICRwYXlsb2FkKTsiLCJhZnRlciI6IiAgICAgICAgJHJlc3BvbnNlID0gJHRoaXMtPmFwaSgnUE9TVCcsIHNlbGY6OkFQSV9DUkVBVEUsICRwYXlsb2FkKTsifSx7ImxhYmVsIjoiYWxsb2NhdGUgcGVyc2lzdGVkIG9yZGVyIHRvdGFsIHRvIGJhbmsgZ29vZHMgaW4gZXhhY3QgY2VudHMiLCJiZWZvcmUiOiIgICAgcHJpdmF0ZSBmdW5jdGlvbiBjcmVhdGVQYXlsb2FkKGFycmF5ICRvcmRlciwgaW50ICR0ZXJtKTogYXJyYXkge1xuICAgICAgICAkZ29vZHMgPSBbXTsgJHRvdGFsID0gMC4wO1xuICAgICAgICAkcHJvZHVjdHMgPSAkdGhpcy0+ZGItPnF1ZXJ5KFwiU0VMRUNUIGBuYW1lYCxgcXVhbnRpdHlgLGBwcmljZWAgRlJPTSBgXCIgLiBEQl9QUkVGSVggLiBcIm9yZGVyX3Byb2R1Y3RgIFdIRVJFIGBvcmRlcl9pZGA9J1wiIC4gKGludCkkb3JkZXJbJ29yZGVyX2lkJ10gLiBcIidcIiktPnJvd3M7XG4gICAgICAgIGZvcmVhY2ggKCRwcm9kdWN0cyBhcyAkcHJvZHVjdCkgeyAkYW1vdW50ID0gcm91bmQoKGZsb2F0KSRwcm9kdWN0WydwcmljZSddLCAyKTsgJGNvdW50ID0gKGludCkkcHJvZHVjdFsncXVhbnRpdHknXTsgJGdvb2RzW10gPSBbJ25hbWUnID0+IChzdHJpbmcpJHByb2R1Y3RbJ25hbWUnXSwgJ2NvdW50JyA9PiAkY291bnQsICdhbW91bnQnID0+ICRhbW91bnRdOyAkdG90YWwgKz0gJGFtb3VudCAqICRjb3VudDsgfVxuICAgICAgICAkdG90YWwgPSByb3VuZCgkdG90YWwsIDIpO1xuICAgICAgICByZXR1cm4gWydzdG9yZV9vcmRlcl9pZCcgPT4gJ09DLScgLiAoaW50KSRvcmRlclsnb3JkZXJfaWQnXSwgJ3BvaW50X29mX3NhbGVfY29kZScgPT4gKHN0cmluZykkdGhpcy0+Y29uZmlnLT5nZXQoJ3BheW1lbnRfcHVtYl9jcmVkaXRfcG9pbnRfb2Zfc2FsZV9jb2RlJyksICdwYXJ0bmVyX25hbWUnID0+IChzdHJpbmcpJHRoaXMtPmNvbmZpZy0+Z2V0KCdwYXltZW50X3B1bWJfY3JlZGl0X3BhcnRuZXJfbmFtZScpLCAnY2hhbm5lbF90eXBlJyA9PiAnSU5URVJORVQnLCAnZmxvdycgPT4gWyd0eXBlJyA9PiAnRElHSVRBTF9TRiddLCAnY3VzdG9tZXInID0+IFsncGhvbmUnID0+ICR0aGlzLT5waG9uZSgoc3RyaW5nKSRvcmRlclsndGVsZXBob25lJ10pXSwgJ2ludm9pY2VzJyA9PiBbWydkYXRlJyA9PiBkYXRlKCdZLW0tZCcpLCAnaW52b2ljZV9udW1iZXInID0+ICdPQy0nIC4gKGludCkkb3JkZXJbJ29yZGVyX2lkJ10sICdnb29kcycgPT4gJGdvb2RzLCAndG90YWxfYW1vdW50JyA9PiAkdG90YWxdXSwgJ2NyZWRpdF9yZXF1ZXN0JyA9PiBbJ3Rlcm0nID0+ICR0ZXJtLCAnYW1vdW50JyA9PiAkdG90YWxdXTtcbiAgICB9XG4iLCJhZnRlciI6IiAgICAvLyBQQVktMDAyLU9SREVSLUFNT1VOVDogcGVyc2lzdGVkIG9yZGVyIHRvdGFsIGlzIGF1dGhvcml0YXRpdmUsIGluIFVBSC5cbiAgICBwcml2YXRlIGZ1bmN0aW9uIGNyZWF0ZVBheWxvYWQoYXJyYXkgJG9yZGVyLCBpbnQgJHRlcm0pOiBhcnJheSB7XG4gICAgICAgIGlmIChQSFBfSU5UX1NJWkUgPCA4KSB0aHJvdyBuZXcgXFxSdW50aW1lRXhjZXB0aW9uKCdhbW91bnRfaW50ZWdlcl93aWR0aCcpO1xuICAgICAgICAkdGFyZ2V0ID0gaW50ZGl2KCR0aGlzLT5wYXkwMDJEZWNpbWFsNCgkb3JkZXJbJ3RvdGFsJ10gPz8gbnVsbCkgKyA1MCwgMTAwKTtcbiAgICAgICAgaWYgKCR0YXJnZXQgPD0gMCkgdGhyb3cgbmV3IFxcUnVudGltZUV4Y2VwdGlvbignYW1vdW50X25vbnBvc2l0aXZlJyk7XG4gICAgICAgICRwcm9kdWN0cyA9ICR0aGlzLT5kYi0+cXVlcnkoXCJTRUxFQ1QgYG5hbWVgLGBxdWFudGl0eWAsYHByaWNlYCBGUk9NIGBcIiAuIERCX1BSRUZJWCAuIFwib3JkZXJfcHJvZHVjdGAgV0hFUkUgYG9yZGVyX2lkYD0nXCIgLiAoaW50KSRvcmRlclsnb3JkZXJfaWQnXSAuIFwiJyBPUkRFUiBCWSBgb3JkZXJfcHJvZHVjdF9pZGAgQVNDXCIpLT5yb3dzO1xuICAgICAgICBpZiAoISRwcm9kdWN0cykgdGhyb3cgbmV3IFxcUnVudGltZUV4Y2VwdGlvbignYW1vdW50X25vX2dvb2RzJyk7XG4gICAgICAgICRsaW5lcyA9IFtdOyAkd2VpZ2h0VG90YWwgPSAwO1xuICAgICAgICBmb3JlYWNoICgkcHJvZHVjdHMgYXMgJHByb2R1Y3QpIHtcbiAgICAgICAgICAgICRyYXdRdWFudGl0eSA9ICRwcm9kdWN0WydxdWFudGl0eSddID8/IG51bGw7XG4gICAgICAgICAgICBpZiAoKCFpc19pbnQoJHJhd1F1YW50aXR5KSAmJiAhaXNfc3RyaW5nKCRyYXdRdWFudGl0eSkpIHx8ICFwcmVnX21hdGNoKCcvXlsxLTldWzAtOV17MCw4fSQvRCcsIChzdHJpbmcpJHJhd1F1YW50aXR5KSkgdGhyb3cgbmV3IFxcUnVudGltZUV4Y2VwdGlvbignYW1vdW50X3F1YW50aXR5Jyk7XG4gICAgICAgICAgICAkcXVhbnRpdHkgPSAoaW50KSRyYXdRdWFudGl0eTtcbiAgICAgICAgICAgICRwcmljZSA9ICR0aGlzLT5wYXkwMDJEZWNpbWFsNCgkcHJvZHVjdFsncHJpY2UnXSA/PyBudWxsKTtcbiAgICAgICAgICAgICRuYW1lID0gKHN0cmluZykoJHByb2R1Y3RbJ25hbWUnXSA/PyAnJyk7XG4gICAgICAgICAgICAvLyBLZWVwIHplcm8tcHJpY2VkIGdpZnRzIGF0IHplcm87IG5ldmVyIGludmVudCBwcmljZXMgb3IgZHJvcCBnb29kcy5cbiAgICAgICAgICAgIGlmICh0cmltKCRuYW1lKSA9PT0gJycgfHwgJHByaWNlID4gaW50ZGl2KFBIUF9JTlRfTUFYLCAkcXVhbnRpdHkpKSB0aHJvdyBuZXcgXFxSdW50aW1lRXhjZXB0aW9uKCdhbW91bnRfZ29vZHMnKTtcbiAgICAgICAgICAgICR3ZWlnaHQgPSAkcHJpY2UgKiAkcXVhbnRpdHk7XG4gICAgICAgICAgICBpZiAoJHdlaWdodCA+IFBIUF9JTlRfTUFYIC0gJHdlaWdodFRvdGFsIHx8ICR3ZWlnaHQgPiBpbnRkaXYoUEhQX0lOVF9NQVgsICR0YXJnZXQpKSB0aHJvdyBuZXcgXFxSdW50aW1lRXhjZXB0aW9uKCdhbW91bnRfb3ZlcmZsb3cnKTtcbiAgICAgICAgICAgICR3ZWlnaHRUb3RhbCArPSAkd2VpZ2h0O1xuICAgICAgICAgICAgJGxpbmVzW10gPSBbJ25hbWUnID0+ICRuYW1lLCAncXVhbnRpdHknID0+ICRxdWFudGl0eSwgJ3dlaWdodCcgPT4gJHdlaWdodF07XG4gICAgICAgIH1cbiAgICAgICAgaWYgKCR3ZWlnaHRUb3RhbCA8PSAwKSB0aHJvdyBuZXcgXFxSdW50aW1lRXhjZXB0aW9uKCdhbW91bnRfbm9fcHJpY2VkX2dvb2RzJyk7XG4gICAgICAgIC8vIExhcmdlc3QtcmVtYWluZGVyIGFsbG9jYXRpb246IGV4YWN0IGludGVnZXIgYXJpdGhtZXRpYywgc3RhYmxlIHJvdyB0aWVzLlxuICAgICAgICAkYWxsb2NhdGVkID0gMDtcbiAgICAgICAgZm9yZWFjaCAoJGxpbmVzIGFzICYkbGluZSkge1xuICAgICAgICAgICAgJG51bWVyYXRvciA9ICR0YXJnZXQgKiAkbGluZVsnd2VpZ2h0J107XG4gICAgICAgICAgICAkbGluZVsnY2VudHMnXSA9IGludGRpdigkbnVtZXJhdG9yLCAkd2VpZ2h0VG90YWwpO1xuICAgICAgICAgICAgJGxpbmVbJ3JlbWFpbmRlciddID0gJG51bWVyYXRvciAlICR3ZWlnaHRUb3RhbDtcbiAgICAgICAgICAgICRhbGxvY2F0ZWQgKz0gJGxpbmVbJ2NlbnRzJ107XG4gICAgICAgIH1cbiAgICAgICAgdW5zZXQoJGxpbmUpO1xuICAgICAgICAkcmFuayA9IGFycmF5X2tleXMoJGxpbmVzKTtcbiAgICAgICAgdXNvcnQoJHJhbmssIHN0YXRpYyBmdW5jdGlvbiAoaW50ICRhLCBpbnQgJGIpIHVzZSAoJGxpbmVzKTogaW50IHtcbiAgICAgICAgICAgIHJldHVybiAoJGxpbmVzWyRiXVsncmVtYWluZGVyJ10gPD0+ICRsaW5lc1skYV1bJ3JlbWFpbmRlciddKSA/OiAoJGEgPD0+ICRiKTtcbiAgICAgICAgfSk7XG4gICAgICAgICRsZWZ0ID0gJHRhcmdldCAtICRhbGxvY2F0ZWQ7XG4gICAgICAgIGlmICgkbGVmdCA8IDAgfHwgJGxlZnQgPj0gY291bnQoJGxpbmVzKSkgdGhyb3cgbmV3IFxcUnVudGltZUV4Y2VwdGlvbignYW1vdW50X3JlbWFpbmRlcicpO1xuICAgICAgICBmb3IgKCRpID0gMDsgJGkgPCAkbGVmdDsgJGkrKykgJGxpbmVzWyRyYW5rWyRpXV1bJ2NlbnRzJ10rKztcbiAgICAgICAgJGdvb2RzID0gW107ICRjaGVjayA9IDA7XG4gICAgICAgIGZvcmVhY2ggKCRsaW5lcyBhcyAkbGluZSkge1xuICAgICAgICAgICAgJHF1YW50aXR5ID0gJGxpbmVbJ3F1YW50aXR5J107XG4gICAgICAgICAgICAkdW5pdCA9IGludGRpdigkbGluZVsnY2VudHMnXSwgJHF1YW50aXR5KTtcbiAgICAgICAgICAgICRleHRyYSA9ICRsaW5lWydjZW50cyddICUgJHF1YW50aXR5O1xuICAgICAgICAgICAgaWYgKCR1bml0IDw9IDAgJiYgJGxpbmVbJ3dlaWdodCddID4gMCkgdGhyb3cgbmV3IFxcUnVudGltZUV4Y2VwdGlvbignYW1vdW50X3VucmVwcmVzZW50YWJsZV9nb29kcycpO1xuICAgICAgICAgICAgLy8gQXQgbW9zdCB0d28gcHJpY2UgYnVja2V0cyBwZXIgcHJvZHVjdCwgcHJlc2VydmluZyBpdHMgdW5pdCBjb3VudC5cbiAgICAgICAgICAgIC8vIEV4YW1wbGU6IDMgdW5pdHMgZm9yIDEwMDAgVUFIID0gMiB4IDMzMy4zMyArIDEgeCAzMzMuMzQuXG4gICAgICAgICAgICBpZiAoJHF1YW50aXR5IC0gJGV4dHJhID4gMCkgJGdvb2RzW10gPSBbJ25hbWUnID0+ICRsaW5lWyduYW1lJ10sICdjb3VudCcgPT4gJHF1YW50aXR5IC0gJGV4dHJhLCAnYW1vdW50JyA9PiAkdW5pdCAvIDEwMF07XG4gICAgICAgICAgICBpZiAoJGV4dHJhID4gMCkgJGdvb2RzW10gPSBbJ25hbWUnID0+ICRsaW5lWyduYW1lJ10sICdjb3VudCcgPT4gJGV4dHJhLCAnYW1vdW50JyA9PiAoJHVuaXQgKyAxKSAvIDEwMF07XG4gICAgICAgICAgICAkY2hlY2sgKz0gJHVuaXQgKiAkcXVhbnRpdHkgKyAkZXh0cmE7XG4gICAgICAgIH1cbiAgICAgICAgaWYgKCRjaGVjayAhPT0gJHRhcmdldCkgdGhyb3cgbmV3IFxcUnVudGltZUV4Y2VwdGlvbignYW1vdW50X21pc21hdGNoJyk7XG4gICAgICAgICR0b3RhbCA9ICR0YXJnZXQgLyAxMDA7XG4gICAgICAgIHJldHVybiBbJ3N0b3JlX29yZGVyX2lkJyA9PiAnT0MtJyAuIChpbnQpJG9yZGVyWydvcmRlcl9pZCddLCAncG9pbnRfb2Zfc2FsZV9jb2RlJyA9PiAoc3RyaW5nKSR0aGlzLT5jb25maWctPmdldCgncGF5bWVudF9wdW1iX2NyZWRpdF9wb2ludF9vZl9zYWxlX2NvZGUnKSwgJ3BhcnRuZXJfbmFtZScgPT4gKHN0cmluZykkdGhpcy0+Y29uZmlnLT5nZXQoJ3BheW1lbnRfcHVtYl9jcmVkaXRfcGFydG5lcl9uYW1lJyksICdjaGFubmVsX3R5cGUnID0+ICdJTlRFUk5FVCcsICdmbG93JyA9PiBbJ3R5cGUnID0+ICdESUdJVEFMX1NGJ10sICdjdXN0b21lcicgPT4gWydwaG9uZScgPT4gJHRoaXMtPnBob25lKChzdHJpbmcpJG9yZGVyWyd0ZWxlcGhvbmUnXSldLCAnaW52b2ljZXMnID0+IFtbJ2RhdGUnID0+IGRhdGUoJ1ktbS1kJyksICdpbnZvaWNlX251bWJlcicgPT4gJ09DLScgLiAoaW50KSRvcmRlclsnb3JkZXJfaWQnXSwgJ2dvb2RzJyA9PiAkZ29vZHMsICd0b3RhbF9hbW91bnQnID0+ICR0b3RhbF1dLCAnY3JlZGl0X3JlcXVlc3QnID0+IFsndGVybScgPT4gJHRlcm0sICdhbW91bnQnID0+ICR0b3RhbF1dO1xuICAgIH1cbiAgICAvKiogUGFyc2UgT3BlbkNhcnQgREVDSU1BTCguLi4sNCkgd2l0aG91dCBmbG9hdGluZy1wb2ludCBtb25leSBhcml0aG1ldGljLiAqL1xuICAgIHByaXZhdGUgZnVuY3Rpb24gcGF5MDAyRGVjaW1hbDQobWl4ZWQgJHZhbHVlKTogaW50IHtcbiAgICAgICAgaWYgKGlzX2Zsb2F0KCR2YWx1ZSkpIHtcbiAgICAgICAgICAgIGlmICghaXNfZmluaXRlKCR2YWx1ZSkgfHwgJHZhbHVlIDwgMCB8fCAkdmFsdWUgPj0gMTAwMDAwMDAwMCkgdGhyb3cgbmV3IFxcUnVudGltZUV4Y2VwdGlvbignYW1vdW50X2RlY2ltYWwnKTtcbiAgICAgICAgICAgICR2YWx1ZSA9IG51bWJlcl9mb3JtYXQoJHZhbHVlLCA0LCAnLicsICcnKTtcbiAgICAgICAgfVxuICAgICAgICBpZiAoIWlzX3N0cmluZygkdmFsdWUpICYmICFpc19pbnQoJHZhbHVlKSkgdGhyb3cgbmV3IFxcUnVudGltZUV4Y2VwdGlvbignYW1vdW50X2RlY2ltYWwnKTtcbiAgICAgICAgaWYgKCFwcmVnX21hdGNoKCcvXihbMC05XXsxLDl9KSg/OlxcLihbMC05XXsxLDR9KSk/JC9EJywgKHN0cmluZykkdmFsdWUsICRtKSkgdGhyb3cgbmV3IFxcUnVudGltZUV4Y2VwdGlvbignYW1vdW50X2RlY2ltYWwnKTtcbiAgICAgICAgcmV0dXJuIChpbnQpJG1bMV0gKiAxMDAwMCArIChpbnQpc3RyX3BhZCgkbVsyXSA/PyAnJywgNCwgJzAnKTtcbiAgICB9XG4ifV19fSwiZ3VhcmRzIjp7fX0=', true), true, 512, JSON_THROW_ON_ERROR);
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
