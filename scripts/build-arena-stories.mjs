import {readFileSync} from 'node:fs';
import {createHash} from 'node:crypto';
import assert from 'node:assert/strict';
const root=process.argv[2];assert.ok(root);
const sources=[],packs=[];
const load=file=>{const text=readFileSync(`${root}/sql/${file}`,'utf8');sources.push({file,sha256:createHash('sha256').update(text).digest('hex')});return text;};
const q="'((?:''|\\\\.|[^'\\\\])*)'";
const decode=s=>s.replace(/''/g,"'").replace(/\\'/g,"'").replace(/\\n/g,'\n');
const text=load('2026-08-13c_story_quiz_catalog.sql');
for(const m of text.matchAll(new RegExp('\\('+[q,q,q,q,'\\d+','(\\d+)'].join(',')+',','g'))){
 const [,code,title,body,summary,gradeText]=m,grade=Number(gradeText);if(grade>11)continue;
 packs.push({slug:'cerita-'+code.replaceAll('_','-'),code,type:'story',title:decode(title),stage:grade<=8?'primary':'secondary',reading:decode(body),description:decode(summary),items:[]});
}
for(const m of text.matchAll(new RegExp('\\('+[q,q,q,q,q,q,'(\\d+)',q,'\\d+'].join(',')+'\\)','g'))){
 const [,code,prompt,a,b,c,d,correct,explanation]=m,pack=packs.find(p=>p.code===code);if(!pack)continue;
 pack.items.push({definition:decode(prompt),options:Object.fromEntries(['A','B','C','D'].map((key,i)=>[key,decode([a,b,c,d][i])])),term:['A','B','C','D'][Number(correct)],explanation:decode(explanation)});
}
assert.equal(packs.length,9);for(const pack of packs)assert.equal(pack.items.length,2);
const starter=load('2026-08-06b_story_quiz.sql');
const passage=starter.split('VALUES (')[1].split('ON DUPLICATE KEY')[0];
const values=[...passage.matchAll(new RegExp(q,'g'))].map(m=>decode(m[1]));
assert.equal(values[0],'semut_belalang');
const pack={slug:'cerita-semut-belalang',type:'story',title:values[1],stage:'primary',reading:values[2],description:values[3],items:[]};
for(const m of starter.matchAll(new RegExp('\\(@pid,\\s*'+[q,q,q,q,q,'(\\d+)',q,'\\d+'].join(',\\s*')+'\\)','g'))){
 const [,prompt,a,b,c,d,correct,explanation]=m;
 pack.items.push({definition:decode(prompt),options:Object.fromEntries(['A','B','C','D'].map((key,i)=>[key,decode([a,b,c,d][i])])),term:['A','B','C','D'][Number(correct)],explanation:decode(explanation)});
}
assert.equal(pack.items.length,3);packs.unshift(pack);
process.stdout.write(JSON.stringify({version:1,source:'Pustaka curated Story Quiz texts; excludes SMA and generated 5x5 filler, not the session-exercise bank.',sources,packs},null,2)+'\n');
