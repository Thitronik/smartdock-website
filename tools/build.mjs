import { readFile, writeFile, readdir, mkdir, rm, lstat } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

export const ROOT = fileURLToPath(new URL('../', import.meta.url));
export const PAGES_BASE = '/smartdock-website';
const TEXT = /\.(html|js|css|json)$/;

export function validateBase(base) {
  if (base !== '' && !/^\/[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)*$/.test(base)) {
    throw new Error('Basis muss leer oder ein absoluter URL-Pfad ohne abschließenden Schrägstrich sein.');
  }
  return base;
}

export function validateRoutes(routes, apps) {
  const seen = new Set();
  if (!Array.isArray(routes) || routes.length === 0) throw new Error('Routenliste fehlt.');
  for (const route of routes) {
    if (!/^\/(?:[a-z0-9-]+\/)*$/.test(route.path)) throw new Error('Ungültiger Seitenpfad: ' + route.path);
    if (seen.has(route.path)) throw new Error('Doppelter Seitenpfad: ' + route.path);
    seen.add(route.path);
    if (!Object.hasOwn(apps, route.application)) throw new Error('Unbekannte Anwendung: ' + route.application);
    if (!/^[a-z]{2}(?:-[A-Z]{2})?$/.test(route.htmlLang)) throw new Error('Ungültige HTML-Sprache.');
    if (!['lf', 'crlf'].includes(route.lineEnding)) throw new Error('Unbekanntes Zeilenformat.');
  }
  if (!seen.has('/')) throw new Error('Startseite fehlt.');
}

// Gleiche Anpassungen wie der bisherige Pages-Workflow. React-Routen selbst
// behalten ihre Pfade; nur Ressourcen, Router-Basis und Vollnavigation ändern sich.
export function adaptForBase(source, name, base) {
  validateBase(base);
  if (!base) return source;
  let text = source.replaceAll('\r\n', '\n');
  text = text.replace(/(?<![\w:/])\/(assets|media|hcgi|data)\//g, base + '/$1/');
  text = text.replaceAll('"/favicon.png"', '"' + base + '/favicon.png"');
  text = text.replaceAll('"/preview.js', '"' + base + '/preview.js');
  if (name.endsWith('.js')) {
    text = text.replace(/basename:([A-Za-z_$][\w$]*)="\/"/g, 'basename:$1="' + base + '"');
    text = text.replace(/window\.location\.href=([A-Za-z_$][\w$]*)(?=[,;}])/g, 'window.location.href=window.__sdPagesUrl($1)');
    text = text.replaceAll('window.location.href="/"', 'window.location.href="' + base + '/"');
  }
  if (name.endsWith('/positionhold.js')) text = text.replaceAll('vA=window.location.origin', 'vA=window.location.origin+"' + base + '"');
  if (name === 'preview.js') text = text.replace('const match = url.pathname.match(', 'const match = url.pathname.replace("' + base + '", "").match(');
  if (/\.(html|js)$/.test(name)) {
    const escaped = base.slice(1).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    text = text.replace(new RegExp('(href[=:]["\x27])/(?!/|' + escaped + '(?:/|["\x27]))', 'g'), '$1' + base + '/');
  }
  if (name.endsWith('.html')) {
    const helper = '<script>window.__sdPagesUrl=function(u){if(typeof u!=="string")return u;try{var v=new URL(u,location.href);if(v.origin===location.origin&&!v.pathname.startsWith("' + base + '/")&&v.pathname!=="' + base + '")return "' + base + '"+v.pathname+v.search+v.hash}catch(e){}return u};document.addEventListener("click",function(e){var a=e.target.closest&&e.target.closest("a[href]");if(a){var u=a.getAttribute("href");if(u&&u.startsWith("/")&&!u.startsWith("//"))a.setAttribute("href",window.__sdPagesUrl(u))}},true);</script>';
    // Auch kopierte Links und „in neuem Tab öffnen“ müssen stimmen. Der bisherige
    // Klick-Handler reparierte dynamisch erzeugte hrefs erst beim normalen Klick.
    const linkRepair = `<script>(()=>{
      const fix=a=>{const u=a.getAttribute('href');if(!u||!(u.startsWith('/')||u.startsWith(location.origin+'/')))return;const v=window.__sdPagesUrl(u);if(v!==u)a.setAttribute('href',v)};
      const scan=node=>{if(node.nodeType!==1)return;if(node.matches('a[href]'))fix(node);node.querySelectorAll('a[href]').forEach(fix)};
      new MutationObserver(records=>{for(const record of records){if(record.type==='attributes')fix(record.target);else record.addedNodes.forEach(scan)}}).observe(document.documentElement,{subtree:true,childList:true,attributes:true,attributeFilter:['href']});
      document.addEventListener('DOMContentLoaded',()=>scan(document.documentElement),{once:true});
    })();</script>`;
    text = text.replace('<head>', '<head>' + helper + linkRepair);
  }
  return text;
}

export async function readTree(dir, prefix = '') {
  const files = new Map();
  for (const item of await readdir(dir, { withFileTypes: true })) {
    const name = prefix + item.name;
    if (item.isSymbolicLink()) throw new Error('Verknüpfungen werden nicht veröffentlicht: ' + name);
    if (item.isDirectory()) {
      for (const entry of await readTree(path.join(dir, item.name), name + '/')) files.set(...entry);
    } else if (item.isFile()) files.set(name, await readFile(path.join(dir, item.name)));
    else throw new Error('Unbekannter Dateityp: ' + name);
  }
  return files;
}

