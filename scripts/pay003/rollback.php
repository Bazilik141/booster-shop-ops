<?php
/** CLI rollback for this PAY-003 backup only; refuses files changed afterwards. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
$manifest = json_decode(file_get_contents(__DIR__ . '/manifest.json'), true);
if (!is_array($manifest) || !is_file($root . '/config.php')) { fwrite(STDERR,"Invalid backup\n"); exit(1); }
foreach ($manifest as $file=>$entry) {
    if (!preg_match('~^(catalog|extension)/[a-zA-Z0-9_./-]+$~D',$file) || strpos($file,'..')!==false) { fwrite(STDERR,"Invalid target\n"); exit(1); }
    $target=$root.'/'.$file;
    $part=$target;
    while ($part!==$root) { if (is_link($part)) { fwrite(STDERR,"Symlink refused\n"); exit(1); } $part=dirname($part); }
    $actual=is_file($target)?hash_file('sha256',$target):null;
    if ($actual!==$entry['after'] && $actual!==$entry['before']) { fwrite(STDERR,'Later change; rollback refused: '.$file."\n"); exit(1); }
    if ($entry['before']!==null && (!is_file(__DIR__.'/original/'.$file)||hash_file('sha256',__DIR__.'/original/'.$file)!==$entry['before'])) { fwrite(STDERR,"Backup hash mismatch\n"); exit(1); }
}
foreach ($manifest as $file=>$entry) {
    $target=$root.'/'.$file;
    if ($entry['before']===null) { if (is_file($target) && !unlink($target)) { fwrite(STDERR,"Removal failed\n"); exit(1); } }
    elseif (!copy(__DIR__.'/original/'.$file,$target) || hash_file('sha256',$target)!==$entry['before']) { fwrite(STDERR,"Restore failed\n"); exit(1); }
}
echo "rollback=ok\ndatabase_touched=no\nnew_files_recoverable=generated_directory\n";
