// Extract authored flashcard content only; never execute Pustaka SQL.
import {readFileSync} from 'node:fs';
import {createHash} from 'node:crypto';
import assert from 'node:assert/strict';
const root=process.argv[2];assert.ok(root);
const sources=[],packs=[];
const load=file=>{const text=readFileSync(`${root}/sql/${file}`,'utf8');sources.push({file,sha256:createHash('sha256').update(text).digest('hex')});return text;};
function fields(line){return [...line.matchAll(/'(?:''|\\.|[^'\\])*'|\bNULL\b|\b\d+\b/g)].map(m=>m[0][0]==="'"?m[0].slice(1,-1).replace(/''/g,"'").replace(/\\'/g,"'"):m[0]==='NULL'?'':Number(m[0]));}
const starter=load('2026-08-06a_flashcards.sql');
const base={slug:'kartu-ipa-dasar',code:'ipa_dasar',type:'flashcard',title:'Sains Kelas 5 — Alam dan Energi',stage:'primary',items:[]};
for(const line of starter.split('\n').filter(s=>s.trim().startsWith('(@deck,'))){const [term,definition,hint]=fields(line);base.items.push({term,definition,hint});}
assert.equal(base.items.length,5);packs.push(base);
for(const file of ['2026-08-13a_flashcard_catalog_expansion.sql','2026-08-13b_flashcard_secondary_school.sql']){
 const text=load(file),decks=text.split('INSERT INTO learn_flashcard_decks')[1].split('ON DUPLICATE KEY')[0];
 for(const line of decks.split('\n').filter(s=>s.startsWith("('"))){const [code,title,description,subject,grade]=fields(line);if(grade>11)continue;packs.push({slug:'kartu-'+code.replaceAll('_','-'),code,type:'flashcard',title,description,stage:grade<=8?'primary':'secondary',items:[]});}
 // Semicolons can occur inside card text: use lines up to the next SQL statement instead.
 const start=text.search(/INSERT INTO tmp_flashcards[^\n]* VALUES/);
 const lines=text.slice(start).split('\n');
 for(const line of lines){if(!line.startsWith("('"))continue;const [code,term,definition,hint]=fields(line);const pack=packs.find(p=>p.code===code);if(pack)pack.items.push({term,definition,hint});}
}
assert.equal(packs.length,14);assert.equal(packs.reduce((n,p)=>n+p.items.length,0),151);
for(const pack of packs){assert.ok(pack.items.length>=10);for(const item of pack.items){assert.equal(typeof item.term,'string');assert.equal(typeof item.definition,'string');item.key=createHash('sha256').update(JSON.stringify([item.term,item.definition])).digest('hex');}}
process.stdout.write(JSON.stringify({version:1,source:'Pustaka flashcard catalog SD/SMP; no user records, no lesson quiz bank.',sources,packs},null,2)+'\n');
