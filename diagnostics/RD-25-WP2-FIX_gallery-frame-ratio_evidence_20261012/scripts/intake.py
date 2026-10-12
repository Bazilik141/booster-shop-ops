from pathlib import Path
import tarfile,hashlib,json,re
w=Path(__file__).resolve().parent;repo=w.parent.parent;sha=lambda b:hashlib.sha256(b).hexdigest()
archive=repo/'RD-25-WP2-FIX-check-20261012-182229-3901452.tar.gz'
stage=repo/'work/rd25-wp2-wp3-phase1-20261012'
expected={p:v['after_sha256'] for p,v in json.loads((stage/'spec-wp2.json').read_text()).items()}
expected['catalog/view/stylesheet/boostershop-ds.css']=json.loads((stage/'spec-wp3.json').read_text())['catalog/view/stylesheet/boostershop-ds.css']['after_sha256']
ga='extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php';expected[ga]='eea58d1e247a16c2cc4bc1e61b8a5028e41dc7ed557e9c652d74382f7984b4b4'
rows=[];files={}
with tarfile.open(archive) as tar:
 for m in tar:
  assert m.isfile() and m.name in expected and m.name not in files,m.name
  data=tar.extractfile(m).read();normalized=data
  if m.name.endswith('common/header.twig'):
   pattern=rb'(catalog/view/stylesheet/boostershop-ds\.css\?v=)([A-Za-z0-9._-]{1,64})(?=["\'])'
   assert len(re.findall(pattern,data))==1
   normalized=re.sub(pattern,rb'\g<1>__RD25_TOKEN__',data)
  assert sha(normalized)==expected[m.name],('SOURCE GATE MISMATCH',m.name,sha(normalized),expected[m.name])
  rows.append({'path':m.name,'sha256':sha(data),'normalized_sha256':sha(normalized),'expected_sha256':expected[m.name],'match':True,'mtime':m.mtime})
  files[m.name]=data
assert set(files)==set(expected)
for p,b in files.items():
 f=w/'source'/p;f.parent.mkdir(parents=True,exist_ok=True);f.write_bytes(b)
(w/'source-gate.json').write_text(json.dumps({'archive':archive.name,'sha256':sha(archive.read_bytes()),'files':rows},indent=2)+'\n')
print('source_gate=5/5 matched')
