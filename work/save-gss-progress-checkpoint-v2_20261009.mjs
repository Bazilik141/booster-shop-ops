import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';

// Public recovery bookkeeping only. Additive storage successor; no native gate.
const ROOT = 'C:\\Users\\14bez\\Downloads\\Booster Shop\\booster-shop-ops';
const WRITER = 'work/save-gss-progress-checkpoint-v2_20261009.mjs';
const DIRECTORY = 'checkpoints/GSS';
const POINTER = DIRECTORY + '/CURRENT.md';
const POLICY = 'docs/WORK_INTERRUPTION_RECOVERY.md';
const STORAGE = 'docs/GSS_CHECKPOINT_STORAGE_20261009.md';
assert.equal(process.cwd(), ROOT);
assert.equal(process.argv.length, 3);
const hash = b => createHash('sha256').update(b).digest('hex').toUpperCase();
function locate(relative) {
  assert.equal(typeof relative, 'string');
  assert(/^(?:work|docs|handoffs|diagnostics|plans|checkpoints)\/[A-Za-z0-9_.\/-]+$/.test(relative));
  assert(relative.split('/').every(x => x && x !== '.' && x !== '..'));
  assert(!/(?:^|\/)(?:\.local|\.git|\.temp|node_modules)(?:\/|$)/i.test(relative));
  return path.join(ROOT, ...relative.split('/'));
}
function read(relative) {
  const q = locate(relative), s = fs.lstatSync(q);
  assert(s.isFile() && !s.isSymbolicLink() && s.nlink === 1);
  assert.equal(fs.realpathSync.native(q), q);
  return fs.readFileSync(q);
}
function save(relative, bytes) {
  const q = locate(relative);
  assert.equal(fs.realpathSync.native(path.dirname(q)), path.dirname(q));
  const fd = fs.openSync(q, 'wx');
  try {
    let n = 0;
    while (n < bytes.length) {
      const k = fs.writeSync(fd, bytes, n, bytes.length - n);
      assert(k > 0); n += k;
    }
    fs.fsyncSync(fd);
  } finally { fs.closeSync(fd); }
  assert(read(relative).equals(bytes));
}
const statePath = process.argv[2];
assert(statePath.startsWith('work/'));
const stateBytes = read(statePath);
assert(stateBytes.length <= 16384);
const state = JSON.parse(stateBytes);
assert.deepEqual(Object.keys(state).sort(), ['artifactPaths', 'completed', 'inProgress', 'next', 'scope'].sort());
for (const k of ['scope', 'inProgress', 'next'])
  assert(typeof state[k] === 'string' && state[k].trim() && state[k].length <= 3000);
