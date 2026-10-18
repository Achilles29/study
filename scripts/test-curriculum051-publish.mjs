import assert from 'node:assert/strict';
import {execFileSync,spawnSync} from 'node:child_process';
assert.equal(process.env.STUDY_MONITOR_TEST_DB,'study_qa_monitor_sso039');
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
const env={...process.env,CI_ENV:'development',DB_HOST:'localhost',DB_PORT:'3308',DB_USERNAME:'root',DB_PASSWORD:'',DB_DATABASE:'study_qa_monitor_sso039',STUDY_MEDIA_ROOT:'/www/study-media-qa047-ZPze1qZD'};
const run=(batch,mode='check',extra={})=>spawnSync('/www/server/php/84/bin/php',['-d','mysqli.default_socket=/tmp/mysql-study.sock','index.php','cli/publish_curriculum','index',batch,mode,'1'],{env:{...env,...extra},encoding:'utf8'});
const old=sql("SELECT values_json FROM st_site_settings WHERE section='pembelajaran'");
try {
 assert.match(run('050','apply').stdout,/Publication gate disabled/);
 sql("UPDATE st_site_settings SET values_json=JSON_SET(values_json,'$.solo_review','0') WHERE section='pembelajaran'");
 assert.match(run('050').stdout,/no bypass/);
 sql("UPDATE st_site_settings SET values_json=JSON_SET(values_json,'$.solo_review','1') WHERE section='pembelajaran'");
 const first=sql("SELECT MIN(l.id) FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id WHERE c.slug='matematika-kelas-1-operasi-bentuk-pengukuran-data'");
 sql(`UPDATE st_lessons SET body_text=CONCAT(body_text,'QA stale content') WHERE id=${first}`);
 try{assert.match(run('050').stdout,/Lesson content changed/);}finally{sql(`UPDATE st_lessons SET body_text=LEFT(body_text,CHAR_LENGTH(body_text)-CHAR_LENGTH('QA stale content')) WHERE id=${first}`);}
 // A database failure while recording the review must not partially publish lessons/questions.
 sql("CREATE TRIGGER qa_publish051_fail BEFORE INSERT ON st_content_reviews FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA publication rollback'");
 try{assert.notEqual(run('050','apply',{STUDY_PUBLISH_CURRICULUM:'050'}).status,0);assert.equal(sql('SELECT COUNT(*) FROM st_courses WHERE id IN (31,32) AND status=\'published\''),'0');assert.equal(sql('SELECT COUNT(*) FROM st_questions WHERE course_id IN (31,32) AND status=\'published\''),'0');}finally{sql('DROP TRIGGER qa_publish051_fail');}
 for(const batch of ['050','051']){
  const check=run(batch);assert.equal(check.status,0,check.stdout+check.stderr);
  const r=run(batch,'apply',{STUDY_PUBLISH_CURRICULUM:batch});assert.equal(r.status,0,r.stdout+r.stderr);assert.ok(JSON.parse(r.stdout).courses.every(c=>c.status==='published'));
  const repeat=run(batch,'apply',{STUDY_PUBLISH_CURRICULUM:batch});assert.ok(JSON.parse(repeat.stdout).courses.every(c=>c.status==='already_published'));
 }
 assert.equal(sql('SELECT COUNT(*) FROM st_courses WHERE id IN (31,32,33,34) AND status=\'published\' AND is_free=0'),'4');
 assert.equal(sql('SELECT COUNT(*) FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id WHERE s.course_id IN (31,32,33,34) AND l.status=\'published\''),'40');
 assert.equal(sql('SELECT COUNT(*) FROM st_questions WHERE course_id IN (31,32,33,34) AND status=\'published\''),'80');
 assert.equal(sql('SELECT COUNT(*) FROM st_package_courses WHERE course_id IN (31,32,33,34)'),'0');
 console.log('PASS publish gate/off setting/changed content rejection, review audit failure rollback, four courses published via review, repeat safe, 40 lessons/80 questions synced, paid flag preserved.');
} finally {sql("UPDATE st_site_settings SET values_json='"+old.replaceAll('\\','\\\\').replaceAll("'","''")+"' WHERE section='pembelajaran'");}
