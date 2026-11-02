// Ausschließlich öffentlich verwendbare Beispieldaten. Keine Authentifizierung.
export const contentAreas = ['News & Termine','Werkstattunterlagen','Einbaurichtzeiten','Schulungen & Videos','Beratung & Unterstützung','Bestellen & Herunterladen'];
export function createDemoState() {
 return {
  dealers:[
   {id:'demo-d1',company:'Muster Marina GmbH',street:'Beispielweg',houseNumber:'12',postalCode:'24100',city:'Musterhafen',countryCode:'DE',email:'kontakt@marina.example',phone:'',website:'',finderVisible:true,status:'active'},
   {id:'demo-d2',company:'Küstenboot Service',street:'Teststraße',houseNumber:'8',postalCode:'24000',city:'Beispielort',countryCode:'DE',email:'info@kuestenboot.example',phone:'',website:'',finderVisible:false,status:'active'},
   {id:'demo-d3',company:'Hafenwerft Beispiel',street:'Musterkai',houseNumber:'3',postalCode:'25000',city:'Testhafen',countryCode:'DE',email:'hallo@werft.example',phone:'',website:'',finderVisible:false,status:'active'}
  ],
  users:[
   {id:'demo-u1',firstName:'Alex',lastName:'Beispiel',email:'alex@marina.example',status:'active',emailVerified:true,passwordSet:true},
   {id:'demo-u2',firstName:'Kim',lastName:'Muster',email:'kim@marina.example',status:'invited',emailVerified:false,passwordSet:false},
   {id:'demo-u3',firstName:'Sam',lastName:'Test',email:'sam@kuestenboot.example',status:'active',emailVerified:true,passwordSet:true},
   {id:'demo-u4',firstName:'Robin',lastName:'Beispiel',email:'robin@werft.example',status:'invited',emailVerified:false,passwordSet:false}
  ],
  memberships:[
   {id:'demo-m1',dealerId:'demo-d1',userId:'demo-u1',status:'approved'},
   {id:'demo-m2',dealerId:'demo-d1',userId:'demo-u2',status:'pending'},
   {id:'demo-m3',dealerId:'demo-d2',userId:'demo-u3',status:'approved'},
   {id:'demo-m4',dealerId:'demo-d3',userId:'demo-u4',status:'pending'}
  ],
  content:contentAreas.map((title,i)=>({id:'area-'+i,title,summary:'Inhalte werden vorbereitet.',status:'draft'})),events:[]
 };
}
export function canAccessDealerArea(state,userId,dealerId) {
 const user=state.users.find(u=>u.id===userId),dealer=state.dealers.find(d=>d.id===dealerId);
 return !!(user?.status==='active'&&user.emailVerified&&user.passwordSet&&dealer?.status==='active'&&state.memberships.some(m=>m.userId===userId&&m.dealerId===dealerId&&m.status==='approved'));
}
export function isFinderVisible(dealer){return dealer.status==='active'&&dealer.finderVisible===true;}
export function recordEvent(state,message){state.events.unshift({message,time:new Date().toISOString()});}
export function decideMembership(state,id,status){
 if(!['pending','approved','rejected','suspended'].includes(status))throw Error('Unbekannter Freigabestatus.');
 const membership=state.memberships.find(m=>m.id===id);if(!membership)throw Error('Zuordnung nicht gefunden.');
 membership.status=status;
 recordEvent(state,'Mitarbeiterfreigabe in Vorschau: '+status);
}
export function moveMembership(state,id,dealerId){
 const membership=state.memberships.find(m=>m.id===id);const dealer=state.dealers.find(d=>d.id===dealerId&&d.status==='active');
 if(!membership||!dealer)throw Error('Firma oder Zuordnung nicht gefunden.');
 if(membership.dealerId===dealerId)return;
 if(state.memberships.some(m=>m.id!==id&&m.userId===membership.userId&&m.dealerId===dealerId))throw Error('Diese Firmenzuordnung besteht bereits.');
 membership.dealerId=dealerId;membership.status='pending';
 recordEvent(state,'Firma geändert; Mitarbeiterfreigabe muss erneut geprüft werden.');
}
export function prepareInvitation(state,id){
 const membership=state.memberships.find(m=>m.id===id);const user=state.users.find(u=>u.id===membership?.userId);
 if(!user||membership.status!=='approved'||user.status==='suspended')throw Error('Einladung erst nach Mitarbeiterfreigabe möglich.');
 recordEvent(state,'Einladung für '+user.email+' in Vorschau vorbereitet. Kein Versand.');
}
export function saveDealer(state,id,input){
 const fields=['company','street','houseNumber','postalCode','city','countryCode','email','phone','website'];
 const values=Object.fromEntries(fields.map(key=>[key,String(input[key]??'').trim()]));
 for(const key of ['company','street','houseNumber','postalCode','city','countryCode','email'])if(!values[key])throw Error('Bitte alle Pflichtfelder ausfüllen.');
 if(fields.some(key=>values[key].length>254))throw Error('Ein Feld ist zu lang.');
 if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email))throw Error('Bitte eine gültige Kontakt-E-Mail angeben.');
 values.countryCode=values.countryCode.toUpperCase();if(!/^[A-Z]{2}$/.test(values.countryCode))throw Error('Ländercode muss aus zwei Buchstaben bestehen, z. B. DE.');
 if(values.website&&!/^https?:\/\//i.test(values.website))throw Error('Website muss mit https:// oder http:// beginnen.');
 if(id){const dealer=state.dealers.find(d=>d.id===id);if(!dealer)throw Error('Firma nicht gefunden.');Object.assign(dealer,values);}
 else {id='demo-d-'+crypto.randomUUID();state.dealers.push({id,...values,status:'active',finderVisible:false});}
 recordEvent(state,'Firmendaten in Vorschau übernommen.');return id;
}
