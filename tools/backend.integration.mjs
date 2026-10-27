import assert from 'node:assert/strict';
import {execFileSync,spawn} from 'node:child_process';
import {mkdtemp,rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import path from 'node:path';
const state=await mkdtemp(path.join(tmpdir(),'sd-backend-'));
const php=process.env.PHP_BINARY||'php';
const env={...process.env,SD_BACKEND_TEST_RUN:'1',SD_TEST_STATE:state};
let server;
try{
 const fixture=JSON.parse(execFileSync(php,['backend/tests/fixture.php'],{env,encoding:'utf8'}));
 server=spawn(php,['-S','127.0.0.1:9473','backend/tests/router.php'],{env,stdio:['ignore','ignore','pipe']});
 let errors='';server.stderr.on('data',b=>errors+=b);
 const base='http://127.0.0.1:9473/?action=';
 for(let i=0;i<50;i++){try{await fetch(base+'session');break;}catch{if(i===49)throw Error(errors);await new Promise(r=>setTimeout(r,100));}}
 function client(){let cookie='',csrf='';return {async call(action,data,options={}){
  const headers={Cookie:cookie,...options.headers};
  if(data!==undefined){headers['Content-Type']='application/json';headers.Origin='https://test.smartdock.de';headers['X-CSRF-Token']=csrf;Object.assign(headers,options.headers);}
  const response=await fetch(base+action,{method:data===undefined?'GET':'POST',headers,body:data===undefined?undefined:JSON.stringify(data)});
  const set=response.headers.get('set-cookie');if(set)cookie=set.split(';')[0];
  const body=await response.json();if(body.csrf)csrf=body.csrf;return {status:response.status,body,headers:response.headers};
 }};}
 const guest=client();let result=await guest.call('session');assert.equal(result.status,200);assert.equal(result.body.user,null);assert.equal(result.body.csrf.length,64);assert.match(result.headers.get('set-cookie'),/secure/i);assert.match(result.headers.get('set-cookie'),/HttpOnly/i);assert.match(result.headers.get('set-cookie'),/SameSite=Strict/i);
 assert.equal((await guest.call('admin')).status,401);
 assert.equal((await guest.call('membership-decision',{id:1,status:'approved'},{headers:{'X-CSRF-Token':'wrong'}})).status,403);
 assert.equal((await guest.call('login',{email:'admin@example.invalid',password:'wrong'})).status,401);
 assert.equal((await guest.call('login',{email:'outsider@example.invalid',password:'FictionalPassword-OnlyForCI!',otp:fixture.otp})).status,401);
 const dealer=client();await dealer.call('session');assert.equal((await dealer.call('login',{email:'dealer@example.invalid',password:'FictionalPassword-OnlyForCI!'})).status,200);
 assert.equal((await dealer.call('admin')).status,403);assert.equal((await dealer.call('dealer')).body.companies.length,1);
 const admin=client();await admin.call('session');assert.equal((await admin.call('login',{email:'admin@example.invalid',password:'FictionalPassword-OnlyForCI!',otp:'invalid'})).status,401);
 assert.equal((await admin.call('login',{email:'admin@example.invalid',password:'FictionalPassword-OnlyForCI!',otp:fixture.otp})).status,200);
 assert.equal((await admin.call('admin')).body.dealers[0].finder_status,'unlisted');
 assert.equal((await admin.call('finder-decision',{id:1,status:'approved'})).status,200);assert.equal((await dealer.call('dealer')).status,200);
 assert.equal((await admin.call('membership-decision',{id:1,status:'suspended'})).status,200);assert.equal((await dealer.call('dealer')).status,403);
 const replay=client();await replay.call('session');assert.equal((await replay.call('login',{email:'admin@example.invalid',password:'FictionalPassword-OnlyForCI!',otp:fixture.otp})).status,401);
 assert.equal((await admin.call('logout',{intent:'logout'})).status,200);assert.equal((await admin.call('admin')).status,401);
 for(let i=0;i<10;i++)assert.equal((await guest.call('login',{email:'missing@example.invalid',password:'wrong'})).status,401);
 assert.equal((await guest.call('login',{email:'missing@example.invalid',password:'wrong'})).status,429);
 console.log('PASS: MariaDB HTTP integration: CSRF, session cookies, login, exact allowlist, OTP/replay, independent approvals, revocation, logout and rate limits.');
}finally{if(server){server.kill();await new Promise(resolve=>server.once('exit',resolve));}await rm(state,{recursive:true,force:true});}
