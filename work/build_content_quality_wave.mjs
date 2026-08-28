import fs from 'node:fs';
import crypto from 'node:crypto';

const root = process.cwd();
const read = (file) => fs.readFileSync(file, 'utf8');
const write = (file, text) => fs.writeFileSync(file, text.replace(/\r?\n/g, '\r\n'), 'utf8');
const sha = (text) => crypto.createHash('sha256').update(text).digest('hex');
const b64 = (value) => Buffer.from(JSON.stringify(value), 'utf8').toString('base64');

function oldPayload(file) {
  const src = read(file);
  const hit = src.match(/const PAYLOAD_B64 = '([^']+)'/);
  if (!hit) throw new Error(`PAYLOAD_B64 missing in ${file}`);
  return JSON.parse(Buffer.from(hit[1], 'base64').toString('utf8'));
}

function replacePayload(src, payload) {
  const json = JSON.stringify(payload);
  return src
    .replace(/const PAYLOAD_B64 = '[^']+';/, `const PAYLOAD_B64 = '${Buffer.from(json).toString('base64')}';`)
    .replace(/const PAYLOAD_SHA256 = '[0-9a-f]+';/, `const PAYLOAD_SHA256 = '${sha(json)}';`);
}

function hardenHtmlChecks(src) {
  const old = "preg_match_all('/ id=\"([^\"]+)\"/', $html, $ids); need(count($ids[1]) === count(array_unique($ids[1])), 'duplicate_html_id:' . $sku);";
  const next = old + " preg_match_all('/aria-controls=\"([^\"]+)\"/', $html, $controls); preg_match_all('/aria-labelledby=\"([^\"]+)\"/', $html, $labels); foreach ($controls[1] as $id) need(in_array($id,$ids[1],true),'aria_controls_broken:' . $sku . ':' . $id); foreach ($labels[1] as $id) need(in_array($id,$ids[1],true),'aria_labelledby_broken:' . $sku . ':' . $id);";
  if (!src.includes(old)) throw new Error('HTML helper anchor missing');
  return src.replace(old, next);
}

function section(markdown, title) {
  const at = markdown.indexOf(title);
  if (at < 0) throw new Error(`Section missing: ${title}`);
  const next = markdown.indexOf('\n## ', at + title.length);
  return markdown.slice(at, next < 0 ? markdown.length : next);
}

function fenced(text) {
  return [...text.matchAll(/```html\r?\n([\s\S]*?)\r?\n```/g)].map((m) => m[1]);
}

function finalWp1Payload() {
  const old = oldPayload('patches/CONTENT-QUALITY_cards-update-28_20260827.php');
  const addendum = read('handoffs/addendum_CONTENT-QUALITY_faq-recovery-readycopy_20260828.md');
  const map = new Map();
  for (const [sku, heading] of [
    ['ACC-3D-PKM-110', '## 3.1 `ACC-3D-PKM-110`'],
    ['ACC-3D-PKM-120', '## 3.2 `ACC-3D-PKM-120`'],
    ['ACC-3D-PKM-130', '## 3.3 `ACC-3D-PKM-130`'],
    ['ACC-3D-PKM-200', '## 3.4 `ACC-3D-PKM-200`'],
    ['ACC-3D-PKM-700', '## 3.5 `ACC-3D-PKM-700`'],
    ['FIG-LUFFY-500', '## 3.6 `FIG-LUFFY-500`'],
    ['ACC-007-400', '## 3.7 `ACC-007-400`'],
  ]) map.set(sku, fenced(section(addendum, heading)).join('\n\n'));
  const existing = old.existing.map((card) => ({ ...card }));
  for (const card of existing) {
    if (map.has(card.sku)) {
      const pos = card.html.lastIndexOf('</section>');
      if (pos < 0 || (card.html.match(/<section class="bs-faq-accordion"/g) || []).length !== 1) throw new Error(`FAQ anchor invalid: ${card.sku}`);
      card.html = `${card.html.slice(0, pos)}${map.get(card.sku)}\n${card.html.slice(pos)}`;
    }
    if (card.sku === 'PKM-JP-INFX-BBX') {
      const exact = fenced(section(addendum, '# 4. `PKM-JP-INFX-BBX`'))[0];
      const start = '<p>У заводськи запечатаній коробці <strong>30 бустерів по 5 карт</strong>.';
      const from = card.html.indexOf(start);
      const end = card.html.indexOf('</p>', from);
      if (from < 0 || end < 0 || !exact) throw new Error('Inferno X paragraph anchor invalid');
      card.html = card.html.slice(0, from) + exact + card.html.slice(end + 4);
    }
  }
  const expected = { 'ACC-3D-PKM-110':3, 'ACC-3D-PKM-120':2, 'ACC-3D-PKM-130':2, 'ACC-3D-PKM-200':2, 'ACC-3D-PKM-700':2, 'FIG-LUFFY-500':2, 'ACC-007-400':4 };
  const count = (html) => (html.match(/class="bs-faq-item"/g) || []).length;
  if (existing.reduce((sum, card) => sum + count(card.html), 0) !== 52) throw new Error('WP1 FAQ total must be 52');
  for (const [sku, value] of Object.entries(expected)) if (count(existing.find((card) => card.sku === sku).html) !== value) throw new Error(`FAQ count invalid: ${sku}`);
  if ((existing.find((card) => card.sku === 'PKM-JP-INFX-BBX').html.match(/не гарантуються виробником/g) || []).length !== 1) throw new Error('Inferno X owner sentence invalid');
  return {
    existing,
    expected_faq_counts: expected,
    attribute_contract: {
      manufacture_time_attribute_id: 43,
      mystery_box_attribute_id: 44,
      compatibility_attribute_id: 55,
    },
  };
}

