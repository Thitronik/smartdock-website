import {execFileSync} from 'node:child_process';
import {readdir} from 'node:fs/promises';
import path from 'node:path';
const executable=process.env.PHP_WASM_CLI?process.execPath:(process.env.PHP_BINARY||'php');
const prefix=process.env.PHP_WASM_CLI?[process.env.PHP_WASM_CLI]:[];
function php(args){const output=execFileSync(executable,[...prefix,...args],{encoding:'utf8',env:{...process.env,PHP:'8.4'}});if(/(?:Fatal error|Warning|Parse error):/.test(output))throw Error(output);return output;}
async function files(dir){const result=[];for(const e of await readdir(dir,{withFileTypes:true})){const name=path.join(dir,e.name);if(e.isDirectory())result.push(...await files(name));else if(name.endsWith('.php'))result.push(name);}return result;}
for(const file of await files('backend'))php(['-l',file]);
const output=php(['backend/tests/security.test.php']);
if(!output.includes('41 security checks passed.'))throw Error(output);
console.log(output.trim());
console.log('PHP syntax checks passed.');
