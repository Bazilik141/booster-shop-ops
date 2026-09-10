/**
 * CRM-011 one-time data setup. Paste this file beside the main Code.gs, run
 * crm011ImportFollowupData20260908(), save the returned JSON, then delete this
 * temporary file from the live Apps Script project.
 *
 * Safe properties:
 * - preflights every tracking/date conflict before any data write;
 * - appends the fiscal header only at the far right;
 * - imports ZenMarket payments idempotently by Payment ID;
 * - writes arrival dates only into blank cells for exact normalized tracks;
 * - reports absent tracks without inventing or changing unmatched purchase rows;
 * - runs the same read-only integrity check before and after.
 */
const CRM011_ZENMARKET_RATE_ = 3.2;
const CRM011_ZENMARKET_PAYMENTS_ = [["4UU7EWP1L6XG","02.09.2026 03:45:31",26000,"Stripe"],["4UU79QH7OP5D","02.09.2026 04:29:55",1600,"Stripe"],["4UU79QCKIGWE","02.09.2026 04:29:27",45000,"Stripe"],["4UU79Q3WKSDU","02.09.2026 04:28:35",10000,"Stripe"],["4UU6N53HELB1","31.08.2026 03:19:00",10600,"Stripe"],["4UU59B6KJEK9","26.08.2026 02:50:43",1000,"Stripe"],["4UU59AR8TBS0","26.08.2026 02:49:11",15000,"Stripe"],["4UU59AHDBAUR","26.08.2026 02:48:11",3200,"Stripe"],["4UU59A4DYWSI","26.08.2026 02:46:53",11200,"Stripe"],["4UU35KEVQ87Q","19.08.2026 05:58:05",100,"Stripe"],["4UU35K41N517","19.08.2026 05:57:00",2500,"Stripe"],["4UU1WATCUVE9","15.08.2026 03:25:57",50000,"Stripe"],["4UU1WAOJAZRH","15.08.2026 03:25:28",1000,"Stripe"],["4UU1WAJ9N3S7","15.08.2026 03:24:56",1000,"Stripe"],["4UTYAN5POQVR","03.08.2026 09:11:50",1250,"Stripe"],["4UTYAMSK23B8","03.08.2026 09:10:30",5350,"Stripe"],["4UTX07B1CJJF","30.07.2026 04:06:24",2000,"Stripe"],["4UTX072ULFS2","30.07.2026 04:05:35",16000,"Stripe"],["4UTX06GUQCYU","30.07.2026 04:03:22",3600,"Stripe"],["4UTX05HH71PQ","30.07.2026 03:59:48",62000,"Stripe"],["4UTOZQYLGKCQ","04.07.2026 12:12:18",4700,"Stripe"],["4UTNSPM8UAJC","30.06.2026 02:31:20",6000,"Stripe"],["4UTNI2KP8MZ2","29.06.2026 03:21:40",2600,"Stripe"],["4UTM9NKYTCD5","25.06.2026 02:40:34",3000,"Stripe"],["4UTL7RPHM2QH","22.06.2026 04:12:28",48000,"Stripe"],["4UTKFE5SNQUQ","19.06.2026 02:26:20",3000,"Stripe"],["4UTJIAYD7VQT","16.06.2026 02:24:40",1400,"Stripe"],["4UTJIAF7QVCH","16.06.2026 02:22:44",1850,"Stripe"],["4UTJIA3DL0LN","16.06.2026 02:21:32",2600,"Stripe"],["4UTJI9AX6V6T","16.06.2026 02:18:40",13200,"Stripe"],["4UTI0KXUKOXF","11.06.2026 05:28:07",700,"Stripe"],["4UTHQ2B7OFH2","10.06.2026 06:34:28",31000,"Stripe"],["4UTDJXL2GMVN","28.05.2026 03:46:17",10200,"Stripe"],["4UTDH7HXABP6","27.05.2026 09:50:25",11500,"Stripe"],["4UTDE2WTAFHA","27.05.2026 03:01:57",1000,"Stripe"],["4UTC5P4VAJ1S","23.05.2026 02:25:16",500,"Stripe"],["4UTC5OVD52ZV","23.05.2026 02:24:19",4700,"Stripe"],["4UTBLRD226MZ","21.05.2026 07:01:13",62000,"Stripe"],["4UT8U4OV77OE","12.05.2026 06:08:52",100000,"Stripe"],["4UT31N9WCK7T","23.04.2026 08:19:27",2700,"Stripe"],["4UT2YU5TWSPR","23.04.2026 02:12:37",3800,"Stripe"],["4UT0UKXG4K8R","16.04.2026 04:12:59",430,"Stripe"],["4UT0UKPAAVCR","16.04.2026 04:12:10",3000,"Stripe"],["4USZBOYRKVLI","11.04.2026 04:44:15",47000,"Stripe"],["4USZ05R4P60T","10.04.2026 03:37:52",8000,"Stripe"],["4USXJGQECC6U","05.04.2026 08:55:32",6000,"Stripe"],["4USW8JE5WZKQ","01.04.2026 02:46:40",10000,"Stripe"],["4USVXPMT4OKD","31.03.2026 03:12:38",34000,"Stripe"],["4USUEYY046EQ","26.03.2026 04:03:01",31000,"Stripe"],["4USUEYK3AMXX","26.03.2026 04:01:37",14000,"Stripe"],["4USSKUUHS4FA","20.03.2026 04:08:06",46000,"Stripe"],["4USSKUK678Z3","20.03.2026 04:07:04",3900,"Stripe"],["4USR3Z0FZ0ZG","15.03.2026 09:01:03",11000,"Stripe"],["4USR1XXVW5NZ","15.03.2026 04:35:57",15000,"Stripe"],["4USIRP9FUR1K","16.02.2026 03:23:35",4500,"Stripe"],["4USHJNUTTCHP","12.02.2026 03:31:47",5000,"Stripe"],["4USHJNI09LO8","12.02.2026 03:30:30",2300,"Stripe"],["4USFPPM91ABE","06.02.2026 03:58:07",11500,"Stripe"],["4USE89HRK6IM","01.02.2026 07:37:27",12000,"Stripe"],["4USE893XSCDP","01.02.2026 07:36:03",1520,"Stripe"],["4USD15DSNWRE","28.01.2026 09:46:26",1900,"Stripe"],["4USD12XS2MUJ","28.01.2026 09:37:34",1000,"Stripe"],["4USD0ZYHWW8K","28.01.2026 09:26:45",500,"Stripe"],["4USD0WOECPO5","28.01.2026 09:14:51",1500,"Stripe"],["4USCPY4ULTU7","27.01.2026 09:23:28",8000,"Stripe"],["4US7BS5QSS2Y","10.01.2026 06:44:03",1000,"Stripe"],["4US7BQY24X1B","10.01.2026 06:39:38",7100,"Stripe"],["4URR7KZRSB73","18.11.2025 03:46:01",2400,"Stripe"],["4URR7KQMN03Y","18.11.2025 03:45:06",13400,"Stripe"],["4URQ14EC8J5B","14.11.2025 07:20:19",14300,"Stripe"],["4URL5NW0O2X1","29.10.2025 09:24:13",2430,"Stripe"],["4URJXDW1IKAN","25.10.2025 09:01:14",200,"Stripe"],["4URJXDNSPLQV","25.10.2025 09:00:24",800,"Stripe"],["4URJXD2YCFNJ","25.10.2025 08:58:18",3300,"Stripe"],["4URIXOPCPLQ6","22.10.2025 03:18:37",1500,"Stripe"],["4URG6NLEMHNT","13.10.2025 03:44:30",10550,"Stripe"],["4UR9I0FNC0G7","21.09.2025 07:54:49",3310,"Stripe"],["4UR97W086I2K","20.09.2025 09:52:41",1000,"Stripe"],["4UR57W3JFLAO","07.09.2025 08:25:37",4900,"Stripe"],["4UR4XHZQ1HYN","06.09.2025 09:48:22",1112,"Stripe"],["4UR4B8N5E92O","04.09.2025 09:21:06",600,"Stripe"],["4UR4B5W2T63M","04.09.2025 09:11:07",1700,"Stripe"],["4UR30W9JCMWA","31.08.2025 04:28:16",4400,"Stripe"],["4UR1K8NBZ16K","26.08.2025 09:51:01",3000,"Stripe"],["4UQYQQC6GNPA","17.08.2025 04:53:18",700,"Stripe"],["4UQTJ7R1AQIL","31.07.2025 04:42:22",4400,"Stripe"],["4UQSVQQJND99","29.07.2025 01:36:41",600,"Stripe"],["4UQSLW9KE36H","28.07.2025 04:10:40",3300,"Stripe"],["4UQQHP5K3UY1","21.07.2025 06:18:44",1000,"Stripe"],["4UQQHOR6S7A1","21.07.2025 06:17:17",2500,"Stripe"],["4UQQ8C4XVD9Z","20.07.2025 09:56:03",2500,"Stripe"],["4UQQ8ADMFYVR","20.07.2025 09:49:40",17000,"Stripe"],["4UQODJD4AYO2","14.07.2025 08:31:36",900,"Stripe"],["4UQOBTTW3DK3","14.07.2025 04:48:22",3500,"Stripe"],["4UQO051XE3QV","13.07.2025 03:21:48",4100,"Stripe"],["4UQN6EBUAUY7","10.07.2025 10:37:14",15000,"Stripe"],["4UQMSNDOF60Y","09.07.2025 04:41:35",3140,"Stripe"],["4UQLX3EDF5ZJ","06.07.2025 08:00:16",545,"Stripe"],["4UQLWCLYS388","06.07.2025 06:23:05",325,"Stripe"],["4UQL1VOF4WIB","04.07.2025 12:03:27",2100,"Stripe"],["4UQL1VHZ21ET","04.07.2025 12:02:48",620,"Stripe"],["4UQKZZPG6JOI","03.07.2025 07:56:51",5150,"Stripe"],["4UQKYZQLX7L1","03.07.2025 05:46:22",1400,"Stripe"],["4UQKPKFU82QF","02.07.2025 09:15:24",3000,"Stripe"],["4UQJRBV64IUF","29.06.2025 06:43:39",4200,"Stripe"],["4UQIT6A12V3V","26.06.2025 04:22:45",1900,"Stripe"],["4UQIL2C4RDXK","25.06.2025 10:43:35",2000,"Stripe"],["4UQIIQVKPZ5B","25.06.2025 05:40:48",7500,"Stripe"]];
const CRM011_ARRIVALS_ = {
  LX328664635JP:'2026-09-04', LX328130128JP:'2026-08-28',
  LX327618957JP:'2026-08-24', LX327166506JP:'2026-08-17',
  LX324338414JP:'2026-07-19', LX323855788JP:'2026-07-14',
  LX323522913JP:'2026-07-09', LX322989939JP:'2026-07-09',
  LX323224044JP:'2026-07-05', LX322612008JP:'2026-07-02',
  LX322066112JP:'2026-06-27', LX321095894JP:'2026-06-15',
  LX320653384JP:'2026-06-11', LX319422318JP:'2026-06-04',
  LX319755939JP:'2026-05-24', ECZEN71202256432:'2026-05-22',
  LX317580024JP:'2026-05-08', LX316494995JP:'2026-04-27',
  LX316339015JP:'2026-04-14', LX315072863JP:'2026-04-11',
  LX314846403JP:'2026-04-01'
};