function wp1() {
  const payload = finalWp1Payload();
  let src = read('patches/CONTENT-QUALITY_cards-update-28_20260827.php');
  src = src.replace('CONTENT-QUALITY_cards-update-28_20260827', 'CONTENT-QUALITY_cards-update-28_20260828').replace(': never', ': void');
  src = replacePayload(src, payload);
  src = hardenHtmlChecks(src);
  const start = src.indexOf('$dryRun = parse_mode();');
  if (start < 0) throw new Error('WP1 main anchor missing');
  const main = String.raw`$dryRun = parse_mode(); lint_self(); $payload = load_payload(); [$db, $prefix] = connect(); $tx = false;
try {
    $t = []; foreach (['product','product_description','product_attribute','attribute_description'] as $name) $t[$name] = table($db, $prefix, $name);
    require_columns($db, $t['product'], ['product_id','model','sku','quantity','stock_status_id','status','price','image']);
    $existing = $payload['existing']; need(count($existing) === 28, 'existing_count_invalid'); $ids = array_column($existing, 'product_id'); sort($ids); need($ids === range(125, 152), 'existing_id_set_invalid');
    $attrNames = [['name'=>'Типовий строк виготовлення при відсутності на складі'],['name'=>'Може трапитись у Mystery Box'],['name'=>'Сумісність']]; $attrIds = resolve_attribute_ids($db, $t['attribute_description'], $attrNames); need($attrIds['Типовий строк виготовлення при відсутності на складі']===(int)$payload['attribute_contract']['manufacture_time_attribute_id'],'attribute_43_id_drift'); need($attrIds['Може трапитись у Mystery Box']===(int)$payload['attribute_contract']['mystery_box_attribute_id'],'attribute_44_id_drift'); need($attrIds['Сумісність']===(int)$payload['attribute_contract']['compatibility_attribute_id'],'attribute_55_id_drift');
    $a43=one($db,'SELECT name FROM '.qi($t['attribute_description']).' WHERE attribute_id=43 AND language_id=?',[LANGUAGE_ID]); $a44=one($db,'SELECT name FROM '.qi($t['attribute_description']).' WHERE attribute_id=44 AND language_id=?',[LANGUAGE_ID]); need($a43!==null&&$a43['name']==='Типовий строк виготовлення при відсутності на складі','attribute_43_unexpected'); need($a44!==null&&$a44['name']==='Може трапитись у Mystery Box','attribute_44_unexpected');
    $beforeDescriptions=[]; $beforeProducts=[]; $beforeAttrs=[];
    foreach($existing as $card){ check_html($card['sku'],$card['html']); $faqCount=substr_count($card['html'],'class="bs-faq-item"'); need($faqCount>=1,'faq_missing:'.$card['sku']); if(isset($payload['expected_faq_counts'][$card['sku']])) need($faqCount===(int)$payload['expected_faq_counts'][$card['sku']],'faq_count_invalid:'.$card['sku']); $row=one($db,'SELECT product_id,language_id,name,description,tag,meta_title,meta_description,meta_keyword FROM '.qi($t['product_description']).' WHERE product_id=? AND language_id=?',[$card['product_id'],LANGUAGE_ID]); need($row!==null,'description_missing:'.$card['sku']); $beforeDescriptions[]=$row; $product=one($db,'SELECT product_id,model,sku,status,price,quantity,stock_status_id,image FROM '.qi($t['product']).' WHERE product_id=? AND model=?',[$card['product_id'],$card['sku']]); need($product!==null,'product_anchor_missing:'.$card['sku']); $beforeProducts[]=$product; }
    $allFaq=0; foreach($existing as $card)$allFaq+=substr_count($card['html'],'class="bs-faq-item"'); need($allFaq===52,'faq_total_invalid');
    $inferno=array_values(array_filter($existing,function($card){return $card['sku']==='PKM-JP-INFX-BBX';}))[0]; need(substr_count($inferno['html'],'не гарантуються виробником')===1,'inferno_sentence_invalid');
    $attrTargets=[];$expectedAttrs=[];foreach(range(125,143) as $id){$attrTargets[]=[$id,43];$expectedAttrs[$id.'|43']='1–2 робочих дні';}foreach(range(125,129) as $id){$attrTargets[]=[$id,44];$expectedAttrs[$id.'|44']='Ні';}$attrTargets[]=[143,$attrIds['Сумісність']];$expectedAttrs['143|'.$attrIds['Сумісність']]='PSA, BGS, SGC, слаби на магніті';
    $currentAttrs=[];foreach($attrTargets as $target){$row=one($db,'SELECT product_id,attribute_id,language_id,text FROM '.qi($t['product_attribute']).' WHERE product_id=? AND attribute_id=? AND language_id=?',[$target[0],$target[1],LANGUAGE_ID]);if($row!==null){$beforeAttrs[]=$row;$currentAttrs[$target[0].'|'.$target[1]]=$row['text'];}}
    $applied=true; foreach($existing as $card){$row=$beforeDescriptions[array_search($card['product_id'],array_column($beforeDescriptions,'product_id'),true)]; if($row['description']!==html_encode($card['html']))$applied=false; foreach(['meta_title','meta_description','meta_keyword'] as $field)if(array_key_exists($field,$card)&&$row[$field]!==$card[$field])$applied=false;}foreach($expectedAttrs as$key=>$value)if(!isset($currentAttrs[$key])||$currentAttrs[$key]!==$value)$applied=false;if($applied&&!$dryRun){out('already_applied','yes');out('faq_items','52');exit(0);}
    if($dryRun){out('dry_run','ok');out('existing_descriptions','28');out('faq_items','52');foreach($payload['expected_faq_counts'] as $sku=>$number)out('faq_'.$sku,(string)$number);exit(0);}
    $dir=backup_dir(); write_backup($dir,'before.json',['descriptions'=>$beforeDescriptions,'products'=>$beforeProducts,'product_attributes'=>$beforeAttrs]); $restore='-- '.PATCH_NAME." rollback\nSTART TRANSACTION;\n"; foreach($beforeDescriptions as $row)$restore.=restore_update($db,$t['product_description'],$row,['name','description','tag','meta_title','meta_description','meta_keyword'],'product_id='.(int)$row['product_id'].' AND language_id='.LANGUAGE_ID); foreach($attrTargets as $target)$restore.='DELETE FROM '.qi($t['product_attribute']).' WHERE product_id='.(int)$target[0].' AND attribute_id='.(int)$target[1].' AND language_id='.LANGUAGE_ID.";\n"; foreach($beforeAttrs as $row)$restore.='INSERT INTO '.qi($t['product_attribute']).' (product_id,attribute_id,language_id,text) VALUES ('.(int)$row['product_id'].','.(int)$row['attribute_id'].','.LANGUAGE_ID.','.quote_sql($db,$row['text']).");\n"; $restore.="COMMIT;\n"; write_restore($dir,$restore); $db->begin_transaction();$tx=true;
    foreach($existing as $card){$fields=['description'=>html_encode($card['html'])];foreach(['meta_title','meta_description','meta_keyword']as$field)if(array_key_exists($field,$card))$fields[$field]=$card[$field];$sets=[];$params=[];foreach($fields as$field=>$value){$sets[]=qi($field).'=?';$params[]=$value;}$params[]=$card['product_id'];$params[]=LANGUAGE_ID;need(exec_sql($db,'UPDATE '.qi($t['product_description']).' SET '.implode(',',$sets).' WHERE product_id=? AND language_id=?',$params)===1,'description_update_failed:'.$card['sku']);}
    foreach(range(125,143) as $id)upsert_attribute($db,$t['product_attribute'],$id,43,'1–2 робочих дні'); foreach(range(125,129) as $id)upsert_attribute($db,$t['product_attribute'],$id,44,'Ні'); upsert_attribute($db,$t['product_attribute'],143,$attrIds['Сумісність'],'PSA, BGS, SGC, слаби на магніті');
    foreach($beforeProducts as $before){$after=one($db,'SELECT product_id,model,sku,status,price,quantity,stock_status_id,image FROM '.qi($t['product']).' WHERE product_id=?',[$before['product_id']]);need($after===$before,'commercial_field_changed:'.$before['model']);} foreach($existing as $card){$after=one($db,'SELECT name,description FROM '.qi($t['product_description']).' WHERE product_id=? AND language_id=?',[$card['product_id'],LANGUAGE_ID]);$original=$beforeDescriptions[array_search($card['product_id'],array_column($beforeDescriptions,'product_id'),true)];need($after['name']===$original['name'],'name_changed:'.$card['sku']);need($after['description']===html_encode($card['html']),'storage_encoding_invalid:'.$card['sku']);}
    $db->commit();$tx=false;out('backup',$dir);out('updated_descriptions','28');out('faq_items','52');out('attribute_43_44','verified_preexisting');out('done','ok');@unlink(__FILE__);
}catch(Throwable $e){if($tx)$db->rollback();fwrite(STDERR,'ERROR='.$e->getMessage().PHP_EOL);exit(1);}
`;
  return src.slice(0, start) + main;
}

