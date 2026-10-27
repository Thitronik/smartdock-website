/* Read-only public content snapshot. No requests to the original backend. */
(() => {
  const originalFetch = window.fetch.bind(window);
  const collections = new Set(['translation_DE','translation_EN','dealers','faqs','anleitungen']);
  window.fetch = async (input, init = {}) => {
    const url = new URL(input instanceof Request ? input.url : input, location.href);
    const match = url.pathname.match(/^\/hcgi\/platform\/api\/collections\/([^/]+)\/records$/);
    if (!match) return originalFetch(input, init);
    const method = (init.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
    if (method !== 'GET') {
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
