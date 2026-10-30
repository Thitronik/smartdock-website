const escape=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
export function newsletterCsv(rows){
 const cell=v=>'"'+String(v??'').replace(/^[\s]*[=+@-]/,m=>"'"+m).replace(/"/g,'""')+'"';
 return '\ufeff'+[['E-Mail-Adresse','Gruppe','Sprache','Anmeldedatum'],...rows.filter(r=>r.status==='confirmed').map(r=>[r.email,r.group==='dealer'?'Händler':'Endkunden',r.language,r.created_at])].map(r=>r.map(cell).join(';')).join('\r\n');
}
export function mountNewsletter(node,api){
 let state=null,group='dealer',current=null,preview=null,busy=false;
 const label=g=>g==='dealer'?'Händler':'Endkunden';
 const message=text=>{node.querySelector('[role="status"]').textContent=text;};
 const eligible=()=>state.subscribers.filter(s=>s.group===group&&s.status==='confirmed');
 async function refresh(){state=await api('newsletter-admin');render();}
 function render(){
  node.innerHTML='<h2>Newsletterstudio</h2><p>Absender: newsletter@smartdock.de</p><div class="newsletter-actions">'+['dealer','customer'].map(g=>'<button type="button" data-group="'+g+'" class="primary-button">'+label(g)+' ('+state.subscribers.filter(s=>s.group===g&&s.status==='confirmed').length+')</button>').join('')+'</div><p>Händlerzuordnung über die E-Mail-Domain der aktiven Händlerdaten. Bei allgemeinen Mailanbietern zählt nur eine exakt hinterlegte Händleradresse. Abgemeldete, gesperrte und ältere unbestätigte Einträge sind vom Export und Versand ausgeschlossen.</p>'+(!state.ready?'<p>Editor und Export sind vorbereitet. Speichern und Versand benötigen noch die Datenbankerweiterung.</p>':'')+(!state.sendEnabled?'<p>Der Versand ist noch gesperrt, bis Absender und Zustellung geprüft sind.</p>':'')+'<div class="newsletter-editor" hidden></div><h3>Gespeicherte Newsletter</h3><div class="table-scroll"><table><thead><tr><th>Betreff</th><th>Gruppe</th><th>Status</th><th>Versand</th></tr></thead><tbody>'+state.campaigns.map(c=>{const content=JSON.parse(c.content_json);return '<tr><td><button type="button" data-campaign="'+Number(c.id)+'">'+escape(content.subject)+'</button></td><td>'+label(c.audience)+'</td><td>'+(c.status==='draft'?'Entwurf':'In Warteschlange')+'</td><td>'+Number(c.sent||0)+' / '+Number(c.recipient_count)+' versendet · '+Number(c.failed||0)+' Fehler · '+Number(c.cancelled||0)+' ausgelassen</td></tr>';}).join('')+'</tbody></table></div><p role="status" aria-live="polite"></p>';
 }
 function editor(c=null){
  current=c;preview=null;if(c)group=c.audience;
  const v=c?JSON.parse(c.content_json):{subject:'Neuigkeiten von SMARTDOCK',title:'Neues für dich und dein Boot',text:'Hallo,\n\nhier findest du unsere aktuellen Neuigkeiten rund um SMARTDOCK.\n\nDein SMARTDOCK Team',button_label:'Mehr erfahren',button_url:'https://www.smartdock.de',image_url:''};
  const box=node.querySelector('.newsletter-editor');box.hidden=false;
  box.innerHTML='<h3>Newsletter an '+label(group)+'</h3><p>'+eligible().length+' aktive Empfänger</p><button type="button" data-export>Gruppe als CSV exportieren</button><form id="newsletter-form">'+[['subject','Betreff'],['title','Überschrift'],['text','Nachricht'],['button_label','Buttontext (optional)'],['button_url','Buttonziel (HTTPS, optional)']].map(([k,l])=>'<label>'+l+(k==='text'?'<textarea name="text" rows="10" maxlength="8000" required>':'<input name="'+k+'" '+(['subject','title'].includes(k)?'required maxlength="160"':'maxlength="1000"')+' value="')+escape(v[k])+(k==='text'?'</textarea>':'">')+'</label>').join('')+'<label>Bild vom PC hochladen (optional)<input type="file" data-image-upload accept="image/jpeg,image/png,image/webp"></label><p>Empfohlen: 1.200 × 675 Pixel im Querformat (16:9). Bilder werden proportional auf maximal 1.200 Pixel Breite und 800 Pixel Höhe verkleinert, ohne Zuschnitt. JPG, PNG oder WebP · bis 5 MB. Das Bild ist nach dem Upload öffentlich abrufbar.</p><input type="hidden" name="image_url" value="'+escape(v.image_url)+'"><div data-image-preview></div><button type="button" data-remove-image>Bild entfernen</button><div class="newsletter-actions"><button class="primary-button" '+(!state.ready||c?.status==='queued'?'disabled':'')+'>Entwurf speichern und Vorschau</button><button type="button" data-close>Schließen</button></div></form><div class="newsletter-review"></div>';
  showImage(v.image_url);
  if(c?.status==='queued')box.querySelectorAll('input,textarea').forEach(el=>el.disabled=true);
  box.scrollIntoView({behavior:'smooth',block:'start'});
 }
 function showImage(url){const box=node.querySelector('[data-image-preview]');box.replaceChildren();if(url){const img=document.createElement('img');img.src=url;img.alt='Ausgewähltes Newsletterbild';img.style.cssText='max-width:100%;max-height:260px;object-fit:contain';box.append(img);}node.querySelector('[data-remove-image]').disabled=!url||current?.status==='queued';}
 node.addEventListener('change',async e=>{if(!e.target.matches('[data-image-upload]'))return;const file=e.target.files[0];if(!file)return;if(busy){e.target.value='';return;}if(file.size>5*1024*1024){message('Das Bild darf höchstens 5 MB groß sein.');e.target.value='';return;}busy=true;const field=e.target;field.disabled=true;message('Bild wird hochgeladen …');try{const data=new FormData();data.append('image',file);const result=await api('newsletter-upload',data);node.querySelector('[name="image_url"]').value=result.url;showImage(result.url);message('Bild hochgeladen. Speichere den Entwurf, um die Newsletter-Vorschau zu aktualisieren.');}catch(err){message(err.message);}finally{busy=false;field.disabled=false;field.value='';}});
 async function review(){
  preview=await api('newsletter-preview',{id:current.id});const box=node.querySelector('.newsletter-review');
  box.innerHTML='<h3>Vorschau · '+label(group)+'</h3><p>'+preview.count+' Empfänger · newsletter@smartdock.de</p><iframe title="Newsletter-Vorschau" sandbox=""></iframe><div class="newsletter-actions"><button type="button" data-test '+(!state.testEnabled?'disabled':'')+'>Testnewsletter an alle aktiven Admins</button><button type="button" data-send class="primary-button" '+(!state.sendEnabled||!preview.count?'disabled':'')+'>An '+preview.count+' '+label(group)+' senden</button></div>';
  box.querySelector('iframe').srcdoc=preview.html;
 }
 node.addEventListener('input',()=>{preview=null;const box=node.querySelector('.newsletter-review');if(box)box.replaceChildren();});
 node.addEventListener('submit',async e=>{
  if(e.target.id!=='newsletter-form')return;e.preventDefault();if(busy)return;busy=true;
  try{const result=await api('newsletter-save',{...Object.fromEntries(new FormData(e.target)),audience:group,id:current?.id||0,revision:current?.revision||0});state=await api('newsletter-admin');current=state.campaigns.find(c=>Number(c.id)===Number(result.id));await review();message('Entwurf gespeichert. Prüfe Inhalt und Empfänger vor dem Versand.');}catch(err){message(err.message);}finally{busy=false;}
 });
 node.addEventListener('click',async e=>{
  const b=e.target.closest('button');if(!b||busy)return;
  if(b.hasAttribute('data-remove-image')){node.querySelector('[name="image_url"]').value='';showImage('');preview=null;node.querySelector('.newsletter-review').replaceChildren();return;}
  if(b.dataset.group){group=b.dataset.group;editor();return;}
  if(b.dataset.campaign){editor(state.campaigns.find(c=>Number(c.id)===Number(b.dataset.campaign)));return;}
  if(b.hasAttribute('data-close')){node.querySelector('.newsletter-editor').hidden=true;return;}
  if(b.hasAttribute('data-export')){const blob=new Blob([newsletterCsv(eligible())],{type:'text/csv;charset=utf-8'});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download='SMARTDOCK-Newsletter-'+label(group)+'.csv';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);return;}
  if(!preview||(!b.hasAttribute('data-test')&&!b.hasAttribute('data-send')))return;
  const send=b.hasAttribute('data-send');
  if(send&&!window.confirm('Newsletter „'+JSON.parse(current.content_json).subject+'“ jetzt an '+preview.count+' '+label(group)+' senden?'))return;
  busy=true;b.disabled=true;
  try{const result=await api(send?'newsletter-send':'newsletter-test',{id:current.id,fingerprint:preview.fingerprint});if(send){await refresh();message('Newsletter wurde einmalig in die Versandwarteschlange übernommen.');}else message('Testmail zum Versand an '+result.recipient+' übergeben.');}catch(err){message(err.message);}finally{busy=false;if(b.isConnected)b.disabled=false;}
 });
 node.innerHTML='<h2>Newsletterstudio</h2><p role="status">Wird geladen …</p>';
 refresh().catch(e=>message(e.message));
}