function wp2() {
  const payload = { category_id:73, meta_keyword:'брелоки Pokémon, фігурки Pokémon, 3D-друк Pokémon, декор Pokémon, Pokémon 3D-друк Україна' };
  let src = read('patches/CONTENT-QUALITY_categories-73-74_20260827.php').replace('CONTENT-QUALITY_categories-73-74_20260827','CONTENT-QUALITY_category-73-keywords_20260828').replace(': never', ': void');
  src = replacePayload(src, payload);
  const start=src.indexOf('$dryRun = parse_mode();');
  const main=String.raw`$dryRun=parse_mode();lint_self();$payload=load_payload();[$db,$prefix]=connect();$tx=false;
try{$cd=table($db,$prefix,'category_description');$category=table($db,$prefix,'category');$ad=table($db,$prefix,'attribute_description');$a43=one($db,'SELECT name FROM '.qi($ad).' WHERE attribute_id=43 AND language_id=?',[LANGUAGE_ID]);$a44=one($db,'SELECT name FROM '.qi($ad).' WHERE attribute_id=44 AND language_id=?',[LANGUAGE_ID]);need($a43!==null&&$a43['name']==='Типовий строк виготовлення при відсутності на складі','attribute_43_unexpected');need($a44!==null&&$a44['name']==='Може трапитись у Mystery Box','attribute_44_unexpected');$before=one($db,'SELECT category_id,language_id,name,meta_keyword FROM '.qi($cd).' WHERE category_id=? AND language_id=?',[$payload['category_id'],LANGUAGE_ID]);need($before!==null,'category_description_missing:73');$state73=one($db,'SELECT status FROM '.qi($category).' WHERE category_id=73');$state74=one($db,'SELECT status FROM '.qi($category).' WHERE category_id=74');$name74=one($db,'SELECT name FROM '.qi($cd).' WHERE category_id=74 AND language_id=?',[LANGUAGE_ID]);need((int)$state73['status']===0&&(int)$state74['status']===0,'category_not_disabled');need($name74!==null&&$name74['name']==='Фігурки та декор One Piece','category_74_name_drift');need($before['name']==='Фігурки та декор Pokémon','category_73_name_drift');if($before['meta_keyword']===$payload['meta_keyword']&&!$dryRun){out('already_applied','yes');exit(0);}if($dryRun){out('dry_run','ok');out('category','73');exit(0);}$dir=backup_dir();write_backup($dir,'before.json',['category'=>$before]);$restore='-- '.PATCH_NAME." rollback\nSTART TRANSACTION;\n".restore_update($db,$cd,$before,['meta_keyword'],'category_id=73 AND language_id='.LANGUAGE_ID)."COMMIT;\n";write_restore($dir,$restore);$db->begin_transaction();$tx=true;need(exec_sql($db,'UPDATE '.qi($cd).' SET meta_keyword=? WHERE category_id=73 AND language_id=?',[$payload['meta_keyword'],LANGUAGE_ID])===1,'category_keyword_update_failed');$after=one($db,'SELECT name,meta_keyword FROM '.qi($cd).' WHERE category_id=73 AND language_id=?',[LANGUAGE_ID]);need($after['name']===$before['name']&&$after['meta_keyword']===$payload['meta_keyword'],'category_verify_failed');$db->commit();$tx=false;out('backup',$dir);out('attribute_43_44','verified_preexisting');out('updated_category','73');out('done','ok');@unlink(__FILE__);}catch(Throwable$e){if($tx)$db->rollback();fwrite(STDERR,'ERROR='.$e->getMessage().PHP_EOL);exit(1);}`;
  return src.slice(0,start)+main;
}

