/*
 * 3D-P-023 — render CRM sync-journal timestamps in Kyiv time
 *
 * Target: main CRM Apps Script Code.gs, V92 baseline with 3D-P-014 journal.
 * This is a paste patch, not a standalone executable. It does not rewrite any
 * journal cell: Google Sheets retains the date value, so sorting and searching
 * by the timestamp remain available.
 *
 * Preflight in the live CRM project:
 * 1. Create a named Apps Script version for rollback.
 * 2. Confirm each OLD anchor below occurs exactly once.
 * 3. Apply both replacements, publish a new Web App version, then re-export
 *    Code.gs to crm/apps-script/Code.gs.
 */

// 1. OLD anchor in apiSyncJournal_:
// timestamp_kyiv: row[0],

// Replace it with:
timestamp_kyiv: crm3dpJournalTimestampKyiv_(row[0]),

// 2. Add this helper immediately after CRM_3DP_SYNC_JOURNAL_DETAIL_MAX_LENGTH_.
function crm3dpJournalTimestampKyiv_(value) {
  if (Object.prototype.toString.call(value) === '[object Date]' && !isNaN(value.getTime())) {
    return Utilities.formatDate(value, CRM_3DP_SYNC_JOURNAL_TIMEZONE_, 'yyyy-MM-dd HH:mm:ss');
  }
  return String(value || '').trim();
}

/*
 * Result: a sheet date stored as 2026-08-08T12:47:22.000Z is returned to the
 * dashboard as the Kyiv display string 2026-08-08 15:47:22. Existing text
 * values are returned unchanged. No journal schema, retention, or search
 * behaviour changes.
 *
 * Rollback: restore `timestamp_kyiv: row[0],` and remove the helper, then
 * publish a new Web App version. No Sheet data needs restoration.
 */
