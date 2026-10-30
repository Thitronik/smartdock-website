import test from 'node:test';
import assert from 'node:assert/strict';
import {createDemoState,canAccessDealerArea,isFinderVisible,decideMembership,moveMembership,prepareInvitation,saveDealer} from '../src/admin-model.js';
import {prepareDealerImport} from './prepare-dealer-import.mjs';

test('Konfigurationsübersicht zeigt Anschrift, Komponenten und Summen ohne Rohdaten oder HTML-Ausführung',async()=>{
 const {readFile}=await import('node:fs/promises');const vm=await import('node:vm');const source=await readFile(new URL('../src/admin-server.js',import.meta.url),'utf8');const context={Intl};vm.createContext(context);vm.runInContext(source.replace('export function','function')+';this.render=configurationDetails',context);
 const html=context.render({selection_json:JSON.stringify({street:'Musterstraße',houseNumber:'12',postalCode:'24340',city:'Eckernförde',country:'Deutschland',dealer:{company_name:'<img src=x onerror=alert(1)>'}}),price_snapshot_json:JSON.stringify({items:[{name:'WCU (1 Ankerwinde)',price:1250}],subtotal:1250,totalPrice:1250,vat:19,currency:'EUR'})});
 assert.match(html,/Musterstraße 12/);assert.match(html,/WCU \(1 Ankerwinde\)/);assert.match(html,/1\.250,00/);assert.match(html,/&lt;img/);assert.doesNotMatch(html,/<img|<pre|selection_json|totalPrice/);
 assert.match(context.render({selection_json:'{}'}),/kein Preisstand gespeichert/);
});

test('Kartenfreigabe und Mitarbeiterzugang sind voneinander unabhängig',()=>{
 const state=createDemoState();
 assert.equal(isFinderVisible(state.dealers[1]),false);
 assert.equal(canAccessDealerArea(state,'demo-u3','demo-d2'),true);
 state.dealers[0].finderVisible=false;
 assert.equal(canAccessDealerArea(state,'demo-u1','demo-d1'),true);
 assert.equal(canAccessDealerArea(state,'demo-u2','demo-d1'),false);
});
test('Individuelle Freigabe aktiviert weder Nachbarkonto noch Passwort',()=>{
 const state=createDemoState();
 decideMembership(state,'demo-m2','approved');
 assert.equal(state.memberships[0].status,'approved');
 assert.equal(state.users[1].status,'invited');
 assert.equal(canAccessDealerArea(state,'demo-u2','demo-d1'),false);
 prepareInvitation(state,'demo-m2');
 assert.equal(state.users[1].passwordSet,false);
 assert.equal(state.users[1].emailVerified,false);
 decideMembership(state,'demo-m1','suspended');
 assert.equal(canAccessDealerArea(state,'demo-u1','demo-d1'),false);
 assert.equal(state.memberships[1].status,'approved');
});
test('Firmeneintrag ist kein Zugang; Sperrung und fehlende E-Mail-Bestätigung verhindern Zugriff',()=>{
 const state=createDemoState();
 assert.equal(canAccessDealerArea(state,'unknown','demo-d1'),false);
 assert.throws(()=>prepareInvitation(state,'demo-m2'));
 state.users[0].emailVerified=false;assert.equal(canAccessDealerArea(state,'demo-u1','demo-d1'),false);
 state.users[0].emailVerified=true;state.users[0].status='suspended';assert.equal(canAccessDealerArea(state,'demo-u1','demo-d1'),false);
 state.users[0].status='active';state.dealers[0].status='archived';assert.equal(canAccessDealerArea(state,'demo-u1','demo-d1'),false);
});
test('Firmenwechsel verlangt neue Freigabe und betrifft nur die Zuordnung',()=>{
 const state=createDemoState();
 moveMembership(state,'demo-m1','demo-d2');
 assert.equal(state.memberships[0].status,'pending');
 assert.equal(canAccessDealerArea(state,'demo-u1','demo-d2'),false);
 assert.equal(state.memberships[2].status,'approved');
 assert.throws(()=>moveMembership(state,'demo-m1','missing'));
 state.memberships.push({id:'duplicate',userId:'demo-u1',dealerId:'demo-d3',status:'pending'});
 assert.throws(()=>moveMembership(state,'demo-m1','demo-d3'));
});
test('Firmenbearbeitung ändert keine Loginadresse und bewahrt Freigaben',()=>{
 const state=createDemoState(),dealer={...state.dealers[0],email:'neu@firma.example'};
 saveDealer(state,dealer.id,dealer);
 assert.equal(state.dealers[0].email,'neu@firma.example');
 assert.equal(state.users[0].email,'alex@marina.example');
 assert.equal(state.dealers[0].finderVisible,true);
 assert.throws(()=>saveDealer(state,dealer.id,{...dealer,company:'  '}));
 assert.throws(()=>saveDealer(state,dealer.id,{...dealer,website:'javascript:alert(1)'}));
});
const record={id:'example001',collectionName:'dealers',name:'Firma Beispiel',address:'G45 / 76–84 Test Drive, 4209 Musterort',country:'Australia',email:'kontakt@firma.example',phone:'',latitude:0,longitude:0,website:'',website2:'',created:'2026-07-10 12:56:09.512Z',updated:'2026-07-10 12:56:09.512Z'};
test('Händlerimport bewahrt Koordinaten und Anschrift und erzeugt keine Zugänge',()=>{
 const result=prepareDealerImport([record]);
 assert.equal(result.dealers[0].original_address,record.address);
 assert.equal(result.dealers[0].latitude,0);
 assert.equal(result.dealers[0].longitude,0);
 assert.equal(result.dealers[0].country_code,'AU');
 assert.equal(result.dealers[0].finder_status,'unlisted');
 assert.deepEqual(result.counts,{dealers:1,users:0,memberships:0,newsletterSubscriptions:0,invitations:0});
});
test('Import stoppt bei doppelten Quellkennungen oder unbrauchbaren Daten',()=>{
 assert.throws(()=>prepareDealerImport([record,record]));
 assert.throws(()=>prepareDealerImport([{...record,latitude:91}]));
 assert.throws(()=>prepareDealerImport([{...record,country:'Unknown'}]));
 assert.throws(()=>prepareDealerImport([{...record,website:'javascript:alert(1)'}]));
 assert.throws(()=>prepareDealerImport([{...record,updated:'invalid'}]));
});