function createProductSource(name, payload, templateProductId, templateModel, physicalFromPayload) {
  let src=read('patches/CONTENT-QUALITY_create-br-charm-200_20260827.php').replace('CONTENT-QUALITY_create-br-charm-200_20260827',name).replace(': never', ': void');
  src=replacePayload(src,payload);
  src=hardenHtmlChecks(src);
  if(physicalFromPayload) src=src.replace("$template['weight'],$template['weight_class_id'],$template['length'],$template['width'],$template['height'],$template['length_class_id']", "$p['weight'],$template['weight_class_id'],$p['length'],$p['width'],$p['height'],$template['length_class_id']");
  const anchor="$p=$payload['new'];";
  if(!src.includes(anchor)) throw new Error('create main anchor missing');
  src=src.replace(anchor, "$p=$payload['new'];"+(physicalFromPayload?"need(isset($p['weight'],$p['length'],$p['width'],$p['height']),'physical_payload_missing');":""));
  src=src.replace("WHERE product_id=126 AND model=\\'BR-CHARM-100\\'", `WHERE product_id=${templateProductId} AND model=\\'${templateModel}\\'`).replace("'charm_template_drift'", "'template_drift'");
  src=src.replace("need(one($db,'SELECT product_id FROM '.qi($t['product']).' WHERE model=? OR sku=?',[$p['sku'],$p['sku']])===null,'new_sku_exists');", "\u0024found=one(\u0024db,'SELECT product_id,model,sku,status,quantity,price,image FROM '.qi(\u0024t['product']).' WHERE model=? OR sku=?',[\u0024p['sku'],\u0024p['sku']]);if(\u0024found!==null){need(\u0024found['model']===\u0024p['sku']&&\u0024found['sku']===\u0024p['sku']&&(int)\u0024found['status']===0&&(int)\u0024found['quantity']===0&&(string)\u0024found['price']===\u0024p['price']&&\u0024found['image']==='','existing_product_state_invalid');\u0024description=one(\u0024db,'SELECT name,description,meta_title,meta_description,meta_keyword FROM '.qi(\u0024t['product_description']).' WHERE product_id=? AND language_id=?',[\u0024found['product_id'],LANGUAGE_ID]);need(\u0024description!==null&&\u0024description['name']===\u0024p['name']&&\u0024description['description']===html_encode(\u0024p['html'])&&\u0024description['meta_title']===\u0024p['meta_title']&&\u0024description['meta_description']===\u0024p['meta_description']&&\u0024description['meta_keyword']===\u0024p['meta_keyword'],'existing_description_state_invalid');\u0024seo=one(\u0024db,'SELECT keyword FROM '.qi(\u0024t['seo_url']).' WHERE '.qi('key').'=\\'product_id\\' AND value=?',[(string)\u0024found['product_id']]);need(\u0024seo!==null&&\u0024seo['keyword']===\u0024p['slug'],'existing_seo_state_invalid');out('already_applied','yes');exit(0);}");
  src=src.replace("out('price_placeholder','1.0000');", "out('configured_price',$p['price']);");
  return src;
}

function svelPayload() {
  const newProduct=oldPayload('patches/CONTENT-QUALITY_cards-update-28_20260827.php').new;
  return { new:{...newProduct,slug:'Pokemon-Starter-Set-Terastal-Loudbone-ex',price:'650.0000',weight:'400.00000000',length:'220.00000000',width:'160.00000000',height:'60.00000000'} };
}

function round3SvelPayload() {
  const payload = svelPayload();
  payload.new.attributes = [
    {name:'Мова',value:'Японська (Japanese)'},
    {name:'Назва сету',value:'Starter Set Terastal Loudbone ex'},
    {name:'Рік випуску',value:'2023'},
    {name:'Стан',value:'Новий, нерозпакований (Sealed)'},
    {name:'Виробник',value:'The Pokémon Company'},
    {name:'Тип пакування',value:'Starter Set'},
    {name:'Додатковий вміст',value:'ігрове поле, монета Pokémon, аркуш жетонів шкоди та маркерів, посібник з правил'},
    {name:'Кількість карток у колоді',value:'60'},
  ];
  return payload;
}

