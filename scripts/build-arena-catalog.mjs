// Read-only extraction of the owner's Pustaka mini-game text catalog.
// Prints JSON only; never connects to Pustaka's database or executes its SQL.
import {readFileSync} from 'node:fs';
import {createHash} from 'node:crypto';
import assert from 'node:assert/strict';
const root=process.argv[2];
assert.ok(root, 'Supply the Pustaka root');
const packs=[], sources=[];
function source(file){const text=readFileSync(`${root}/sql/${file}`,'utf8');sources.push({file,sha256:createHash('sha256').update(text).digest('hex')});return text;}
for(const [file,type,prefix] of [['2026-08-12k_word_scramble_seed.sql','word','kata'],['2026-08-12j_memory_match_seed.sql','memory','pasangan']]){
 const text=source(file);
 const blocks=[...text.matchAll(/CROSS JOIN \(SELECT ([\s\S]*?)\) v\s+JOIN learn_game_categories[\s\S]*?s\.name\s*=\s*'([^']+)'/g)];
 assert.equal(blocks.length,type==='word'?5:6);
 for(const [i,block] of blocks.entries()){
  const items=[...block[1].matchAll(/'([^']+)'\s*(?:term)?\s*,\s*'([^']+)'\s*(?:definition)?\s*,\s*\d+/g)].map(m=>({term:m[1],definition:m[2]}));
  assert.equal(items.length,type==='word'?10:8);
  packs.push({slug:`${prefix}-dasar-${i+1}`,type,title:block[2],stage:i===0?'early_childhood':i===blocks.length-1?'secondary':'primary',items});
 }
}
const expansion=source('2026-08-13e_word_scramble_sd_sma.sql');
const terms=expansion.split('SET terms=CASE g')[1].split('SET clues=CASE g');
for(let grade=3;grade<=11;grade++){
 const pattern=new RegExp(`WHEN ${grade} THEN '([^']+)'`);
 const words=terms[0].match(pattern)[1].split('|'), clues=terms[1].match(pattern)[1].split('|');
 assert.equal(words.length,10);assert.equal(clues.length,10);
 const label=grade<=8?`SD kelas ${grade-2}`:`SMP kelas ${grade-8}`;
 for(const type of ['word','memory']) packs.push({slug:`${type}-${grade<=8?'sd-'+(grade-2):'smp-'+(grade-8)}`,type,title:`${type==='word'?'Susun Kata':'Pasangan'} ${label}`,stage:grade<=8?'primary':'secondary',items:words.map((term,i)=>({term,definition:clues[i]}))});
}
source('2026-08-13f_memory_match_sd_sma.sql');
// Correct one ambiguous animal clue; no external media or third-party assets.
packs.find(p=>p.slug==='kata-dasar-1').items.find(i=>i.term==='AYAM').definition='Hewan unggas; pejantannya berkokok';
const levels=[['Pemanasan','primary',60,['+'],10],['Pelari Angka','primary',60,['+','-'],20],['Penakluk Puluhan','primary',60,['+','-'],50],['Master Perkalian','primary',60,['*'],10],['Pembagi Andal','primary',65,['/'],100],['Operasi Campuran','secondary',75,['+','-','*','/'],50],['Arena Ratusan','secondary',75,['+','-'],500],['Grandmaster','secondary',90,['+','-','*','/'],1000]];
for(const [i,[title,stage,duration,operators,max]] of levels.entries()) packs.push({slug:`hitung-${i+1}`,type:'math',title:`Hitung Cepat ${i+1}: ${title}`,stage,level:i+1,duration,operators,max});
assert.equal(packs.length,37);
process.stdout.write(JSON.stringify({version:1,source:'Pustaka mini games; adopted for Project Pintar, 2026-10-16. No exercise-session bank or personal data.',sources,packs},null,2)+'\n');
