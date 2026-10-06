from pathlib import Path
import json, hashlib, shutil
B=Path(__file__).resolve().parent; R=B.parents[1]
D=R/'diagnostics/UX-003_BUG-004A_20261006_evidence';D.mkdir(exist_ok=True)
cases=json.loads((B/'browser-results.json').read_text(encoding='utf-8'))+json.loads((B/'browser-results-interaction-styles-and-loading-state.json').read_text(encoding='utf-8'))
assert len(cases)==25 and all(c['pass'] for c in cases)
(D/'browser-results.json').write_text(json.dumps(cases,ensure_ascii=False,indent=2),encoding='utf-8')
shutil.copyfile(B/'runner-results.json',D/'runner-results.json')
for p in (B/'screenshots').glob('*.png'):shutil.copyfile(p,D/p.name)
manifest={'source_archive':'ux003-r9-bug004a-live-20261006-223210.tar.gz','local_php':'8.3.30','local_twig':'3.28.0','browser_cases':25,'source_sha256':{},'patch_sha256':{},'final_target_sha256':json.loads((B/'manifest.json').read_text(encoding='utf-8'))}
for p in (B/'source').rglob('*'):
    if p.is_file():manifest['source_sha256'][str(p.relative_to(B/'source')).replace('\\','/')]=hashlib.sha256(p.read_bytes()).hexdigest()
for n in ['BUG-004A_mobile-cart-badge_20261006.php','UX-003_runner9_filters-no-reload_20261006.php']:manifest['patch_sha256'][n]=hashlib.sha256((R/'patches'/n).read_bytes()).hexdigest()
(D/'manifest.json').write_text(json.dumps(manifest,indent=2),encoding='utf-8')
print('Published local fixture evidence:',D,'25/25 passed')