function parseKitPayload() {
  const md=read('handoffs/handoff_3D-P-CARDCONTENT_kit-cards-7sku_20260828.md'); const products=[];
  for(const hit of md.matchAll(/^## 4\.\d `([^`]+)`\r?\n([\s\S]*?)(?=^## 4\.|^# 5\.)/gm)){
    const [_,sku,body]=hit; const title=(body.match(/### Назва\r?\n\r?\n\*\*([^*]+)\*\*/)||[])[1]; const html=fenced(section(body,'### HTML body'))[0]; const faq=fenced(section(body,'### FAQ'))[0]; const attrs=[...section(body,'### Attributes').matchAll(/^- ([^:]+): `([^`]+)`/gm)].map((m)=>({name:m[1],value:m[2]})); const seo=section(body,'### SEO'); const metaTitle=(seo.match(/Meta Title:\*\* `([^`]+)`/)||[])[1]; const metaDescription=(seo.match(/Meta Description:\*\* `([^`]+)`/)||[])[1]; const metaKeyword=(seo.match(/Meta Keywords:\*\* `([^`]+)`/)||[])[1]; const slug=(seo.match(/SEO URL:\*\* `([^`]+)`/)||[])[1]; if(!title||!html||!faq||attrs.length!==13||!metaTitle||!metaDescription||!metaKeyword||!slug)throw new Error(`Kit parser failed: ${sku}`); const dimensions=attrs.find((a)=>a.name==='Розміри').value.match(/^(\d+)×(\d+)×(\d+) мм/); if(!dimensions)throw new Error(`Kit dimensions failed: ${sku}`); const grams=(attrs.find((a)=>a.name==='Маса').value.match(/([\d,]+)/)||[])[1]; if(!grams)throw new Error(`Kit mass failed: ${sku}`); products.push({sku,name:title,html:`${html}\n\n${faq}`,meta_title:metaTitle,meta_description:metaDescription,meta_keyword:metaKeyword,slug,categories:[59,73],attributes:attrs,price:'1.0000',weight:grams.replace(',','.'),length:dimensions[1],width:dimensions[2],height:dimensions[3]});
  }
  if(products.length!==7||products.reduce((n,p)=>n+(p.html.match(/class="bs-faq-item"/g)||[]).length,0)!==23)throw new Error('Kit count contract failed'); return {products};
}

function wp5() {
  const payload=parseKitPayload(); let src=createProductSource('3D-P-CARDCONTENT_create-kit-cards-7_20260828',{new:payload.products[0]},126,'BR-CHARM-100',true);
  src=replacePayload(src,payload);
  const start=src.indexOf('$dryRun=parse_mode();');
  if(start<0) throw new Error('WP5 main missing');
  const main=String.raw`$dryRun=parse_mode();lint_self();$payload=load_payload();[$db,$prefix]=connect();$tx=false;
try{$t=[];foreach(['product','product_description','product_attribute','attribute_description','seo_url','product_to_category','product_to_store','category']as$n)$t[$n]=table($db,$prefix,$n);need(count($payload['products'])===7,'kit_count_invalid');$template=one($db,'SELECT manufacturer_id,shipping,tax_class_id,weight_class_id,length_class_id,sort_order FROM '.qi($t['product']).' WHERE product_id=126 AND model=\'BR-CHARM-100\'');need($template!==null,'template_drift');$allAttrs=[];foreach($payload['products']as$p){check_html($p['sku'],$p['html']);need((substr_count($p['html'],'class="bs-faq-item"')>=3),'kit_faq_count_invalid:'.$p['sku']);foreach($p['attributes']as$a)$allAttrs[$a['name']]=$a;}need(count($allAttrs)===13,'kit_attribute_count_invalid');$ids=resolve_attribute_ids($db,$t['attribute_description'],array_values($allAttrs));$existing=[];foreach($payload['products']as$p){$row=one($db,'SELECT product_id FROM '.qi($t['product']).' WHERE model=? OR sku=?',[$p['sku'],$p['sku']]);$existing[$p['sku']]=$row;if($row===null)need(one($db,'SELECT seo_url_id FROM '.qi($t['seo_url']).' WHERE keyword=?',[$p['slug']])===null,'new_keyword_collision:'.$p['slug']);foreach($p['categories']as$cid)need(one($db,'SELECT category_id FROM '.qi($t['category']).' WHERE category_id=?',[$cid])!==null,'category_missing:'.$cid);}if(count(array_filter($existing))===7&&!$dryRun){out('already_applied','yes');exit(0);}need(count(array_filter($existing))===0,'partial_kit_batch_exists');if($dryRun){out('dry_run','ok');out('create_skus','7');out('faq_items','23');out('attributes_per_product','13');exit(0);}$dir=backup_dir();write_backup($dir,'before.json',['products'=>$payload['products'],'template'=>$template]);$restorePath=write_restore($dir,'-- '.PATCH_NAME." rollback\nSTART TRANSACTION;\n");$db->begin_transaction();$tx=true;$created=[];foreach($payload['products']as$p){$id=create_charm($db,$t,$p,$template,$ids);$created[$p['sku']]=$id;}$restore='';foreach($created as$id)$restore.='DELETE FROM '.qi($t['product_attribute']).' WHERE product_id='.$id.";\nDELETE FROM ".qi($t['product_to_category']).' WHERE product_id='.$id.";\nDELETE FROM ".qi($t['product_to_store']).' WHERE product_id='.$id.";\nDELETE FROM ".qi($t['seo_url'])." WHERE ".qi('key')."='product_id' AND value=".quote_sql($db,(string)$id).";\nDELETE FROM ".qi($t['product_description']).' WHERE product_id='.$id.";\nDELETE FROM ".qi($t['product']).' WHERE product_id='.$id.";\n";appender($restorePath,$restore."COMMIT;\n");foreach($payload['products']as$p){$id=$created[$p['sku']];$row=one($db,'SELECT status,quantity,price,image FROM '.qi($t['product']).' WHERE product_id=?',[$id]);need((int)$row['status']===0&&(int)$row['quantity']===0&&(string)$row['price']==='1.0000'&&$row['image']==='','new_product_state_invalid:'.$p['sku']);$attributes=rows($db,'SELECT attribute_id,text FROM '.qi($t['product_attribute']).' WHERE product_id=? AND language_id=?',[$id,LANGUAGE_ID]);need(count($attributes)===13,'new_attribute_count_invalid:'.$p['sku']);}$db->commit();$tx=false;out('backup',$dir);out('created_skus','7');out('faq_items','23');out('attributes_per_product','13');out('done','ok');@unlink(__FILE__);}catch(Throwable$e){if($tx)$db->rollback();fwrite(STDERR,'ERROR='.$e->getMessage().PHP_EOL);exit(1);}`;
  return src.slice(0,start)+main;
}

function csvRows(text) {
  const rows=[]; let row=[], cell='', quote=false;
  for(let i=0;i<text.length;i++){const ch=text[i]; if(ch==='"'){if(quote&&text[i+1]==='"'){cell+='"';i++;}else quote=!quote;}else if(ch===','&&!quote){row.push(cell);cell='';}else if((ch==='\n'||ch==='\r')&&!quote){if(ch==='\r'&&text[i+1]==='\n')i++;row.push(cell);if(row.some((v)=>v!==''))rows.push(row);row=[];cell='';}else cell+=ch;} if(cell!==''||row.length){row.push(cell);rows.push(row);} return rows;
}

function wp6Payload() {
  const rows=csvRows(read('Booster Shop CRM — облік товарів - РРЦ.csv')); const map={};
  for(const row of rows.slice(2)){const sku=row[0];const price=(row[4]||'').replace(/[^0-9,]/g,'').replace(',','.');if(!sku||!price)continue;map[sku]=Number(price).toFixed(4);}
  if(Object.keys(map).length!==95)throw new Error(`RRP map count invalid: ${Object.keys(map).length}`);
  return {rrp:map,aliases:{'PKM-KR-HWA-BST':'PKM-KR-HWAK-BST','YGO-JP-BODE-BST':'YGO-JP-BDOM-BST','PKM-MEGA-BOX':'PKM-JP-MSYM-BBX'},exception:'PKM-JP-OUTL-BST',specials:{'MTG-JP-AFRS-BST':{id:1116,price:'270.0000'},'PKM-EN-PORD-BBN':{id:1153,price:'1700.0000'},'PKM-EN-CHRS-BBN':{id:1152,price:'1700.0000'},'PKM-EN-CHRS-BST':{id:1123,price:'300.0000'}}};
}

function wp6() {
  const payload=wp6Payload(); const json=JSON.stringify(payload); const encoded=Buffer.from(json).toString('base64');
  return String.raw`<?php
declare(strict_types=1);
/* CRM RRP reconciliation. Run from ~/public_html only. Rollback is written to
 * _patch_backups/${'${PATCH_NAME}'}-<utc>/restore.sql before every write. */
const PATCH_NAME='CRM-RRP_site-price-reconcile_20260828'; const PAYLOAD_B64='${encoded}'; const PAYLOAD_SHA256='${sha(json)}';
function fail(string $m):void{throw new RuntimeException($m);} function out(string $k,string $v):void{echo $k.'='.$v.PHP_EOL;} function need(bool $ok,string $m):void{if(!$ok)fail($m);} function qi(string $n):string{return chr(96).str_replace(chr(96),chr(96).chr(96),$n).chr(96);} function q(mysqli $db,$v):string{return $v===null?'NULL':chr(39).$db->real_escape_string((string)$v).chr(39);}
function bind(mysqli_stmt $s,array $p):void{if($p===[])return;$types=str_repeat('s',count($p));$args=[&$types];foreach($p as$i=>$v){$p[$i]=(string)$v;$args[]=&$p[$i];}need(call_user_func_array([$s,'bind_param'],$args),'bind_failed:'.$s->error);} function stmt(mysqli $db,string $sql,array $p=[]):mysqli_stmt{$s=$db->prepare($sql);need($s instanceof mysqli_stmt,'prepare_failed:'.$db->error);bind($s,$p);need($s->execute(),'execute_failed:'.$s->error);return$s;} function rows(mysqli $db,string $sql,array $p=[]):array{$s=stmt($db,$sql,$p);$m=$s->result_metadata();need($m instanceof mysqli_result,'result_metadata_failed');$fields=$m->fetch_fields();$values=[];$refs=[];foreach($fields as$i=>$field){$values[$i]=null;$refs[]=&$values[$i];}need(call_user_func_array([$s,'bind_result'],$refs),'result_bind_failed');$out=[];while($s->fetch()){$copy=[];foreach($fields as$i=>$field)$copy[$field->name]=$values[$i];$out[]=$copy;}$s->close();return$out;} function one(mysqli $db,string $sql,array $p=[]):?array{$r=rows($db,$sql,$p);need(count($r)<=1,'expected_one_row_got_'.count($r));return$r[0]??null;} function execq(mysqli $db,string $sql,array $p=[]):int{$s=stmt($db,$sql,$p);$n=$s->affected_rows;$s->close();return$n;}
function table(mysqli $db,string $prefix,string $name):string{$full=$prefix.$name;stmt($db,'SELECT 1 FROM '.qi($full).' LIMIT 0')->close();return$full;} function mode():bool{$a=array_slice($GLOBALS['argv'],1);need($a===[]||$a===['--dry-run'],'usage:php '.basename(__FILE__).' [--dry-run]');return$a===['--dry-run'];} function lint():void{$status=1;$o=[];@exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg(__FILE__),$o,$status);need($status===0,'php_lint_failed:'.implode(' ',$o));out('php_lint','ok');} function payload():array{$json=base64_decode(PAYLOAD_B64,true);need($json!==false&&hash('sha256',$json)===PAYLOAD_SHA256,'payload_integrity_failed');$p=json_decode($json,true,512,JSON_THROW_ON_ERROR);need(is_array($p),'payload_invalid');return$p;} function connect():array{need(PHP_SAPI==='cli','cli_only');need(is_file(getcwd().DIRECTORY_SEPARATOR.'config.php'),'run_from_public_html_required');require getcwd().DIRECTORY_SEPARATOR.'config.php';foreach(['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PORT','DB_PREFIX']as$c)need(defined($c),'config_constant_missing:'.$c);need((string)DB_PREFIX==='ocp5_','db_prefix_mismatch_expected_ocp5_');mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);$db=new mysqli((string)DB_HOSTNAME,(string)DB_USERNAME,(string)DB_PASSWORD,(string)DB_DATABASE,(int)DB_PORT);$db->set_charset('utf8mb4');return[$db,(string)DB_PREFIX];} function backup():string{$d=getcwd().DIRECTORY_SEPARATOR.'_patch_backups'.DIRECTORY_SEPARATOR.PATCH_NAME.'-'.gmdate('Ymd-His');need(!file_exists($d),'backup_path_exists');need(mkdir($d,0750,true),'backup_create_failed');return$d;} function save(string $file,string $text):void{need(file_put_contents($file,$text,LOCK_EX)!==false,'backup_write_failed');}
$dry=mode();lint();$p=payload();[$db,$prefix]=connect();$tx=false;
try{$product=table($db,$prefix,'product');$discount=table($db,$prefix,'product_discount');$visible=rows($db,'SELECT product_id,model,price,status,quantity,stock_status_id,image,date_modified FROM '.qi($product).' WHERE status=1 ORDER BY product_id');$updates=[];$skips=[];$beforeDiscounts=[];foreach($visible as$row){$sku=$row['model'];if($sku===$p['exception']){$skips[]=$sku.':permanent_promotion';continue;}$crm=$p['rrp'][$sku]??($p['rrp'][$p['aliases'][$sku]??'']??null);if($crm===null){$skips[]=$sku.':crm_missing';continue;}$specialRows=rows($db,'SELECT product_discount_id,price,date_start,date_end FROM '.qi($discount).' WHERE product_id=? AND date_start<=CURDATE() AND (date_end=\'0000-00-00\' OR date_end>=CURDATE())',[$row['product_id']]);$disable=null;if(isset($p['specials'][$sku])){$rule=$p['specials'][$sku];$match=null;foreach($specialRows as$special)if((int)$special['product_discount_id']===(int)$rule['id'])$match=$special; if($match===null||(string)$match['price']!==$rule['price']){$skips[]=$sku.':named_special_drift';continue;}$disable=$match;$beforeDiscounts[]=$match;}else{foreach($specialRows as$special)if((float)$crm<=(float)$special['price']){$skips[]=$sku.':inverted_special_guard';continue;}}if((string)$row['price']!==$crm||$disable!==null)$updates[]=['product'=>$row,'crm'=>$crm,'disable'=>$disable];}
if($updates===[]&&!$dry){out('already_applied','yes');foreach($skips as$s)out('skip',$s);exit(0);}if($dry){out('dry_run','ok');out('visible_products',(string)count($visible));foreach($updates as$u)out('plan',$u['product']['model'].' '.$u['product']['price'].'->'.$u['crm'].($u['disable']!==null?' disable_special_'.$u['disable']['product_discount_id']:''));foreach($skips as$s)out('skip',$s);exit(0);}$dir=backup();save($dir.DIRECTORY_SEPARATOR.'before.json',json_encode(['updates'=>$updates,'skips'=>$skips],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);$restore="-- ".PATCH_NAME." rollback\nSTART TRANSACTION;\n";foreach($updates as$u){$restore.='UPDATE '.qi($product).' SET price='.q($db,$u['product']['price']).' WHERE product_id='.(int)$u['product']['product_id'].";\n";if($u['disable']!==null)$restore.='UPDATE '.qi($discount).' SET date_end='.q($db,$u['disable']['date_end']).' WHERE product_discount_id='.(int)$u['disable']['product_discount_id'].";\n";}$restore.="COMMIT;\n";save($dir.DIRECTORY_SEPARATOR.'restore.sql',$restore);$db->begin_transaction();$tx=true;$disabled=0;$priced=0;foreach($updates as$u){if($u['disable']!==null){need(execq($db,'UPDATE '.qi($discount).' SET date_end=DATE_SUB(CURDATE(),INTERVAL 1 DAY) WHERE product_discount_id=? AND price=?',[$u['disable']['product_discount_id'],$u['disable']['price']])===1,'special_disable_failed:'.$u['product']['model']);$disabled++;}if((string)$u['product']['price']!==$u['crm']){need(execq($db,'UPDATE '.qi($product).' SET price=? WHERE product_id=?',[$u['crm'],$u['product']['product_id']])===1,'price_update_failed:'.$u['product']['model']);$priced++;}$after=one($db,'SELECT status,quantity,stock_status_id,image,date_modified,price FROM '.qi($product).' WHERE product_id=?',[$u['product']['product_id']]);foreach(['status','quantity','stock_status_id','image','date_modified']as$field)need($after[$field]===$u['product'][$field],'out_of_scope_field_changed:'.$u['product']['model'].':'.$field);need((string)$after['price']===$u['crm'],'price_verify_failed:'.$u['product']['model']);}$db->commit();$tx=false;out('backup',$dir);out('price_updates',(string)$priced);out('specials_disabled',(string)$disabled);foreach($updates as$u)out('price',$u['product']['model'].' '.$u['product']['price'].'->'.$u['crm']);foreach($skips as$s)out('skip',$s);out('done','ok');@unlink(__FILE__);}catch(Throwable$e){if($tx)$db->rollback();fwrite(STDERR,'ERROR='.$e->getMessage().PHP_EOL);exit(1);}
`;
}

function round2Wp1() {
  let src = wp1().replace(/\r\n/g, '\n');
  const oldHeader = ' * ROLLBACK: run the generated `_patch_backups/<run>/restore.sql`. It restores\n * the 28 original language-4 description rows and targeted attributes, then\n * deletes the exact generated PKM-JP-SVEL-SET dependent rows and product ID.\n * The runner writes that ID into restore.sql before COMMIT.';
  const newHeader = ' * ROLLBACK: run the generated `_patch_backups/<run>/restore.sql`. It restores\n * the original 28 `language_id = 4` product-description rows and only the\n * targeted `product_attribute` rows captured before this runner writes.';
  if (!src.includes(oldHeader)) throw new Error('WP1 round-2 rollback header anchor missing');
  src = src.replace(oldHeader, newHeader);
  const before = src.length;
  src = src.replace(/function insert_product\([\s\S]*?\n}\n\n(?=\$dryRun = parse_mode\(\);)/, '');
  if (src.length === before) throw new Error('WP1 insert_product removal anchor missing');
  return src;
}

function round3Wp4(src) {
  const resolver = "$ids=resolve_attribute_ids($db,$t['attribute_description'],$p['attributes']);";
  const resolverNext = "$expectedAttributeIds=['Мова'=>12,'Назва сету'=>13,'Рік випуску'=>14,'Стан'=>17,'Виробник'=>20,'Тип пакування'=>21,'Додатковий вміст'=>24,'Кількість карток у колоді'=>49];need(count($p['attributes'])===8,'svel_attribute_count_invalid');$ids=resolve_attribute_ids($db,$t['attribute_description'],$p['attributes']);foreach($expectedAttributeIds as$name=>$attributeId)need(isset($ids[$name])&&$ids[$name]===$attributeId,'attribute_id_drift:'.$name);";
  if (!src.includes(resolver)) throw new Error('WP4 round-3 attribute resolver anchor missing');
  src = src.replace(resolver, resolverNext);
  const legacyGuards = ";$a43=one($db,'SELECT name FROM '.qi($t['attribute_description']).' WHERE attribute_id=43 AND language_id=?',[LANGUAGE_ID]);$a44=one($db,'SELECT name FROM '.qi($t['attribute_description']).' WHERE attribute_id=44 AND language_id=?',[LANGUAGE_ID]);need($a43!==null&&$a43['name']==='Типовий строк виготовлення при відсутності на складі','attribute_43_unexpected');need($a44!==null&&$a44['name']==='Може трапитись у Mystery Box','attribute_44_unexpected');";
  if (!src.includes(legacyGuards)) throw new Error('WP4 round-3 legacy guard anchor missing');
  src = src.replace(legacyGuards, ';');
  const mysteryCheck = String.raw`$mystery=$ids['Може трапитись у Mystery Box'];$attribute=one($db,'SELECT text FROM '.qi($t['product_attribute']).' WHERE product_id=? AND attribute_id=? AND language_id=?',[$id,$mystery,LANGUAGE_ID]);need($attribute!==null&&$attribute['text']==='Так','new_mystery_value_invalid');`;
  const attributeCheck = String.raw`$written=rows($db,'SELECT attribute_id,text FROM '.qi($t['product_attribute']).' WHERE product_id=? AND language_id=? ORDER BY attribute_id',[$id,LANGUAGE_ID]);need(count($written)===8,'new_attribute_count_invalid');$expectedWritten=[];foreach($p['attributes']as$item){$attributeId=(int)$ids[$item['name']];need(!isset($expectedWritten[$attributeId]),'duplicate_attribute_id:'.$attributeId);$expectedWritten[$attributeId]=$item['value'];}need(count($expectedWritten)===8,'new_attribute_expectation_invalid');foreach($written as$row){$attributeId=(int)$row['attribute_id'];need(isset($expectedWritten[$attributeId])&&$expectedWritten[$attributeId]===$row['text'],'new_attribute_value_invalid:'.$attributeId);}`;
  if (!src.includes(mysteryCheck)) throw new Error('WP4 round-3 post-insert assertion anchor missing');
  return src.replace(mysteryCheck, attributeCheck);
}

function round2Wp6() {
  const src = wp6();
  const mainAt = src.indexOf('$dry=mode();');
  if (mainAt < 0) throw new Error('WP6 round-2 main anchor missing');
  const main = String.raw`function same_price(string $left,string $right):bool{return abs((float)$left-(float)$right)<0.005;}
$dry=mode();lint();$p=payload();[$db,$prefix]=connect();$tx=false;
try{
    $product=table($db,$prefix,'product');$discount=table($db,$prefix,'product_discount');
    $visible=rows($db,'SELECT product_id,model,price,status,quantity,stock_status_id,image,date_modified FROM '.qi($product).' WHERE status=1 ORDER BY product_id');
    $updates=[];$skips=[];
    foreach($visible as$row){
        $sku=$row['model'];
        if($sku===$p['exception']){$skips[]=$sku.':permanent_promotion';continue;}
        $crm=$p['rrp'][$sku]??($p['rrp'][$p['aliases'][$sku]??'']??null);
        if($crm===null){$skips[]=$sku.':crm_missing';continue;}
        $discountRows=rows($db,'SELECT product_discount_id,quantity,special,price,date_start,date_end FROM '.qi($discount).' WHERE product_id=? AND (date_start=\'0000-00-00\' OR date_start<NOW()) AND (date_end=\'0000-00-00\' OR date_end>NOW())',[$row['product_id']]);
        $disable=null;
        if(isset($p['specials'][$sku])){
            $rule=$p['specials'][$sku];$match=null;
            foreach($discountRows as$candidate)if((int)$candidate['product_discount_id']===(int)$rule['id']){$match=$candidate;break;}
            if($match===null){$skips[]='special_already_disabled:'.$sku;}
            elseif(!same_price((string)$match['price'],$rule['price'])){$skips[]=$sku.':named_special_drift';continue;}
            else{$disable=$match;}
        }else{
            $guard=false;
            foreach($discountRows as$candidate){if((int)$candidate['special']===1&&(int)$candidate['quantity']===1&&(float)$crm<=(float)$candidate['price']+0.005){$guard=true;break;}}
            if($guard){$skips[]=$sku.':inverted_special_guard';continue;}
        }
        if(!same_price((string)$row['price'],$crm)||$disable!==null)$updates[]=['product'=>$row,'crm'=>$crm,'disable'=>$disable];
    }
    foreach($updates as$u)out('plan',$u['product']['model'].' '.$u['product']['price'].'->'.$u['crm'].($u['disable']!==null?' disable_special_'.$u['disable']['product_discount_id']:''));
    if(count($updates)>24)fail('price_update_limit_exceeded:'.count($updates));
    $plannedDisables=0;foreach($updates as$u)if($u['disable']!==null)$plannedDisables++;
    if($updates===[]&&!$dry){out('already_applied','yes');foreach($skips as$s)out('skip',$s);exit(0);}
    if($dry){out('dry_run','ok');out('visible_products',(string)count($visible));out('planned_price_updates',(string)count($updates));out('planned_special_disables',(string)$plannedDisables);foreach($skips as$s)out('skip',$s);exit(0);}
    $dir=backup();save($dir.DIRECTORY_SEPARATOR.'before.json',json_encode(['updates'=>$updates,'skips'=>$skips],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
    $restore="-- ".PATCH_NAME." rollback\nSTART TRANSACTION;\n";
    foreach($updates as$u){$restore.='UPDATE '.qi($product).' SET price='.q($db,$u['product']['price']).' WHERE product_id='.(int)$u['product']['product_id'].";\n";if($u['disable']!==null)$restore.='UPDATE '.qi($discount).' SET date_end='.q($db,$u['disable']['date_end']).' WHERE product_discount_id='.(int)$u['disable']['product_discount_id'].";\n";}
    $restore.="COMMIT;\n";save($dir.DIRECTORY_SEPARATOR.'restore.sql',$restore);
    $db->begin_transaction();$tx=true;$disabled=0;$priced=0;
    foreach($updates as$u){
        if($u['disable']!==null){need(execq($db,'UPDATE '.qi($discount).' SET date_end=DATE_SUB(CURDATE(),INTERVAL 1 DAY) WHERE product_discount_id=? AND price=?',[$u['disable']['product_discount_id'],$u['disable']['price']])===1,'special_disable_failed:'.$u['product']['model']);$disabled++;}
        if(!same_price((string)$u['product']['price'],$u['crm'])){need(execq($db,'UPDATE '.qi($product).' SET price=? WHERE product_id=?',[$u['crm'],$u['product']['product_id']])===1,'price_update_failed:'.$u['product']['model']);$priced++;}
        $after=one($db,'SELECT status,quantity,stock_status_id,image,date_modified,price FROM '.qi($product).' WHERE product_id=?',[$u['product']['product_id']]);
        foreach(['status','quantity','stock_status_id','image','date_modified']as$field)need($after[$field]===$u['product'][$field],'out_of_scope_field_changed:'.$u['product']['model'].':'.$field);
        need(same_price((string)$after['price'],$u['crm']),'price_verify_failed:'.$u['product']['model']);
    }
    $db->commit();$tx=false;out('backup',$dir);out('price_updates',(string)$priced);out('specials_disabled',(string)$disabled);foreach($skips as$s)out('skip',$s);out('done','ok');@unlink(__FILE__);
}catch(Throwable$e){if($tx)$db->rollback();fwrite(STDERR,'ERROR='.$e->getMessage().PHP_EOL);exit(1);}
`;
  return src.slice(0, mainAt) + main;
}

function resolve3dMaterialAttribute(src) {
  const old = "function resolve_attribute_ids(mysqli $db,string $ad,array $items):array{$out=[];foreach($items as $item){$row=one($db,'SELECT attribute_id FROM '.qi($ad).' WHERE language_id=? AND name=?',[LANGUAGE_ID,$item['name']]);need($row!==null,'attribute_missing:'.$item['name']);$out[$item['name']]=(int)$row['attribute_id'];}return$out;}";
  const next = "function resolve_attribute_ids(mysqli $db,string $ad,array $items):array{$out=[];foreach($items as $item){if($item['name']==='Матеріал'){$row=one($db,'SELECT attribute_id FROM '.qi($ad).' WHERE attribute_id=51 AND language_id=? AND name=?',[LANGUAGE_ID,$item['name']]);need($row!==null,'canonical_3d_material_attribute_missing');$out[$item['name']]=51;continue;}$row=one($db,'SELECT attribute_id FROM '.qi($ad).' WHERE language_id=? AND name=?',[LANGUAGE_ID,$item['name']]);need($row!==null,'attribute_missing:'.$item['name']);$out[$item['name']]=(int)$row['attribute_id'];}return$out;}";
  if (!src.includes(old)) throw new Error('3D material resolver anchor missing');
  return src.replace(old, next);
}

const wp1Payload = finalWp1Payload();
const kitPayload = parseKitPayload();
const rrpPayload = wp6Payload();
write('patches/CONTENT-QUALITY_cards-update-28_20260828.php',round2Wp1());
write('patches/CONTENT-QUALITY_category-73-keywords_20260828.php',wp2());
write('patches/CONTENT-QUALITY_create-br-charm-200_20260828.php',resolve3dMaterialAttribute(createProductSource('CONTENT-QUALITY_create-br-charm-200_20260828',oldPayload('patches/CONTENT-QUALITY_create-br-charm-200_20260827.php'),126,'BR-CHARM-100',false)));
write('patches/CONTENT-QUALITY_create-svel-set_20260828.php',round3Wp4(createProductSource('CONTENT-QUALITY_create-svel-set_20260828',round3SvelPayload(),146,'PKM-JP-STES-BBX',true)));
write('patches/3D-P-CARDCONTENT_create-kit-cards-7_20260828.php',resolve3dMaterialAttribute(wp5()));
write('patches/CRM-RRP_site-price-reconcile_20260828.php',round2Wp6());
console.log('built=wp1,wp2,wp3,wp4,wp5,wp6');
console.log(`static_wp1_cards=${wp1Payload.existing.length} static_wp1_faq=${wp1Payload.existing.reduce((n,p)=>n+(p.html.match(/class="bs-faq-item"/g)||[]).length,0)}`);
console.log(`static_wp5_products=${kitPayload.products.length} static_wp5_faq=${kitPayload.products.reduce((n,p)=>n+(p.html.match(/class="bs-faq-item"/g)||[]).length,0)}`);
console.log(`static_wp6_rrp=${Object.keys(rrpPayload.rrp).length} static_wp6_specials=${Object.keys(rrpPayload.specials).length}`);
