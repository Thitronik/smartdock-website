import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { createOutput, adaptForBase, validateBase, validateRoutes, PAGES_BASE } from './build.mjs';

test('URL-Basis und Routen lassen weder Pfadausbruch noch mehrdeutige Ziele zu', () => {
  for (const base of ['../dist', '/a/', '//host', '/a?b', '/a/../b', '/a"']) assert.throws(() => validateBase(base));
  const app = { main: {} };
  const route = { path: '/', application: 'main', htmlLang: 'de', lineEnding: 'lf' };
  validateRoutes([route], app);
  for (const path of ['/../', '/%2e%2e/', '/a\\b/', '/A/', '/no-slash']) assert.throws(() => validateRoutes([{ ...route, path }], app));
  assert.throws(() => validateRoutes([route, route], app), /Doppelter/);
  assert.throws(() => validateRoutes([{ ...route, application: 'unknown' }], app), /Unbekannte/);
});

test('Pages-Anpassung erhält React-Routen, externe URLs und Query/Hash', () => {
  const source = 'const x={path:"/faq",src:"/media/image.png",href:"/faq?q=1#answer",external:"https://example.com/media/image.png"};';
  const result = adaptForBase(source, 'assets/main.js', PAGES_BASE);
  assert.ok(result.includes('path:"/faq"'));
  assert.ok(result.includes('src:"/smartdock-website/media/image.png"'));
  assert.ok(result.includes('href:"/smartdock-website/faq?q=1#answer"'));
  assert.ok(result.includes('https://example.com/media/image.png'));
});

test('Navigation unter Pages verdoppelt den Präfix nicht und verändert keine fremden Ziele', () => {
  const html = adaptForBase('<html><head></head></html>', 'index.html', PAGES_BASE);
  const script = html.match(/<script>([\s\S]*?)<\/script>/)[1];
  const context = { URL, window: {}, location: { href: 'https://example.com/smartdock-website/', origin: 'https://example.com' }, document: { addEventListener() {} } };
  vm.runInNewContext(script, context, { timeout: 100 });
  const navigate = context.window.__sdPagesUrl;
  assert.equal(navigate('/faq?q=1#answer'), '/smartdock-website/faq?q=1#answer');
  for (const target of ['/smartdock-website/faq', 'https://other.example/faq', 'mailto:smartdock@thitronik.de']) assert.equal(navigate(target), target);
});

test('Der Build erzeugt beide Anwendungen, fünf Sprach-Einstiege und alle historischen Seiten', async () => {
  const root = await createOutput();
  const pages = await createOutput(PAGES_BASE);
  assert.equal([...root.keys()].filter(name => name.endsWith('.html')).length, 122);
  assert.equal(root.size, 207);
  assert.equal(pages.size, root.size + 1);
  for (const language of ['', 'en/', 'fr/', 'nl/', 'es/']) {
    assert.ok(root.has(language + 'index.html'));
    assert.ok(root.get(language + 'positionhold/index.html').toString().includes('/assets/positionhold.js'));
  }
  assert.ok(root.has('ch/positionhold/index.html'));
  assert.ok(pages.has('.nojekyll'));
  for (const [name, bytes] of root) {
    if (!/\.(html|js|css|json)$/.test(name)) assert.deepEqual(pages.get(name), bytes, name);
    assert.ok(!name.startsWith('src/') && !name.startsWith('tools/') && !name.startsWith('docs/'));
  }
});

test('Wiederholte Builds sind ohne Uhrzeit, Netzwerk oder Zufallswerte identisch', async () => {
  for (const base of ['', PAGES_BASE]) {
    const first = await createOutput(base), second = await createOutput(base);
    assert.deepEqual([...second.keys()], [...first.keys()]);
    for (const [name, bytes] of first) assert.deepEqual(second.get(name), bytes, name);
  }
});

