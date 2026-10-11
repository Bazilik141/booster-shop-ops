from pathlib import Path
import hashlib,json,re,difflib
W=Path(__file__).resolve().parent; R=W.parents[1]; S=W/'source'; E=R/'diagnostics/RD-25-WP1-FIX_photo-contain_evidence_20261011'
E.mkdir(exist_ok=True)
sha=lambda b:hashlib.sha256(b).hexdigest()
name='RD-25-WP1-FIX_photo-contain_20261011.php'; marker='RD-25-WP1-FIX-PHOTO 20261011'; token='rd25-wp1-photo-contain-20261011'
css='catalog/view/stylesheet/boostershop-ds.css'; header='catalog/view/template/common/header.twig'; prefix='catalog/view/stylesheet/boostershop-ds.css?v='
gate=json.loads((W/'source-gate.json').read_text())
expected_context={'catalog/model/tool/image.php':'85cf9489244c48fe915bc25f370cdfdb2b39c9c6d6a42219cec7f84a9e375e71','system/library/image.php':'7f81dcbb8b5d2d032005ae28ce01cea5ba7542f7334fd9909e538f6952adeff9','catalog/controller/product/thumb.php':'cf4f8e354b18a4318af159c34bf3c083d0991007b42d03e2c57aa4963c1ed3b1','catalog/view/template/product/thumb.twig':'d9237b2c9c0cc21cda73a86ba6cf1ff66b7ad956f0da3c24a8e1add6518037d6'}
assert all(sha((S/p).read_bytes())==h for p,h in expected_context.items())
before=(S/css).read_bytes().decode('utf8'); old='''.bs-pcard__media--photo img {
  inset: 0; width: 100%; height: 100%; object-fit: cover;
  border-radius: var(--bs-r);
}'''
new='''/* RD-25-WP1-FIX-PHOTO 20261011: whole photo, bottom aligned; empty space allowed. */
.bs-pcard__media--photo img {
  inset: 0; width: 100%; height: 100%; object-fit: contain;
  object-position: center bottom;
  border-radius: var(--bs-r);
}'''
old_comment='Geometry below is the approved catalog framing: cut 8%/6%/84%/94%, photo full bleed.'
new_comment='Geometry below keeps cut 8%/6%/84%/94%; photos preserve their full source.'
replacements=[(old,new),(old_comment,new_comment)]
after=before
for b,a in replacements: assert after.count(b)==1;after=after.replace(b,a)
assert before.count('.bs-pcard__media--photo img')==after.count('.bs-pcard__media--photo img')==1
hdr=(S/header).read_bytes().decode('utf8'); pattern=re.escape(prefix)+r'([A-Za-z0-9._-]{1,64})(?=["\'])'
assert hdr.count(prefix)==1 and len(re.findall(pattern,hdr))==1
normalize=lambda t:re.sub(pattern,lambda m:prefix+'__RD25_TOKEN__',t,count=1)
hnorm=normalize(hdr);ha=re.sub(pattern,lambda m:prefix+token,hdr,count=1)
targets={css:{'before_sha256':sha(before.encode()),'after_sha256':sha(after.encode()),'replacements':replacements},header:{'before_sha256':sha(hnorm.encode()),'after_sha256':sha(hnorm.encode()),'replacements':[(prefix+'__RD25_TOKEN__',prefix+'__RD25_TOKEN__')]}}
for p,a in [(css,after),(header,ha)]:
 out=W/'expected'/p;out.parent.mkdir(parents=True,exist_ok=True);out.write_bytes(a.encode())
