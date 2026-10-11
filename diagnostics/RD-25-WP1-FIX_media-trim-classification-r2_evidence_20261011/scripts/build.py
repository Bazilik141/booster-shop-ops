from pathlib import Path
import hashlib,json,difflib,shutil
W=Path(__file__).resolve().parent; R=W.parents[1]; A=R/'work/rd25-wp1-fix-phase1-20261011'; S=R/'work/rd25-wp1-fix-phase0-20261011/source'
E=R/'diagnostics/RD-25-WP1-FIX_media-trim-classification-r2_evidence_20261011'; E.mkdir(exist_ok=True)
NAME='RD-25-WP1-FIX_media-trim-classification-r2_20261011.php'
sha=lambda b:hashlib.sha256(b).hexdigest()
old=(A/'expected/catalog/model/tool/image.php').read_text(); new=old
def replace(a,b):
 global new
 assert new.count(a)==1,a[:120]; new=new.replace(a,b)
replace('private const RD25_MEDIA_VERSION = 4;', '''private const RD25_MEDIA_VERSION = 4;
\t// RD-25 G1: request-local decode/measurement admission budget; finish the
\t// admitted source so an expensive cut can warm rather than starve forever.
\tprivate const RD25_MEASURE_BUDGET_NS = 1250000000;''')
replace("\t\t// On unavailable persistent storage", "\t\tstatic $measuring_ns = 0;\n\t\tif ($measuring_ns >= self::RD25_MEASURE_BUDGET_NS) { return []; }\n\n\t\t// On unavailable persistent storage")
# Both cold locks may otherwise make a visitor wait for another request's GD work.
assert new.count('flock($lock, LOCK_EX)')==2; new=new.replace('flock($lock, LOCK_EX)','flock($lock, LOCK_EX | LOCK_NB)')
replace("\t\ttry {\n\t\t\tif (!flock($lock, LOCK_EX | LOCK_NB))", "\t\t$measure_started = null;\n\t\ttry {\n\t\t\tif (!flock($lock, LOCK_EX | LOCK_NB))") if new.count("\t\ttry {\n\t\t\tif (!flock($lock, LOCK_EX | LOCK_NB))")==1 else None
# The resizeFit block has $temp before try, so use the media-only $cached anchor.
if '$measure_started = null;' not in new:
 replace("\t\ttry {\n\t\t\tif (!flock($lock, LOCK_EX | LOCK_NB)) {\n\t\t\t\treturn [];", "\t\t$measure_started = null;\n\t\ttry {\n\t\t\tif (!flock($lock, LOCK_EX | LOCK_NB)) {\n\t\t\t\treturn [];")
replace("\t\t\t$dimensions = @getimagesize($source['path']);", "\t\t\t$measure_started = hrtime(true);\n\t\t\t$dimensions = @getimagesize($source['path']);")
replace("\t\t} finally {\n\t\t\tflock($lock, LOCK_UN);", "\t\t} finally {\n\t\t\tif ($measure_started !== null) { $measuring_ns += hrtime(true) - $measure_started; }\n\t\t\tflock($lock, LOCK_UN);")
replace('private function rd25Measure($gd, int $width, int $height): array {', 'private function rd25Measure($gd, int $width, int $height, bool $full_diagnostics = false): array {')
replace("\t\t$rows = [array_fill", '''\t\t$background_corners = 0;
\t\tfor ($i = 0; $i < 4; $i++) { $background_corners += $white[$i] || $transparent[$i] ? 1 : 0; }
\t\t$outside = $width * 100 < $height * 70 || $width * 100 > $height * 145;
\t\t// G1: inside-band photo classification and full crop need only four corners.
\t\t// The owner permits null for unused diagnostic bboxes; never infer them.
\t\tif (!$full_diagnostics && !$outside && $background_corners === 0) {
\t\t\treturn ['kind' => 'photo', 'class_reason' => 'non_white_or_mixed_non_white_corners',
\t\t\t\t'corner_alpha' => array_column($corners, 3),
\t\t\t\t'corner_rgb' => array_map(static function(array $c): array { return array_slice($c, 0, 3); }, $corners),
\t\t\t\t'all_near_white_corners' => false, 'corner_span' => $span, 'background_rgb' => $background,
\t\t\t\t'alpha_bbox_16' => null, 'background_bbox_12' => null, 'cover_check' => null,
\t\t\t\t'trim_method' => 'none', 'crop_box_exclusive' => [0, 0, $width, $height],
\t\t\t\t'crop_width' => $width, 'crop_height' => $height];
\t\t}
\t\t// Read source corners first: palette conversion may zero hidden RGB at
\t\t// fully transparent pixels. Preserve the r1 background median for parity.
\t\tif (!imageistruecolor($gd) && !imagepalettetotruecolor($gd)) {
\t\t\tthrow new \\RuntimeException('Fit source truecolor conversion failed');
\t\t}
\t\t$rows = [array_fill''')
a="""\t\t\t\t$c = imagecolorsforindex($gd, imagecolorat($gd, $x, $y));
\t\t\t\t// GD alpha is integral; opacity > 16 is exactly GD alpha < 119.
\t\t\t\t$opaque = $opaque && $c['alpha'] === 0;
\t\t\t\t$has_transparent = $has_transparent || $c['alpha'] >= 119;
\t\t\t\tif ($c['alpha'] < 119) {
\t\t\t\t\t$rows[0][$y]++; $cols[0][$x]++;
\t\t\t\t\tif (max(abs($c['red'] - $background[0]), abs($c['green'] - $background[1]), abs($c['blue'] - $background[2])) > 12) {"""
b="""\t\t\t\t$v = imagecolorat($gd, $x, $y);
\t\t\t\t$alpha = ($v >> 24) & 0x7F;
\t\t\t\t// GD alpha is integral; opacity > 16 is exactly GD alpha < 119.
\t\t\t\t$opaque = $opaque && $alpha === 0;
\t\t\t\t$has_transparent = $has_transparent || $alpha >= 119;
\t\t\t\tif ($alpha < 119) {
\t\t\t\t\t$rows[0][$y]++; $cols[0][$x]++;
\t\t\t\t\tif (max(abs((($v >> 16) & 0xFF) - $background[0]), abs((($v >> 8) & 0xFF) - $background[1]), abs(($v & 0xFF) - $background[2])) > 12) {"""
replace(a,b)
# Local scan variables still feed all decisions. Stored nullable fields are never inputs.
rel='catalog/model/tool/image.php'; candidates={}
for p in ['catalog/model/tool/image.php','system/library/image.php','catalog/controller/product/thumb.php']:
 data=new.encode() if p==rel else (A/'expected'/p).read_bytes(); out=W/'expected'/p;out.parent.mkdir(parents=True,exist_ok=True);out.write_bytes(data);candidates[p]=data