function safeOutputName(name) {
  if (name.includes('\\') || name.startsWith('/') || name.split('/').some(part => !part || part === '.' || part === '..')) {
    throw new Error('Unsicherer Ausgabepfad: ' + name);
  }
}

export async function createOutput(base = '') {
  validateBase(base);
  const files = new Map();
  function add(name, bytes) {
    safeOutputName(name);
    if (files.has(name)) throw new Error('Doppelte Ausgabedatei: ' + name);
    files.set(name, Buffer.isBuffer(bytes) ? bytes : Buffer.from(bytes));
  }
  for (const directory of ['public', 'vendor/website']) {
    for (const [name, bytes] of await readTree(path.join(ROOT, directory))) add(name, bytes);
  }
  // Kleine lesbare Ergänzung zum übernommenen Position-Hold-Stylesheet.
  files.set('assets/positionhold.css', Buffer.concat([files.get('assets/positionhold.css'), Buffer.from('\n'), await readFile(path.join(ROOT, 'src/positionhold-motion.css'))]));
  for (const [name, bytes] of await readTree(path.join(ROOT, 'content'))) {
    if (!name.endsWith('.json')) throw new Error('In content sind nur JSON-Daten erlaubt.');
    const parsed = JSON.parse(bytes.toString('utf8'));
    if (!Array.isArray(parsed.items)) throw new Error('Datensatz ohne items: ' + name);
    add('data/' + name, bytes);
  }
  for (const name of ['dealer-link.js', 'dealer-portal.js', 'dealer-server.js', 'dealer-portal.css', 'admin-model.js', 'admin-portal.js', 'admin-server.js', 'newsletter-admin.js', 'admin-portal.css']) add('assets/' + name, await readFile(path.join(ROOT, 'src', name)));
  add('admin/index.html', await readFile(path.join(ROOT, 'src/admin-portal.html')));
  const dealerShell = await readFile(path.join(ROOT, 'src/dealer-portal.html'), 'utf8');
  for (const lang of ['de', 'en', 'fr', 'nl', 'es']) add((lang === 'de' ? '' : lang + '/') + 'haendlerbereich/index.html', dealerShell.replaceAll('%%LANG%%', lang));
  add('preview.js', await readFile(path.join(ROOT, 'src/preview.js')));
  add('assets/module-allocation.js', await readFile(path.join(ROOT, 'src/module-allocation.js')));
  add('assets/pdf-pagination.js', await readFile(path.join(ROOT, 'src/pdf-pagination.js')));
  const apps = JSON.parse(await readFile(path.join(ROOT, 'src/apps.json'), 'utf8'));
  const routes = JSON.parse(await readFile(path.join(ROOT, 'src/routes.json'), 'utf8'));
  validateRoutes(routes, apps);
  const shell = await readFile(path.join(ROOT, 'src/page.html'), 'utf8');
  for (const route of routes) {
    const values = { htmlLang: route.htmlLang, ...apps[route.application] };
    for (const asset of [values.entry, values.stylesheet]) {
      if (!files.has(asset?.slice(1))) throw new Error('Seitenressource fehlt: ' + asset);
    }
    const html = shell.replace(/\{\{(\w+)\}\}/g, (_, key) => {
      if (!Object.hasOwn(values, key)) throw new Error('Unbekannter Platzhalter: ' + key);
      return String(values[key]).replaceAll('&', '&amp;').replaceAll('"', '&quot;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
    });
    add(route.path.slice(1) + 'index.html', html.replaceAll('\n', route.lineEnding === 'crlf' ? '\r\n' : '\n'));
  }
  if (base) {
    for (const [name, bytes] of files) if (TEXT.test(name)) files.set(name, Buffer.from(adaptForBase(bytes.toString('utf8'), name, base)));
    add('.nojekyll', '');
  }
  return files;
}

export async function build({ pages = false } = {}) {
  // Erst alle Quellen prüfen. Bei einem Fehler bleibt der vorherige Bau bestehen.
  const files = await createOutput(pages ? PAGES_BASE : '');
  const target = path.resolve(ROOT, pages ? '_site' : 'dist');
  if (path.dirname(target) !== path.resolve(ROOT)) throw new Error('Ausgabe liegt außerhalb des Repositorys.');
  const existing = await lstat(target).catch(error => { if (error.code !== 'ENOENT') throw error; });
  if (existing?.isSymbolicLink() || (existing && !existing.isDirectory())) throw new Error('Ausgabe muss ein echtes Verzeichnis sein.');
  await rm(target, { recursive: true, force: true });
  for (const [name, bytes] of files) {
    const dest = path.resolve(target, name);
    if (!dest.startsWith(target + path.sep)) throw new Error('Ausgabe verlässt Zielverzeichnis.');
    await mkdir(path.dirname(dest), { recursive: true });
    await writeFile(dest, bytes);
  }
  console.log(`Build: ${files.size} Dateien → ${pages ? '_site' : 'dist'}`);
  return files;
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
  if (process.argv.slice(2).some(arg => arg !== '--pages')) throw new Error('Aufruf: node tools/build.mjs [--pages]');
  await build({ pages: process.argv.includes('--pages') });
}
