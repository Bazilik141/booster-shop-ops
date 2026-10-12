from pathlib import Path, PurePosixPath
import hashlib, json, tarfile, os

repo = Path(r'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops')
archive = Path(r'C:\Users\14bez\Downloads\RD-25-WP2-WP3-check-20261012-094705-34448.tar.gz')
out = repo / 'work/rd25-wp2-wp3-phase0-20261012/source'
evidence = repo / 'diagnostics/RD-25-WP2-WP3_phase0_evidence_20261012'
assert not out.exists(), 'Existing intake; preserve it'
out.mkdir(parents=True)
evidence.mkdir(parents=True, exist_ok=True)
with tarfile.open(archive) as tf:
    members = tf.getmembers()
    assert len({m.name for m in members}) == len(members)
    data = json.load(tf.extractfile('phase0.json'))
    assert data['task'] == 'RD-25-WP2-WP3' and data['done'] == 'ok'
    checks = []
    for m in members:
        name = PurePosixPath(m.name)
        assert m.isfile() and not name.is_absolute() and '..' not in name.parts
        assert m.name == 'phase0.json' or m.name in data['file_hashes'], m.name
        raw = tf.extractfile(m).read()
        digest = hashlib.sha256(raw).hexdigest()
        if m.name != 'phase0.json':
            expected = data['file_hashes'][m.name]
            assert expected['sha256'] == digest and expected['bytes'] == len(raw), m.name
        target = out.joinpath(*name.parts)
        assert target.resolve().is_relative_to(out.resolve())
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(raw)
        os.utime(target, (m.mtime, m.mtime))
        checks.append({'path': m.name, 'bytes': len(raw), 'sha256': digest, 'source_mtime': m.mtime, 'export_hash_matches': m.name != 'phase0.json'})
expected_wp1 = {
    'catalog/model/tool/image.php': '85cf9489244c48fe915bc25f370cdfdb2b39c9c6d6a42219cec7f84a9e375e71',
    'system/library/image.php': '7f81dcbb8b5d2d032005ae28ce01cea5ba7542f7334fd9909e538f6952adeff9',
    'catalog/controller/product/thumb.php': 'cf4f8e354b18a4318af159c34bf3c083d0991007b42d03e2c57aa4963c1ed3b1',
    'catalog/view/stylesheet/boostershop-ds.css': 'c140e25de36e40b89830972cbc270b35cdda017cac0cd64118df935312eaf0f9',
    'catalog/view/template/common/header.twig': 'bf0328cd9da4095282069584150ca7a396fc4a09426b0e90b00ab852ffaa70d2',
    'catalog/view/template/product/thumb.twig': 'd9237b2c9c0cc21cda73a86ba6cf1ff66b7ad956f0da3c24a8e1add6518037d6',
}
gate = [{'path': path, 'expected_wp1': value, 'actual': data['file_hashes'][path]['sha256'], 'matches': value == data['file_hashes'][path]['sha256']} for path, value in expected_wp1.items()]
result = {'archive': str(archive), 'sha256': hashlib.file_digest(archive.open('rb'), 'sha256').hexdigest(), 'export_utc': data['utc'], 'export_revision': data['export_revision'], 'hosting_php': data['php_version'], 'members': len(checks), 'verified_export_hashes': len(data['file_hashes']), 'wp1_gate': gate, 'files': checks}
(evidence / 'source-gate.json').write_text(json.dumps(result, indent=2) + '\n', encoding='utf-8')
(evidence / 'public-product-inventory-source.json').write_text(json.dumps({k: data[k] for k in ['utc','scope','product_count','products','additional_images','product_names','image_path_resolution']}, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
(evidence / 'theme-and-image-settings.json').write_text(json.dumps({k: data[k] for k in ['theme_override_check','store0_image_and_live_search_settings','live_search_modules']}, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
print(json.dumps({'verified_export_hashes': len(data['file_hashes']), 'wp1_gate': gate, 'theme_rows': len(data['theme_override_check']['rows']), 'primary_refs':len(data['products']), 'additional_refs':len(data['additional_images'])}))
