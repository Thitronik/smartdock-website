// Loaded only by the Hetzner test portal. All access decisions stay on the server.
export async function connectDealerPortal(root,language){
 const messages={
  de:['Zugang anfragen','Anmelden','Abmelden','E-Mail zur Bestätigung angefordert. Prüfen Sie bitte Ihr Postfach.','Falls ein freigeschalteter Händlerzugang besteht, erhalten Sie eine E-Mail zum Festlegen Ihres Passworts.','Bitte geben Sie oben Ihre E-Mail-Adresse ein.','Ihr Händlerzugang ist freigeschaltet.','Die Inhalte dieser Rubriken werden von SMARTDOCK noch vorbereitet.','Die Testumgebung speichert Ihre Anfrage in der separaten Hetzner-Testdatenbank.'],
  en:['Request access','Sign in','Sign out','Confirmation email requested. Please check your inbox.','If you have approved dealer access, you will receive an email to set your password.','Please enter your email address above.','Your dealer access is approved.','SMARTDOCK is preparing the content for these sections.','This test environment stores requests in the separate Hetzner test database.'],
  fr:['Demander un accès','Se connecter','Se déconnecter','E-mail de confirmation demandé. Consultez votre messagerie.','Si votre accès est approuvé, vous recevrez un e-mail pour définir votre mot de passe.','Saisissez votre adresse e-mail ci-dessus.','Votre accès revendeur est approuvé.','SMARTDOCK prépare le contenu de ces rubriques.','Cet environnement enregistre les demandes dans la base de test Hetzner séparée.'],
  nl:['Toegang aanvragen','Inloggen','Uitloggen','Bevestigingsmail aangevraagd. Controleer uw inbox.','Bij goedgekeurde toegang ontvangt u een e-mail om uw wachtwoord in te stellen.','Vul hierboven uw e-mailadres in.','Uw dealertoegang is goedgekeurd.','SMARTDOCK bereidt de inhoud van deze rubrieken voor.','Deze testomgeving slaat aanvragen op in de aparte Hetzner-testdatabase.'],
  es:['Solicitar acceso','Iniciar sesión','Cerrar sesión','Correo de confirmación solicitado. Revise su bandeja de entrada.','Si tiene acceso aprobado, recibirá un correo para establecer su contraseña.','Introduzca su correo electrónico arriba.','Su acceso está aprobado.','SMARTDOCK está preparando el contenido de estas secciones.','Este entorno guarda las solicitudes en la base de pruebas Hetzner independiente.']
 };
 const t=messages[language]||messages.de;let csrf='',busy=false;
 const status=root.querySelector('#login-status'),result=root.querySelector('#result');
 async function api(action,data){
  const response=await fetch('/api/index.php?action='+action,{method:data===undefined?'GET':'POST',credentials:'same-origin',cache:'no-store',headers:data===undefined?{}:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:data===undefined?undefined:JSON.stringify(data)});
  let body;try{body=await response.json();}catch{throw Error('SMARTDOCK: Server nicht erreichbar.');}
  if(!response.ok)throw Error(body.error||'SMARTDOCK: Anfrage fehlgeschlagen.');if(body.csrf)csrf=body.csrf;return body;
 }
 const session=await api('session');if(!root.isConnected)return;
 const previewNote=document.getElementById('preview-note');if(previewNote)previewNote.textContent=t[8];
 root.querySelectorAll('form button,#reset').forEach(button=>button.disabled=false);
 root.querySelector('.notice').textContent=t[8];
 root.querySelector('#request button').textContent=t[0];
 root.querySelector('#login-status').nextElementSibling.textContent='';
 const details=root.querySelector('.dealer-preview');details.hidden=true;
 async function showDealer(){
  const data=await api('dealer');if(!root.isConnected)return;
  root.querySelector('.columns').hidden=true;root.querySelector('.notice').textContent=t[6];
  const p=document.createElement('p');p.textContent=data.companies.map(d=>d.company_name).join(' · ');result.replaceChildren(p);
  const logout=document.createElement('button');logout.type='button';logout.textContent=t[2];logout.addEventListener('click',async()=>{if(busy)return;busy=true;try{await api('logout',{});location.reload();}catch(error){p.textContent=error.message;busy=false;}});result.append(logout);
  details.hidden=false;details.open=true;details.querySelector('summary').textContent=t[6];details.querySelector('.notice').textContent=t[7];details.querySelectorAll('.tile p').forEach(node=>node.textContent=t[7]);
 }
 root.addEventListener('submit',async event=>{
  const form=event.target;if(!['login','request'].includes(form.id))return;event.preventDefault();event.stopImmediatePropagation();if(busy||!form.reportValidity())return;
  busy=true;const button=form.querySelector('button');button.disabled=true;
  try {
   if(form.id==='login'){
    const password=form.querySelector('[type=password]');const data={email:form.querySelector('[type=email]').value,password:password.value};password.value='';
    try{await api('login',data);}finally{data.password='';}await showDealer();
   }else {
    const data=Object.fromEntries(new FormData(form));data.privacy=form.querySelector('[name=privacy]').checked;data.language=language;
    await api('dealer-request',data);result.textContent=t[3];result.focus();form.reset();
   }
  }catch(error){(form.id==='login'?status:result).textContent=error.message;}finally{busy=false;if(button.isConnected)button.disabled=false;}
 },true);
 root.querySelector('#reset').addEventListener('click',async event=>{
  event.preventDefault();event.stopImmediatePropagation();if(busy)return;const email=root.querySelector('#login-email');if(!email.value||!email.reportValidity()){status.textContent=t[5];email.focus();return;}
  busy=true;try{await api('password-request',{email:email.value});status.textContent=t[4];}catch(error){status.textContent=error.message;}finally{busy=false;}
 },true);
 if(session.user){if(session.user.roles?.length){status.textContent=({de:'In diesem Browser ist ein Administratorkonto angemeldet. Melden Sie sich hier mit Ihrer Händler-E-Mail-Adresse und Ihrem Händlerpasswort an.',en:'An administrator account is signed in in this browser. Sign in here with your dealer email address and dealer password.',fr:'Un compte administrateur est connecté dans ce navigateur. Connectez-vous ici avec votre adresse e-mail et votre mot de passe revendeur.',nl:'In deze browser is een beheerder aangemeld. Meld u hier aan met uw dealer-e-mailadres en dealerwachtwoord.',es:'Hay una cuenta de administrador conectada en este navegador. Inicie sesión aquí con su correo y contraseña de distribuidor.'})[language]||'Please sign in with your dealer account.';}else{try{await showDealer();}catch(error){status.textContent=error.message;}}}
}