test('Dynamische Pages-Links sind schon vor dem Klick für neue Tabs korrekt', () => {
  const html = adaptForBase('<html><head></head></html>', 'index.html', PAGES_BASE);
  let observer;
  const context = { URL, window: {}, location: { href: 'https://example.com/smartdock-website/', origin: 'https://example.com' },
    document: { addEventListener() {}, documentElement: {} },
    MutationObserver: class { constructor(callback) { observer = callback; } observe() {} },
  };
  for (const [, script] of html.matchAll(/<script>([\s\S]*?)<\/script>/g)) vm.runInNewContext(script, context);
  const anchor = href => ({ nodeType: 1, href, matches: () => true, querySelectorAll: () => [],
    getAttribute() { return this.href; }, setAttribute(_, value) { this.href = value; } });
  for (const [input, expected] of [
    ['/faq', '/smartdock-website/faq'], ['/positionhold', '/smartdock-website/positionhold'],
    ['https://example.com/en/faq?q=1#answer', '/smartdock-website/en/faq?q=1#answer'],
    ['/smartdock-website/faq', '/smartdock-website/faq'],
    ['https://other.example/faq', 'https://other.example/faq'], ['#download-app', '#download-app'],
  ]) {
    const element = anchor(input);
    observer([{ type: 'childList', addedNodes: [element] }]);
    assert.equal(element.href, expected);
    observer([{ type: 'attributes', target: element }]);
    assert.equal(element.href, expected);
  }
});

test('Lokale Konfigurationsvorschau speichert wie Pages nur im aktuellen Tab', async () => {
  const { readFile } = await import('node:fs/promises');
  const source = await readFile(new URL('../src/preview.js', import.meta.url), 'utf8');
  for (const hostname of ['127.0.0.1', 'localhost', 'thitronik.github.io']) {
    let saved; let network = 0;
    const context = { URL, Request, Response, crypto: { randomUUID: () => 'test-id' },
      location: { hostname, href: 'http://' + hostname + '/' },
      sessionStorage: { setItem: (key, value) => { saved = { key, value: JSON.parse(value) }; } },
      window: { fetch: async () => { network++; throw Error('Unerwarteter Netzwerkzugriff'); } },
    };
    vm.runInNewContext(source, context);
    const response = await context.window.fetch('/hcgi/platform/api/collections/configuration_requests/records',
      { method: 'POST', body: JSON.stringify({ firstName: 'Test', email: 'test@example.invalid' }) });
    assert.equal(response.status, 200);
    assert.equal(network, 0);
    assert.equal(saved.value.preview, true);
    assert.equal(saved.key, 'smartdock-last-test-configuration');
  }
});