prior=json.loads((R/'diagnostics/RD-25-WP1-FIX_media-trim-classification_evidence_20261011/manifest.json').read_text())
for p in prior['targets']:assert sha((S/p).read_bytes())==prior['targets'][p]['before_sha256']
# Reuse the reviewed runner byte-for-byte; replace only the model transformation
# literal and its candidate hash. Existing library and Thumb transformations stay exact.
patch=(R/'patches'/prior['patch']).read_text();oldlit=(A/'helpers.txt').read_text()+'\n\tprivate function rd25Source(string $filename): array {'
newhelper=new[new.index('\t// RD-25-WP1-FIX 20261011:'):new.index('\tprivate function rd25Source')]
newlit=newhelper+'\tprivate function rd25Source(string $filename): array {'
assert patch.count(oldlit)==1;patch=patch.replace(oldlit,newlit)
# Other small changes are folded into the model's existing nowdoc replacements,
# or appended as readable extra transformations against the first revision candidate.
first_manifest_start=patch.index("'catalog/model/tool/image.php' =>")
first_manifest_end=patch.index("\n]],\n'system/library/image.php'")+4
model_entry="'catalog/model/tool/image.php' => ['before_sha256' => '"+prior['targets'][rel]['before_sha256']+"', 'after_sha256' => '"+sha(new.encode())+"', 'replacements' => [\n['before' => <<<'RD25_G1_B'\n"+(S/rel).read_text()+"\nRD25_G1_B, 'after' => <<<'RD25_G1_A'\n"+new+"\nRD25_G1_A]\n]],"
patch=patch[:first_manifest_start]+model_entry+patch[first_manifest_end:]
patch=patch.replace(prior['patch'],NAME).replace(prior['patch'][:-4]+'-',NAME[:-4]+'-')
patch=patch.replace(' * RD-25 WP1-FIX: measured classification', ' * Revision 2 / G1: corner photo fast path, truecolor bit scan, request 1.25s\n * measurement admission budget and non-blocking cold locks.\n * RD-25 WP1-FIX: measured classification',1)
destination=R/'patches'/NAME;destination.write_bytes(patch.encode())
manifest={'patch':NAME,'patch_sha256':sha(destination.read_bytes()),'patch_bytes':destination.stat().st_size,'targets':{p:{'before_sha256':prior['targets'][p]['before_sha256'],'after_sha256':sha(data)} for p,data in candidates.items()},'metadata_version':4,'fit_key_version':2,'measure_budget_seconds':1.25,'previous_patch_sha256':prior['patch_sha256']}
(E/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
(E/'revision.diff').write_text(''.join(difflib.unified_diff(old.splitlines(True),new.splitlines(True),fromfile='r1/'+rel,tofile='r2/'+rel)),encoding='utf8')
print(json.dumps(manifest,indent=2))
