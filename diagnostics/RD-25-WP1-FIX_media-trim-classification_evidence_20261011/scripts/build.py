from pathlib import Path
import hashlib,json,re,difflib
W=Path(__file__).resolve().parent; R=W.parents[1]; S=R/'work/rd25-wp1-fix-phase0-20261011/source'
E=R/'diagnostics/RD-25-WP1-FIX_media-trim-classification_evidence_20261011'; E.mkdir(exist_ok=True)
NAME='RD-25-WP1-FIX_media-trim-classification_20261011.php'; MARK='RD-25-WP1-FIX 20261011'
sha=lambda b:hashlib.sha256(b).hexdigest()
pins={'catalog/model/tool/image.php':'9d6aaec56eaa44be072e67957e43b7365ca8f115283988d0ab7a3227db885285','system/library/image.php':'b032345fa128dc867a55dbe4bf49f4070a34a2efa952525a69006fdb706875d9','catalog/controller/product/thumb.php':'47e56e45e134359074aecdd3a1a50a068e8b19766b40589cb8688e077df5d2eb'}
targets={}; diffs=[]
for rel,pin in pins.items():
 b=(S/rel).read_bytes(); assert sha(b)==pin; original=b.decode(); after=original; changes=[]
 def replace(old,new):
  global after
  assert after.count(old)==1,(rel,old[:90]); after=after.replace(old,new); changes.append((old,new))
 if rel.startswith('catalog/model'):
  replace('private const RD25_MEDIA_VERSION = 3;', 'private const RD25_MEDIA_VERSION = 4;')
  start=after.index('\t\t\t$corner_alpha = [];'); end=after.index('\t\t\t$json = json_encode',start)
  replace(after[start:end], '''\t\t\t$measurement = $this->rd25Measure($gd, (int)$dimensions[0], (int)$dimensions[1]);
\t\t\timagedestroy($gd);
\t\t\t$metadata = array_merge([
\t\t\t\t'version' => self::RD25_MEDIA_VERSION, 'source' => $source['relative'],
\t\t\t\t'mtime' => $source['mtime'], 'size' => $source['size'],
\t\t\t\t'width' => (int)$dimensions[0], 'height' => (int)$dimensions[1], 'type' => (int)$dimensions[2]
\t\t\t], $measurement);
''')
  replace("'rd25-fit-v1:'", "'rd25-fit-v2:'")
  replace("\t\t\t$image->resizeFit($width, $height);", "\t\t\tif ($metadata['kind'] === 'cut') {\n\t\t\t\t$image->cropFit(...$metadata['crop_box_exclusive']);\n\t\t\t}\n\t\t\t$image->resizeFit($width, $height);")
  replace('\tprivate function rd25Source(string $filename): array {', (W/'helpers.txt').read_text()+'\n\tprivate function rd25Source(string $filename): array {')
  replace('\t\treturn $metadata;\n\t}\n\t/**', '''\t\t$box = $metadata['crop_box_exclusive'] ?? null;
\t\tif (!is_array($box) || array_keys($box) !== [0, 1, 2, 3]) { return []; }
\t\tforeach ($box as $value) { if (!is_int($value)) { return []; } }
\t\tif ($box[0] < 0 || $box[1] < 0 || $box[2] > $metadata['width'] || $box[3] > $metadata['height']
\t\t\t|| $box[2] <= $box[0] || $box[3] <= $box[1]
\t\t\t|| ($metadata['crop_width'] ?? null) !== $box[2] - $box[0] || ($metadata['crop_height'] ?? null) !== $box[3] - $box[1]
\t\t\t|| ($metadata['kind'] === 'photo' && $box !== [0, 0, $metadata['width'], $metadata['height']])) { return []; }
\t\treturn $metadata;
\t}
\t/**''')
 elif rel.startswith('system/'):
  replace('\tpublic function crop(int $top_x, int $top_y, int $bottom_x, int $bottom_y): void {', (W/'cropFit.txt').read_text()+'\n\tpublic function crop(int $top_x, int $top_y, int $bottom_x, int $bottom_y): void {')
 else:
  replace("\t\t\t\t$bs_scale = min(1.0, 700 / $bs_media['width'], 875 / $bs_media['height']);", "\t\t\t\t// RD-25-WP1-FIX 20261011: match the generated crop + fit dimensions.\n\t\t\t\t$bs_scale = min(1.0, 700 / $bs_media['crop_width'], 875 / $bs_media['crop_height']);")
  replace("floor($bs_media['width'] * $bs_scale)","floor($bs_media['crop_width'] * $bs_scale)")
  replace("floor($bs_media['height'] * $bs_scale)","floor($bs_media['crop_height'] * $bs_scale)")
 p=W/'expected'/rel; p.parent.mkdir(parents=True,exist_ok=True); p.write_bytes(after.encode())
 targets[rel]={'before_sha256':pin,'after_sha256':sha(after.encode()),'replacements':changes}
 diffs.extend(difflib.unified_diff(original.splitlines(True),after.splitlines(True),fromfile='a/'+rel,tofile='b/'+rel))
