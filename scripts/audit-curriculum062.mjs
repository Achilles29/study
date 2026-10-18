// Read-only milestone audit. No UPDATE/INSERT/DELETE, no account/payment selects.
import assert from 'node:assert/strict';import {execFileSync as run} from 'node:child_process';
const db=process.env.STUDY_CURRICULUM_AUDIT_DB;assert.ok(['db_study','study_qa_monitor_sso039'].includes(db));
const sql=q=>run('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','-r',db],{input:q,encoding:'utf8',maxBuffer:8*1024*1024}).trim();
const rows=q=>{const s=sql(q);return s?s.split('\n').map(x=>JSON.parse(x)):[];};
let courseCount=0,lessonCount=0,cardCount=0,questionCount=0,chapterCount=0;
for(let n=50;n<=62;n++){
 const batch=String(n).padStart(3,'0');
 const manifests=JSON.parse(run('/www/server/php/84/bin/php',['-r',"define('BASEPATH',getcwd().'/system/');define('APPPATH',getcwd().'/application/');require APPPATH.'libraries/Curriculum_batch.php';echo json_encode((new Curriculum_batch)->load($argv[1]));",batch],{encoding:'utf8',maxBuffer:8*1024*1024}));
 for(const m of manifests){
  assert.match(m.slug,/^[a-z0-9-]+$/);
  const [c]=rows(`SELECT JSON_OBJECT('id',c.id,'title',c.title,'status',c.status,'summary',c.summary,'objectives',c.learning_objectives,'prerequisites',c.prerequisites,'source',c.source_note,'free',c.is_free,'grade',g.code,'category',x.slug) FROM st_courses c JOIN st_learning_grades g ON g.id=c.grade_id JOIN st_categories x ON x.id=c.category_id WHERE c.slug='${m.slug}'`);assert.ok(c,m.slug);assert.equal(c.status,'published');assert.equal(Number(c.free),0);
  for(const k of ['title','objectives','prerequisites','source','grade','category'])assert.equal(c[k],m[k],m.slug+':'+k);
  assert.equal(c.summary,batch==='050'?m.summary.replace('Draf pengembangan, ','Materi pengembangan, '):m.summary);
  const [meta]=rows(`SELECT details_json FROM st_audit_events WHERE event_type='content.curriculum_imported' AND entity_type='course' AND entity_id=${c.id}`);assert.equal(meta.batch,batch);assert.equal(meta.key,m.key);assert.equal(meta.source_sha256,m.source_sha256);
  const sections=rows(`SELECT JSON_OBJECT('id',id,'title',title) FROM st_course_sections WHERE course_id=${c.id} ORDER BY sort_order,id`);assert.deepEqual(sections.map(s=>s.title),m.sections.map(s=>s.title));chapterCount+=sections.length;
  const expectedLessons=m.sections.flatMap(s=>s.lessons);
  const lessons=rows(`SELECT JSON_OBJECT('id',l.id,'title',l.title,'body',l.body_text,'minutes',l.estimated_minutes,'status',l.status,'preview',l.is_preview) FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id WHERE s.course_id=${c.id} ORDER BY s.sort_order,s.id,l.sort_order,l.id`);
  assert.equal(lessons.length,expectedLessons.length);
  for(const [i,l]of lessons.entries()){assert.equal(l.status,'published');assert.equal(Number(l.preview),0);assert.equal(l.title,expectedLessons[i].title);assert.equal(l.body,expectedLessons[i].body);assert.equal(Number(l.minutes),expectedLessons[i].minutes);}
  const cards=rows(`SELECT JSON_ARRAY(f.front_text,f.back_text,f.hint_text) FROM st_flashcards f JOIN st_flashcard_decks d ON d.id=f.deck_id JOIN st_lessons l ON l.id=d.lesson_id JOIN st_course_sections s ON s.id=l.section_id WHERE s.course_id=${c.id} ORDER BY s.sort_order,s.id,l.sort_order,l.id,f.sort_order,f.id`);assert.deepEqual(cards,expectedLessons.flatMap(l=>l.cards));
  const questions=rows(`SELECT JSON_OBJECT('id',id,'prompt',prompt,'explanation',explanation,'status',status,'visibility',visibility,'target',target_child_user_id) FROM st_questions WHERE course_id=${c.id} ORDER BY id`),expectedQuestions=expectedLessons.flatMap(l=>l.questions);
  assert.equal(questions.length,expectedQuestions.length);
  const options=rows(`SELECT JSON_OBJECT('question',o.question_id,'text',o.option_text,'correct',o.is_correct) FROM st_question_options o JOIN st_questions q ON q.id=o.question_id WHERE q.course_id=${c.id} ORDER BY q.id,o.sort_order,o.id`);
  for(const [i,q]of questions.entries()){const e=expectedQuestions[i];assert.equal(q.status,'published');assert.equal(q.visibility,'shared');assert.equal(q.target,null);assert.equal(q.prompt,e[0]);assert.equal(q.explanation,e[3]);const opts=options.filter(o=>Number(o.question)===Number(q.id));assert.deepEqual(opts.map(o=>o.text),e[1]);assert.deepEqual(opts.map(o=>Number(o.correct)),[0,1,2,3].map(j=>j===e[2]?1:0));}
  assert.equal(sql(`SELECT COUNT(*) FROM st_package_courses WHERE course_id=${c.id}`),'0');
  courseCount++;lessonCount+=lessons.length;cardCount+=cards.length;questionCount+=questions.length;
 }
}
assert.deepEqual([courseCount,lessonCount,cardCount,questionCount],[40,358,716,716]);
console.log(JSON.stringify({result:'PASS',database:db,courses:courseCount,chapters:chapterCount,lessons:lessonCount,cards:cardCount,questions:questionCount,checks:'exact metadata/source SHA/section-body-card-question-option-key identity, published statuses, grade/category, no unintended free/preview/package grants; historical sample course excluded'}));