baseline=(R/'patches/RD-25-WP1_media-fit-catalog_20261009.php').read_text(encoding='utf8')
helpers=baseline[baseline.index('function rd25Out'):baseline.index('/* Reviewed transformations')]
body=baseline[baseline.index('$originals = [];'):]
body=body.replace('RD-25-WP1_media-fit-catalog_20261009-',name[:-4]+'-')
start=body.index('\t$marked = 0;');end=body.index('\n\t$candidates = [];',start)
repeat='''	$marked = substr_count($originals['catalog/view/stylesheet/boostershop-ds.css'], RD25_MARKER);
	if ($marked > 1) { rd25Fail('duplicate_marker'); }
	if ($marked === 1) {
		foreach ($targets['catalog/view/stylesheet/boostershop-ds.css']['replacements'] as $replace) {
			if (substr_count($originals['catalog/view/stylesheet/boostershop-ds.css'], $replace['after']) !== 1) { rd25Fail('applied_content_changed'); }
		}
		flock($lock, LOCK_UN); fclose($lock); $lock = null;
		rd25Out('already_applied', 'yes');
		if ($dry_run) { rd25Out('done', 'ok'); } else { rd25Finish(); }
		exit(0);
	}
	foreach ($context as $relative => $expected_hash) {
		$path = $root . '/' . $relative;
		if (!is_file($path) || hash('sha256', rd25Read($path)) !== $expected_hash) { rd25Fail('context_sha256_mismatch:' . $relative); }
		rd25Out('context_file', $relative);
	}
'''
body=body[:start]+repeat+body[end:]
body=body.replace("\tif (PHP_SAPI !== 'cli')", "\tif (PHP_VERSION_ID < 80000) { rd25Fail('php_8_0_required'); }\n\trd25Out('php_version', PHP_VERSION);\n\tif (PHP_SAPI !== 'cli')",1)
body=body.replace("\t$root = realpath(getcwd());", "\trd25Lint(__FILE__);\n\t$root = realpath(getcwd());",1)
description='''<?php
/**
 * RD-25 WP1-FIX-PHOTO: complete product photographs, contain + bottom.
 * Owner: whole product visible; empty space acceptable (2026-10-11).
 * Fresh six-file owner export RD-25-WP1-FIX-photo-check-20261011.tar.gz.
 * Root cause: boostershop-ds.css:276 photo rule uses cover in 1:1 / 4:5 slots.
 * Edit that source rule directly; no override layer, !important, JS or new positioning.
 * Prior selector writer: RD-25-WP1. RD-26 amber preorder rules stay byte-identical.
 * Only CSS + header token are written. Header is hash-pinned with its validated
 * current token normalized; token is read by path prefix and replaced wholesale.
 * CSS marker alone controls repeat: later header tokens are never overwritten.
 * Four PHP/Thumb context files are read/hash-gated before first apply, never written.
 * r2 trim/classification/G1 budget, fit caches, source images and commerce unchanged.
 * All card photos (including Mystery Box on mobile) gain possible empty space.
 * PHP >=8.0, CLI only; runner self-lint before writes. CSS/Twig are not PHP files.
 * Backup/readback, atomic writes, restore-on-failure, exact rollback receipt.
 * Usage: php RD-25-WP1-FIX_photo-contain_20261011.php [--dry-run]
 * Rollback: re-upload runner; --rollback=_patch_backups/<printed-directory>.
 * Successful apply/repeat/rollback self-deletes; dry-run does not.
 * No DB changes or media cache deletion. Clear only compiled Twig cache after apply.
 */
declare(strict_types=1);

'''
out=description+f"const RD25_MARKER = '{marker}';\nconst RD25_TOKEN = '{token}';\nconst RD25_PREFIX = '{prefix}';\n\n"+helpers
out+='$context = [\n'+''.join(f"'{p}' => '{h}',\n" for p,h in expected_context.items())+'];\n\n$targets = [\n'
for i,(p,t) in enumerate(targets.items()):
 out+=f"'{p}' => ['before_sha256' => '{t['before_sha256']}', 'after_sha256' => '{t['after_sha256']}', 'replacements' => [\n"
 for j,(b,a) in enumerate(t['replacements']):
  out+=f"['before' => <<<'RD25_PHOTO_B_{i}_{j}'\n{b}\nRD25_PHOTO_B_{i}_{j}, 'after' => <<<'RD25_PHOTO_A_{i}_{j}'\n{a}\nRD25_PHOTO_A_{i}_{j}],\n"
 out+=']],\n'
out+='];\n\n'+body
patch=R/'patches'/name;assert not patch.exists();patch.write_bytes(out.encode())
manifest={'patch':name,'patch_sha256':sha(patch.read_bytes()),'patch_bytes':patch.stat().st_size,'marker':marker,'token':token,'targets':{p:{k:v for k,v in t.items() if k!='replacements'} for p,t in targets.items()},'context':expected_context,'header_before_actual_sha256':sha(hdr.encode()),'header_after_actual_sha256':sha(ha.encode()),'header_token_read':re.findall(pattern,hdr)[0]}
(E/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n',encoding='utf8');(E/'source-gate.json').write_text(json.dumps(gate,indent=2)+'\n',encoding='utf8')
diff=''
for p,a in [(css,after),(header,ha)]:diff+=''.join(difflib.unified_diff((S/p).read_text(encoding='utf8').splitlines(True),a.splitlines(True),fromfile='before/'+p,tofile='after/'+p))
(E/'source.diff').write_text(diff,encoding='utf8')
print(json.dumps(manifest,indent=2))
