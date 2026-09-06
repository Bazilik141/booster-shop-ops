#!/usr/bin/env python3
"""Generate the narrow owner-run CRM canonical catalogue correction wrapper."""

from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PAYLOAD = ROOT / "plans" / "3dp-catalog-reset-20260902" / "migration-payload.json"
OUTPUT = ROOT / "scripts" / "3dp-catalog-reset" / "TemporaryCrmCatalogCanonicalCorrection.gs"


def js(value: object) -> str:
    return json.dumps(value, ensure_ascii=False, separators=(",", ":"))


def main() -> None:
    payload = json.loads(PAYLOAD.read_text(encoding="utf-8"))
    rows = []
    for item in payload["records"]:
        source = item["source"]
        rows.append({
            "source": f"{source['sheet']}!{source['row']}",
            "old_sku": item["pre_correction_sku"],
            "new_sku": item["sku"],
            "old_name": item["pre_correction_name"],
            "new_name": item["name"],
            "old_active": "Так" if item["pre_correction_active"] else "Ні",
            "new_active": "Так" if item["active"] else "Ні",
            "rrp": item["rrp_uah"],
        })
    if len(rows) != 72 or len({r["source"] for r in rows}) != 72 or len({r["new_sku"] for r in rows}) != 72:
        raise SystemExit("Expected 72 unique source identities and canonical articles")
    changes = [r for r in rows if (r["old_sku"], r["old_name"], r["old_active"]) != (r["new_sku"], r["new_name"], r["new_active"])]
    if (sum(r["old_sku"] != r["new_sku"] for r in rows), sum(r["old_name"] != r["new_name"] for r in rows), sum(r["old_active"] != r["new_active"] for r in rows)) != (3, 39, 3):
        raise SystemExit("Canonical correction delta must be exactly 3 articles, 39 names and 3 statuses")

    source = f'''/** TEMPORARY owner-run wrapper. Narrow 72-row canonical correction; never runs the legacy full Apply. */
const CATALOG_CANONICAL_CRM_ROWS_ = Object.freeze({js(rows)});
const CATALOG_CANONICAL_CRM_CHANGES_ = Object.freeze({js(changes)});
const CATALOG_CANONICAL_CRM_FP_KEY_ = 'catalog_canonical_crm_rehearsal_fp_v1';
const CATALOG_CANONICAL_CRM_AT_KEY_ = 'catalog_canonical_crm_rehearsal_at_v1';
const CATALOG_CANONICAL_CRM_MAX_AGE_MS_ = 30 * 60 * 1000;

function catalogCanonicalCrmLog_(x) {{ console.log('CATALOG_CANONICAL_CRM '+JSON.stringify(x)); return x; }}
function catalogCanonicalCrmHash_(x) {{ return Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256,JSON.stringify(x),Utilities.Charset.UTF_8).map(function(b){{b=(b+256)%256;return ('0'+b.toString(16)).slice(-2);}}).join(''); }}
function catalogCanonicalCrmSource_(note) {{ const m=/\\bsource=([^;]+);/.exec(String(note||'')); return m?m[1]:''; }}
function catalogCanonicalCrmSheets_(ss) {{ const p=ss.getSheetByName('Товари'),r=ss.getSheetByName('РРЦ'),s=ss.getSheetByName('Склад');if(!p||!r||!s)throw new Error('CRM catalogue sheet is missing.');return {{p:p,r:r,s:s,last:crmCatalogLastWritableRow_(p,r,s)}}; }}
function catalogCanonicalCrmRelated_(ss) {{
  const names=['Продажі','Використання_компонентів','Списання','Використання_фурнітури','3D_облік_замовлень','Витрати','_Журнал_3DP_синхронізації'];
  const out={{}};names.forEach(function(n){{const sh=ss.getSheetByName(n);if(!sh){{out[n]=null;return;}}const rg=sh.getDataRange();out[n]={{rows:sh.getLastRow(),cols:sh.getLastColumn(),hash:catalogCanonicalCrmHash_({{v:rg.getValues(),f:rg.getFormulasR1C1()}})}};}});return out;
}}
function catalogCanonicalCrmFingerprint_(ss) {{ const x=catalogCanonicalCrmSheets_(ss);return catalogCanonicalCrmHash_({{p:{{v:x.p.getRange(3,1,x.last-2,15).getValues(),f:x.p.getRange(3,1,x.last-2,15).getFormulasR1C1()}},r:{{v:x.r.getRange(3,1,x.last-2,8).getValues(),f:x.r.getRange(3,1,x.last-2,8).getFormulasR1C1()}},related:catalogCanonicalCrmRelated_(ss)}}); }}
function catalogCanonicalCrmInspect_(ss) {{
  const x=catalogCanonicalCrmSheets_(ss),values=x.p.getRange(3,1,x.last-2,15).getValues(),formulas=x.p.getRange(3,1,x.last-2,15).getFormulasR1C1(),rrp=x.r.getRange(3,1,x.last-2,8).getValues(),rrpFormulas=x.r.getRange(3,1,x.last-2,8).getFormulasR1C1(),bySource={{}},allSku={{}},problems=[];
  values.forEach(function(v,i){{const row=i+3,sku=String(v[0]||'').trim(),src=catalogCanonicalCrmSource_(v[13]);if(sku){{if(allSku[sku])problems.push({{code:'duplicate_sku',sku:sku,rows:[allSku[sku],row]}});allSku[sku]=row;}}if(src){{if(bySource[src])problems.push({{code:'duplicate_source',source:src}});bySource[src]={{row:row,v:v,f:formulas[i],rrp:rrp[i],rf:rrpFormulas[i]}};}}}});
  let current=true,target=true;CATALOG_CANONICAL_CRM_ROWS_.forEach(function(d){{const a=bySource[d.source];if(!a){{problems.push({{code:'source_missing',source:d.source}});current=false;target=false;return;}}if(!a.f[1]||!a.f[9]||!a.rf[7])problems.push({{code:'formula_missing',source:d.source,row:a.row}});if(d.rrp===null?String(a.rrp[4]||'')!=='':Math.abs(Number(a.rrp[4])-Number(d.rrp))>0.000001)problems.push({{code:'rrp_changed',source:d.source,row:a.row}});const now=[String(a.v[0]||''),String(a.v[2]||''),String(a.v[11]||'')];if(JSON.stringify(now)!==JSON.stringify([d.old_sku,d.old_name,d.old_active]))current=false;if(JSON.stringify(now)!==JSON.stringify([d.new_sku,d.new_name,d.new_active]))target=false;}});
  const active=CATALOG_CANONICAL_CRM_ROWS_.filter(function(d){{const a=bySource[d.source];return a&&String(a.v[11]||'')==='Так';}}).length;
  return {{ok:problems.length===0&&(current||target),state:target?'already_applied':(current?'ready':'blocked_catalogue_drift'),problems:problems.slice(0,30),active:active,inactive:72-active,rows:bySource}};
}}
function catalogCanonicalCrmApplyTo_(ss) {{ const check=catalogCanonicalCrmInspect_(ss);if(!check.ok||check.state==='blocked_catalogue_drift')throw new Error('Canonical preflight failed: '+JSON.stringify(check.problems));if(check.state==='already_applied')return {{already_applied:true,changed:0}};CATALOG_CANONICAL_CRM_CHANGES_.forEach(function(d){{const a=check.rows[d.source];a.v[0]=d.new_sku;a.v[2]=d.new_name;a.v[11]=d.new_active;const p=ss.getSheetByName('Товари');p.getRange(a.row,1).setValue(d.new_sku);p.getRange(a.row,3).setValue(d.new_name);p.getRange(a.row,12).setValue(d.new_active);}});SpreadsheetApp.flush();const after=catalogCanonicalCrmInspect_(ss);if(!after.ok||after.state!=='already_applied'||after.active!==62)throw new Error('Canonical post-check failed: '+JSON.stringify(after));return {{already_applied:false,changed:CATALOG_CANONICAL_CRM_CHANGES_.length,active:after.active,inactive:after.inactive}}; }}
function catalogCanonicalCrmPreview() {{ const c=catalogCanonicalCrmInspect_(_getCrmSs());return catalogCanonicalCrmLog_({{ok:c.ok,action:'catalog_canonical_crm_preview',state:c.state,problems:c.problems,active:c.active,inactive:c.inactive,article_changes:3,name_changes:39,status_changes:3}}); }}
function catalogCanonicalCrmIntegrityCheck() {{ const result=apiIntegrityCheck_();console.log('CATALOG_CANONICAL_CRM_INTEGRITY '+JSON.stringify(result));return result; }}
function catalogCanonicalCrmRehearsal() {{ const live=_getCrmSs(),before=catalogCanonicalCrmFingerprint_(live),c=catalogCanonicalCrmInspect_(live);if(!c.ok||c.state!=='ready')return catalogCanonicalCrmLog_({{ok:false,action:'catalog_canonical_crm_rehearsal',state:'blocked_live_preflight',preview:c}});const stamp=Utilities.formatDate(new Date(),Session.getScriptTimeZone(),'yyyyMMdd-HHmmss'),file=DriveApp.getFileById(live.getId()).makeCopy(live.getName()+' DRYRUN-3DP-CANONICAL-'+stamp);try{{const copy=SpreadsheetApp.openById(file.getId()),relatedBefore=catalogCanonicalCrmRelated_(copy),result=catalogCanonicalCrmApplyTo_(copy),relatedAfter=catalogCanonicalCrmRelated_(copy);if(JSON.stringify(relatedBefore)!==JSON.stringify(relatedAfter))throw new Error('Related business sheets changed in rehearsal.');if(catalogCanonicalCrmFingerprint_(live)!==before)throw new Error('Live CRM changed during rehearsal.');PropertiesService.getScriptProperties().setProperties({{[CATALOG_CANONICAL_CRM_FP_KEY_]:before,[CATALOG_CANONICAL_CRM_AT_KEY_]:String(new Date().getTime())}});file.setTrashed(true);return catalogCanonicalCrmLog_({{ok:true,action:'catalog_canonical_crm_rehearsal',state:'rehearsal_passed',live_unchanged:true,rehearsal_copy_trashed:true,fingerprint:before,result:result}});}}catch(e){{return catalogCanonicalCrmLog_({{ok:false,action:'catalog_canonical_crm_rehearsal',state:'rehearsal_failed',live_unchanged:catalogCanonicalCrmFingerprint_(live)===before,rehearsal_file_id:file.getId(),error:String(e&&e.message?e.message:e)}});}} }}
function catalogCanonicalCrmApply() {{ const lock=LockService.getScriptLock();if(!lock.tryLock(30000))throw new Error('CRM busy, retry later');try{{const ss=_getCrmSs(),p=PropertiesService.getScriptProperties(),fp=String(p.getProperty(CATALOG_CANONICAL_CRM_FP_KEY_)||''),at=Number(p.getProperty(CATALOG_CANONICAL_CRM_AT_KEY_)||0);if(!fp||new Date().getTime()-at>CATALOG_CANONICAL_CRM_MAX_AGE_MS_||catalogCanonicalCrmFingerprint_(ss)!==fp)return catalogCanonicalCrmLog_({{ok:false,action:'catalog_canonical_crm_apply',state:'blocked_fresh_rehearsal_required'}});const backup=DriveApp.getFileById(ss.getId()).makeCopy(ss.getName()+' PRE-3DP-CANONICAL-'+Utilities.formatDate(new Date(),Session.getScriptTimeZone(),'yyyyMMdd-HHmmss'));try{{const result=catalogCanonicalCrmApplyTo_(ss);invalidateDoGetCache_();p.deleteProperty(CATALOG_CANONICAL_CRM_FP_KEY_);p.deleteProperty(CATALOG_CANONICAL_CRM_AT_KEY_);return catalogCanonicalCrmLog_({{ok:true,action:'catalog_canonical_crm_apply',state:'applied',backup_file_id:backup.getId(),result:result}});}}catch(e){{return catalogCanonicalCrmLog_({{ok:false,action:'catalog_canonical_crm_apply',state:'apply_failed_backup_preserved',backup_file_id:backup.getId(),error:String(e&&e.message?e.message:e)}});}}}}finally{{lock.releaseLock();}} }}
'''
    OUTPUT.write_text(source, encoding="utf-8")
    print(json.dumps({"ok": True, "records": 72, "changes": len(changes), "output": str(OUTPUT)}, ensure_ascii=False))


if __name__ == "__main__":
    main()