assert(Array.isArray(state.completed) && state.completed.length <= 20);
assert(state.completed.every(s => typeof s === 'string' && s.trim() && s.length <= 3000));
assert(Array.isArray(state.artifactPaths) && state.artifactPaths.length <= 24);
assert.equal(new Set(state.artifactPaths).size, state.artifactPaths.length);
const artifacts = state.artifactPaths.map(p => ({ path: p, sha256: hash(read(p)) }));
// Flush task-owned new files only; historical procedures/anchors remain read-only.
for (const p of [STORAGE, WRITER, statePath]) {
  read(p); const fd = fs.openSync(locate(p), 'r+');
  try { fs.fsyncSync(fd); } finally { fs.closeSync(fd); }
}
const acceptedPath = 'handoffs/GSS_foundation_READ51_REVIEWED_checkpoint_20261009.md';
const acceptedReceiptPath = 'diagnostics/GSS_foundation-read51_reviewed-result_receipt_20261009.json';
const acceptedBytes = read(acceptedPath), acceptedReceiptBytes = read(acceptedReceiptPath);
assert.equal(hash(acceptedBytes), 'A2B691BCF69FA9EF9512A3CD1F57236DE9A1D0027C77FB03377DC29914A7BB7D');
assert.equal(hash(acceptedReceiptBytes), 'E94DFAF218F1383F77F8D8DAAD7A9CE12945698FFABD21C85075B68008EC0AAA');
const acceptedReceipt = JSON.parse(acceptedReceiptBytes);
assert.equal(acceptedReceipt.checkpoint.sha256, hash(acceptedBytes));
assert.equal(acceptedReceipt.actualClaudeReview.verdict, 'Review OK');
assert.equal(acceptedReceipt.grantConsumed, true);
assert.equal(acceptedReceipt.nativeOperationOnResumeAuthorized, false);
const oldPointer = read(POINTER);
assert(oldPointer.toString('utf8').includes('GSS_foundation_'));
const actualUtc = new Date().toISOString(), stamp = actualUtc.replace(/[-:.]/g, '');
const base = DIRECTORY + '/GSS_foundation_PROGRESS_' + stamp;
const snapshotPath = base + '.md', previousPath = base + '_previous-pointer.md';
const receiptPath = DIRECTORY + '/GSS_foundation-progress_checkpoint_' + stamp + '.json';
const md = Buffer.from(`# GSS Foundation progress recovery snapshot\n\nUTC: ${actualUtc}. Executor-authored PROGRESS ONLY; no new acceptance/grant.\n\nContinuous recovery: ${POLICY}\nPolicy SHA256: ${hash(read(POLICY))}\nCurrent storage amendment: ${STORAGE}\nStorage SHA256: ${hash(read(STORAGE))}\nAll artifact paths resolve from the repository root. Continue in this same conversation.\n\nScope: ${state.scope}\n\nCompleted:\n${state.completed.map(s => '- ' + s).join('\n')}\n\nIn progress: ${state.inProgress}\n\nNext bounded action: ${state.next}\n\nSaved public artifacts:\n${artifacts.map(p => '- ' + p.path + '; SHA256 ' + p.sha256).join('\n')}\n\nLast accepted native/result checkpoint: ${acceptedPath}\nSHA256: ${hash(acceptedBytes)}\nAccepted receipt: ${acceptedReceiptPath}\nSHA256: ${hash(acceptedReceiptBytes)}\nRead the full authority/review/no-repeat limits. Read51 is complete; grant consumed. Never replay read51/CR11/D081/D082. No native action on resume is authorized here.\n\nPrevious pointer snapshot: ${previousPath}\nRead current AGENTS.md/GSS rules and retained operation evidence before dependent work. Verify actual dispatch/result/journal before treating a launched operation as complete or restarting it. Keep incomplete work explicitly incomplete. Local-disk recovery bookkeeping is not a remote backup or zero-loss guarantee.\n`);
save(previousPath, oldPointer);
save(snapshotPath, md);
const record = {
  scope: 'PUBLIC GSS PROGRESS RECOVERY ONLY', actualUtc,
  state: { path: statePath, sha256: hash(stateBytes) },
  policy: { path: POLICY, sha256: hash(read(POLICY)) },
  storage: { path: STORAGE, sha256: hash(read(STORAGE)) },
  snapshot: { path: snapshotPath, sha256: hash(md) },
  previousPointer: { path: previousPath, sha256: hash(oldPointer) },
  lastAcceptedCheckpoint: { path: acceptedPath, sha256: hash(acceptedBytes) },
  lastAcceptedReceipt: { path: acceptedReceiptPath, sha256: hash(acceptedReceiptBytes) },
  artifacts, writer: { path: WRITER, sha256: hash(read(WRITER)) },
  progressOnly: true, newAcceptanceClaimed: false, newNativeOperation: false,
  nativeOperationOnResumeAuthorized: false, originalGrantConsumed: true,
  automaticReplay: false, checkpointWriteFsyncReadback: true,
  remoteBackupClaimed: false
};
save(receiptPath, Buffer.from(JSON.stringify(record, null, 2) + '\n'));
const pointer = Buffer.from(`# GSS Foundation current resume pointer\n\nUTC: ${actualUtc}\n\nRead ${snapshotPath}\nCheckpoint SHA256: ${hash(md)}\nReceipt: ${receiptPath}\nReceipt SHA256: ${hash(read(receiptPath))}\n\nCurrent storage: ${STORAGE}\nContinuous recovery: ${POLICY}\nLast accepted result: ${acceptedPath}\nState: read51 PASSED; actual Claude RESULT Review OK; grant consumed.\nThis latest snapshot is progress only; read the accepted result and authority limits.\nContinue in this same conversation. Never repeat read51/CR11/D081/D082. No new native authority.\n`);
const temp = base + '_pointer.tmp';
save(temp, pointer);
assert(read(POINTER).equals(oldPointer));
fs.renameSync(locate(temp), locate(POINTER));
assert(read(POINTER).equals(pointer));
console.log(JSON.stringify({ passed: true, snapshotPath, receiptPath, pointerPath: POINTER,
  progressOnly: true, checkpointWriteFsyncReadback: true,
  artifactsVerified: artifacts.length, nativeOperation: false }));
