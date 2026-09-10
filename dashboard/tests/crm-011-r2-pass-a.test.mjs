import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const html = fs.readFileSync(new URL('../booster-dashboard.html', import.meta.url), 'utf8');
const inline = [...html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/gi)]
  .map(match => match[1])
  .filter(Boolean)
  .join('\n');

function sourceOf(name) {
  const marker = `function ${name}`;
  const start = inline.indexOf(marker);
  assert.notEqual(start, -1, `${name} declaration is missing`);
  const brace = inline.indexOf('{', start);
  let depth = 0;
  for (let i = brace; i < inline.length; i += 1) {
    if (inline[i] === '{') depth += 1;
    if (inline[i] === '}') {
      depth -= 1;
      if (depth === 0) return inline.slice(start, i + 1);
    }
  }
  throw new Error(`${name} declaration is incomplete`);
}

test('Pass A dashboard inline script compiles', () => {
  assert.doesNotThrow(() => new Function(inline));
});

test('order table sorting owns final order and low-margin filter excludes unknown values', () => {
  assert.match(sourceOf('renderOrderRows'), /return rows\.map/);
  assert.doesNotMatch(sourceOf('renderOrderRows'), /sortOrderRows\(/);
  assert.match(sourceOf('sortOrderRows_'), /if\(!s\.field\)return sortOrderRows\(rows\)/);

  const factory = new Function(`
    const orderState={tableSort:{field:'amount',dir:'desc'},filters:{search:'',channel:'Prom',status:'',payment:'',clientType:'',preorder:'',profit:'low_margin'}};
    function sortOrderRows(rows){return rows.slice().sort((a,b)=>String(b.date).localeCompare(String(a.date)));}
    function orderFilterSearch_(row){return [row.order_id].join(' ').toLowerCase();}
    function isPreorderOrder(){return false;}
    ${sourceOf('orderProfitPct')}
    ${sourceOf('orderTableSortValue_')}
    ${sourceOf('sortOrderRows_')}
    ${sourceOf('filterOrderRows_')}
    return {sortOrderRows_,filterOrderRows_,orderState};
  `)();
  const rows = [
    {order_id:'A',date:'2026-09-09',source:'Prom',amount:100,profit:10},
    {order_id:'B',date:'2026-09-08',source:'Prom',amount:300,profit:60},
    {order_id:'C',date:'2026-09-07',source:'Prom',amount:200,profit:null},
    {order_id:'D',date:'2026-09-06',source:'OLX',amount:400,profit:20},
  ];
  assert.deepEqual(factory.sortOrderRows_(rows).map(row => row.order_id), ['D','B','C','A']);
  factory.orderState.tableSort.dir = 'asc';
  assert.deepEqual(factory.sortOrderRows_(rows).map(row => row.order_id), ['A','C','B','D']);
  assert.deepEqual(factory.filterOrderRows_(rows).map(row => row.order_id), ['A']);
  factory.orderState.tableSort.field = '';
  assert.deepEqual(factory.sortOrderRows_(rows).map(row => row.order_id), ['A','B','C','D']);
});

test('all six finance presets use calendar-safe boundaries', () => {
  for (const [value, label] of [['7d','7 днів'],['30d','30 днів'],['month','Цей місяць'],['last_month','Минулий місяць'],['90d','90 днів'],['custom','Свій період']]) {
    assert.match(html, new RegExp(`<option value="${value}"[^>]*>${label}<\\/option>`));
  }
  const elements = {
    financePreset:{value:'month'},
    financeFrom:{value:''},
    financeTo:{value:''},
  };
  const factory = new Function('document', `
    const financeState={preset:'month'};
    ${sourceOf('dateInputValue_')}
    ${sourceOf('financeDate_')}
    ${sourceOf('financeParseDate_')}
    ${sourceOf('financeAddDays_')}
    ${sourceOf('financeMonthEnd_')}
    function financeKyivToday_(){return financeDate_(2026,8,9);}
    ${sourceOf('applyFinancePreset')}
    ${sourceOf('financeComparisonRange_')}
    return {applyFinancePreset,financeComparisonRange_,financeState};
  `)({getElementById:id => elements[id]});

  const expected = {
    '7d':['2026-09-03','2026-09-09'],
    '30d':['2026-08-11','2026-09-09'],
    month:['2026-09-01','2026-09-09'],
    last_month:['2026-08-01','2026-08-31'],
    '90d':['2026-06-12','2026-09-09'],
  };
  for (const [preset, range] of Object.entries(expected)) {
    elements.financePreset.value = preset;
    factory.applyFinancePreset();
    assert.deepEqual([elements.financeFrom.value,elements.financeTo.value], range, preset);
  }
  assert.deepEqual(factory.financeComparisonRange_('month','2026-09-01','2026-09-09'), {from:'2026-08-01',to:'2026-08-09'});
  assert.deepEqual(factory.financeComparisonRange_('last_month','2026-08-01','2026-08-31'), {from:'2026-07-01',to:'2026-07-31'});
  assert.deepEqual(factory.financeComparisonRange_('custom','2026-09-03','2026-09-09'), {from:'2026-08-27',to:'2026-09-02'});
  assert.deepEqual(factory.financeComparisonRange_('month','2026-03-01','2026-03-31'), {from:'2026-02-01',to:'2026-02-28'});
});

test('finance deltas handle zero and unavailable comparison without fake percentages', () => {
  const factory = new Function(`
    function financeValue_(value,type){if(value==null)return 'Немає даних';if(type==='count')return String(Math.round(Number(value)||0));if(type==='percent')return Number(value).toFixed(1)+'%';return '₴'+Number(value);}
    ${sourceOf('financeDeltaHtml_')}
    ${sourceOf('financeMetric_')}
    return {financeMetric_};
  `)();
  assert.match(factory.financeMetric_('Виручка',100,0,'money'), /↑ \+₴100 · —/);
  assert.match(factory.financeMetric_('Виручка',120,100,'money'), /↑ \+₴20 · \+20\.0%/);
  assert.match(factory.financeMetric_('Маржа',20.4,24.8,'percent'), /↓/);
  assert.match(factory.financeMetric_('Виручка',null,100,'money'), /зміна: —/);
  assert.doesNotMatch(factory.financeMetric_('Виручка',100,undefined,'money'), /зміна|↑|↓/);
  assert.match(factory.financeMetric_('Витрати',80,100,'money','down'), /var\(--green\)/);
});

test('client table is eight columns and only the pre-existing render is duplicated', () => {
  assert.match(inline, /const CLIENT_COLUMN_COUNT = 8/);
  const clientRenderer = sourceOf('renderClientsTable_');
  for (const field of ['identity','segment','orders','ltv','profit','margin_pct','last_order_date','days_since_last_order']) {
    assert.match(clientRenderer, new RegExp(`clientTh_\\('${field}'`));
  }
  for (const moved of ['Канал:','IP:','Перша покупка:','Інтервал:','Витрати 60д:','Телефон:']) {
    assert.match(sourceOf('toggleClientDetail'), new RegExp(moved));
  }

  const names = [...inline.matchAll(/(?:async\s+)?function\s+([A-Za-z0-9_]+)/g)].map(match => match[1]);
  const duplicates = [...new Set(names.filter((name,index) => names.indexOf(name) !== index))].sort();
  assert.deepEqual(duplicates, ['render']);
});

test('owner QA feedback is explained in the dashboard instead of looking like missing data', () => {
  assert.match(html, /id="clientsModeNote"/);
  assert.match(inline, /Важливі = 2\+ замовлення АБО витрати за 60 днів понад ₴1 500 АБО маржа понад 40%/);
  assert.match(inline, /Прибуток із замовлень/);
  assert.match(inline, /за весь час; після прямих витрат замовлень, без загальних витрат бізнесу/);
  assert.match(html, /<div class="grid2"><div class="section"><div class="section-header"><span class="section-title">Нові та повторні клієнти/);
  assert.match(inline, /Ця версія CRM API ще не віддає розподіл активів/);
  assert.match(inline, /ця версія CRM API віддає надходження, але ще не віддає повний відтік коштів/i);
  assert.match(inline, /Сума поповнень у єнах/);
  assert.match(inline, /Це рух грошей, а не бухгалтерський прибуток/);
  assert.match(inline, /<th>SKU \/ позиція<\/th>/);
  assert.match(inline, /Причину не деталізовано у джерелі/);
});

test('assets are explicitly a current balance and not a period comparison', () => {
  assert.match(html, /Активи · станом на сьогодні/);
  assert.match(inline, /Поточний баланс станом на сьогодні/);
  assert.match(inline, /Вибраний фінансовий період не змінює ці цифри/);
  assert.doesNotMatch(html, /Знімки_Активів|Історії ще немає/i);
});
