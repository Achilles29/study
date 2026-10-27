import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';

assert.equal(process.env.STUDY_MONITOR_TEST_DB, 'study_qa_monitor_sso039');
const base = 'http://127.0.0.1:8097';
const sql = query => execFileSync('/www/server/mysql/bin/mysql', ['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'], {input:query, encoding:'utf8'}).trim();
const field = (html, name) => {
  const match = html.match(new RegExp(`name="${name}" value="([^"]*)"`));
  assert.ok(match, `Missing ${name}`);
  return match[1];
};
function client() {
  const cookies = new Map();
  return async (path, data) => {
    const response = await fetch(base + path, {redirect:'manual', method:data ? 'POST' : 'GET', headers:{Cookie:[...cookies].map(([key,value]) => `${key}=${value}`).join('; '), ...(data ? {'Content-Type':'application/x-www-form-urlencoded'} : {})}, body:data ? new URLSearchParams(data) : undefined});
    for (const cookie of response.headers.getSetCookie()) {
      const item = cookie.split(';')[0], split = item.indexOf('=');
      cookies.set(item.slice(0,split), item.slice(split+1));
    }
    const body = await response.text();
    assert.ok(!/Severity:|Fatal error|Database Error|A PHP Error/.test(body), body.slice(0, 500));
    return {status:response.status, body, location:response.headers.get('location')};
  };
}
async function login(name) {
  const request = client();
  const page = await request('/masuk');
  const result = await request('/masuk', {identity:name,password:'Monitor-QA-Only-2026!',study_csrf_token:field(page.body,'study_csrf_token')});
  assert.equal(result.status, 303, `${name}: ${result.body.replace(/<[^>]+>/g,' ').replace(/\s+/g,' ').slice(0,1200)}`);
  return request;
}

