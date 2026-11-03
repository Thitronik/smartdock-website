// Loaded only by the Hetzner test portal. All access decisions stay on the server.
export async function connectDealerPortal(root,language){
 const messages={
  de:['Zugang anfragen','Anmelden','Abmelden','E-Mail zur Bestätigung angefordert. Prüfe bitte dein Postfach.','Falls ein freigeschalteter Händlerzugang besteht, erhältst du eine E-Mail zum Festlegen deines Passworts.','Bitte gib oben deine E-Mail-Adresse ein.','Dein Händlerzugang ist freigeschaltet.','Die Inhalte dieser Rubriken werden von SMARTDOCK noch vorbereitet.','Die Testumgebung speichert deine Anfrage in der separaten Hetzner-Testdatenbank.'],
  en:['Request access','Sign in','Sign out','Confirmation email requested. Please check your inbox.','If you have approved dealer access, you will receive an email to set your password.','Please enter your email address above.','Your dealer access is approved.','SMARTDOCK is preparing the content for these sections.','This test environment stores requests in the separate Hetzner test database.'],
  fr:['Demander un accès','Se connecter','Se déconnecter','E-mail de confirmation demandé. Consultez votre messagerie.','Si votre accès est approuvé, vous recevrez un e-mail pour définir votre mot de passe.','Saisissez votre adresse e-mail ci-dessus.','Votre accès revendeur est approuvé.','SMARTDOCK prépare le contenu de ces rubriques.','Cet environnement enregistre les demandes dans la base de test Hetzner séparée.'],
  nl:['Toegang aanvragen','Inloggen','Uitloggen','Bevestigingsmail aangevraagd. Controleer uw inbox.','Bij goedgekeurde toegang ontvangt u een e-mail om uw wachtwoord in te stellen.','Vul hierboven uw e-mailadres in.','Uw dealertoegang is goedgekeurd.','SMARTDOCK bereidt de inhoud van deze rubrieken voor.','Deze testomgeving slaat aanvragen op in de aparte Hetzner-testdatabase.'],
  es:['Solicitar acceso','Iniciar sesión','Cerrar sesión','Correo de confirmación solicitado. Revise su bandeja de entrada.','Si tiene acceso aprobado, recibirá un correo para establecer su contraseña.','Introduzca su correo electrónico arriba.','Su acceso está aprobado.','SMARTDOCK está preparando el contenido de estas secciones.','Este entorno guarda las solicitudes en la base de pruebas Hetzner independiente.']
 };
 const t=messages[language]||messages.de;let csrf='',busy=false;
 const status=root.querySelector('#login-status'),result=root.querySelector('#result');
 const employeeCopy={
 de:['Mitarbeiter einladen','Lade weitere Mitarbeiter deiner Firma ein. Nach der persönlichen Einrichtung prüft SMARTDOCK den Zugang. Erst nach der Freigabe ist der Zugriff möglich.','Firma','E-Mail-Adresse des Mitarbeiters','Einladung senden','Bitte Firma auswählen …','Die Einladung wurde an den E-Mail-Server übergeben. Nach der Einrichtung prüft SMARTDOCK den Zugang.','Die Einladung wurde gespeichert. Der Versand steht noch aus.','Die Einladung wurde gespeichert, aber der E-Mail-Versand ist fehlgeschlagen. Bitte wende dich an SMARTDOCK.','Einladung wird versendet …'],
 en:['Invite employees','Invite more employees of your company. SMARTDOCK reviews each account after setup. Access is available only after approval.','Company','Employee email address','Send invitation','Select a company …','The invitation was handed to the email server. SMARTDOCK will review access after setup.','Invitation saved. Email delivery is pending.','Invitation saved, but email delivery failed. Please contact SMARTDOCK.','Sending invitation …'],
 fr:['Inviter des collaborateurs','Invite des collaborateurs de ton entreprise. SMARTDOCK vérifie chaque compte après sa création. L’accès nécessite une validation.','Entreprise','Adresse e-mail du collaborateur','Envoyer l’invitation','Choisis une entreprise …','L’invitation a été transmise au serveur de messagerie. SMARTDOCK vérifiera ensuite l’accès.','Invitation enregistrée. Envoi en attente.','Invitation enregistrée, mais l’envoi a échoué. Contacte SMARTDOCK.','Envoi de l’invitation …'],
 nl:['Medewerkers uitnodigen','Nodig medewerkers van je bedrijf uit. SMARTDOCK controleert hun toegang na het instellen van het account. Toegang volgt pas na goedkeuring.','Bedrijf','E-mailadres van de medewerker','Uitnodiging versturen','Kies een bedrijf …','De uitnodiging is aan de mailserver overgedragen. SMARTDOCK controleert de toegang na het instellen.','Uitnodiging opgeslagen. De verzending staat nog open.','Uitnodiging opgeslagen, maar verzenden is mislukt. Neem contact op met SMARTDOCK.','Uitnodiging wordt verstuurd …'],
 es:['Invitar empleados','Invita a empleados de tu empresa. SMARTDOCK revisa cada cuenta después de su configuración. El acceso requiere aprobación.','Empresa','Correo electrónico del empleado','Enviar invitación','Selecciona una empresa …','La invitación se ha entregado al servidor de correo. SMARTDOCK revisará el acceso después de la configuración.','Invitación guardada. Envío pendiente.','Invitación guardada, pero el envío ha fallado. Contacta con SMARTDOCK.','Enviando invitación …']
 }[language]||null;
 function employeeForm(companies,details){
  const allowed=companies.filter(d=>Number(d.can_invite)===1);if(!allowed.length||!employeeCopy)return;
  root.querySelector('#dealer-employees')?.remove();const group=document.createElement('div');group.id='dealer-employees';
  for(const company of allowed){
   const section=document.createElement('section');section.className='card employee-invite';
   section.innerHTML=`<h2>${employeeCopy[0]}</h2><p class="muted">${employeeCopy[1]}</p><form class="dealer-employee-invite-form"><input type="hidden" name="dealer_id"><label>${employeeCopy[3]}<input name="email" type="email" maxlength="254" required autocomplete="off"></label><button>${employeeCopy[4]}</button><p role="status" aria-live="polite" class="employee-status"></p></form>`;
   section.querySelector('[name=dealer_id]').value=String(company.id);
   if(allowed.length>1){const name=document.createElement('p');name.className='dealer-company-name';name.textContent=company.company_name;section.querySelector('h2').after(name);}
   group.append(section);
  }
  details.after(group);
 }

 async function api(action,data){
  const response=await fetch('/api/index.php?portal=dealer&action='+action,{method:data===undefined?'GET':'POST',credentials:'same-origin',cache:'no-store',headers:data===undefined?{}:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:data===undefined?undefined:JSON.stringify(data)});
  let body;try{body=await response.json();}catch{throw Error('SMARTDOCK: Server nicht erreichbar.');}
  if(!response.ok)throw Error(body.error||'SMARTDOCK: Anfrage fehlgeschlagen.');if(body.csrf)csrf=body.csrf;return body;
 }
 const session=await api('session');if(!root.isConnected)return;
 const previewNote=document.getElementById('preview-note');if(previewNote)previewNote.textContent=t[8];
 root.querySelectorAll('form button,#reset').forEach(button=>button.disabled=false);
 root.querySelector('.notice').hidden=true;

 root.querySelector('#login-status').nextElementSibling.textContent='';
 const details=root.querySelector('.dealer-preview');details.hidden=true;
 async function showDealer(){
  const data=await api('dealer');if(!root.isConnected)return;
  root.querySelector('.columns').hidden=true;root.querySelector('.page-hero h1').textContent=({de:'Willkommen im Händlerbereich',en:'Welcome to the dealer area',fr:'Bienvenue dans l’espace revendeurs',nl:'Welkom in het dealerportaal',es:'Bienvenido al área de distribuidores'})[language]||'Willkommen im Händlerbereich';
  result.classList.add('dealer-company-card');const p=document.createElement('p');p.className='dealer-company-name';p.textContent=data.companies.map(d=>d.company_name).join(' · ');result.replaceChildren(p);
  const logout=document.createElement('button');logout.type='button';logout.className='dealer-logout';logout.textContent=t[2];logout.addEventListener('click',async()=>{if(busy)return;busy=true;try{await api('logout',{});location.reload();}catch(error){p.textContent=error.message;busy=false;}});result.append(logout);
  details.hidden=false;details.open=true;details.querySelector('summary').hidden=true;details.querySelector('.notice').hidden=true;details.querySelectorAll('.tile p').forEach(node=>node.hidden=true);employeeForm(data.companies,details);
 }
 root.addEventListener('submit',async event=>{
  const form=event.target;
  if(form.classList.contains('dealer-employee-invite-form')){
   event.preventDefault();event.stopImmediatePropagation();if(busy||!form.reportValidity())return;
   const feedback=form.querySelector('[role=status]'),button=form.querySelector('button');busy=true;button.disabled=true;feedback.textContent=employeeCopy[9];
   try{const data=await api('dealer-employee-invite',Object.fromEntries(new FormData(form)));feedback.textContent=employeeCopy[data.mailStatus==='sent'?6:data.mailStatus==='failed'?8:7];form.querySelector('[name=email]').value='';}catch(error){feedback.textContent=error.message;}finally{busy=false;button.disabled=false;}return;
  }
  if(form.id!=='login')return;event.preventDefault();event.stopImmediatePropagation();if(busy||!form.reportValidity())return;
  busy=true;const button=form.querySelector('button');button.disabled=true;
  try {
   if(form.id==='login'){
    const password=form.querySelector('[type=password]');const data={email:form.querySelector('[type=email]').value,password:password.value};password.value='';
    try{await api('login',data);}finally{data.password='';}await showDealer();
   }
  }catch(error){(form.id==='login'?status:result).textContent=error.message;}finally{busy=false;if(button.isConnected)button.disabled=false;}
 },true);
 root.querySelector('#reset').addEventListener('click',async event=>{
  event.preventDefault();event.stopImmediatePropagation();if(busy)return;const email=root.querySelector('#login-email');if(!email.value||!email.reportValidity()){status.textContent=t[5];email.focus();return;}
  busy=true;try{await api('password-request',{email:email.value});status.textContent=t[4];}catch(error){status.textContent=error.message;}finally{busy=false;}
 },true);
 if(session.user){try{await showDealer();}catch(error){status.textContent=error.message;}}
}
