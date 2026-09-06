#!/usr/bin/env python3
"""Generate reviewable, owner-run one-time Apps Script migration wrappers."""

from __future__ import annotations

import argparse
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PAYLOAD = ROOT / "plans" / "3dp-catalog-reset-20260902" / "migration-payload.json"
OUT = ROOT / "scripts" / "3dp-catalog-reset"
PREFLIGHT_3DP = ROOT / "work" / "3dp-catalog-reset-20260902" / "3dp-preflight-20260904.json"
PREFLIGHT_CRM = ROOT / "work" / "3dp-catalog-reset-20260902" / "crm-preflight-20260904.json"
CLEANUP_CRM = ROOT / "work" / "3dp-catalog-reset-20260902" / "crm-cleanup-evidence.json"


def js(value) -> str:
    return json.dumps(value, ensure_ascii=False, separators=(",", ":"))


def sheet_preflight(path: Path, name: str) -> dict:
    data = json.loads(path.read_text(encoding="utf-8"))
    matches = [item for item in data["sections"]["sheet"] if item.get("name") == name]
    if len(matches) != 1:
        raise RuntimeError(f"Expected one preflight record for {name!r}, found {len(matches)}")
    return matches[0]


def build_3dp(records: list[dict]) -> str:
    rows = [item["3dp"] for item in records]
    expected = ["ACC-3D-DITTO-410", "FIG-LUFFY-500", "BR-CHARM-100", "BR-BULB-100", "BR-MEW-100", "BR-PIKA-100", "FIG-LUFFY-410", "FIG-123-500"]
    guarded = {}
    for name in ["Друк-лог", "Продажі", "Маркетингові_плюшки", "Виплати", "Наявність", "_Чернетки_партій", "_Коригування_наявності"]:
        item = sheet_preflight(PREFLIGHT_3DP, name)
        guarded[name] = {
            "key_column": item["key_column"],
            "first_data_row": item["first_data_row"],
            "snapshot_sha256": item["key_snapshot_sha256"],
            "populated_keys": item["populated_keys"],
        }
    return f"""/** TEMPORARY owner-run wrapper. Delete from the Apps Script project after verified apply. */
const CATALOG_MIGRATION_3DP_ROWS_ = Object.freeze({js(rows)});
const CATALOG_MIGRATION_3DP_EXPECTED_OLD_ = Object.freeze({js(sorted(expected))});
const CATALOG_MIGRATION_3DP_PREFLIGHT_ = Object.freeze({js(guarded)});

function catalogMigration3dpLog_(result) {{console.log('CATALOG_MIGRATION_3DP '+JSON.stringify(result));return result;}}

function catalogMigration3dpKeys_(sheet) {{
  if (sheet.getLastRow() < 2) return [];
  return sheet.getRange(2, 1, sheet.getLastRow() - 1, 1).getDisplayValues().map(function(r){{return String(r[0]||'').trim();}}).filter(Boolean).sort();
}}

function catalogMigration3dpSnapshot_(sheet, width) {{
  const rows = Math.max(sheet.getLastRow(), 2);
  const range = sheet.getRange(2, 1, rows - 1, width);
  return {{ sheet: sheet, range: range, width: width, values: range.getValues(), formulas: range.getFormulasR1C1(), numberFormats: range.getNumberFormats() }};
}}

function catalogMigration3dpRestore_(snapshot) {{
  const currentRows=Math.max(snapshot.sheet.getLastRow()-1,1);
  snapshot.sheet.getRange(2,1,currentRows,snapshot.width).clearContent();
  snapshot.range.clearContent();
  snapshot.range.setValues(snapshot.values);
  snapshot.formulas.forEach(function(row, ri){{row.forEach(function(formula, ci){{if(formula)snapshot.range.getCell(ri+1,ci+1).setFormulaR1C1(formula);}});}});
  snapshot.range.setNumberFormats(snapshot.numberFormats);
}}

function catalogMigration3dpHex_(bytes) {{
  return bytes.map(function(value){{const normalized=(value+256)%256;return ('0'+normalized.toString(16)).slice(-2);}}).join('');
}}

function catalogMigration3dpKeyState_(sheet, spec) {{
  const last=Math.max(sheet.getLastRow(),spec.first_data_row-1),count=Math.max(last-spec.first_data_row+1,0);
  const values=count?sheet.getRange(spec.first_data_row,spec.key_column,count,1).getDisplayValues():[];
  return {{populated_keys:values.filter(function(row){{return String(row[0]||'').trim();}}).length,
    snapshot_sha256:catalogMigration3dpHex_(Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256,JSON.stringify(values),Utilities.Charset.UTF_8))}};
}}

function catalogMigration3dpBusinessState_(ss) {{
  const result={{}},names=Object.keys(CATALOG_MIGRATION_3DP_PREFLIGHT_);
  names.forEach(function(name){{
    const sheet=ss.getSheetByName(name);
    if(!sheet)throw apiError3dp_('SHEET_NOT_FOUND','Required migration sheet not found: '+name);
    result[name]=catalogMigration3dpKeyState_(sheet,CATALOG_MIGRATION_3DP_PREFLIGHT_[name]);
  }});
  [CATALOG_FIFO_3DP.batchesSheet,CATALOG_FIFO_3DP.allocationsSheet].forEach(function(name){{const sheet=ss.getSheetByName(name);result[name]={{populated_keys:sheet&&sheet.getLastRow()>1?sheet.getRange(2,1,sheet.getLastRow()-1,1).getDisplayValues().filter(function(row){{return String(row[0]||'').trim();}}).length:0}};}});
  return result;
}}

function catalogMigration3dpBusinessMatchesPreflight_(state) {{
  return Object.keys(CATALOG_MIGRATION_3DP_PREFLIGHT_).every(function(name){{const expected=CATALOG_MIGRATION_3DP_PREFLIGHT_[name],actual=state[name];return actual.populated_keys===expected.populated_keys&&actual.snapshot_sha256===expected.snapshot_sha256;}});
}}

function catalogMigration3dpBusinessIsReset_(state) {{
  return ['Друк-лог','Продажі','Маркетингові_плюшки','Виплати','_Чернетки_партій','_Коригування_наявності',CATALOG_FIFO_3DP.batchesSheet,CATALOG_FIFO_3DP.allocationsSheet]
    .every(function(name){{return state[name]&&state[name].populated_keys===0;}})&&state['Наявність']&&state['Наявність'].populated_keys===CATALOG_MIGRATION_3DP_ROWS_.length;
}}

function catalogMigration3dpValueMatches_(actual, expected) {{
  if(expected===null||expected==='')return actual===null||actual==='';
  if(typeof expected==='number')return Math.abs(Number(actual)-expected)<0.000001;
  return String(actual)===String(expected);
}}

function catalogMigration3dpRowsMatchTarget_(sheet) {{
  const range=sheet.getRange(2,1,CATALOG_MIGRATION_3DP_ROWS_.length,19),values=range.getValues(),formulas=range.getFormulas(),numberFormats=range.getNumberFormats();
  return CATALOG_MIGRATION_3DP_ROWS_.every(function(data,index){{return Object.keys(data).every(function(column){{const ci=column.charCodeAt(0)-65;return catalogMigration3dpValueMatches_(values[index][ci],data[column]);}})&&Boolean(formulas[index][10])&&numberFormats[index][6]===PRINT_TIME_ENTRY_3DP.numberFormat;}});
}}

function catalogMigration3dpPreview() {{
  const ss = getSpreadsheet3dp_();
  const actual = catalogMigration3dpKeys_(getSheet3dp_(ss, SHEETS_3DP.nomenclature));
  const target = CATALOG_MIGRATION_3DP_ROWS_.map(function(r){{return r.A;}}).sort();
  const nomenclature=getSheet3dp_(ss,SHEETS_3DP.nomenclature),business=catalogMigration3dpBusinessState_(ss),catalogueTarget=JSON.stringify(actual)===JSON.stringify(target)&&catalogMigration3dpRowsMatchTarget_(nomenclature);
  const state = catalogueTarget&&catalogMigration3dpBusinessIsReset_(business) ? 'already_applied' :
    (JSON.stringify(actual) === JSON.stringify(CATALOG_MIGRATION_3DP_EXPECTED_OLD_)&&catalogMigration3dpBusinessMatchesPreflight_(business) ? 'ready' : 'blocked_catalogue_drift');
  return catalogMigration3dpLog_({{ok:state!=='blocked_catalogue_drift',action:'catalog_migration_3dp_preview',state:state,current_skus:actual,target_count:target.length,
    business_state:business,backup_will_be_created:true,preserved_sheets:['_Аудит_API','_Журнал_налаштувань_3DP','Аналітика']}});
}}

function catalogMigration3dpDiagnose0339() {{
  const ss=getSpreadsheet3dp_(),audit=ss.getSheetByName(API_3DP.auditSheet),order='OC-FOP-0339';
  if(!audit)throw apiError3dp_('AUDIT_NOT_INITIALIZED','_Аудит_API is missing.');
  const count=Math.max(audit.getLastRow()-1,0),values=count?audit.getRange(2,1,count,8).getDisplayValues():[];
  const cleanupEvents=[];
  values.forEach(function(row,index){{
    if(String(row[2]||'').trim()!=='CLEANUP_TEST_ORDER')return;
    cleanupEvents.push({{row:index+2,timestamp:String(row[0]||''),target:String(row[4]||''),old_value:String(row[5]||''),new_value:String(row[6]||''),details:String(row[7]||'')}});
  }});
  return catalogMigration3dpLog_({{ok:true,action:'catalog_migration_3dp_diagnose_0339',order:order,matching_cleanup_events:cleanupEvents.filter(function(item){{return item.target===order;}}),all_cleanup_targets:cleanupEvents.map(function(item){{return item.target;}})}});
}}

function catalogMigration3dpApply() {{
  return withScriptLock3dp_(function(){{
    const preview = catalogMigration3dpPreview();
    if (preview.state === 'already_applied') return catalogMigration3dpLog_({{ok:true,action:'catalog_migration_3dp_apply',already_applied:true,count:72}});
    if (!preview.ok) throw apiError3dp_('CATALOGUE_DRIFT', '3D-P catalogue changed after preflight; run the read-only preflight again.');
    const ss = getSpreadsheet3dp_();
    const stamp = Utilities.formatDate(new Date(), API_3DP.timezone, 'yyyyMMdd-HHmmss');
    const backup = DriveApp.getFileById(ss.getId()).makeCopy(ss.getName() + ' PRE-3DP-CATALOG-' + stamp);
    const sheets = [
      [getSheet3dp_(ss,SHEETS_3DP.nomenclature),19], [getSheet3dp_(ss,SHEETS_3DP.printLog),11],
      [getSheet3dp_(ss,SHEETS_3DP.sales),27], [getSheet3dp_(ss,SHEETS_3DP.plyushky),8],
      [getSheet3dp_(ss,SHEETS_3DP.payouts),Math.max(getSheet3dp_(ss,SHEETS_3DP.payouts).getLastColumn(),8)],
      [getSheet3dp_(ss,SHEETS_3DP.availability),7],
    ];
    [SHEETS_3DP.drafts,SHEETS_3DP.stockAdjustments].forEach(function(name){{const sh=ss.getSheetByName(name);if(sh)sheets.push([sh,Math.max(sh.getLastColumn(),1)]);}});
    const snapshots = sheets.map(function(item){{return catalogMigration3dpSnapshot_(item[0],item[1]);}});
    try {{
      sheets.forEach(function(item){{const sh=item[0];if(sh.getLastRow()<=1)return;if(sh.getName()===SHEETS_3DP.payouts){{sh.getRange(2,1,sh.getLastRow()-1,1).clearContent();if(item[1]>=4)sh.getRange(2,4,sh.getLastRow()-1,item[1]-3).clearContent();}}else sh.getRange(2,1,sh.getLastRow()-1,item[1]).clearContent();}});
      const nomenclature = getSheet3dp_(ss,SHEETS_3DP.nomenclature);
      const formulaK = snapshots[0].formulas[0][10];
      if (!formulaK) throw apiError3dp_('FORMULA_TEMPLATE_MISSING','Nomenclature K2 formula template is missing.');
      CATALOG_MIGRATION_3DP_ROWS_.forEach(function(data,index){{
        const row=index+2;Object.keys(data).forEach(function(column){{const value=data[column];if(value!==null&&value!=='')nomenclature.getRange(column+row).setValue(value);}});
        nomenclature.getRange('K'+row).setFormulaR1C1(formulaK);
      }});
      nomenclature.getRange(2,7,CATALOG_MIGRATION_3DP_ROWS_.length,1).setNumberFormat(PRINT_TIME_ENTRY_3DP.numberFormat);
      const availability=getSheet3dp_(ss,SHEETS_3DP.availability),availabilityTemplate=snapshots[5].formulas[0];
      if (!availabilityTemplate.some(Boolean)) throw apiError3dp_('FORMULA_TEMPLATE_MISSING','Availability row 2 formula template is missing.');
      for(let index=0;index<CATALOG_MIGRATION_3DP_ROWS_.length;index++){{availabilityTemplate.forEach(function(formula,ci){{if(formula)availability.getRange(index+2,ci+1).setFormulaR1C1(formula);}});}}
      fifo3dpEnsureHiddenSheet_(ss,CATALOG_FIFO_3DP.batchesSheet,CATALOG_FIFO_3DP.batchHeaders);
      fifo3dpEnsureHiddenSheet_(ss,CATALOG_FIFO_3DP.allocationsSheet,CATALOG_FIFO_3DP.allocationHeaders);
      const analytics=fifo3dpEnsureAnalyticsSheet_(ss).sheet;if(analytics.getLastRow()>1)analytics.getRange(2,1,analytics.getLastRow()-1,14).clearContent();
      const sync=syncActiveNomenclatureAnalytics3dp_(ss);SpreadsheetApp.flush();
      const after=catalogMigration3dpPreview();if(after.state!=='already_applied')throw apiError3dp_('VERIFY_FAILED','3D-P catalogue verification failed.');
      appendAudit3dp_(ss,{{role:'owner',identity:'catalog-migration'}},'CATALOG_RESET_IMPORT',SHEETS_3DP.nomenclature,'A2:S73',preview.current_skus,after.current_skus,'backup_file_id='+backup.getId());
      return catalogMigration3dpLog_({{ok:true,action:'catalog_migration_3dp_apply',already_applied:false,count:72,active:62,inactive:10,analytics_rows:sync.active_sku_count,backup_file_id:backup.getId()}});
    }} catch(error) {{ snapshots.reverse().forEach(catalogMigration3dpRestore_);SpreadsheetApp.flush();throw error; }}
  }});
}}

function catalogMigration3dpProtectedState_(ss) {{
  const out={{}};['_Аудит_API','_Журнал_налаштувань_3DP','Аналітика'].forEach(function(name){{
    const sh=ss.getSheetByName(name);if(!sh){{out[name]=null;return;}}const rg=sh.getDataRange(),values=rg.getValues(),formulas=rg.getFormulasR1C1();
    const stableValues=name==='Аналітика'?values.map(function(row,ri){{return row.map(function(value,ci){{return formulas[ri][ci]?null:value;}});}}):values;
    out[name]=catalogMigration3dpHex_(Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256,JSON.stringify({{values:stableValues,formulas:formulas}}),Utilities.Charset.UTF_8));
  }});return out;
}}

function catalogMigration3dpLatestRehearsalCopy_() {{
  const files=DriveApp.searchFiles("title contains 'DRYRUN-3DP-CATALOG-V2-' and trashed = false");
  let latest=null;
  while(files.hasNext()){{const file=files.next();if(!latest||file.getDateCreated().getTime()>latest.getDateCreated().getTime())latest=file;}}
  if(!latest)throw new Error('No non-trashed DRYRUN-3DP-CATALOG-V2 rehearsal copy found.');
  return latest;
}}

function catalogMigration3dpDiagnoseLatestRehearsalV2() {{
  const file=catalogMigration3dpLatestRehearsalCopy_(),ss=SpreadsheetApp.openById(file.getId());
  const live=getSpreadsheet3dp_(),nomenclature=getSheet3dp_(ss,SHEETS_3DP.nomenclature);
  const targetKeys=CATALOG_MIGRATION_3DP_ROWS_.map(function(row){{return row.A;}}).sort(),actualKeys=catalogMigration3dpKeys_(nomenclature);
  const targetSet={{}},actualSet={{}};targetKeys.forEach(function(key){{targetSet[key]=true;}});actualKeys.forEach(function(key){{actualSet[key]=true;}});
  const range=nomenclature.getRange(2,1,CATALOG_MIGRATION_3DP_ROWS_.length,19),values=range.getValues(),display=range.getDisplayValues(),formulas=range.getFormulas(),numberFormats=range.getNumberFormats();
  const rowProblems=[];
  CATALOG_MIGRATION_3DP_ROWS_.forEach(function(data,index){{
    Object.keys(data).forEach(function(column){{
      const ci=column.charCodeAt(0)-65,actual=values[index][ci],expected=data[column];
      if(!catalogMigration3dpValueMatches_(actual,expected)&&rowProblems.length<40)rowProblems.push({{row:index+2,sku:data.A,column:column,expected:expected,actual:display[index][ci]}});
    }});
    if(numberFormats[index][6]!==PRINT_TIME_ENTRY_3DP.numberFormat&&rowProblems.length<40)rowProblems.push({{row:index+2,sku:data.A,column:'G',code:'number_format_mismatch',expected:PRINT_TIME_ENTRY_3DP.numberFormat,actual:numberFormats[index][6]}});
    if(!formulas[index][10]&&rowProblems.length<40)rowProblems.push({{row:index+2,sku:data.A,column:'K',code:'formula_missing'}});
  }});
  const business=catalogMigration3dpBusinessState_(ss),protectedCopy=catalogMigration3dpProtectedState_(ss),protectedLive=catalogMigration3dpProtectedState_(live);
  const result={{ok:true,action:'catalog_migration_3dp_diagnose_latest_rehearsal_v2',rehearsal_file_id:file.getId(),rehearsal_file_name:file.getName(),
    checks:{{target_keys_exact:JSON.stringify(actualKeys)===JSON.stringify(targetKeys),target_rows_exact:rowProblems.length===0,business_reset:catalogMigration3dpBusinessIsReset_(business),protected_matches_live:JSON.stringify(protectedCopy)===JSON.stringify(protectedLive)}},
    key_diff:{{missing:targetKeys.filter(function(key){{return !actualSet[key];}}),extra:actualKeys.filter(function(key){{return !targetSet[key];}})}},row_problems:rowProblems,business_state:business,
    protected_copy:protectedCopy,protected_live:protectedLive}};
  return catalogMigration3dpLog_(result);
}}

function catalogMigration3dpRehearsalV2() {{
  const live=getSpreadsheet3dp_(),preview=catalogMigration3dpPreview();
  if(!preview.ok||preview.state!=='ready')return catalogMigration3dpLog_({{ok:false,action:'catalog_migration_3dp_rehearsal_v2',state:'blocked_live_preflight',preview:preview}});
  const liveBefore=JSON.stringify({{business:catalogMigration3dpBusinessState_(live),keys:catalogMigration3dpKeys_(getSheet3dp_(live,SHEETS_3DP.nomenclature)),protected:catalogMigration3dpProtectedState_(live)}});
  const stamp=Utilities.formatDate(new Date(),API_3DP.timezone,'yyyyMMdd-HHmmss'),file=DriveApp.getFileById(live.getId()).makeCopy(live.getName()+' DRYRUN-3DP-CATALOG-V2-'+stamp);
  try {{
    const ss=SpreadsheetApp.openById(file.getId()),beforeProtected=catalogMigration3dpProtectedState_(ss),beforeBusiness=catalogMigration3dpBusinessState_(ss);
    if(!catalogMigration3dpBusinessMatchesPreflight_(beforeBusiness))throw new Error('Rehearsal copy business state differs from approved preflight.');
    const sheets=[[getSheet3dp_(ss,SHEETS_3DP.nomenclature),19],[getSheet3dp_(ss,SHEETS_3DP.printLog),11],[getSheet3dp_(ss,SHEETS_3DP.sales),27],[getSheet3dp_(ss,SHEETS_3DP.plyushky),8],[getSheet3dp_(ss,SHEETS_3DP.payouts),Math.max(getSheet3dp_(ss,SHEETS_3DP.payouts).getLastColumn(),8)],[getSheet3dp_(ss,SHEETS_3DP.availability),7]];
    [SHEETS_3DP.drafts,SHEETS_3DP.stockAdjustments].forEach(function(name){{const sh=ss.getSheetByName(name);if(sh)sheets.push([sh,Math.max(sh.getLastColumn(),1)]);}});
    const snapshots=sheets.map(function(item){{return catalogMigration3dpSnapshot_(item[0],item[1]);}});
    sheets.forEach(function(item){{const sh=item[0];if(sh.getLastRow()<=1)return;if(sh.getName()===SHEETS_3DP.payouts){{sh.getRange(2,1,sh.getLastRow()-1,1).clearContent();if(item[1]>=4)sh.getRange(2,4,sh.getLastRow()-1,item[1]-3).clearContent();}}else sh.getRange(2,1,sh.getLastRow()-1,item[1]).clearContent();}});
    const nomenclature=getSheet3dp_(ss,SHEETS_3DP.nomenclature),formulaK=snapshots[0].formulas[0][10];if(!formulaK)throw new Error('Nomenclature K2 formula template is missing.');
    CATALOG_MIGRATION_3DP_ROWS_.forEach(function(data,index){{const row=index+2;Object.keys(data).forEach(function(column){{const value=data[column];if(value!==null&&value!=='')nomenclature.getRange(column+row).setValue(value);}});nomenclature.getRange('K'+row).setFormulaR1C1(formulaK);}});
    nomenclature.getRange(2,7,CATALOG_MIGRATION_3DP_ROWS_.length,1).setNumberFormat(PRINT_TIME_ENTRY_3DP.numberFormat);
    const availability=getSheet3dp_(ss,SHEETS_3DP.availability),template=snapshots[5].formulas[0];if(!template.some(Boolean))throw new Error('Availability formula template is missing.');for(let i=0;i<72;i++)template.forEach(function(formula,ci){{if(formula)availability.getRange(i+2,ci+1).setFormulaR1C1(formula);}});
    fifo3dpEnsureHiddenSheet_(ss,CATALOG_FIFO_3DP.batchesSheet,CATALOG_FIFO_3DP.batchHeaders);fifo3dpEnsureHiddenSheet_(ss,CATALOG_FIFO_3DP.allocationsSheet,CATALOG_FIFO_3DP.allocationHeaders);const analytics=fifo3dpEnsureAnalyticsSheet_(ss).sheet;if(analytics.getLastRow()>1)analytics.getRange(2,1,analytics.getLastRow()-1,14).clearContent();const sync=syncActiveNomenclatureAnalytics3dp_(ss);SpreadsheetApp.flush();
    const targetKeys=CATALOG_MIGRATION_3DP_ROWS_.map(function(r){{return r.A;}}).sort(),afterBusiness=catalogMigration3dpBusinessState_(ss);if(JSON.stringify(catalogMigration3dpKeys_(nomenclature))!==JSON.stringify(targetKeys)||!catalogMigration3dpRowsMatchTarget_(nomenclature)||!catalogMigration3dpBusinessIsReset_(afterBusiness))throw new Error('Rehearsal target verification failed.');if(JSON.stringify(beforeProtected)!==JSON.stringify(catalogMigration3dpProtectedState_(ss)))throw new Error('Protected sheet changed in rehearsal.');
    const liveAfter=JSON.stringify({{business:catalogMigration3dpBusinessState_(live),keys:catalogMigration3dpKeys_(getSheet3dp_(live,SHEETS_3DP.nomenclature)),protected:catalogMigration3dpProtectedState_(live)}});if(liveAfter!==liveBefore)throw new Error('Live 3D-P workbook changed during rehearsal.');file.setTrashed(true);return catalogMigration3dpLog_({{ok:true,action:'catalog_migration_3dp_rehearsal_v2',state:'rehearsal_passed',live_unchanged:true,rehearsal_copy_trashed:true,target_count:72,active:62,inactive:10,analytics_rows:sync.active_sku_count,business_state:afterBusiness}});
  }} catch(error) {{return catalogMigration3dpLog_({{ok:false,action:'catalog_migration_3dp_rehearsal_v2',state:'rehearsal_failed',live_unchanged:JSON.stringify({{business:catalogMigration3dpBusinessState_(live),keys:catalogMigration3dpKeys_(getSheet3dp_(live,SHEETS_3DP.nomenclature)),protected:catalogMigration3dpProtectedState_(live)}})===liveBefore,rehearsal_file_id:file.getId(),error:String(error&&error.message?error.message:error)}});}}
}}
"""


