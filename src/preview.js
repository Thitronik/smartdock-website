/* Read-only public content snapshot. No requests to the original backend. */
(() => {
  const originalFetch = window.fetch.bind(window);
  const testBackend=location.hostname==='test.smartdock.de';
  let submittedForm=null;
  if(testBackend) {
    document.addEventListener('submit',event=>{submittedForm=event.target;},true);
    void originalFetch('/api/index.php?action=session',{credentials:'same-origin',cache:'no-store'}).catch(()=>{});
    const labels={de:'Hinweise zur Verarbeitung deiner Anfrage findest du in unserer Datenschutzerklärung.',en:'Please see our privacy policy for information about processing your request.',fr:'Notre politique de confidentialité explique le traitement de votre demande.',nl:'In onze privacyverklaring lees je hoe wij je aanvraag verwerken.',es:'Consulta nuestra política de privacidad para saber cómo tratamos tu solicitud.'};
    const language=location.pathname.match(/^\/(en|fr|nl|es)(?:\/|$)/)?.[1]||'de';
    const privacy={de:'/datenschutz/',en:'/en/privacy/',fr:'/fr/confidentialite/',nl:'/nl/privacy/',es:'/es/privacidad/'}[language];
    const prepareForms=()=>document.querySelectorAll('form').forEach(form=>{
      if(form.closest('.sd-admin-portal,.sd-dealer-portal')||form.querySelector('[data-sd-trap]'))return;
      const trap=document.createElement('label');trap.dataset.sdTrap='';trap.setAttribute('aria-hidden','true');trap.style.cssText='position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden';trap.textContent='Website';
      const input=document.createElement('input');input.name='sd_website';input.tabIndex=-1;input.autocomplete='off';trap.append(input);form.append(trap);
      if(!form.querySelector('#newsletter-email')){const p=document.createElement('p');p.dataset.sdPrivacy='';p.className='text-sm text-muted-foreground';const link=document.createElement('a');link.href=privacy;link.target='_blank';link.rel='noopener';link.textContent=labels[language];p.append(link);form.append(p);}
    });
    document.addEventListener('DOMContentLoaded',()=>{const note=document.getElementById('preview-note');if(note)note.textContent='HETZNER · TESTUMGEBUNG · Formulare sind mit der separaten Testdatenbank verbunden. Bestätigungsmails werden versendet.';prepareForms();new MutationObserver(prepareForms).observe(document.body,{childList:true,subtree:true});},{once:true});
  }
  const collections = new Set(['translation_DE','translation_EN','dealers','faqs','anleitungen']);
  window.fetch = async (input, init = {}) => {
    const url = new URL(input instanceof Request ? input.url : input, location.href);
    const match = url.pathname.match(/^\/hcgi\/platform\/api\/collections\/([^/]+)\/records$/);
    if (!match) return originalFetch(input, init);
    const method = (init.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
    if (method !== 'GET') {
      const actions={newsletter_subscribers:'newsletter-request',contact_requests:'contact-request',configuration_requests:'configuration-request'};
      if(testBackend&&Object.hasOwn(actions,match[1])) {
        try {
          const data=JSON.parse(init.body||'{}');
          const sessionResponse=await originalFetch('/api/index.php?action=session',{credentials:'same-origin',cache:'no-store'});if(!sessionResponse.ok)return sessionResponse;const session=await sessionResponse.json();
          const form=submittedForm?.isConnected?submittedForm:match[1]==='newsletter_subscribers'?document.querySelector('#newsletter-email')?.closest('form'):match[1]==='contact_requests'?document.querySelector('textarea[name="message"]')?.closest('form'):document.activeElement?.closest('form')||[...document.querySelectorAll('form')].find(f=>f.querySelector('[name="firstName"]'));
          const language=location.pathname.match(/^\/(en|fr|nl|es)(?:\/|$)/)?.[1]||'de';
          const response=await originalFetch('/api/index.php?action='+actions[match[1]],{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-CSRF-Token':session.csrf},body:JSON.stringify({...data,language,sd_website:form?.querySelector('[name="sd_website"]')?.value||'',privacy:!!form?.querySelector('[data-sd-privacy]')})});
          if(!response.ok)return response;
          const accepted=await response.json();
          const reference=match[1]==='configuration_requests'?{referenceNumber:accepted.id}:{};
          return new Response(JSON.stringify({...data,...accepted,...reference,preview:false}),{status:200,headers:{'Content-Type':'application/json'}});
        }catch{return new Response(JSON.stringify({message:'SMARTDOCK test backend unavailable.'}),{status:503,headers:{'Content-Type':'application/json'}});}
      }
      if (match[1] === 'configuration_requests') {
        // Lokal und auf Pages gleich: kein nicht vorhandenes Test-Backend aufrufen.
        try {
          const body = JSON.parse(init.body || '{}');
          const record = {...body, id: 'test-' + crypto.randomUUID(), preview: true, created: new Date().toISOString()};
          sessionStorage.setItem('smartdock-last-test-configuration', JSON.stringify(record));
          return new Response(JSON.stringify(record), {status:200,headers:{'Content-Type':'application/json'}});
        } catch {
          return new Response(JSON.stringify({message:'Browser test storage is unavailable.'}), {status:503,headers:{'Content-Type':'application/json'}});
        }
      }
      return new Response(JSON.stringify({status:503,message:'Preview: form delivery is not connected.',data:{}}), {status:503,headers:{'Content-Type':'application/json'}});
    }
    if (!collections.has(match[1])) return new Response('{}',{status:404});
    const liveFinder=location.hostname==='test.smartdock.de'&&match[1]==='dealers';
    const response = await originalFetch(liveFinder?'/api/index.php?action=finder':'/data/'+match[1]+'.json',liveFinder?{credentials:'same-origin',cache:'no-store'}:undefined);
    if (!response.ok) return response;
    const data = await response.json();
    let items = liveFinder?data.dealers.map(d=>({id:String(d.id),name:d.company_name,name_de:d.name_de||d.company_name,name_en:d.name_en||d.company_name,name_fr:d.name_fr||d.company_name,address:d.original_address||[d.street,d.house_number,d.postal_code,d.city].filter(Boolean).join(' '),country:d.original_country||d.country_code,email:d.public_email||'',phone:d.public_phone||'',website:d.website||'',website2:d.website_secondary||'',latitude:Number(d.latitude),longitude:Number(d.longitude)})):data.items;
    if (match[1] === 'anleitungen') items = items.filter(x=>x.aktiv);
    const sort = url.searchParams.get('sort');
    if (sort) {
      const field = sort.replace(/^[+-]/,'');
      items = [...items].sort((a,b)=> (typeof a[field] === 'number' ? a[field]-b[field] : String(a[field]??'').localeCompare(String(b[field]??''))) * (sort.startsWith('-')?-1:1));
    }
    const page = Math.max(1, Number(url.searchParams.get('page') || 1));
    const perPage = Math.max(1, Number(url.searchParams.get('perPage') || 500));
    return new Response(JSON.stringify({page,perPage,totalItems:items.length,totalPages:Math.ceil(items.length/perPage),items:items.slice((page-1)*perPage,page*perPage)}), {headers:{'Content-Type':'application/json'}});
  };
})();
