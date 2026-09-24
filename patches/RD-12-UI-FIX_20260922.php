<?php
declare(strict_types=1);
/* RD-12 UI fix (2026-09-22): mini-cart trigger badge/label + shipping banner colors. CSS-only, no markup/logic changes. */
$id=pathinfo(__FILE__,PATHINFO_FILENAME);$root=getcwd();
function fail12f(string $m):void{fwrite(STDERR,"error=$m\n");exit(1);}
exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg(__FILE__).' 2>&1',$o,$c);if($c)fail12f('php_l_failed '.implode(' | ',$o));
if(!is_file($root.'/config.php'))fail12f('run_from_opencart_root_config_missing');
$path='catalog/view/stylesheet/boostershop-ds.css';
if(!is_file($root.'/'.$path))fail12f('target_missing file='.$path);
echo 'cwd='.$root."\ntime=".date(DATE_ATOM)."\n";
$css=file_get_contents($root.'/'.$path);
if(str_contains($css,'/* RD-12 UI fix 2026-09-22 */')){echo "already_applied=yes\n";@unlink(__FILE__);exit;}
$fix=<<<'CSS'

/* RD-12 UI fix 2026-09-22: trigger badge + shipping banner colors (overrides legacy #cart theme bleed) */
.mini-cart-trigger{position:relative;display:inline-flex;align-items:center;gap:6px}
.mini-cart-trigger .bs-btn-label{position:absolute;width:1px;height:1px;padding:0;margin:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.bs-cart-qty{display:inline-grid;place-items:center;min-width:20px;height:20px;padding:0 5px;border-radius:999px;background:var(--bs-ink);color:#fff;font-size:11.5px;font-weight:700;line-height:1}
.bs-mini-cart__shipping,.bs-mini-cart__shipping *{color:var(--bs-ink-2) !important}
.bs-mini-cart__shipping{background:var(--bs-bg) !important}
.bs-mini-cart__shipping.is-free,.bs-mini-cart__shipping.is-free *{color:var(--bs-green-hover) !important}
.bs-mini-cart__shipping.is-free{background:var(--bs-green-soft) !important}
.bs-mini-cart__shipping small{color:var(--bs-ink-3) !important}
CSS;
$new=$css.$fix;
$backup=$root.'/_patch_backups/'.$id.'-'.date('Ymd-His');
if(!is_dir($backup)&&!mkdir($backup,0755,true))fail12f('backup_dir_failed');
if(!copy($root.'/'.$path,$backup.'/boostershop-ds.css'))fail12f('backup_failed');
echo "backup=$backup\n";
if(file_put_contents($root.'/'.$path,$new,LOCK_EX)!==strlen($new)){@copy($backup.'/boostershop-ds.css',$root.'/'.$path);fail12f('write_failed restored=yes');}
echo "changed_file=$path\ndone=ok\n";
@unlink(__FILE__);
