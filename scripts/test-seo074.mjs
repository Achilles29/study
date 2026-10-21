import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.STUDY_MONITOR_TEST_DB,'study_qa_monitor_sso039');
const base='http://127.0.0.1:8097';
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
const hex=s=>Buffer.from(s).toString('hex');
const get=async path=>{const r=await fetch(base+path,{redirect:'manual',headers:{'User-Agent':'ProjectPintar-QA/SEO074'}});const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),path);return{status:r.status,headers:r.headers,body};};
const course=Number(sql("SELECT id FROM st_courses WHERE slug='matematika-kelas-1-mengenal-bilangan-1-20'"));
assert.ok(course>0);
const qid=Number(sql(`SELECT id FROM st_questions WHERE course_id=${course} AND visibility='shared' AND status='published' AND target_child_user_id IS NULL AND reviewed_by_user_id IS NOT NULL ORDER BY id LIMIT 1`));
const savedQuestion=JSON.parse(sql(`SELECT JSON_OBJECT('status',status,'visibility',visibility,'rights_status',rights_status,'target_child_user_id',target_child_user_id,'reviewed_by_user_id',reviewed_by_user_id,'prompt',prompt) FROM st_questions WHERE id=${qid}`));
const savedSettings=sql("SELECT HEX(values_json),revision,IFNULL(updated_by_user_id,0),HEX(updated_at) FROM st_site_settings WHERE section='umum'").split('\t');
const lesson=sql(`SELECT l.id,l.slug FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id WHERE s.course_id=${course} ORDER BY l.id LIMIT 1`).split('\t');
const restoreQuestion=()=>sql(`UPDATE st_questions SET status='${savedQuestion.status}',visibility='${savedQuestion.visibility}',rights_status='${savedQuestion.rights_status}',target_child_user_id=${savedQuestion.target_child_user_id??'NULL'},reviewed_by_user_id=${savedQuestion.reviewed_by_user_id??'NULL'},prompt=UNHEX('${hex(savedQuestion.prompt)}') WHERE id=${qid}`);
const restoreSettings=()=>sql(`UPDATE st_site_settings SET values_json=UNHEX('${savedSettings[0]}'),revision=${Number(savedSettings[1])},updated_by_user_id=${Number(savedSettings[2])||'NULL'},updated_at=UNHEX('${savedSettings[3]}') WHERE section='umum'`);
const landing='/latihan-soal/kelas-1/matematika';
let browser;
try{
	const map=await get('/sitemap.xml');assert.equal(map.status,200);
	const urls=[...map.body.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m=>m[1].replaceAll('&amp;','&'));
	assert.equal(new Set(urls).size,urls.length);
	const publicUrls=urls.filter(u=>u.includes('/latihan-soal'));
	assert.equal(publicUrls.length,47);
	const titles=new Set(),descriptions=new Set();
	for(const url of publicUrls){const r=await get(new URL(url).pathname);assert.equal(r.status,200);assert.match(r.body,/<meta name="robots" content="index, follow">/);assert.ok(r.body.includes('href="'+url+'"'));const title=r.body.match(/<title>(.*?)<\/title>/s)[1],description=r.body.match(/name="description" content="([^"]*)"/)[1];assert.ok(!titles.has(title));assert.ok(!descriptions.has(description));titles.add(title);descriptions.add(description);}
	const queue=[...urls];await Promise.all(Array.from({length:4},async()=>{while(queue.length){const url=queue.shift();const r=await fetch(url,{method:'HEAD',redirect:'manual'});assert.equal(r.status,200,url);assert.ok(!/noindex/.test(r.headers.get('x-robots-tag')||''),url);}}));
	for(const path of ['/latihan-soal/kelas-7','/latihan-soal/kelas-1/ipas','/latihan-soal/kelas-1/unknown','/latihan-soal/kelas-1/matematika/secret'])assert.equal((await get(path)).status,404,path);
	assert.equal((await fetch(base+landing,{method:'PUT'})).status,405);
	for(const changes of ["visibility='family_private'","status='draft'","status='archived'","rights_status='pending_review'","reviewed_by_user_id=NULL","target_child_user_id=4"]){
		sql(`UPDATE st_questions SET ${changes} WHERE id=${qid}`);assert.ok(!(await get(landing)).body.includes(savedQuestion.prompt),changes);restoreQuestion();
	}
	const options=sql(`SELECT id,is_correct FROM st_question_options WHERE question_id=${qid}`).split('\n').map(r=>r.split('\t').map(Number));
	try{sql(`UPDATE st_question_options SET is_correct=0 WHERE question_id=${qid}`);assert.ok(!(await get(landing)).body.includes(savedQuestion.prompt));}finally{for(const[id,correct]of options)sql(`UPDATE st_question_options SET is_correct=${correct} WHERE id=${id}`);}
	const originalLessonRights=sql(`SELECT rights_status FROM st_lessons WHERE id=${Number(lesson[0])}`);
	try{sql(`UPDATE st_lessons SET rights_status='pending_review' WHERE id=${Number(lesson[0])}`);assert.match((await get('/materi/'+lesson[1])).headers.get('x-robots-tag'),/noindex/);assert.ok(!(await get('/sitemap.xml')).body.includes('/materi/'+lesson[1]+'</loc>'));}finally{sql(`UPDATE st_lessons SET rights_status='${originalLessonRights}' WHERE id=${Number(lesson[0])}`);}
	const originalCourse=sql(`SELECT is_free,status FROM st_courses WHERE id=${course}`).split('\t');
	try{sql(`UPDATE st_courses SET is_free=0 WHERE id=${course}`);assert.ok(!(await get(landing)).body.includes(savedQuestion.prompt));assert.ok(!(await get('/sitemap.xml')).body.includes('/materi/'+lesson[1]+'</loc>'));sql(`UPDATE st_courses SET status='draft' WHERE id=${course}`);assert.equal((await get('/materi/'+lesson[1])).status,404);}finally{sql(`UPDATE st_courses SET is_free=${Number(originalCourse[0])},status='${originalCourse[1]}' WHERE id=${course}`);}
	// Embedded HTML stays text, not executable markup.
	sql(`UPDATE st_questions SET prompt=UNHEX('${hex('<script>window.seoLeak=1</script> Soal aman')}') WHERE id=${qid}`);
	const escaped=await get(landing);assert.ok(escaped.body.includes('&lt;script&gt;window.seoLeak=1&lt;/script&gt;'));restoreQuestion();
	browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
	const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
	await page.setUserAgent('ProjectPintar-QA/SEO074');
	for(const width of[1280,360]){
		await page.setViewport({width,height:900});
		for(const path of ['/', '/latihan-soal','/latihan-soal/kelas-1',landing,'/materi/'+lesson[1]]){
			assert.equal((await page.goto(base+path)).status(),200);
			assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),path);
			assert.equal(await page.$$eval('h1',els=>els.length),1);
			if(path!=='/'){const data=await page.$$eval('script[type="application/ld+json"]',els=>els.map(n=>JSON.parse(n.textContent)));assert.equal(data.find(d=>d['@type']==='BreadcrumbList').itemListElement.length,await page.$$eval('.breadcrumb li',els=>els.length));}
			if(width===360){
				await page.click('.navbar-toggler');assert.equal(await page.$eval('.navbar-toggler',n=>n.getAttribute('aria-expanded')),'true');
				await page.keyboard.press('Escape');assert.equal(await page.$eval('.navbar-toggler',n=>n.getAttribute('aria-expanded')),'false');assert.equal(await page.evaluate(()=>document.activeElement.matches('.navbar-toggler')),true);
			}
			if(path===landing){await page.focus('details summary');await page.keyboard.press('Enter');assert.ok(await page.$eval('details',n=>n.open));await page.screenshot({path:'/tmp/seo074-subject-'+width+'.png',fullPage:true});}
			if(path==='/materi/'+lesson[1])await page.screenshot({path:'/tmp/seo074-lesson-'+width+'.png',fullPage:true});
		}
	}
	await page.setJavaScriptEnabled(false);await page.goto(base+landing);assert.ok(await page.$eval('#navigasi-utama',n=>getComputedStyle(n).display!=='none'));await page.setJavaScriptEnabled(true);
	await page.goto(base+'/masuk');await page.type('[name=identity]','monitor1');await page.type('[name=password]','Monitor-QA-Only-2026!');
	await Promise.all([page.waitForNavigation(),page.click('form[action$="/masuk"] button[type=submit]')]);
	await page.goto(base+'/kelola/pengaturan/umum');
	const fields=()=>page.$eval('form[action$="/kelola/pengaturan/umum"]',n=>Object.fromEntries(new FormData(n)));
	const post=data=>page.evaluate(async data=>{const r=await fetch('/kelola/pengaturan/umum',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data)});return r.status;},data);
	assert.equal(await post({...await fields(),google_site_verification:'<script>bad</script>'}),400);await page.reload();
	assert.equal(await post({...await fields(),google_site_verification:'a'.repeat(43),study_csrf_token:'bad'}),403);await page.reload();
	assert.equal(await post({...await fields(),google_site_verification:'SEO074_NOT_GOOGLE_'+'a'.repeat(30)}),200);
	assert.ok((await get('/')).body.includes('name="google-site-verification" content="SEO074_NOT_GOOGLE_'));
	assert.ok(!(await get('/kursus')).body.includes('name="google-site-verification"'));
	assert.match((await page.goto(base+'/materi/'+lesson[1])).headers()['x-robots-tag'],/noindex/);
	assert.equal(await page.$('script[type="application/ld+json"]'),null);
	assert.ok(await page.$('script[src*="bootstrap.bundle"]'));
	await page.goto(base+'/kelola/pengaturan/umum');await page.click('#search-console summary');
	await page.screenshot({path:'/tmp/seo074-search-console.png',fullPage:true});
	const memberContext=await browser.createBrowserContext(),member=await memberContext.newPage();
	await member.goto(base+'/masuk');await member.type('[name=identity]','monitor2');await member.type('[name=password]','Monitor-QA-Only-2026!');
	await Promise.all([member.waitForNavigation(),member.click('form[action$="/masuk"] button[type=submit]')]);
	assert.equal((await member.goto(base+'/kelola/pengaturan/umum')).status(),403);await memberContext.close();
	assert.deepEqual(errors,[]);
	console.log(`PASS SEO074: ${urls.length} sitemap targets reachable/indexable, 47 unique public landing pages; privacy/publication/rights/paid guards, invalid keys, XSS, breadcrumb JSON-LD, mobile/desktop/keyboard/no-JS, member noindex, settings token/CSRF/role and live tag on QA. No production writes.`);
}finally{restoreQuestion();restoreSettings();if(browser)await browser.close();}