def build_crm(records: list[dict]) -> str:
    rows = [item["crm"] for item in records]
    expected = ["ACC-3D-DITTO-410", "FIG-LUFFY-500", "BR-CHARM-100", "BR-BULB-100", "BR-MEW-100", "BR-PIKA-100", "FIG-LUFFY-410"]
    usage_rows = sheet_preflight(PREFLIGHT_CRM, "Використання_компонентів")["three_dp_keys"]
    sync_rows = sheet_preflight(PREFLIGHT_CRM, "_Журнал_3DP_синхронізації")["three_dp_keys"]
    cleanup = json.loads(CLEANUP_CRM.read_text(encoding="utf-8"))
    component_rows = []
    for item in cleanup["components"]:
        values = item["values"]
        component_rows.append({
            "row": item["row"],
            "id": values[0],
            "order": values[2],
            "kind": values[3],
            "sku": values[4],
            "qty": values[5],
            "prro_unit": values[6],
            "mgmt_unit": values[7],
            "prro_total": values[8],
            "mgmt_total": values[9],
        })
    affected_orders = sorted({item["order"] for item in component_rows})
    return f"""/** TEMPORARY owner-run wrapper. Delete from the Apps Script project after verified apply. */
const CATALOG_MIGRATION_CRM_ROWS_ = Object.freeze({js(rows)});
const CATALOG_MIGRATION_CRM_EXPECTED_OLD_ = Object.freeze({js(sorted(expected))});
const CATALOG_MIGRATION_CRM_EXPECTED_USAGE_ = Object.freeze({js(usage_rows)});
const CATALOG_MIGRATION_CRM_EXPECTED_SYNC_ = Object.freeze({js(sync_rows)});
const CATALOG_MIGRATION_CRM_EXPECTED_COMPONENT_ROWS_ = Object.freeze({js(component_rows)});
const CATALOG_MIGRATION_CRM_AFFECTED_ORDERS_ = Object.freeze({js(affected_orders)});
const CATALOG_MIGRATION_CRM_DROPPED_ORDER_ = 'OC-FOP-0339';

function catalogMigrationCrmLog_(result) {{console.log('CATALOG_MIGRATION_CRM '+JSON.stringify(result));return result;}}

function catalogMigrationCrm3dRows_(products,last) {{
  return products.getRange(3,1,last-2,1).getDisplayValues().map(function(r,i){{return {{row:i+3,sku:String(r[0]||'').trim()}};}})
    .filter(function(x){{return /^(?:BR|FIG|ACC-3D)-/.test(x.sku);}});
}}

function catalogMigrationCrmMatchingRows_(sheet,skuColumn,fromRow) {{
  if(!sheet||sheet.getLastRow()<fromRow)return [];
  return sheet.getRange(fromRow,skuColumn,sheet.getLastRow()-fromRow+1,1).getDisplayValues().map(function(r,i){{return {{row:i+fromRow,sku:String(r[0]||'').trim()}};}})
    .filter(function(x){{return CATALOG_MIGRATION_CRM_EXPECTED_OLD_.indexOf(x.sku)!==-1;}});
}}

function catalogMigrationCrmSameRows_(actual,expected) {{return JSON.stringify(actual)===JSON.stringify(expected);}}

function catalogMigrationCrmComponentRowsMatch_(usage) {{
  if(!usage)return CATALOG_MIGRATION_CRM_EXPECTED_COMPONENT_ROWS_.length===0;
  return CATALOG_MIGRATION_CRM_EXPECTED_COMPONENT_ROWS_.every(function(expected){{
    if(expected.row>usage.getLastRow())return false;
    const row=usage.getRange(expected.row,1,1,10).getValues()[0];
    return String(row[0]||'').trim()===expected.id&&String(row[2]||'').trim()===expected.order&&
      String(row[3]||'').trim()===expected.kind&&String(row[4]||'').trim()===expected.sku&&
      Math.abs(Number(row[5])-expected.qty)<0.000001&&Math.abs(Number(row[6])-expected.prro_unit)<0.000001&&
      Math.abs(Number(row[7])-expected.mgmt_unit)<0.000001&&Math.abs(Number(row[8])-expected.prro_total)<0.000001&&
      Math.abs(Number(row[9])-expected.mgmt_total)<0.000001;
  }});
}}

function catalogMigrationCrmDroppedOrderPlan_(ss) {{
  const order=CATALOG_MIGRATION_CRM_DROPPED_ORDER_,usage=ss.getSheetByName('Використання_компонентів'),writeoffs=ss.getSheetByName('Списання'),sync=ss.getSheetByName('_Журнал_3DP_синхронізації'),fixtures=ss.getSheetByName('Використання_фурнітури'),accounting=ss.getSheetByName('3D_облік_замовлень'),expenses=ss.getSheetByName('Витрати');
  if(!usage||!writeoffs||!sync||!fixtures||!accounting||!expenses)throw new Error('Dropped-order cleanup prerequisites are missing.');
  function matches_(sheet,firstRow,width,predicate){{
    if(sheet.getLastRow()<firstRow)return [];
    const rows=[];sheet.getRange(firstRow,1,sheet.getLastRow()-firstRow+1,width).getValues().forEach(function(values,index){{if(predicate(values))rows.push({{row:index+firstRow,values:values}});}});return rows;
  }}
  const components=matches_(usage,2,15,function(row){{return String(row[2]||'').trim()===order;}});
  const writeoffIds={{}};components.forEach(function(item){{const id=String(item.values[12]||'').trim();if(id)writeoffIds[id]=true;}});
  const writeoffRows=matches_(writeoffs,3,12,function(row){{return !!writeoffIds[String(row[0]||'').trim()]||String(row[11]||'').indexOf(order)!==-1;}});
  const syncRows=matches_(sync,2,7,function(row){{return String(row[2]||'').trim()===order;}});
  const fixtureRows=matches_(fixtures,2,14,function(row){{return String(row[3]||'').trim()===order;}});
  const accountingRows=matches_(accounting,2,20,function(row){{return String(row[2]||'').trim()===order;}});
  const expenseRows=matches_(expenses,3,13,function(row){{return String(row[5]||'').trim()===order;}});
  const componentSummary=components.map(function(item){{return {{row:item.row,id:String(item.values[0]||''),kind:String(item.values[3]||''),sku:String(item.values[4]||''),qty:Number(item.values[5]||0),writeoff_id:String(item.values[12]||'')}};}});
  const writeoffSummary=writeoffRows.map(function(item){{return {{row:item.row,id:String(item.values[0]||''),sku:String(item.values[3]||''),qty:Number(item.values[5]||0)}};}});
  const syncSummary=syncRows.map(function(item){{return {{row:item.row,source:String(item.values[1]||''),outcome:String(item.values[5]||'')}};}});
  const total=components.length+writeoffRows.length+syncRows.length+fixtureRows.length+accountingRows.length+expenseRows.length;
  const componentExact=JSON.stringify(componentSummary)===JSON.stringify([
    {{row:60,id:'CMP-USE-00059',kind:'3D-P',sku:'BR-CHARM-100',qty:1,writeoff_id:''}},
    {{row:61,id:'CMP-USE-00060',kind:'SKU',sku:'ACC-001',qty:1,writeoff_id:'WRT-0239'}}
  ]);
  const writeoffExact=writeoffSummary.length===1&&writeoffSummary[0].id==='WRT-0239'&&writeoffSummary[0].sku==='ACC-001'&&writeoffSummary[0].qty===1;
  const syncExact=JSON.stringify(syncSummary)===JSON.stringify([{{row:72,source:'apiUpdateSale_',outcome:'skipped_no_3dp_sku'}}]);
  const state=total===0?'already_applied':(componentExact&&writeoffExact&&syncExact&&!fixtureRows.length&&!accountingRows.length&&!expenseRows.length?'ready':'blocked_drift');
  return {{state:state,order:order,components:componentSummary,writeoffs:writeoffSummary,sync_journal:syncSummary,fixture_rows:fixtureRows.map(function(item){{return item.row;}}),accounting_rows:accountingRows.map(function(item){{return item.row;}}),expense_rows:expenseRows.map(function(item){{return item.row;}})}};
}}

function catalogMigrationCrmValueMatches_(actual,expected) {{
  if(expected===null||expected==='')return actual===null||actual==='';
  if(typeof expected==='number')return Math.abs(Number(actual)-expected)<0.000001;
  return String(actual)===String(expected);
}}

function catalogMigrationCrmRowsMatchTarget_(products,rrc,last) {{
  const bySku={{}};catalogMigrationCrm3dRows_(products,last).forEach(function(item){{bySku[item.sku]=item.row;}});
  return CATALOG_MIGRATION_CRM_ROWS_.every(function(data){{const row=bySku[data.A];if(!row)return false;
    const productOk=Object.keys(data).filter(function(column){{return /^[A-O]$/.test(column)&&column!=='B'&&column!=='J';}}).every(function(column){{return catalogMigrationCrmValueMatches_(products.getRange(column+row).getValue(),data[column]);}});
    return productOk&&catalogMigrationCrmValueMatches_(rrc.getRange('E'+row).getValue(),data.rrp_E)&&Boolean(rrc.getRange('H'+row).getFormula());
  }});
}}

function catalogMigrationCrmPreview() {{
  const ss=_getCrmSs(),products=ss.getSheetByName('Товари'),rrc=ss.getSheetByName('РРЦ'),stock=ss.getSheetByName('Склад'),sales=ss.getSheetByName('Продажі');
  const last=crmCatalogLastWritableRow_(products,rrc,stock),actual=catalogMigrationCrm3dRows_(products,last).map(function(x){{return x.sku;}}).sort();
  const target=CATALOG_MIGRATION_CRM_ROWS_.map(function(x){{return x.A;}}).sort(),integrity=apiIntegrityCheck_();
  const usage=ss.getSheetByName('Використання_компонентів'),sync=ss.getSheetByName('_Журнал_3DP_синхронізації');
  const usageRows=catalogMigrationCrmMatchingRows_(usage,5,2),syncRows=catalogMigrationCrmMatchingRows_(sync,5,2);
  const relatedReset=usageRows.length===0&&syncRows.length===0;
  const componentRowsExact=catalogMigrationCrmComponentRowsMatch_(usage);
  const droppedOrderCleanup=catalogMigrationCrmDroppedOrderPlan_(ss),affectedOrderRowCounts={{}},affectedOrdersReady=Boolean(sales),orphanOrders=[];
  if(sales)CATALOG_MIGRATION_CRM_AFFECTED_ORDERS_.forEach(function(order){{affectedOrderRowCounts[order]=findSaleRowsByOrder_(sales,order).length;}});
  Object.keys(affectedOrderRowCounts).forEach(function(order){{if(affectedOrderRowCounts[order]===0)orphanOrders.push(order);}});
  const relatedReady=catalogMigrationCrmSameRows_(usageRows,CATALOG_MIGRATION_CRM_EXPECTED_USAGE_)&&catalogMigrationCrmSameRows_(syncRows,CATALOG_MIGRATION_CRM_EXPECTED_SYNC_)&&componentRowsExact&&affectedOrdersReady;
  const targetReady=JSON.stringify(actual)===JSON.stringify(target)&&catalogMigrationCrmRowsMatchTarget_(products,rrc,last);
  const allowedOrphan=orphanOrders.length===1&&orphanOrders[0]===CATALOG_MIGRATION_CRM_DROPPED_ORDER_;
  const state=targetReady&&relatedReset&&droppedOrderCleanup.state==='already_applied'?'already_applied':(JSON.stringify(actual)===JSON.stringify(CATALOG_MIGRATION_CRM_EXPECTED_OLD_)&&relatedReady&&allowedOrphan&&droppedOrderCleanup.state==='ready'?'ready':'blocked_catalogue_drift');
  return catalogMigrationCrmLog_({{ok:(state==='ready'||state==='already_applied')&&integrity.problems.length===0,action:'catalog_migration_crm_preview',state:state,current_skus:actual,target_count:target.length,related_rows:{{component_usage:usageRows,sync_journal:syncRows,component_rows_exact:componentRowsExact,affected_order_row_counts:affectedOrderRowCounts,orphan_orders:orphanOrders,dropped_order_cleanup:droppedOrderCleanup}},integrity:integrity,backup_will_be_created:true}});
}}

function catalogMigrationCrmDiagnose0339() {{
  const ss=_getCrmSs(),sales=ss.getSheetByName('Продажі'),usage=ss.getSheetByName('Використання_компонентів'),sync=ss.getSheetByName('_Журнал_3DP_синхронізації'),writeoffs=ss.getSheetByName('Списання'),order='OC-FOP-0339';
  if(!sales||!usage||!sync||!writeoffs)throw new Error('Required CRM sheet is missing.');
  const saleCount=Math.max(sales.getLastRow()-2,0),saleValues=saleCount?sales.getRange(3,1,saleCount,31).getValues():[],saleMatches=[];
  saleValues.forEach(function(row,index){{
    const key=String(row[0]||'').trim();
    if(key===order||/(^|[^0-9])0*339([^0-9]|$)/.test(key))saleMatches.push({{row:index+3,order:key,has_component_cost_marker:CRM_ORDER_COMPONENT_AUDIT_RE_.test(String(row[30]||''))}});
  }});
  const candidateRows={{}},usageCount=Math.max(usage.getLastRow()-1,0),usageValues=usageCount?usage.getRange(2,1,usageCount,15).getValues():[],componentMatches=[];
  usageValues.forEach(function(row,index){{if(String(row[2]||'').trim()===order){{const targetRow=Math.floor(Number(row[13]||0));if(targetRow>=3)candidateRows[targetRow]=true;componentMatches.push({{row:index+2,id:String(row[0]||''),date:row[1]||'',order:String(row[2]||''),kind:String(row[3]||''),sku:String(row[4]||''),qty:Number(row[5]||0),prro_total:Number(row[8]||0),mgmt_total:Number(row[9]||0),created_at:row[11]||'',writeoff_id:String(row[12]||''),target_row:targetRow||'',target_sku:String(row[14]||'')}});}}}});
  const syncCount=Math.max(sync.getLastRow()-1,0),syncValues=syncCount?sync.getRange(2,1,syncCount,7).getValues():[],syncMatches=[];
  syncValues.forEach(function(row,index){{if(String(row[2]||'').trim()===order){{const crmRow=Math.floor(Number(row[3]||0));if(crmRow>=3)candidateRows[crmRow]=true;syncMatches.push({{row:index+2,timestamp:row[0]||'',source:String(row[1]||''),order:String(row[2]||''),crm_row:crmRow||'',sku:String(row[4]||''),outcome:String(row[5]||''),detail:String(row[6]||'')}});}}}});
  const writeoffIds={{}};componentMatches.forEach(function(item){{if(item.writeoff_id)writeoffIds[item.writeoff_id]=true;}});
  const writeoffCount=Math.max(writeoffs.getLastRow()-2,0),writeoffValues=writeoffCount?writeoffs.getRange(3,1,writeoffCount,12).getValues():[],writeoffMatches=[];
  writeoffValues.forEach(function(row,index){{const id=String(row[0]||'').trim(),note=String(row[11]||'');if(writeoffIds[id]||note.indexOf(order)!==-1)writeoffMatches.push({{row:index+3,id:id,date:row[1]||'',kind:String(row[2]||''),sku:String(row[3]||''),qty:Number(row[5]||0),note_has_order:note.indexOf(order)!==-1}});}});
  const neighborhood=[];
  saleValues.forEach(function(row,index){{const key=String(row[0]||'').trim(),match=/^OC-FOP-0*([0-9]+)$/.exec(key);if(match&&Number(match[1])>=330&&Number(match[1])<=345)neighborhood.push({{row:index+3,order:key,date:row[2]||'',sku:String(row[5]||''),qty:Number(row[7]||0),payment_status:String(row[22]||''),order_status:String(row[23]||'')}});}});
  let before=null,after=null;
  neighborhood.forEach(function(item){{const number=Number(item.order.replace(/[^0-9]/g,''));if(number<339&&(!before||number>before.number||(number===before.number&&item.row>before.row)))before={{number:number,row:item.row,order:item.order}};if(number>339&&(!after||number<after.number||(number===after.number&&item.row<after.row)))after={{number:number,row:item.row,order:item.order}};}});
  const blankRows=[];
  if(before&&after&&before.row<after.row)for(let row=before.row+1;row<after.row;row++){{const values=sales.getRange(row,1,1,32).getValues()[0],formulas=sales.getRange(row,1,1,32).getFormulas()[0];if(!String(values[0]||'').trim()&&!String(values[5]||'').trim()){{blankRows.push({{row:row,formula_columns:formulas.map(function(formula,index){{return formula?index+1:0;}}).filter(Boolean)}});candidateRows[row]=true;}}}}
  return catalogMigrationCrmLog_({{ok:true,action:'catalog_migration_crm_diagnose_0339',order:order,exact_sale_row_count:findSaleRowsByOrder_(sales,order).length,sale_key_matches:saleMatches,component_matches:componentMatches,sync_journal_matches:syncMatches,writeoff_matches:writeoffMatches,neighbor_orders:{{before:before,after:after}},blank_rows_between_neighbors:blankRows,candidate_sale_rows:Object.keys(candidateRows).map(Number).sort(function(a,b){{return a-b;}})}});
}}

function catalogMigrationCrmSnapshot_(range) {{return {{range:range,values:range.getValues(),formulas:range.getFormulasR1C1()}};}}
function catalogMigrationCrmRestore_(snapshot) {{snapshot.range.clearContent();snapshot.range.setValues(snapshot.values);snapshot.formulas.forEach(function(row,ri){{row.forEach(function(f,ci){{if(f)snapshot.range.getCell(ri+1,ci+1).setFormulaR1C1(f);}});}});}}

function catalogMigrationCrmSaleRowsByOrder_(sales) {{
  const result={{}};
  CATALOG_MIGRATION_CRM_AFFECTED_ORDERS_.forEach(function(order){{
    const rows=findSaleRowsByOrder_(sales,order);
    if(!rows.length&&order!==CATALOG_MIGRATION_CRM_DROPPED_ORDER_)throw new Error('Migration stopped: '+order+' has component records but no sale rows.');
    result[order]=rows;
  }});
  return result;
}}

function catalogMigrationCrmRemoveEmptyComponentMarker_(sales,rows) {{
  rows.forEach(function(row){{
    const auditCell=sales.getRange(row,31),audit=String(auditCell.getValue()||'');
    auditCell.setValue(audit.replace(CRM_ORDER_COMPONENT_AUDIT_RE_,'').replace(/^; |; $/g,'').trim());
    const methodCell=sales.getRange(row,30),method=String(methodCell.getValue()||'');
    methodCell.setValue(method.replace(/\\s*\\+\\s*компоненти замовлення/g,'').trim());
  }});
}}

function catalogMigrationCrmApply() {{
  const lock=LockService.getScriptLock();if(!lock.tryLock(30000))throw new Error('crm busy, retry later');
  try {{
    const preview=catalogMigrationCrmPreview();if(preview.state==='already_applied')return catalogMigrationCrmLog_({{ok:true,action:'catalog_migration_crm_apply',already_applied:true,count:72,integrity:preview.integrity}});
    if(!preview.ok)throw new Error('CRM catalogue/integrity changed after preflight; run the read-only preflight again.');
    const ss=_getCrmSs(),products=ss.getSheetByName('Товари'),rrc=ss.getSheetByName('РРЦ'),stock=ss.getSheetByName('Склад'),usage=ss.getSheetByName('Використання_компонентів'),sync=ss.getSheetByName('_Журнал_3DP_синхронізації'),sales=ss.getSheetByName('Продажі'),writeoffs=ss.getSheetByName('Списання'),fixtures=ss.getSheetByName('Використання_фурнітури'),accounting=ss.getSheetByName('3D_облік_замовлень'),expenses=ss.getSheetByName('Витрати');
    const droppedOrderPlan=catalogMigrationCrmDroppedOrderPlan_(ss);if(droppedOrderPlan.state!=='ready')throw new Error('Dropped-order cleanup changed after preflight: '+droppedOrderPlan.state);
    const last=crmCatalogLastWritableRow_(products,rrc,stock),stamp=Utilities.formatDate(new Date(),Session.getScriptTimeZone(),'yyyyMMdd-HHmmss');
    const backup=DriveApp.getFileById(ss.getId()).makeCopy(ss.getName()+' PRE-3DP-CATALOG-'+stamp);
    const oldRows=catalogMigrationCrm3dRows_(products,last),productSnapshot=catalogMigrationCrmSnapshot_(products.getRange(3,1,last-2,15)),rrpSnapshot=catalogMigrationCrmSnapshot_(rrc.getRange(3,1,last-2,8));
    const saleRowsByOrder=catalogMigrationCrmSaleRowsByOrder_(sales),salesSnapshots=[];
    Object.keys(saleRowsByOrder).forEach(function(order){{saleRowsByOrder[order].forEach(function(row){{salesSnapshots.push(catalogMigrationCrmSnapshot_(sales.getRange(row,1,1,32)));}});}});
    const droppedOrderSnapshots=[];
    droppedOrderPlan.writeoffs.forEach(function(item){{droppedOrderSnapshots.push(catalogMigrationCrmSnapshot_(writeoffs.getRange(item.row,1,1,writeoffs.getLastColumn())));}});
    droppedOrderPlan.fixture_rows.forEach(function(row){{droppedOrderSnapshots.push(catalogMigrationCrmSnapshot_(fixtures.getRange(row,1,1,fixtures.getLastColumn())));}});
    droppedOrderPlan.accounting_rows.forEach(function(row){{droppedOrderSnapshots.push(catalogMigrationCrmSnapshot_(accounting.getRange(row,1,1,accounting.getLastColumn())));}});
    droppedOrderPlan.expense_rows.forEach(function(row){{droppedOrderSnapshots.push(catalogMigrationCrmSnapshot_(expenses.getRange(row,1,1,expenses.getLastColumn())));}});
    let usageSnapshot=null,syncSnapshot=null,componentProjection={{orders_with_sales:0,rows_reset:0,rows_reapplied:0,dropped_order:CATALOG_MIGRATION_CRM_DROPPED_ORDER_}};
    try {{
      oldRows.forEach(function(x){{products.getRange(x.row,1).clearContent();products.getRange(x.row,3,1,7).clearContent();products.getRange(x.row,11,1,5).clearContent();rrc.getRange(x.row,5,1,3).clearContent();}});
      if(usage&&usage.getLastRow()>1){{
        const headers=usage.getRange(1,1,1,usage.getLastColumn()).getDisplayValues()[0],skuColumn=headers.map(function(x){{return String(x||'').trim().toUpperCase();}}).indexOf('SKU')+1;
        if(!skuColumn)throw new Error('Використання_компонентів SKU header missing');
        usageSnapshot=catalogMigrationCrmSnapshot_(usage.getRange(2,1,usage.getLastRow()-1,usage.getLastColumn()));
        Object.keys(saleRowsByOrder).forEach(function(order){{const rows=saleRowsByOrder[order];if(rows.length)componentProjection.rows_reset+=resetOrderComponentCostProjectionBeforeBaseRefresh_(ss,rows).rows_reset;}});
        droppedOrderPlan.components.forEach(function(item){{clearCrmRangePreservingFormulas_(usage.getRange(item.row,1,1,usage.getLastColumn()));}});
        const values=usage.getRange(2,skuColumn,usage.getLastRow()-1,1).getDisplayValues();values.forEach(function(r,i){{if(CATALOG_MIGRATION_CRM_EXPECTED_OLD_.indexOf(String(r[0]||'').trim())!==-1)usage.getRange(i+2,1,1,usage.getLastColumn()).clearContent();}});
        Object.keys(saleRowsByOrder).forEach(function(order){{const rows=saleRowsByOrder[order];if(!rows.length)return;const result=reapplyOrderComponentCostAfterBaseRefresh_(ss,order,rows);componentProjection.orders_with_sales++;componentProjection.rows_reapplied+=result.rows_updated;if(!result.rows_updated)catalogMigrationCrmRemoveEmptyComponentMarker_(sales,rows);}});
      }}
      if(sync&&sync.getLastRow()>1){{syncSnapshot=catalogMigrationCrmSnapshot_(sync.getRange(2,1,sync.getLastRow()-1,sync.getLastColumn()));droppedOrderPlan.sync_journal.forEach(function(item){{clearCrmRangePreservingFormulas_(sync.getRange(item.row,1,1,sync.getLastColumn()));}});const values=sync.getRange(2,5,sync.getLastRow()-1,1).getDisplayValues();values.forEach(function(r,i){{if(CATALOG_MIGRATION_CRM_EXPECTED_OLD_.indexOf(String(r[0]||'').trim())!==-1)sync.getRange(i+2,1,1,sync.getLastColumn()).clearContent();}});}}
      droppedOrderPlan.writeoffs.forEach(function(item){{clearCrmRangePreservingFormulas_(writeoffs.getRange(item.row,1,1,writeoffs.getLastColumn()));}});
      droppedOrderPlan.fixture_rows.forEach(function(row){{clearCrmRangePreservingFormulas_(fixtures.getRange(row,1,1,fixtures.getLastColumn()));}});
      droppedOrderPlan.accounting_rows.forEach(function(row){{clearCrmRangePreservingFormulas_(accounting.getRange(row,1,1,accounting.getLastColumn()));}});
      droppedOrderPlan.expense_rows.forEach(function(row){{clearCrmRangePreservingFormulas_(expenses.getRange(row,1,1,expenses.getLastColumn()));}});
      CATALOG_MIGRATION_CRM_ROWS_.forEach(function(data){{
        const row=crmNextAppendRow_(ss,'Товари',1);if(!products.getRange(row,10).getFormula()||!rrc.getRange(row,8).getFormula())throw new Error('Formula coverage missing at CRM row '+row);
        Object.keys(data).filter(function(k){{return /^[A-O]$/.test(k);}}).forEach(function(column){{const value=data[column];if(value!==null&&value!=='')products.getRange(column+row).setValue(value);}});
        products.getRange('B'+row).setFormula('=IF($A'+row+'="";"";$C'+row+')');
        if(data.rrp_E!==null)rrc.getRange('E'+row).setValue(data.rrp_E);else rrc.getRange('E'+row).clearContent();
        rrc.getRange('F'+row).setValue(new Date());rrc.getRange('G'+row).setValue(data.rrp_G);
      }});
      SpreadsheetApp.flush();const after=catalogMigrationCrmPreview();if(after.state!=='already_applied'||after.integrity.problems.length)throw new Error('CRM post-import integrity verification failed: '+JSON.stringify(after.integrity.problems));
      invalidateDoGetCache_();return catalogMigrationCrmLog_({{ok:true,action:'catalog_migration_crm_apply',already_applied:false,count:72,active:59,inactive:13,backup_file_id:backup.getId(),component_projection:componentProjection,integrity:after.integrity}});
    }} catch(error) {{catalogMigrationCrmRestore_(productSnapshot);catalogMigrationCrmRestore_(rrpSnapshot);if(usageSnapshot)catalogMigrationCrmRestore_(usageSnapshot);if(syncSnapshot)catalogMigrationCrmRestore_(syncSnapshot);droppedOrderSnapshots.forEach(catalogMigrationCrmRestore_);salesSnapshots.forEach(catalogMigrationCrmRestore_);SpreadsheetApp.flush();invalidateDoGetCache_();throw error;}}
  }} finally {{lock.releaseLock();}}
}}
"""


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--target", choices=("3dp", "crm", "all"), default="all")
    args = parser.parse_args()
    data = json.loads(PAYLOAD.read_text(encoding="utf-8"))
    records = data["records"]
    if len(records) != 72 or sum(bool(item["active"]) for item in records) != 62:
        raise SystemExit("Expected the approved 72-row, 62-active canonical payload")
    OUT.mkdir(parents=True, exist_ok=True)
    files = []
    if args.target in ("3dp", "all"):
        (OUT / "Temporary3dpCatalogMigration.gs").write_text(build_3dp(records), encoding="utf-8")
        files.append("Temporary3dpCatalogMigration.gs")
    if args.target in ("crm", "all"):
        (OUT / "TemporaryCrmCatalogMigration.gs").write_text(build_crm(records), encoding="utf-8")
        files.append("TemporaryCrmCatalogMigration.gs")
    print(json.dumps({"ok": True, "records": len(records), "active": 62, "inactive": 10, "files": files}))


if __name__ == "__main__":
    main()