template=(R/'work/rd25-wp1-20261009/runner-template.txt').read_text(); body=template[template.index('declare(strict_types=1);'):]
body=body.replace("const RD25_MARKER = 'RD-25-WP1 20261009';", "const RD25_MARKER = '"+MARK+"';")
body=re.sub(r'const RD25_TOKEN = .*?;\nconst RD25_PREFIX = .*?;\n','',body)
a=body.index('function rd25Normalize'); z=body.index('function rd25Mkdir',a)
body=body[:a]+"function rd25Normalize(string $path, string $content): string { return $content; }\n"+body[z:]
body=body.replace("$relative === 'catalog/view/template/common/header.twig' ? str_replace('__RD25_TOKEN__', RD25_TOKEN, $candidate) : $candidate",'$candidate')
body=body.replace('RD-25-WP1_media-fit-catalog_20261009-',NAME[:-4]+'-')
body=body.replace("if (PHP_SAPI !== 'cli')", "if (PHP_VERSION_ID < 80000) { rd25Fail('php_8_0_required'); }\n\trd25Out('php_version', PHP_VERSION);\n\tif (PHP_SAPI !== 'cli')")
body=body.replace("foreach ($target['replacements'] as $replace) {\n\t\t\t\tif", "if (hash('sha256', $originals[$relative]) !== $target['after_sha256']) { rd25Fail('applied_source_changed:' . $relative); }\n\t\t\tforeach ($target['replacements'] as $replace) {\n\t\t\t\tif")
lit=lambda s,n:"<<<'"+n+"'\n"+s+'\n'+n
entries=[]
for i,(rel,t) in enumerate(targets.items()):
 changes=[]
 for j,(old,new) in enumerate(t['replacements']): changes.append("['before' => "+lit(old,f'RD25_B_{i}_{j}')+", 'after' => "+lit(new,f'RD25_A_{i}_{j}')+"]")
 entries.append("'"+rel+"' => ['before_sha256' => '"+t['before_sha256']+"', 'after_sha256' => '"+t['after_sha256']+"', 'replacements' => [\n"+',\n'.join(changes)+"\n]]")
body=body.replace('__RD25_MANIFEST__','[\n'+',\n'.join(entries)+'\n]')
header='''<?php
/**
 * RD-25 WP1-FIX: measured classification and alpha-preserving fit-only trimming.
 * Fresh 2026-10-11 export; three exact SHA-256 targets, all gated before writes.
 * Phase 0 thresholds unchanged: alpha >16, min line count 2, pad 2px,
 * white >=250, corner span <=8, median background distance >12,
 * cover band .70..1.45, both slot windows with 2% safety margin.
 * Metadata v4 / fit key v2. Original sources and old cache files retained.
 * Twig/CSS, resize(), crop(), lightbox and JSON-LD paths unchanged.
 * Owner read-only #222 lookup 2026-10-11: twelve public DB fields, zero references.
 * PHP 8.0 compatible; CLI PHP_BINARY -l gates candidates AND written files.
 * No DB writes, cache deletion, commit, push or deployment.
 * Usage: php RD-25-WP1-FIX_media-trim-classification_20261011.php [--dry-run]
 * Rollback: re-upload runner; --rollback=_patch_backups/<printed-directory>.
 * Exact backup/readback, atomic writes, restore on failure, idempotency,
 * success self-delete. Independent of the Featured F6 companion.
 */
'''
patch=R/'patches'/NAME; patch.write_bytes((header+body).encode())
(E/'source.diff').write_text(''.join(diffs),encoding='utf8')
data={'patch':NAME,'patch_sha256':sha(patch.read_bytes()),'patch_bytes':patch.stat().st_size,'targets':{r:{k:v for k,v in t.items() if k!='replacements'} for r,t in targets.items()},'metadata_version':4,'fit_key_version':2}
(E/'manifest.json').write_text(json.dumps(data,indent=2)+'\n'); print(json.dumps(data,indent=2))
