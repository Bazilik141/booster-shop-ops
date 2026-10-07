// Pure temporary-review attestation classifier. Local operator evidence only.
// Provider/model identity is never disguised as a historical Claude CLI run.
const own=(o,k)=>{const d=o&&typeof o==='object'?Object.getOwnPropertyDescriptor(o,k):null;return d&&Object.hasOwn(d,'value')?d.value:undefined;};
export function acceptedPeer(run,expected) {
  try {
    const result=own(run,'result');if(typeof result!=='string')return false;
    const lines=result.split(/\r?\n/).filter(s=>s.trim());
    return own(run,'provider')==='OpenAI'&&own(run,'transport')==='collaboration'&&own(run,'agentName')===expected.agentName&&
      own(run,'readOnly')===true&&own(run,'completed')===true&&own(run,'provenance')==='actual subagent final response'&&
      own(run,'scope')===expected.scope&&own(run,'bundleSha256')===expected.bundleSha256&&own(run,'sourceManifestSha256')===expected.sourceManifestSha256&&
      own(run,'complete47ManifestSha256')===expected.complete47ManifestSha256&&own(run,'adapterManifestSha256')===expected.adapterManifestSha256&&
      own(run,'freshPhase1Sha256')===expected.freshPhase1Sha256&&own(run,'attemptRef')===expected.attemptRef&&
      own(run,'applicationAuthorized')===expected.applicationAuthorized&&own(run,'productApproval')===false&&
      lines[0]==='## Verdict: Review OK'&&lines.filter(s=>/^## Verdict:/i.test(s)).length===1&&
      lines.filter(s=>s===expected.scopeHeading).length===1;
  }catch{return false;}
}
