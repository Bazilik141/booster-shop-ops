// Additive public-source admission only. No database, private files or native run.
import fs from 'node:fs';
import assert from 'node:assert/strict';
import {createHash} from 'node:crypto';
import {assertCompleteCommandReview,assertCompleteCommandInputs} from './GSS_command-ledger47_20261006/source-contract.mjs';
const h=b=>createHash('sha256').update(b).digest('hex').toUpperCase();
let stage='argument';
try {
  assert.equal(process.argv.length,2);
  const base='diagnostics/GSS_foundation-command-complete-source47';
  const mb=fs.readFileSync(base+'_revision-source-manifest_20261006.json');
  assert.equal(h(mb),'FB022E5EA7089378F95D7B0FAC93B063BAA182A72D6BD9DA9886CEA218FADBB5');
  const manifest=JSON.parse(mb);stage='complete_review';
  const gate=assertCompleteCommandReview();stage='complete_inputs';
  const inputs=assertCompleteCommandInputs(manifest);stage='lineage';
  assert.equal(gate.historicalParents.attempts,2);
  assert.equal(inputs.sourceInputsByteVerified,1819);
  const wp='diagnostics/GSS_foundation-command-gate47-pre-dispatch_20261006.json';
  stage='predispatch_witness';
  const witness=JSON.parse(fs.readFileSync(wp));
  const review=JSON.parse(fs.readFileSync(base+'_claude-review-run_20261006.json'));
  assert.equal(witness.passed,true);assert.equal(witness.cases.length,15);
  assert.equal(witness.manifestSha256,h(mb));assert.equal(witness.bundleSha256,manifest.inputSha256);
  assert.equal(review.preDispatchGate.actualWitnessSha256,h(fs.readFileSync(wp)));
  assert.equal(review.nativePhase1Authorized,true);
  stage='absence';const absence=Object.fromEntries([
    'diagnostics/GSS_foundation-command-phase1-original47_20261006.json',
    'diagnostics/GSS_foundation-command-phase1-original47_20261006.txt',
    'diagnostics/GSS_foundation-command-phase1-original47_failure_20261006.json',
    'diagnostics/GSS_foundation-command-phase1-original47_invocation-reserved_20261006.json',
    'diagnostics/GSS_foundation-command-apply47_invocation-reserved_20261006.json'
  ].map(p=>[p,!fs.existsSync(p)]));
  assert(Object.values(absence).every(Boolean));
  console.log(JSON.stringify({passed:true,actualUtc:new Date().toISOString(),gate,inputs,preDispatchWitnessPassed:true,absence,nativeConnections:0,privateReads:0}));
} catch {
  console.error(JSON.stringify({passed:false,stage,scope:'Public source47 resumption admission only',valuesWithheld:true,nativeConnections:0,privateReads:0}));
  process.exitCode=1;
}
