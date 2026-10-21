import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';

assert.equal(process.env.STUDY_MONITOR_TEST_DB, 'study_qa_monitor_sso039');
const base = 'http://127.0.0.1:8097';
const sql = q => execFileSync('/www/server/mysql/bin/mysql', ['--defaults-file=/etc/projectpintar/mariadb.cnf', '-uroot', '-N', 'study_qa_monitor_sso039'], {input:q, encoding:'utf8'}).trim();
const saved = sql("SELECT HEX(values_json), revision, IFNULL(updated_by_user_id,0), HEX(updated_at) FROM st_site_settings WHERE section='umum'").split('\t');
const course = '/kursus/matematika-kelas-6-pecahan-geometri-data-dan-peluang';
const courseId = Number(sql("SELECT id FROM st_courses WHERE slug='matematika-kelas-6-pecahan-geometri-data-dan-peluang'"));
assert.ok(courseId > 0);
const browser = await puppeteer.launch({executablePath:'/usr/bin/google-chrome', headless:true, args:['--no-sandbox']});
try {
	const page = await browser.newPage(), errors = [];
	await page.setUserAgent('ProjectPintar-QA/SEO073');
	page.on('pageerror', e => errors.push(e.message));
	for (const width of [1280, 360]) {
		await page.setViewport({width, height:900});
		for (const route of ['/', '/kursus', course]) {
			const response = await page.goto(base + route);
			assert.equal(response.status(), 200, route);
			assert.ok(!/Severity:|Fatal error|Database Error/.test(await page.content()), route);
			assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), route);
			assert.equal(await page.$$eval('h1', nodes => nodes.length), 1, route);
			assert.equal(await page.$eval('meta[name=robots]', n => n.content), 'index, follow', route);
			assert.equal(await page.$eval('link[rel=canonical]', n => n.href), base + route, route);
			if (route === '/') {
				assert.match(await page.title(), /Latihan Soal Online SD Kelas 1–6/);
				assert.equal(await page.$$eval('#latihan-soal-sd a', nodes => nodes.length), 6);
				assert.ok(!(await page.content()).includes('Pembelian paket belum dibuka.'));
				assert.equal(await page.$eval('script[type="application/ld+json"]', n => JSON.parse(n.textContent)['@type']), 'WebSite');
				await page.focus('details summary');
				await page.keyboard.press('Enter');
				assert.equal(await page.$eval('details', n => n.open), true);
				await page.screenshot({path:'/tmp/seo073-home-' + width + '.png', fullPage:true});
			}
			if (route === course) {
				assert.match(await page.$eval('meta[name=description]', n => n.content), /^Latihan soal online dan materi Matematika Kelas 6/);
				assert.ok(await page.$('a[href$="/latihan/kursus/'+courseId+'"]'));
				await page.screenshot({path:'/tmp/seo073-course-' + width + '.png', fullPage:true});
			}
		}
	}
	for (let grade=1; grade<=6; grade++) {
		const r=await page.goto(base+'/kursus?kelas=sd_'+grade);
		assert.equal(r.status(),200);
		assert.match(r.headers()['x-robots-tag'],/noindex/);
		assert.ok(await page.$('a[href*="/kursus/"]'), 'Grade contains courses');
	}
	await page.goto(base+'/kursus?halaman=2');
	assert.equal(await page.$eval('link[rel=canonical]',n=>n.href),base+'/kursus?halaman=2');
	assert.match(await page.title(), /Halaman 2/);
	assert.equal((await page.goto(base+'/sitemap.xml')).status(),200);

	// Authentication is QA-only. Exercise the existing guarded settings form.
	await page.goto(base+'/masuk');
	await page.type('[name=identity]', 'monitor1');
	await page.type('[name=password]', 'Monitor-QA-Only-2026!');
	await Promise.all([page.waitForNavigation(),page.click('form[action$="/masuk"] button[type=submit]')]);
	await page.goto(base+'/kelola/pengaturan/umum');
	const stale = await page.$eval('form[action$="/kelola/pengaturan/umum"]', n=>Object.fromEntries(new FormData(n)));
	const post = data => page.evaluate(async data => {
		const r = await fetch('/kelola/pengaturan/umum', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams(data)});
		return {status:r.status, body:await r.text()};
	}, data);
	assert.equal((await post({...stale,study_csrf_token:'invalid'})).status,403);
	await page.reload();
	const form = await page.$eval('form[action$="/kelola/pengaturan/umum"]', n=>Object.fromEntries(new FormData(n)));
	assert.equal((await post({...form,seo_title:''})).status,400);
	await page.reload();
	const current = await page.$eval('form[action$="/kelola/pengaturan/umum"]', n=>Object.fromEntries(new FormData(n)));
	const beforeAudit = Number(sql("SELECT COUNT(*) FROM st_audit_events WHERE event_type='settings.updated'"));
	const custom = 'Latihan & belajar <b>aman</b>';
	const description = 'Deskripsi pilihan pemilik & keluarga <script>window.seoLeak=1</script>';
	assert.equal((await post({...current,seo_title:custom,seo_description:description})).status,200);
	assert.equal(Number(sql("SELECT COUNT(*) FROM st_audit_events WHERE event_type='settings.updated'")),beforeAudit+1);
	await page.reload();
	const fresh = await page.$eval('form[action$="/kelola/pengaturan/umum"]', n=>Object.fromEntries(new FormData(n)));
	assert.equal((await post({...fresh,revision:current.revision})).status,409);
	await page.goto(base+'/');
	assert.equal(await page.title(), custom+' · '+fresh.brand_name);
	assert.equal(await page.evaluate(()=>window.seoLeak),undefined);
	assert.equal(await page.$eval('meta[name=robots]',n=>n.content),'noindex, nofollow');
	const context = await browser.createBrowserContext();
	const publicPage = await context.newPage();
	await publicPage.goto(base+'/');
	assert.equal(await publicPage.title(),custom+' · '+fresh.brand_name);
	assert.equal(await publicPage.$eval('meta[name=description]',n=>n.content),description);
	assert.equal(await publicPage.evaluate(()=>window.seoLeak),undefined);
	await context.close();
	// Restore exact pre-test settings data. QA audit retains evidence of this test.
	sql(`UPDATE st_site_settings SET values_json=UNHEX('${saved[0]}'),revision=${Number(saved[1])},updated_by_user_id=${Number(saved[2])||'NULL'},updated_at=UNHEX('${saved[3]}') WHERE section='umum'`);
	for (const width of [1280,360]) {
		await page.setViewport({width,height:900});
		await page.goto(base+'/kelola/pengaturan/umum');
		await page.click('details summary');
		assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
		await page.$eval('#setting-seo_title', n=>n.scrollIntoView());
		await page.screenshot({path:'/tmp/seo073-settings-'+width+'.png',fullPage:true});
	}
	assert.deepEqual(errors,[]);
	console.log('PASS SEO073: public titles/descriptions/canonical/JSON-LD/grade links, noindex filters and member views, pagination, desktop/mobile and keyboard; settings save/audit/CSRF/validation/stale revision and escaped owner text. QA only.');
} finally {
	sql(`UPDATE st_site_settings SET values_json=UNHEX('${saved[0]}'),revision=${Number(saved[1])},updated_by_user_id=${Number(saved[2])||'NULL'},updated_at=UNHEX('${saved[3]}') WHERE section='umum'`);
	await browser.close();
}
