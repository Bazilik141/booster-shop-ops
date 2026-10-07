// One-use D075 application reservation; consumed even if subsequent bind fails.
import fs from 'node:fs';
import {assertFreshApplyAuthority,bindConsumedInvocation,reservationPath} from './source-contract.mjs';
export function reserveApplyInvocation(approval) {
  if(arguments.length!==1)throw Error('GSS application48 invocation refused;values withheld');
  const authority=assertFreshApplyAuthority(approval);
  const record={scope:'SINGLE APPLICATION ATTEMPT3 ADAPTER48',sourceManifestSha256:approval.sourceManifestSha256,adapterManifestSha256:authority.adapterManifestSha256,applicationManifestSha256:authority.applicationManifestSha256,attemptRef:authority.attemptRef,executorPid:process.pid,operation:'apply',consumedBeforeTarget:true,consumedOnRefusal:true,automaticRetry:false,valuesWithheld:true};
  const b=Buffer.from(JSON.stringify(record,null,2)+'\n');let fd;
  try{fd=fs.openSync(reservationPath,'wx');let n=0;while(n<b.length){const q=fs.writeSync(fd,b,n,b.length-n);if(!Number.isSafeInteger(q)||q<1||q>b.length-n)throw Error('GSS reservation48 write refused');n+=q;}fs.fsyncSync(fd);fs.closeSync(fd);fd=undefined;}
  finally{if(fd!==undefined)fs.closeSync(fd);}
  bindConsumedInvocation(approval,b);return authority;
}