test('Hetzner-Bestätigung zeigt die Serverreferenz statt der lokalen Formularnummer', async () => {
  const { readFile } = await import('node:fs/promises');
  const source = await readFile(new URL('../src/preview.js', import.meta.url), 'utf8');
  const form={querySelector:selector=>selector==='[data-sd-privacy]'?{}:null};
  const context={URL,Request,Response,location:{hostname:'test.smartdock.de',href:'https://test.smartdock.de/',pathname:'/'},
    document:{addEventListener(){},activeElement:{closest:()=>form}},
    window:{fetch:async url=>new Response(JSON.stringify(url.includes('action=session')?{csrf:'test'}:{ok:true,id:'SD-0000-0123'}),{status:200})}};
  vm.runInNewContext(source,context);
  const response=await context.window.fetch('/hcgi/platform/api/collections/configuration_requests/records',{method:'POST',body:JSON.stringify({referenceNumber:'LOCAL-123456'})});
  const data=await response.json();
  assert.equal(data.id,'SD-0000-0123');assert.equal(data.referenceNumber,data.id);assert.equal(data.preview,false);
});

 test('Händlerseiten bleiben ohne Backend und enthalten keine geschützten Inhalte', async () => {
 const files = await createOutput(PAGES_BASE);
 for (const lang of ['', 'en/', 'fr/', 'nl/', 'es/']) {
 const html = files.get(lang+'haendlerbereich/index.html').toString();
 assert.ok(html.includes('/smartdock-website/assets/index-RRVGC1mK.js'));
 assert.ok(html.includes('noindex,nofollow'));
 }
 const js = files.get('assets/dealer-portal.js').toString();
 assert.ok(!/fetch\(|localStorage|sessionStorage/.test(js));
 const {dealerLink} = await import('../src/dealer-link.js');
 for (const lang of ['DE','EN','FR','NL','ES']) {
 const link = dealerLink(lang);
 assert.ok(files.has(link.href.slice(1)+'index.html'));
 assert.ok(link.children);
 }
 });

test('Händler-Sprachwechsel erhält die Seite und nutzt dieselbe Anwendung wie die Startseite', async () => {
  for (const base of ['', PAGES_BASE]) {
    const files = await createOutput(base);
    const bundle = files.get('assets/index-RRVGC1mK.js').toString();
    const source = bundle.match(/function JQ\(e,t\)\{[\s\S]*?(?=function Ek\()/)?.[0];
    assert.ok(source, 'Sprachrouting fehlt');
    const { dealerLink } = await import('../src/dealer-link.js');
    const translate = vm.runInNewContext('(' + source + ')', {sdDealerLink:dealerLink});
    for (const lang of ['DE', 'EN', 'FR', 'NL', 'ES']) {
      for (const current of ['/haendlerbereich', '/en/haendlerbereich/']) {
        assert.equal(translate(current, lang), dealerLink(lang).href);
      }
      const page = files.get((lang === 'DE' ? '' : lang.toLowerCase() + '/') + 'haendlerbereich/index.html').toString();
      const entry=page.match(/<script type="module" src="([^"]+)"/)[1];
      assert.equal(new URL(entry,'https://example.invalid').pathname,base+'/assets/index-RRVGC1mK.js');
      assert.equal(entry,files.get('index.html').toString().match(/<script type="module" src="([^"]+)"/)[1]);
      assert.ok(page.includes('id="root"'));
      assert.ok(!page.includes('<header'), 'Keine zweite Headerimplementierung');
    }
  }
});

test('Cookie-Auswahl zeigt gleichwertige Buttons mit getrennten Einwilligungsaktionen', async () => {
 const files = await createOutput();
 for (const name of ['index-RRVGC1mK.js','positionhold.js']) {
  const source=files.get('assets/'+name).toString();
  const at=source.lastIndexOf('children:e("cookie_accept_necessary")');
  const start=source.lastIndexOf('children:[',at)+'children:'.length;
  const end=source.indexOf(']})',at)+1;
  const jsx=(_type,props)=>props;
  const all=Object.assign(()=> 'all',{jsx});
  const buttons=vm.runInNewContext(source.slice(start,end),{r:{jsx},s:all,o:()=> 'necessary',i:()=> 'all',vt:null,Tt:null,e:key=>key});
  assert.equal(buttons.length,2);
  const visual=button=>JSON.stringify({variant:button.variant,className:button.className,style:button.style});
  assert.equal(visual(buttons[0]),visual(buttons[1]),name);
  assert.equal(buttons[0].style.background,'transparent');
  assert.equal(buttons[0].style.color,'hsl(var(--foreground))');
  assert.equal(buttons[0].style.boxShadow,'none');
  assert.equal(buttons[0].onClick(),'necessary');
  assert.equal(buttons[1].onClick(),'all');
 }
});

// #9: Die Wörterbücher liegen als Objektliterale im übernommenen Paket.
function dictionary(source, name) {
  const start = source.indexOf(name + '={nav_home:') + name.length + 1;
  assert.ok(start > name.length, 'Wörterbuch fehlt: ' + name);
  let depth = 0, quote = null, i = start;
  for (; i < source.length; i++) {
    const c = source[i];
    if (quote) { if (c === '\\') i++; else if (c === quote) quote = null; continue; }
    if (c === '"' || c === "'" || c === '`') quote = c;
    else if (c === '{') depth++;
    else if (c === '}' && --depth === 0) break;
  }
  return vm.runInNewContext('(' + source.slice(start, i + 1) + ')');
}

test('Newsletter, Seitentitel und Brotkrumen fallen in NL und ES nicht auf Deutsch zurück', async () => {
  const bundle = (await createOutput()).get('assets/index-RRVGC1mK.js').toString();
  const de = dictionary(bundle, 'YQ');
  const newsletter = Object.keys(de).filter(key => key.startsWith('newsletter_'));
  assert.equal(newsletter.length, 11);
  for (const name of ['kk', 'Sk']) {
    const dict = dictionary(bundle, name);
    for (const key of newsletter) {
      assert.ok(dict[key]?.trim(), name + ' fehlt ' + key);
      if (key !== 'newsletter_label') assert.notEqual(dict[key], de[key], name + ' ist deutsch: ' + key);
    }
  }
  for (const text of ['s?"Functies - SMARTDOCK":o?"Funciones - SMARTDOCK"', 's?"Home":o?"Inicio":"Startseite"', 's?"Functies":o?"Funciones":"Funktionen"',
    'o("Installation - SMARTDOCK","Installation - SMARTDOCK","Installation - SMARTDOCK","Installatie - SMARTDOCK","Instalación - SMARTDOCK")'])
    assert.ok(bundle.includes(text), 'Fehlt: ' + text);
});

test('Alte Features-Seiten leiten mit Query und Hash auf die aktuelle Funktionsseite derselben Sprache', async () => {
  const bundle = (await createOutput()).get('assets/index-RRVGC1mK.js').toString();
  const mapping = JSON.parse(bundle.match(/sdLF=(\{[^}]*\})/)[1]);
  const redirect = bundle.match(/sdLFR=(\(\)=>\{[\s\S]*?\}),fo=/)[1];
  const normalize = bundle.match(/x0=(e=>[^;]*);function b0/)[1];
  const expected = { '/features': '/funktionen', '/en/features-legacy': '/en/features', '/fr/fonctionnalites': '/fr/fonctions',
    '/nl/functionaliteiten': '/nl/functies', '/es/funciones-legado': '/es/funciones' };
  assert.deepEqual(mapping, expected);
  for (const [path, target] of Object.entries(expected)) {
    assert.ok(bundle.includes(`r.jsx(Ye,{path:"${path}",element:r.jsx(sdLFR,{})})`), 'Route leitet nicht weiter: ' + path);
    assert.ok(bundle.includes(`r.jsx(Ye,{path:"${target}",element:r.jsx(tf,{})})`), 'Ziel ist keine Funktionsseite: ' + target);
    for (const suffix of ['', '/']) {
      const input = { pathname: path + suffix, search: '?a=1', hash: '#x' };
      const result = vm.runInNewContext('const x0 = ' + normalize + ';(' + redirect + ')()', {
        ai: () => input, sdLF: mapping, Yg: null, r: { jsx: (_component, props) => props } }, { timeout: 100 });
      assert.deepEqual({ ...result }, { to: target + '?a=1#x', replace: true });
    }
  }
});

test('Verwaltungsvorschau veröffentlicht keine Backend- oder Importdateien',async()=>{
 for(const base of ['',PAGES_BASE]){const files=await createOutput(base);const html=files.get('admin/index.html').toString();assert.ok(html.includes('noindex,nofollow'));assert.ok(html.includes(base+'/assets/admin-portal.css'));assert.ok(html.includes('id="root"'));assert.ok(files.has('assets/admin-model.js'));for(const name of files.keys())assert.ok(!name.startsWith('database/')&&!name.startsWith('backend/')&&!name.endsWith('.sql')&&!name.includes('9_dealers_records'));}
});

 test("Öffentlicher Admineinstieg bietet weder Beispieldaten noch Anmeldeformular",async()=>{
 const {readFile}=await import("node:fs/promises");
 const source=(await readFile(new URL("../src/admin-portal.js",import.meta.url),"utf8")).replace(/^import .*;\n/,"").replace("export function mountAdminPortal","function mountAdminPortal").replaceAll("import.meta.url",JSON.stringify("https://thitronik.github.io/smartdock-website/assets/admin-portal.js"));
 for(const hostname of ["thitronik.github.io","www.smartdock.de","smartdock.de"]){
 const root={innerHTML:""},styles=[];const context={URL,location:{hostname},document:{getElementById:()=>root,querySelector:()=>null,createElement:()=>({dataset:{}}),head:{append:link=>styles.push(link)}}};
 vm.runInNewContext(source+";mountAdminPortal();",context);
 assert.equal(styles[0].href,"https://thitronik.github.io/smartdock-website/assets/admin-portal.css?v=20261029g");assert.match(root.innerHTML,/Zugang geschlossen/);assert.doesNotMatch(root.innerHTML,/<button|<form|<input|Beispieldaten öffnen/);
 }
 const files=await createOutput();assert.ok(files.has("admin/index.html"));assert.ok(!files.has("verwaltung/index.html"));
 });

test('Händlerkarten verwenden die schlüsselfreie OSM-Quelle mit sichtbarer Quellenangabe',async()=>{
 const files=await createOutput();const entry=files.get('assets/index-RRVGC1mK.js').toString();
 assert.doesNotMatch(entry,/basemaps\.cartocdn\.com|\{s\}\.tile\.openstreetmap\.org/);
 assert.equal((entry.match(/url:"https:\/\/tile\.openstreetmap\.org\/\{z\}\/\{x\}\/\{y\}\.png"[^}]*attribution:/g)||[]).length,2);
});