const original = sql("SELECT JSON_EXTRACT(values_json, '$.parent_live_enabled') FROM st_site_settings WHERE section='latihan_mandiri'");
const originalPassword = sql('SELECT password_hash FROM st_users WHERE id=3');
const qaPassword = execFileSync('/www/server/php/84/bin/php', ['-r', "echo password_hash('Monitor-QA-Only-2026!', PASSWORD_DEFAULT);"], {encoding:'utf8'});
const toggle = enabled => sql(`UPDATE st_site_settings SET values_json=JSON_SET(values_json,'$.parent_live_enabled','${enabled ? 1 : 0}') WHERE section='latihan_mandiri'`);
let child, attemptPath, browser;
try {
  toggle(false);
  sql(`UPDATE st_users SET password_hash='${qaPassword}' WHERE id=3`);
  const parent = await login('monitor2');
  child = await login('monitor3');
  let course = await child('/latihan/kursus/4');
  const started = await child('/latihan/kursus/4', {start_token:field(course.body,'start_token'),study_csrf_token:field(course.body,'study_csrf_token')});
  assert.equal(started.status, 303, started.body.slice(0, 400));
  attemptPath = new URL(started.location).pathname;
  const attemptId = Number(attemptPath.split('/').at(-1));
  const parentPath = `/keluarga/anak/3/latihan/${attemptId}/live`;
  assert.equal((await parent(parentPath)).status, 404, 'Global off must deny HTML');
  toggle(true);
  let page = await child(attemptPath);
  assert.equal(page.status, 200);
  assert.ok(page.body.includes('data-parent-live="1"'));
  assert.equal((await parent(parentPath)).status, 200, 'Linked parent should see active sheet');
  assert.equal((await parent('/keluarga/anak/4/latihan/' + attemptId + '/live')).status, 404, 'Wrong child scope must be denied');
  const qid = Number(page.body.match(/data-question-id="(\d+)"/)[1]);
  const option = Number(page.body.match(new RegExp(`name="answers\\[${qid}\\]" value="(\\d+)"`))[1]);
  const presence = await child(attemptPath, {sheet_presence:'1',current_question_id:String(qid),study_csrf_token:field(page.body,'study_csrf_token')});
  assert.equal(presence.status, 200, presence.body.replace(/<[^>]+>/g,' ').replace(/\s+/g,' ').slice(-900));
  const presenceData = JSON.parse(presence.body);
  let status = await parent(parentPath + '/status');
  assert.equal(status.status, 200);
  assert.equal(Number(JSON.parse(status.body).current_question_id), qid);
  assert.ok(!status.body.includes('is_correct') && !status.body.includes('explanation'), 'Live JSON must hide answer keys');
  const saved = await child(attemptPath, {sheet_ajax:'1',action:'save',revision:String(presenceData.revision),answers_json:JSON.stringify({[qid]:String(option)}),study_csrf_token:presenceData.csrf_token});
  assert.equal(saved.status, 200, saved.body.slice(0, 300));
  status = await parent(parentPath + '/status');
  assert.equal(status.status, 200);
  assert.equal(Number(JSON.parse(status.body).answers[qid]), option, 'Parent should see saved choice');
  browser = await puppeteer.launch({executablePath:'/usr/bin/google-chrome', headless:true, args:['--no-sandbox']});
  const childContext = await browser.createBrowserContext(), parentContext = await browser.createBrowserContext();
  const childPage = await childContext.newPage(), parentPage = await parentContext.newPage();
  const errors = [];
  childPage.on('pageerror', error => errors.push(error.message));
  parentPage.on('pageerror', error => errors.push(error.message));
  async function browserLogin(page, username) {
    await page.goto(base + '/masuk');
    await page.type('[name=identity]', username);
    await page.type('[name=password]', 'Monitor-QA-Only-2026!');
    await Promise.all([page.waitForNavigation(), page.click('form[action$="/masuk"] button[type=submit]')]);
  }
  await browserLogin(childPage, 'monitor3');
  await browserLogin(parentPage, 'monitor2');
  await childPage.goto(base + attemptPath);
  await parentPage.goto(base + parentPath);
  const otherOption = await childPage.$eval(`[name="answers[${qid}]"][value]:not([value="${option}"]):not([value=""])`, el => el.value);
  await childPage.evaluate(({qid, otherOption}) => {
    const input = document.querySelector(`[name="answers[${qid}]"][value="${otherOption}"]`);
    input.checked = true;
    input.dispatchEvent(new Event('change', {bubbles:true}));
  }, {qid, otherOption});
  try {
    await childPage.waitForFunction(() => document.querySelector('#sheet-save-status')?.textContent.startsWith('Tersimpan'), {timeout:10000});
  } catch (error) {
    const state = await childPage.evaluate(() => ({status:document.querySelector('#sheet-save-status')?.textContent, live:document.querySelector('#practice-sheet')?.dataset.parentLive, revision:document.querySelector('[name=revision]')?.value}));
    throw new Error(`Child browser autosave failed: ${JSON.stringify(state)}; page errors: ${JSON.stringify(errors)}; ${error.message}`);
  }
  await parentPage.waitForFunction(({qid, otherOption}) => {
    const card = document.querySelector(`[data-live-question="${qid}"]`);
    return card && !card.querySelector(`[data-live-option="${otherOption}"] [data-live-selected]`).hidden;
  }, {timeout:12000}, {qid, otherOption});
  assert.deepEqual(errors, [], 'Browser should not raise script errors');
  await browser.close(); browser = null;
  toggle(false);
  assert.equal((await parent(parentPath + '/status')).status, 404, 'Global off must revoke live polling');
  console.log('PASS 054 HTTP/browser: linked parent, live presence, autosave, parent polling, key hiding, scope and immediate revocation.');
} finally {
  if (browser) await browser.close();
  try {
    if (original === 'NULL') sql("UPDATE st_site_settings SET values_json=JSON_REMOVE(values_json,'$.parent_live_enabled') WHERE section='latihan_mandiri'");
    else sql(`UPDATE st_site_settings SET values_json=JSON_SET(values_json,'$.parent_live_enabled',${original}) WHERE section='latihan_mandiri'`);
    if (child && attemptPath) {
      const page = await child(attemptPath);
      if (page.status === 200 && page.body.includes('name="revision"')) {
        await child(attemptPath, {sheet_ajax:'1',action:'finish',revision:field(page.body,'revision'),answers_json:'{}',study_csrf_token:field(page.body,'study_csrf_token')});
      }
    }
  } finally {
    sql(`UPDATE st_users SET password_hash='${originalPassword}' WHERE id=3`);
  }
}
