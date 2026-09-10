import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const importer = fs.readFileSync(new URL('../one-time/CRM-011_followup_data_import_20260908.gs', import.meta.url), 'utf8');
const alerts = fs.readFileSync(new URL('../../alerts-apps-script/Code.gs', import.meta.url), 'utf8');

function sourceOf(source, name) {
  const marker = `function ${name}`;
  const start = source.indexOf(marker);
  assert.notEqual(start, -1, `${name} declaration is missing`);
  const brace = source.indexOf('{', start);
  let depth = 0;
  for (let i = brace; i < source.length; i += 1) {
    if (source[i] === '{') depth += 1;
    if (source[i] === '}') {
      depth -= 1;
      if (depth === 0) return source.slice(start, i + 1);
    }
  }
  throw new Error(`${name} declaration is incomplete`);
}

test('one-time importer compiles and contains the complete bounded datasets', () => {
  assert.doesNotThrow(() => new Function(importer));
  const paymentIds = importer.match(/4U[A-Z0-9]{10}/g) || [];
  assert.equal(paymentIds.length, 108);
  assert.match(importer, /LX321095894JP:'2026-06-15'/);
  assert.match(importer, /PRE_INTEGRITY_NOT_CLEAN/);
  assert.match(importer, /POST_INTEGRITY_FAILED/);
  assert.match(importer, /ARRIVAL_DATE_CONFLICT/);
  assert.match(importer, /arrival_tracks_missing:missingTracks/);
  assert.doesNotMatch(importer, /if \(missingTracks\.length\) throw/);
});

test('alerts source compiles and dismissal controls both API and Telegram', () => {
  assert.doesNotThrow(() => new Function(alerts));
  assert.match(alerts, /BOOSTER_ALERTS_TOKEN/);
  assert.match(alerts, /function doGet\(/);
  assert.match(alerts, /function doPost\(/);
  assert.match(alerts, /setManagedAlertStatus_/);
  assert.match(alerts, /status === 'active'/);
  assert.doesNotMatch(alerts, /getDataRange\(\)/);
});

test('alerts are managed per SKU and include actionable stock queues', () => {
  assert.match(alerts, /function collectNegativeStockIssues_\(/);
  assert.match(alerts, /Мінусовий залишок ·/);
  assert.match(alerts, /function collectStockQueueIssues_\(/);
  assert.match(alerts, /function collectCountifQualityIssues_\(/);
  assert.match(alerts, /title:'Докупити'/);
  assert.match(alerts, /title:'Пильнувати'/);
  assert.match(alerts, /title:'Не просувати'/);
  assert.match(alerts, /function collectManagedIssueCandidates_\(/);
  assert.match(alerts, /collectDataQualityIssues_\(\)\.concat\(collectStockQueueIssues_\(\)\)/);
  assert.match(alerts, /const current = collectManagedIssueCandidates_\(\)/);
});

test('one aggregate negative-stock check expands into separate dismissible SKU alerts', () => {
  const quality = [
    ['Перевірка','Статус','К-сть','Деталі','Рекомендована дія'],
    ['Мінусовий залишок','Перевірити','2','Залишок CRM нижче нуля','Перевірити рухи'],
  ];
  const masterHeader = ['SKU','Назва','','','','','','','','','','Залишок','','','','','Проблеми'];
  const master = [masterHeader];
  for (const [sku,name,balance] of [['SKU-A','Alpha','-1'],['SKU-B','Beta','-3']]) {
    const row = Array(17).fill('');row[0]=sku;row[1]=name;row[11]=balance;row[16]='мінусовий_залишок';master.push(row);
  }
  const queue = {
    'A9:G14':[['SKU-C','Gamma','',5,1,0,4]],
    'I18:O23':[['SKU-D','Delta','',2,3,1,0]],
    'A18:G23':[['SKU-E','Epsilon','',0,20,20,0]],
  };
  const sheet = (values,formulas=values.map(row => row.map(() => ''))) => ({
    getLastRow:() => values.length,
    getLastColumn:() => Math.max(...values.map(row => row.length)),
    getRange:(...args) => ({
      getDisplayValues:() => typeof args[0] === 'string' ? (queue[args[0]] || []) : values,
      getFormulas:() => formulas,
    }),
  });
  const sheets = {Якість_Даних:sheet(quality),Майстер_Товарів:sheet(master),Черга_Складу:sheet([])};
  const SpreadsheetApp = {getActive:() => ({getSheetByName:name => sheets[name] || null})};
  const names = ['findHeaderRow_','findColumnLoose_','cell_','normalizeText_','trimText_','alertHeaderRow_','alertDisplayNumber_','alertColumnNumber_','alertWildcardMatch_','collectCountifQualityIssues_','collectNegativeStockIssues_','collectDataQualityIssues_','collectStockQueueIssues_','collectManagedIssueCandidates_'];
  const factory = new Function('SpreadsheetApp', `
    const ALERT_API_MAX_=200;
    function hashText_(value){return 'id:'+value;}
    ${names.map(name => sourceOf(alerts,name)).join('\n')}
    return {collectManagedIssueCandidates_};
  `)(SpreadsheetApp);
  const issues = factory.collectManagedIssueCandidates_();
  assert.equal(issues.length, 5);
  assert.deepEqual(issues.map(issue => issue.sku).sort(), ['SKU-A','SKU-B','SKU-C','SKU-D','SKU-E']);
  assert.equal(new Set(issues.map(issue => issue.id)).size, 5);
  assert.equal(issues.some(issue => issue.title === 'Мінусовий залишок' && issue.count === '2'), false);
});
