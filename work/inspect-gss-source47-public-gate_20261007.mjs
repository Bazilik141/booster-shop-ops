// Public-only refusal localization. Never prints error messages or raw values.
import {assertFixedCommandProcess} from './GSS_command-ledger47_20261006/command-process-fences.mjs';
import {assertHistoricalCommandParents,currentJournalLineage} from './GSS_command-ledger47_20261006/historical-parent-gate.mjs';
import {assertCompleteCommandReview,assertCompleteCommandInputs} from './GSS_command-ledger47_20261006/source-contract.mjs';
import fs from 'node:fs';
const manifest=JSON.parse(fs.readFileSync('diagnostics/GSS_foundation-command-complete-source47_revision-source-manifest_20261006.json'));
for (const [stage,fn] of [['process',assertFixedCommandProcess],['journal',currentJournalLineage],['parents',assertHistoricalCommandParents],['inputs',()=>assertCompleteCommandInputs(manifest)],['review',assertCompleteCommandReview]]) {
  try { fn(); console.log(JSON.stringify({stage,passed:true})); }
  catch(e) { const locations=String(e.stack).split('\n').slice(1).map(line=>line.match(/(?:file:\/\/\/[^ )]+\.mjs):(\d+):(\d+)/)?.[0]).filter(Boolean);console.log(JSON.stringify({stage,passed:false,locations,valuesWithheld:true})); }
}
