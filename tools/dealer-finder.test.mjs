import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
import * as helper from '../src/dealer-finder.js';

const source = await readFile(new URL('../vendor/website/assets/index-RRVGC1mK.js', import.meta.url), 'utf8');
const dealers = JSON.parse(await readFile(new URL('../content/dealers.json', import.meta.url), 'utf8')).items;
const empty = { country: '', city: '', postalCode: '' };

async function app({ language = 'DE', loadError = false, geolocation } = {}) {
  const first = source.indexOf('y5=e=>'), last = source.indexOf(';var Ov=', first);
  assert.ok(first > 0 && last > first);
  const code = source.slice(first, last), slots = [], effects = [];
  let cursor = 0;
  const context = {
    console: { error() {} }, Intl, navigator: { geolocation }, window: { innerWidth: 375 },
    jv: { divIcon: options => options },
    sdDealerCoordinates: helper.coordinates, sdFilterDealers: helper.filterDealers,
    sdSyncDealerMap: helper.syncDealerMap, sdRequestDealerLocation: helper.requestDealerLocation,
    sdDealerMessage: helper.dealerMessage,
    j: {
      useState(initial) { const i = cursor++; slots[i] ??= { value: initial }; return [slots[i].value, value => { slots[i].value = typeof value === 'function' ? value(slots[i].value) : value; }]; },
      useMemo: fn => fn(),
      useEffect(fn, deps) { const i = cursor++; const old = slots[i]; if (!old || deps.some((x, n) => x !== old[n])) effects.push(fn); slots[i] = deps; },
    },
    r: { jsx: (type, props) => ({ type, props }), jsxs: (type, props) => ({ type, props }), Fragment: 'fragment' },
    vn: () => ({ t: key => key, currentLanguage: language }), ai() {}, Fn() {}, fn: () => '/',
    Sc: { collection: () => ({ getFullList: async () => { if (loadError) throw new Error('synthetic load failure'); return [...dealers].sort((a,b) => a.name.localeCompare(b.name)); } }) },
  };
  for (const [, name] of code.matchAll(/r\.jsx[s]?\(([\w$]+),/g)) if (!(name in context)) context[name] = name;
  vm.createContext(context);
  vm.runInContext('const ' + code + ';globalThis.parts={Jh,_5,Y$,qb,w5};', context);
  const render = () => { cursor = 0; return context.parts.Jh(); };
  render(); effects.splice(0).forEach(fn => fn());
  await new Promise(resolve => setImmediate(resolve));
  return { render, ...context.parts };
}

function nodes(tree) {
  if (!tree || typeof tree !== 'object') return [];
  if (Array.isArray(tree)) return tree.flatMap(nodes);
  return [tree, ...nodes(tree.props?.children)];
}
const by = (tree, predicate) => { const result = nodes(tree).find(x => predicate(x.props || {}, x.type)); assert.ok(result, 'Bedienelement fehlt'); return result.props; };
const cards = tree => nodes(tree).filter(x => x.props?.dealer).map(x => x.props);

test('Händlerfilter: neun Datensätze, fünf Länder, jede Stadt/PLZ, Kombination und Leerzustand', async () => {
  const { w5 } = await app();
  assert.equal(dealers.length, 9);
  for (const [country, count] of [['DE',5],['CH',1],['FR',1],['ES',1],['AU',1]])
    assert.equal(helper.filterDealers(dealers, { ...empty, country }, null, w5).length, count);
  for (const [city, postalCode] of [['Schortens','26419'],['Eckernförde','24340'],['Heist','25492'],['Vierkirchen','85256'],['Hittnau','8335'],['Barth','18356'],['Coomera','4209'],['Santanyí','07660'],['Hendaye','64700']]) {
    const result = helper.filterDealers(dealers, { ...empty, city: ' '+city.toUpperCase()+' ', postalCode }, null, w5);
    assert.equal(result.length, 1, city);
  }
  assert.equal(helper.filterDealers(dealers, { ...empty, country:'CH', city:'Eckernförde' }, null, w5).length, 0);
  assert.equal(helper.filterDealers(dealers, { ...empty, postalCode:'00000' }, null, w5).length, 0);
});

test('Distanz und Kartenkoordinaten: Nullmeridian gültig, leere/ungültige Werte ausgeschlossen', () => {
  assert.deepEqual(helper.coordinates({ latitude:0, longitude:0 }), [0,0]);
  for (const latitude of ['', null, undefined, 'NaN', '48x', 91, Infinity]) assert.equal(helper.coordinates({latitude,longitude:0}),null);
  assert.equal(helper.coordinates({latitude:0,longitude:181}),null);
  assert.equal(helper.distanceKm(0,0,0,0),0);
  assert.ok(Math.abs(helper.distanceKm(0,0,0,1)-111.195)<0.001);
  assert.equal(helper.distanceKm(0,0,null,1),Infinity);
  const sorted=helper.filterDealers([...dealers,{name:'Ohne Koordinaten',latitude:null,longitude:null}],empty,{lat:54.490331,lng:9.802934},()=>null);
  assert.equal(sorted[0].name,'Thitronik GmbH');
  assert.equal(sorted[0].distance,0);
  assert.equal(sorted.at(-1).name,'Ohne Koordinaten');
});

test('Standort: Erfolg, Verweigerung, Timeout, nicht verfügbar und fehlende Browserfunktion', () => {
  let success, failure, options;
  helper.requestDealerLocation({getCurrentPosition(ok, no, opts){options=opts;ok({coords:{latitude:0,longitude:0}});}},x=>success=x,x=>failure=x);
  assert.deepEqual(success,{lat:0,lng:0}); assert.equal(failure,undefined); assert.equal(options.timeout,10000);
  for (const [code,expected] of [[1,'denied'],[2,'unavailable'],[3,'timeout']]) {
    helper.requestDealerLocation({getCurrentPosition(ok,no){no({code});}},()=>assert.fail(),x=>failure=x);
    assert.equal(failure,expected);
  }
  helper.requestDealerLocation(null,()=>assert.fail(),x=>failure=x); assert.equal(failure,'unsupported');
  helper.requestDealerLocation({getCurrentPosition(ok){ok({coords:{latitude:NaN,longitude:9}});}},()=>assert.fail(),x=>failure=x); assert.equal(failure,'unavailable');
  helper.requestDealerLocation({getCurrentPosition(){throw new Error('disabled');}},()=>assert.fail(),x=>failure=x); assert.equal(failure,'unavailable');
});

test('Versteckte Karte: erst nach Einblenden zentrieren, Größenwechsel und Aufräumen ohne flyTo', () => {
  const container={clientWidth:0,clientHeight:0}, calls=[]; let resized, disconnected=false;
  class Observer { constructor(fn){resized=fn;} observe(node){assert.equal(node,container);} disconnect(){disconnected=true;} }
  const map={_loaded:true,_mapPane:{_leaflet_pos:{x:0,y:0}},getContainer:()=>container,stop(){assert.ok(this._mapPane);calls.push('stop');},invalidateSize(){calls.push('invalidate');},setView(point){calls.push(point);},fitBounds(points){calls.push(points);}};
  const stop=helper.syncDealerMap(map,dealers[1],dealers,Observer);
  assert.deepEqual(calls,[]);
  container.clientWidth=343;container.clientHeight=800;resized();
  assert.deepEqual(calls.at(-1),[54.490331,9.802934]);
  container.clientWidth=0;const length=calls.length;resized();assert.equal(calls.length,length);
  stop();assert.equal(disconnected,true);const closed=calls.length;resized();assert.equal(calls.length,closed);
  container.clientWidth=343;const remove=helper.syncDealerMap(map,null,[dealers[0],{latitude:'bad',longitude:9}],Observer);
  assert.deepEqual(calls.at(-1),[[53.541462,7.965069]]);
  map._mapPane=undefined;resized();remove();
});

test('Echte Komponente: Filter, Auswahl, Filterwechsel und leere Trefferliste', async () => {
  const a=await app(); let tree=a.render(); assert.equal(cards(tree).length,9);
  cards(tree)[0].onSelect(cards(tree)[0].dealer); tree=a.render();assert.equal(cards(tree).filter(x=>x.isSelected).length,1);
  by(tree,p=>p.id==='dealer-country').onChange({target:{value:'DE'}});tree=a.render();
  assert.equal(cards(tree).length,5); assert.equal(cards(tree).filter(x=>x.isSelected).length,0);
  by(tree,p=>p.id==='dealer-city').onChange({target:{value:' Eckernförde '}});tree=a.render();assert.equal(cards(tree)[0].dealer.name,'Thitronik GmbH');
  by(tree,p=>p.id==='dealer-postalCode').onChange({target:{value:'00000'}});tree=a.render();assert.equal(cards(tree).length,0);
  assert.ok(nodes(tree).some(x=>x.props?.children==='df_empty'));
});

test('Echte Standort-Bedienung: Erfolg sortiert, erneute Filterung beendet Standortmodus', async () => {
  const a=await app({geolocation:{getCurrentPosition(ok){ok({coords:{latitude:54.490331,longitude:9.802934}});}}});let tree=a.render();
  by(tree,p=>p['aria-busy']!==undefined).onClick();tree=a.render();assert.equal(cards(tree)[0].dealer.name,'Thitronik GmbH');assert.equal(cards(tree)[0].dealer.distance,0);
  by(tree,p=>p.id==='dealer-country').onChange({target:{value:'CH'}});tree=a.render();assert.equal(cards(tree).length,1);assert.equal(cards(tree)[0].dealer.distance,undefined);
});

test('Fehlermeldungen und Sprachwechsel in allen fünf Sprachen; kein stiller Standortfehler', async () => {
  for(const language of ['DE','EN','FR','NL','ES']) {
    const a=await app({language,geolocation:{getCurrentPosition(ok,no){no({code:1});}}});let tree=a.render();
    by(tree,p=>p['aria-busy']!==undefined).onClick();tree=a.render();
    assert.ok(by(tree,p=>p.role==='alert').children.includes(helper.dealerMessage(language,'denied')));
    assert.equal(by(tree,p=>p['aria-busy']!==undefined).disabled,false);
    assert.equal(by(tree,(p,type)=>type==='html').lang,language.toLowerCase());
    by(tree,p=>p.id==='dealer-city').onChange({target:{value:'Heist'}});tree=a.render();assert.equal(cards(tree).length,1);assert.equal(nodes(tree).some(x=>x.props?.role==='alert'),false);
    for(const key of ['timeout','unavailable','unsupported','loading','loadError','location'])assert.ok(helper.dealerMessage(language,key));
  }
});

test('Echte Händlerkarte: Enter/Leertaste wählen; eingebettete Links bleiben eigenständig', async () => {
  const a=await app();let count=0,prevented=0; const card=a._5({dealer:dealers[0],isSelected:true,onSelect:()=>count++,t:x=>x,displayName:dealers[0].name});
  assert.equal(card.props.role,'button');assert.equal(card.props.tabIndex,0);assert.equal(card.props['aria-pressed'],true);
  const target={};for(const key of ['Enter',' ','Escape'])card.props.onKeyDown({key,target,currentTarget:target,preventDefault(){prevented++;}});
  card.props.onKeyDown({key:'Enter',target:{},currentTarget:target,preventDefault(){prevented++;}});
  assert.equal(count,2);assert.equal(prevented,2);
});

test('Echte Kartenmarker: Mausklick und Enter wählen denselben Händler', async () => {
  const a=await app();let tree=a.render();
  const marker=by(tree,p=>p.eventHandlers&&p.title==='Thitronik GmbH');
  marker.eventHandlers.keypress({originalEvent:{key:'Enter'}});tree=a.render();
  assert.equal(cards(tree).find(x=>x.isSelected).dealer.name,'Thitronik GmbH');
  by(tree,p=>p.eventHandlers&&p.title==='CPS TEAM GmbH').eventHandlers.click();tree=a.render();
  assert.equal(cards(tree).find(x=>x.isSelected).dealer.name,'CPS TEAM GmbH');
});

test('Beide Karten verwenden die abgesicherte Synchronisierung; Ladefehler werden angezeigt', async () => {
  assert.match(source,/O\$=\(\{center:e,markers:t\}\)=>\{const n=j4\(\);return j.useEffect\(\(\)=>sdSyncDealerMap\(n,e,t\)/);
  const a=await app({language:'EN',loadError:true});assert.ok(by(a.render(),p=>p.role==='alert').children.includes(helper.dealerMessage('EN','loadError')));
});

test('Standortaktion im Konfigurator zeigt Fehler und übernimmt eine gültige Position', () => {
  const start=source.indexOf('qe=()=>{Ae("");sdRequestDealerLocation('),end=source.indexOf(',$e=j.useMemo',start);
  assert.ok(start>0&&end>start);
  for(const scenario of ['success','denied']) {
    let location,error,filters,view;
    const context={H:'DE',sdRequestDealerLocation:helper.requestDealerLocation,sdDealerMessage:helper.dealerMessage,
      navigator:{geolocation:{getCurrentPosition(ok,no){scenario==='success'?ok({coords:{latitude:0,longitude:0}}):no({code:1});}}},
      Ae:value=>error=value,Z:value=>location=value,oe:value=>filters=value,se:value=>view=value};
    vm.runInNewContext('const '+source.slice(start,end)+';qe();',context);
    if(scenario==='success'){assert.deepEqual(location,{lat:0,lng:0});assert.equal(view,'list');assert.equal(filters.country,'');}
    else{assert.equal(error,helper.dealerMessage('DE','denied'));assert.equal(location,undefined);}
  }
});
