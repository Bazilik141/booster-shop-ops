from pathlib import Path
import re,json,shutil
w=Path(__file__).resolve().parent;repo=w.parent.parent;previous=repo/'work/rd25-wp2-wp3-phase1-20261012'
probe=(previous/'render.php').read_text()
probe=probe.replace("foreach(['before','wp2','wp3'] as $state)","foreach(['before','after'] as $state)")
probe=probe.replace("$base=$state==='before'?$source:$w.'/after-'.$state;","$base=$state==='before'?$w.'/source':$w.'/after';")
probe=probe.replace('$selected=[78,80,77,131,144,96,201,73];','$selected=[190,193,163,78,80,77,144,201];')
start=probe.index(" foreach(['common/cart.twig','checkout/cart_list.twig'] as $template)");end=probe.index("foreach($result['schema']",start)
probe=probe[:start]+"}\n"+probe[end:]
probe=probe.replace("$w.'/after-wp2/catalog/controller/product/product.php'","$w.'/after/catalog/controller/product/product.php'")
probe=probe.replace("$row['main']!==$legacy||$row['small']!==$legacy", "$row['main']!==($index===0?$legacy:$payload['images'][$index-1]['popup'])||$row['small']!==$legacy")
probe=probe.replace("'legacy_url_exact'=>true", "'primary_legacy_and_additional_popup_exact'=>true,'additional_main_dimensions'=>[700,700]")
probe=probe.replace("$result['warnings']=$warnings;",'''foreach($payload['bs_gallery'] as $index=>$row){if($index>0&&($row['main_width']!==700||$row['main_height']!==700))throw new \\RuntimeException('N1 popup dimensions');}
$context->model_tool_image=new class($model){private $base;function __construct($x){$this->base=$x;}function resize($f,$x,$y){return $this->base->resize($f,$x,$y);}function resizeFit($f,$x,$y){return $x===600?'':$this->base->resizeFit($f,$x,$y);}function mediaInfo($f){return $this->base->mediaInfo($f);}};
$partial=$execute($products[201],201,$block[1]);
foreach($partial['bs_gallery'] as $index=>$row){if($index>0&&($row['main']!==$partial['images'][$index-1]['popup']||$row['main_width']!==700||$row['main_height']!==700||strpos($row['small'],'128x160-fit')===false))throw new \\RuntimeException('N1 partial-fit refusal');}
$result['fallback']['main_refused_small_fit_available']=true;
$result['warnings']=$warnings;''')
probe=probe.replace("'states'=>3","'states'=>2")
(w/'render.php').write_text(probe,encoding='utf-8')
ga=(previous/'ga4-probe.php').read_text().replace("$root.'/ga4/extension/","$root.'/source/extension/")
ga=ga.replace("['before','wp2','wp3']","['before','after']")
ga=ga.replace("$base=$state==='before'?dirname($root).'/rd25-wp2-wp3-phase0-20261012/source':$root.'/after-'.$state;","$base=$root.'/'.($state==='before'?'source':'after');")
ga=ga.replace("$row['before']!==$row['wp2']||$row['before']!==$row['wp3']","$row['before']!==$row['after']")
# Untouched cart templates in the checker use accepted WP3 bytes, not pre-WP3 files.
for p in ['catalog/view/template/common/cart.twig','catalog/view/template/checkout/cart.twig','catalog/view/template/checkout/cart_list.twig']:
    for state in ['source','after']:
        f=w/state/p;f.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(previous/'after-wp3'/p,f)
(w/'ga4-probe.php').write_text(ga,encoding='utf-8')
print('native probes prepared')