function crm011ImportFollowupData20260908() {
  const lock = LockService.getScriptLock();
  lock.waitLock(30000);
  try {
    const before = apiIntegrityCheck_();
    if (!before.clean) throw new Error('PRE_INTEGRITY_NOT_CLEAN: ' + JSON.stringify(before.problems || []));
    const ss = _getCrmSs(), sales = ss.getSheetByName('Продажі'), purchases = ss.getSheetByName('Закупки');
    if (!sales || !purchases) throw new Error('CRM_SHEET_MISSING');

    const salesHeaders = sales.getRange(2, 1, 1, sales.getLastColumn()).getDisplayValues()[0].map(String);
    let fiscalColumn = salesHeaders.indexOf('Фіскальний чек') + 1;
    let fiscalHeaderAdded = false;
    if (!fiscalColumn) {
      fiscalColumn = sales.getLastColumn() + 1;
      fiscalHeaderAdded = true;
    }

    const trackRows = purchases.getLastRow() < 3 ? [] : purchases.getRange(3, 3, purchases.getLastRow() - 2, 2).getValues();
    const hits = {}, writes = [], conflicts = [];
    trackRows.forEach(function(row, index) {
      const key = String(row[0] || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
      if (!CRM011_ARRIVALS_[key]) return;
      hits[key] = (hits[key] || 0) + 1;
      const wanted = new Date(CRM011_ARRIVALS_[key] + 'T12:00:00');
      const current = row[1];
      if (current) {
        const currentKey = Utilities.formatDate(new Date(current), Session.getScriptTimeZone(), 'yyyy-MM-dd');
        if (currentKey !== CRM011_ARRIVALS_[key]) conflicts.push({ row: index + 3, track: key, current: currentKey, wanted: CRM011_ARRIVALS_[key] });
      } else writes.push({ row: index + 3, date: wanted });
    });
    const missingTracks = Object.keys(CRM011_ARRIVALS_).filter(function(key) { return !hits[key]; });
    if (conflicts.length) throw new Error('ARRIVAL_DATE_CONFLICT: ' + JSON.stringify(conflicts));
    if (missingTracks.length) console.warn('ARRIVAL_TRACK_NOT_FOUND: ' + missingTracks.join(','));

    if (fiscalHeaderAdded) sales.getRange(2, fiscalColumn).setValue('Фіскальний чек');

    let topups = ss.getSheetByName('ZenMarket_Поповнення');
    if (!topups) topups = ss.insertSheet('ZenMarket_Поповнення');
    const headers = ['Payment ID','Дата','Сума JPY','Курс JPY за 1 UAH','Сума UAH','Gateway','Джерело оцінки'];
    if (!topups.getRange(1, 1).getDisplayValue()) topups.getRange(1, 1, 1, headers.length).setValues([headers]);
    const actualHeaders = topups.getRange(1, 1, 1, headers.length).getDisplayValues()[0];
    if (JSON.stringify(actualHeaders) !== JSON.stringify(headers)) throw new Error('ZENMARKET_HEADER_MISMATCH');

    const existing = {};
    if (topups.getLastRow() >= 2) topups.getRange(2, 1, topups.getLastRow() - 1, 1).getDisplayValues().forEach(function(row) { if (row[0]) existing[String(row[0])] = true; });
    const newTopups = CRM011_ZENMARKET_PAYMENTS_.filter(function(row) { return !existing[String(row[0])]; }).map(function(row) {
      const match = String(row[1]).match(/^(\d{2})\.(\d{2})\.(\d{4}) (\d{2}):(\d{2}):(\d{2})$/);
      if (!match) throw new Error('ZENMARKET_DATE_INVALID: ' + row[1]);
      const date = new Date(+match[3], +match[2] - 1, +match[1], +match[4], +match[5], +match[6]);
      return [row[0], date, row[2], CRM011_ZENMARKET_RATE_, Math.round(row[2] / CRM011_ZENMARKET_RATE_ * 100) / 100, row[3], 'approx_rate_3.2'];
    });

    if (newTopups.length) topups.getRange(topups.getLastRow() + 1, 1, newTopups.length, headers.length).setValues(newTopups);
    writes.forEach(function(item) { purchases.getRange(item.row, 4).setValue(item.date); });
    SpreadsheetApp.flush();
    const after = apiIntegrityCheck_();
    if (!after.clean || JSON.stringify(after.problems || []) !== JSON.stringify(before.problems || [])) throw new Error('POST_INTEGRITY_FAILED: ' + JSON.stringify(after.problems || []));
    invalidateDoGetCache_();
    const result = { ok:true, fiscal_header_added:fiscalHeaderAdded, fiscal_column:fiscalColumn, zenmarket_imported:newTopups.length, zenmarket_total:CRM011_ZENMARKET_PAYMENTS_.length, arrival_cells_written:writes.length, arrival_tracks_matched:Object.keys(hits).length, arrival_tracks_missing:missingTracks, integrity_before:before, integrity_after:after };
    console.log(JSON.stringify(result));
    return result;
  } finally {
    lock.releaseLock();
  }
}
