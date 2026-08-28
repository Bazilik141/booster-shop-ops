import assert from 'node:assert/strict';
import crypto from 'node:crypto';
import fs from 'node:fs';
import test from 'node:test';

const read = (file) => fs.readFileSync(file, 'utf8');

function payload(file) {
  const source = read(file);
  const hit = source.match(/const PAYLOAD_B64 = '([^']+)'/);
  const sha = source.match(/const PAYLOAD_SHA256 = '([0-9a-f]+)'/);
  assert.ok(hit && sha, `payload constants missing: ${file}`);
  const json = Buffer.from(hit[1], 'base64').toString('utf8');
  assert.equal(crypto.createHash('sha256').update(json).digest('hex'), sha[1], `payload SHA mismatch: ${file}`);
  return JSON.parse(json);
}

function section(markdown, title) {
  const at = markdown.indexOf(title);
  assert.notEqual(at, -1, `section missing: ${title}`);
  const next = markdown.indexOf('\n## ', at + title.length);
  return markdown.slice(at, next < 0 ? markdown.length : next);
}

function fenced(markdown) {
  return [...markdown.matchAll(/```html\r?\n([\s\S]*?)\r?\n```/g)].map((match) => match[1]);
}

function canonicalRound2Existing() {
  const old = payload('patches/CONTENT-QUALITY_cards-update-28_20260827.php');
  const addendum = read('handoffs/addendum_CONTENT-QUALITY_faq-recovery-readycopy_20260828.md');
  const replacements = new Map();
  for (const [sku, heading] of [
    ['ACC-3D-PKM-110', '## 3.1 `ACC-3D-PKM-110`'],
    ['ACC-3D-PKM-120', '## 3.2 `ACC-3D-PKM-120`'],
    ['ACC-3D-PKM-130', '## 3.3 `ACC-3D-PKM-130`'],
    ['ACC-3D-PKM-200', '## 3.4 `ACC-3D-PKM-200`'],
    ['ACC-3D-PKM-700', '## 3.5 `ACC-3D-PKM-700`'],
    ['FIG-LUFFY-500', '## 3.6 `FIG-LUFFY-500`'],
    ['ACC-007-400', '## 3.7 `ACC-007-400`'],
  ]) replacements.set(sku, fenced(section(addendum, heading)).join('\n\n'));
  const existing = old.existing.map((card) => ({ ...card }));
  for (const card of existing) {
    if (replacements.has(card.sku)) {
      const end = card.html.lastIndexOf('</section>');
      assert.notEqual(end, -1, `FAQ section missing: ${card.sku}`);
      card.html = `${card.html.slice(0, end)}${replacements.get(card.sku)}\n${card.html.slice(end)}`;
    }
    if (card.sku === 'PKM-JP-INFX-BBX') {
      const exact = fenced(section(addendum, '# 4. `PKM-JP-INFX-BBX`'))[0];
      const start = card.html.indexOf('<p>У заводськи запечатаній коробці <strong>30 бустерів по 5 карт</strong>.');
      const end = card.html.indexOf('</p>', start);
      assert.ok(start >= 0 && end >= 0 && exact, 'Inferno X canonical paragraph missing');
      card.html = card.html.slice(0, start) + exact + card.html.slice(end + 4);
    }
  }
  return existing;
}

test('WP1 round 3 keeps all decoded round-2 descriptions byte-identical', () => {
  const runner = read('patches/CONTENT-QUALITY_cards-update-28_20260828.php');
  const actual = payload('patches/CONTENT-QUALITY_cards-update-28_20260828.php');
  const expected = canonicalRound2Existing();
  assert.deepEqual(actual.existing, expected);
  assert.equal(actual.existing.length, 28);
  assert.equal(actual.existing.reduce((sum, card) => sum + (card.html.match(/class="bs-faq-item"/g) || []).length, 0), 52);
  assert.equal(actual.existing.find((card) => card.sku === 'ACC-007-400').html.includes('<h2>'), false, 'ACC-007-400 intentionally uses an h3 lead');
  assert.deepEqual(actual.attribute_contract, {
    manufacture_time_attribute_id: 43,
    mystery_box_attribute_id: 44,
    compatibility_attribute_id: 55,
  });
  assert.match(runner, /\$attrNames = \[\['name'=>'Типовий строк виготовлення при відсутності на складі'\],\['name'=>'Може трапитись у Mystery Box'\],\['name'=>'Сумісність'\]\]/);
  assert.doesNotMatch(runner, /Місткість дисплея|Внутрішнє зберігання/);
  assert.match(runner, /upsert_attribute\(\$db,\$t\['product_attribute'\],143,\$attrIds\['Сумісність'\],'PSA, BGS, SGC, слаби на магніті'\)/);
  assert.match(runner, /need\(\$after\['description'\]===html_encode\(\$card\['html'\]\),'storage_encoding_invalid:'/);
});

test('WP4 round 3 uses only the eight confirmed existing attributes', () => {
  const runner = read('patches/CONTENT-QUALITY_create-svel-set_20260828.php');
  const actual = payload('patches/CONTENT-QUALITY_create-svel-set_20260828.php');
  assert.deepEqual(actual.new.attributes, [
    {name:'Мова',value:'Японська (Japanese)'},
    {name:'Назва сету',value:'Starter Set Terastal Loudbone ex'},
    {name:'Рік випуску',value:'2023'},
    {name:'Стан',value:'Новий, нерозпакований (Sealed)'},
    {name:'Виробник',value:'The Pokémon Company'},
    {name:'Тип пакування',value:'Starter Set'},
    {name:'Додатковий вміст',value:'ігрове поле, монета Pokémon, аркуш жетонів шкоди та маркерів, посібник з правил'},
    {name:'Кількість карток у колоді',value:'60'},
  ]);
  assert.match(runner, /\$expectedAttributeIds=\['Мова'=>12,'Назва сету'=>13,'Рік випуску'=>14,'Стан'=>17,'Виробник'=>20,'Тип пакування'=>21,'Додатковий вміст'=>24,'Кількість карток у колоді'=>49\]/);
  assert.match(runner, /need\(count\(\$written\)===8,'new_attribute_count_invalid'\)/);
  assert.doesNotMatch(runner, /TCG|Назва продукту|Код продукту|Формат|Видання|Мова карт|Карт у колоді|Склад колоди|Головна карта|Основний тип Енергії|Стан упаковки|Тип запечатування|Дата релізу|Може трапитись у Mystery Box/);
});

test('round 3 runners contain no PHP 8.1-only declarations or attribute-definition writes', () => {
  for (const file of [
    'patches/CONTENT-QUALITY_cards-update-28_20260828.php',
    'patches/CONTENT-QUALITY_create-svel-set_20260828.php',
  ]) {
    const runner = read(file);
    assert.doesNotMatch(runner, /:\s*never\b|\breadonly\b|\benum\s+\w+|\bfn\s*\(/);
    assert.doesNotMatch(runner, /table\(\$db,\$prefix,'attribute'\)|INSERT INTO `?ocp5_attribute_description`?/);
  }
});