// A different tab or an expired anonymous session can rotate the CSRF token.
test('Adminlogin aktualisiert Sitzung vor Passwortprüfung; Fehler senden keine Zugangsdaten',async()=>{
 const {readFile}=await import('node:fs/promises');const vm=await import('node:vm');
 const source=await readFile(new URL('../src/admin-server.js',import.meta.url),'utf8');
 for(const refreshFails of [false,true]){
  const calls=[];const handlers={};let sessions=0;const status={textContent:''};
  const root={innerHTML:'',addEventListener:(name,handler)=>handlers[name]=handler,removeEventListener(){},querySelector:()=>status};
  const context={document:{getElementById:()=>null},AbortController,FormData:class{constructor(){return new Map([['email','test@example.invalid'],['password','fixture-password'],['otp','123456']]);}},fetch:async(url,options)=>{
   const action=new URL(url,'https://test.smartdock.de').searchParams.get('action');calls.push({action,options});
   if(action==='session'){sessions++;return{ok:!(refreshFails&&sessions===2),status:503,json:async()=>({csrf:sessions===1?'old':'fresh',user:null,error:'Sitzung nicht erreichbar'})};}
   assert.equal(action,'login');assert.equal(options.headers['X-CSRF-Token'],'fresh');return{ok:false,status:401,json:async()=>({error:'Test: Zugangsdaten abgelehnt'})};
  }};vm.createContext(context);vm.runInContext(source.replace('export function','function')+';mountServerAdmin(root)',Object.assign(context,{root}));
  await new Promise(resolve=>setImmediate(resolve));const button={disabled:false,isConnected:true};const password={value:'fixture-password'},otp={value:'123456'};
  const form={id:'staff-login',classList:{contains:()=>false},reportValidity:()=>true,querySelector:s=>s==='button'?button:s.includes('password')?password:otp};
  await handlers.submit({target:form,preventDefault(){}});
  assert.deepEqual(calls.map(c=>c.action),refreshFails?['session','session']:['session','session','login']);
  assert.equal(password.value,'');assert.equal(otp.value,'');assert.equal(button.disabled,false);
  assert.equal(status.textContent,refreshFails?'Sitzung nicht erreichbar':'Test: Zugangsdaten abgelehnt');
 }
});

test('Händlerdatensuche findet Firmennamen, Mitarbeiter und E-Mail ohne fremde Treffer',async()=>{
 const {readFile}=await import('node:fs/promises');const vm=await import('node:vm');const source=await readFile(new URL('../src/admin-server.js',import.meta.url),'utf8');const context={Intl};vm.createContext(context);vm.runInContext(source.replace('export function','function')+';this.search=filterDealerRows',context);
 const dealers=[{id:1,company_name:'Bootswerft',public_email:'kontakt@werft.example'},{id:2,company_name:'Marina',public_email:'info@marina.example'}];const members=[{dealer_id:1,first_name:'Brigitte',last_name:'Müller',email:'brigitte@werft.example'}];
 for(const query of ['boots','BRIGITTE','müller','brigitte@werft.example'])assert.equal(context.search(dealers,members,query)[0].id,1);
 assert.equal(context.search(dealers,members,'unbekannt').length,0);assert.equal(context.search(dealers,members,'').length,2);
});
