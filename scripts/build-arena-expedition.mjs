// Extract only Math Expedition authored content. Never execute source SQL.
import {readFileSync} from 'node:fs';
import {createHash} from 'node:crypto';
import assert from 'node:assert/strict';
const root=process.argv[2];assert.ok(root);
const file='2026-08-15a_math_expedition.sql',text=readFileSync(`${root}/sql/${file}`,'utf8');
const quoted="'((?:''|\\\\.|[^'\\\\])*)'";
const strings=s=>[...s.matchAll(new RegExp(quoted,'g'))].map(m=>m[1].replace(/''/g,"'").replace(/\\'/g,"'").replace(/\\n/g,'\n'));
const levels=text.split('INSERT INTO math_expedition_levels')[1].split('ON DUPLICATE KEY')[0];
const packs=[];
for(const line of levels.split('\n').filter(s=>s.startsWith("('"))){
 const values=strings(line),level=Number(line.match(/^\('[^']+',([0-9]+)/)[1]);
 packs.push({slug:'ekspedisi-'+values[0],type:'expedition',title:`Math Expedition ${level}: ${values[1]}`,stage:level<=2?'early_childhood':'primary',level,subtitle:values[2],skill:values[3],description:values[4],items:[]});
}
assert.equal(packs.length,12);
for(const line of text.split('\n').filter(s=>s.startsWith("SELECT id,'"))){
 const [code,prompt,optionsJson,answer,hint,hintDeeper,explanation,tip,levelCode]=strings(line);
 const pack=packs.find(p=>p.slug==='ekspedisi-'+levelCode);assert.ok(pack,code);
 const options=JSON.parse(optionsJson);assert.ok(options[answer]);assert.equal(Object.keys(options).length,4);
 pack.items.push({code,definition:prompt,term:answer,options,hint,hint_deeper:hintDeeper,explanation,tip});
}
for(const pack of packs)assert.equal(pack.items.length,5,pack.slug);
process.stdout.write(JSON.stringify({version:1,source:'Pustaka Math Expedition, adapted for Project Pintar; no session-exercise bank.',sources:[{file,sha256:createHash('sha256').update(text).digest('hex')}],packs},null,2)+'\n');
