import {execFileSync} from 'node:child_process';
import {readdir,writeFile,rm} from 'node:fs/promises';
import {calculate,countries} from './fixtures/live-pricing-2026-10-22.js';
import path from 'node:path';
const executable=process.env.PHP_WASM_CLI?process.execPath:(process.env.PHP_BINARY||'php');
const prefix=process.env.PHP_WASM_CLI?[process.env.PHP_WASM_CLI]:[];
function php(args){const output=execFileSync(executable,[...prefix,...args],{encoding:'utf8',env:{...process.env,PHP:'8.4'}});if(/(?:Fatal error|Warning|Parse error):/.test(output))throw Error(output);return output;}
async function files(dir){const result=[];for(const e of await readdir(dir,{withFileTypes:true})){const name=path.join(dir,e.name);if(e.isDirectory())result.push(...await files(name));else if(name.endsWith('.php'))result.push(name);}return result;}
for(const file of await files('backend'))php(['-l',file]);
const output=php(['backend/tests/security.test.php']);
if(!output.includes('65 security checks passed.'))throw Error(output);
console.log(output.trim());
console.log('PHP syntax checks passed.');
const cases=[];
for(const country of Object.keys(countries))for(let bits=0;bits<128;bits++){
 const selected={ecu:bits&1?'2+ Motoren':null,tcu:bits&2?['Bugstrahlruder',...(bits&4?['Heckstrahlruder']:[])]:[],acu:bits&8?'2 Ankerwinden':'1 Ankerwinde',wcu:bits&16?'2 Winches':null,horn:!!(bits&32)};
 const manufacturer=bits&64?'Sleipner proportional':'';const install=!!(bits&4);const promo=bits&16?'PIBS26':'';
 const s={country,motorCount:bits&1?2:0,bugstrahlruder:!!(bits&2),heckstrahlruder:!!((bits&2)&&(bits&4)),ankerwinde1:true,ankerwinde2:!!(bits&8),ankerwinch1:!!(bits&16),ankerwinch2:!!(bits&16),horn:!!(bits&32),thrusterManufacturer:manufacturer,installationService:install?'Ja':'Nein',promoCode:promo};
 cases.push({selection:s,expected:calculate(selected,manufacturer,country,promo,install,'DE')});
}
const fixture='backend/tests/.configuration-price-cases.json';
try{await writeFile(fixture,JSON.stringify(cases));const report=php(['backend/tests/configuration.test.php']);if(!report.includes('checks passed.'))throw Error(report);console.log(report.trim());}finally{await rm(fixture,{force:true});}
