// Nur Importvorschau: keine Datenbankverbindung und kein SQL-Versand.
import {readFile,writeFile,realpath} from 'node:fs/promises';
import {pathToFileURL,fileURLToPath} from 'node:url';
import path from 'node:path';
export function prepareDealerImport(rows){
 if(!Array.isArray(rows))throw Error('Export muss eine JSON-Liste sein.');
 const seen=new Set(),countries={Germany:'DE',Deutschland:'DE',France:'FR',Spain:'ES',Australia:'AU',Switzerland:'CH'};
 const dealers=rows.map((r,index)=>{
  if(!r||typeof r!=='object'||Array.isArray(r))throw Error('Ungültiger Datensatz '+(index+1));
  for(const key of ['id','name','address','country','email'])if(typeof r[key]!=='string'||!r[key].trim())throw Error('Pflichtfeld fehlt in Datensatz '+(index+1)+': '+key);
  if(r.collectionName!=='dealers')throw Error('Unerwartete Quellsammlung.');
  if(seen.has(r.id))throw Error('Doppelte Quellkennung.');seen.add(r.id);
  if(!/^[a-zA-Z0-9_-]{1,64}$/.test(r.id))throw Error('Ungültige Quellkennung.');
  if(!Object.hasOwn(countries,r.country))throw Error('Länderzuordnung fehlt für Datensatz '+(index+1));
  if(!Number.isFinite(r.latitude)||Math.abs(r.latitude)>90||!Number.isFinite(r.longitude)||Math.abs(r.longitude)>180)throw Error('Ungültige Koordinaten in Datensatz '+(index+1));
  if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(r.email)||r.email.length>254)throw Error('Ungültige öffentliche E-Mail in Datensatz '+(index+1));
  for(const key of ['name','name_de','name_en','name_fr'])if(String(r[key]??'').length>254)throw Error('Firmenname zu lang.');
  for(const key of ['website','website2'])if(r[key]){const u=new URL(r[key]);if(!['http:','https:'].includes(u.protocol)||r[key].length>2048)throw Error('Ungültige Website.');}
  if(r.phone&&((typeof r.phone!=='string')||r.phone.length>100))throw Error('Ungültige Telefonnummer.');
  for(const key of ['created','updated'])if(typeof r[key]!=='string'||!Number.isFinite(Date.parse(r[key])))throw Error('Ungültiger Quellzeitpunkt.');
  return {source_system:'hostinger-horizons',source_id:r.id,company_name:r.name,name_de:r.name_de||null,name_en:r.name_en||null,name_fr:r.name_fr||null,original_address:r.address,country_code:countries[r.country],original_country:r.country,public_email:r.email,public_phone:r.phone||null,website:r.website||null,website_secondary:r.website2||null,latitude:r.latitude,longitude:r.longitude,source_created_at:new Date(r.created).toISOString(),source_updated_at:new Date(r.updated).toISOString(),status:'active',finder_status:'unlisted'};
 });
 return {mode:'dry-run',requiresApproval:true,counts:{dealers:dealers.length,users:0,memberships:0,newsletterSubscriptions:0,invitations:0},warnings:['Kartenfreigabe bestehender Händler noch bestätigen.','Internationale Anschriften bleiben unverändert; getrennte Adressfelder später prüfen.','Keine Benutzerkonten oder Newslettereinwilligungen aus Firmenkontakten ableiten.'],dealers};
}
if(process.argv[1]&&import.meta.url===pathToFileURL(path.resolve(process.argv[1])).href){
 try{const [source,target]=process.argv.slice(2);if(!source||!target||process.argv.length!==4)throw Error('Aufruf: node tools/prepare-dealer-import.mjs QUELLE.json PRIVATE_AUSGABE.json');
 const root=await realpath(fileURLToPath(new URL('../',import.meta.url))),dest=path.resolve(await realpath(path.dirname(path.resolve(target))),path.basename(target)),rel=path.relative(root,dest);
 if(!rel.startsWith('..'+path.sep)&&rel!=='..')throw Error('Importvorschau außerhalb des öffentlichen Repositorys speichern.');
 if(path.resolve(source)===dest)throw Error('Quelle darf nicht überschrieben werden.');
 const prepared=prepareDealerImport(JSON.parse(await readFile(source,'utf8')));await writeFile(dest,JSON.stringify(prepared,null,2)+'\n',{flag:'wx',mode:0o600});
 console.log(JSON.stringify({mode:prepared.mode,counts:prepared.counts,output:dest}));
 }catch(error){console.error(error.message);process.exitCode=1;}
}
