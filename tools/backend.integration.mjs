import assert from 'node:assert/strict';
import {execFileSync,spawn} from 'node:child_process';
import {mkdtemp,rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import path from 'node:path';
import {createHmac} from 'node:crypto';
const state=await mkdtemp(path.join(tmpdir(),'sd-backend-'));
const php=process.env.PHP_BINARY||'php';
const env={...process.env,SD_BACKEND_TEST_RUN:'1',SD_TEST_STATE:state};
let server;
try{
 const initial=JSON.parse(execFileSync(php,['backend/tests/fixture.php'],{env,encoding:'utf8'}));
 server=spawn(php,['-S','127.0.0.1:9473','backend/tests/router.php'],{env,stdio:['ignore','ignore','pipe']});
 let errors='';server.stderr.on('data',b=>errors+=b);
 const base='http://127.0.0.1:9473/?action=';
 for(let i=0;i<50;i++){try{await fetch(base+'session');break;}catch{if(i===49)throw Error(errors);await new Promise(r=>setTimeout(r,100));}}
 function client(){let cookie='',csrf='';return {async page(action,form){
  const response=await fetch(base+action,{method:form?'POST':'GET',headers:{Cookie:cookie,...(form?{Origin:'https://test.smartdock.de'}:{})},body:form?new URLSearchParams(form):undefined,redirect:'manual'});
  const set=response.headers.get('set-cookie');if(set)cookie=set.split(';')[0];return {status:response.status,text:await response.text(),headers:response.headers};
 },async call(action,data,options={}){
  const headers={Cookie:cookie,...options.headers};
  if(data!==undefined){headers['Content-Type']='application/json';headers.Origin='https://test.smartdock.de';headers['X-CSRF-Token']=csrf;Object.assign(headers,options.headers);}
  const response=await fetch(base+action,{method:data===undefined?'GET':'POST',headers,body:data===undefined?undefined:JSON.stringify(data)});
  const set=response.headers.get('set-cookie');if(set)cookie=set.split(';')[0];
  const body=await response.json();if(body.csrf)csrf=body.csrf;return {status:response.status,body,headers:response.headers};
 }};}
 const bootstrap=client();assert.equal((await bootstrap.page('bootstrap')).status,403);
 const setupRedirect=await bootstrap.page('bootstrap&token='+initial.bootstrapToken);
 assert.equal(setupRedirect.status,303);assert.equal(setupRedirect.headers.get('referrer-policy'),'no-referrer');
 const form=await bootstrap.page('bootstrap');assert.equal(form.status,200);
 assert.equal(form.headers.get('referrer-policy'),'same-origin');
 const secret=form.text.match(/data-testid="totp-secret"[^>]*>([A-Z2-7]+)</)[1];
 await bootstrap.page('bootstrap&token='+initial.bootstrapToken);
 const reopened=await bootstrap.page('bootstrap');assert.equal(reopened.text.match(/data-testid="totp-secret"[^>]*>([A-Z2-7]+)</)[1],secret,'Reopening the same link preserves the authenticator key');
 assert.match(form.text,/<details class="setup-secret">/);assert.doesNotMatch(form.text,/<details[^>]*\sopen/);
 const formCsrf=form.text.match(/name="csrf" value="([a-f0-9]{64})"/)[1];
 assert.equal((await bootstrap.page('bootstrap&check=1',{csrf:'wrong'})).status,403);
 const probe=await bootstrap.page('bootstrap&check=1',{csrf:formCsrf});assert.equal(probe.status,200);assert.match(probe.text,/Verbindungsprüfung erfolgreich/);
 function otp(key){const alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';let bits='';for(const char of key)bits+=alphabet.indexOf(char).toString(2).padStart(5,'0');const bytes=[];for(let i=0;i+8<=bits.length;i+=8)bytes.push(parseInt(bits.slice(i,i+8),2));const counter=Buffer.alloc(8);counter.writeBigUInt64BE(BigInt(Math.floor(Date.now()/30000)));const hash=createHmac('sha1',Buffer.from(bytes)).update(counter).digest();const offset=hash[19]&15;return String((hash.readUInt32BE(offset)&0x7fffffff)%1000000).padStart(6,'0');}
 const setup={csrf:formCsrf,first_name:'Test',last_name:'Person',password:'FictionalPassword-OnlyForCI!',repeat:'FictionalPassword-OnlyForCI!',otp:otp(secret)};
 assert.equal((await bootstrap.page('bootstrap',{...setup,csrf:'wrong'})).status,403);
 assert.equal((await bootstrap.page('bootstrap',{...setup,otp:'invalid'})).status,200);
 assert.equal((await bootstrap.page('bootstrap',{...setup,otp:otp(secret)})).status,303);
 assert.equal((await bootstrap.page('bootstrap')).status,403);
 const bootstrapReplay=client();await bootstrapReplay.page('bootstrap&token='+initial.bootstrapToken);assert.equal((await bootstrapReplay.page('bootstrap')).status,409);
 const fixture=JSON.parse(execFileSync(php,['backend/tests/fixture.php','--seed'],{env,encoding:'utf8'}));
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
 console.log('PASS: MariaDB HTTP integration: one-time first-admin setup, encrypted MFA, CSRF, session cookies, login, exact allowlist, OTP/replay, independent approvals, revocation, logout and rate limits.');
}finally{if(server){server.kill();await new Promise(resolve=>server.once('exit',resolve));}await rm(state,{recursive:true,force:true});}
