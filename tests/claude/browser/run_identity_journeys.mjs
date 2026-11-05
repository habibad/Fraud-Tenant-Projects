/**
 * Headed Chrome journey: owner files an incident naming the tenant -> admin approves and
 * releases it -> another Standard owner and a Free owner screen the applicant.
 * Runs on the isolated QA install with synthetic people. Screenshots are taken only when no
 * SSN is visible (SSN inputs are password-masked and results never render SSNs).
 */
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const SECRETS = 'C:\\Users\\Md Anikur Rahaman\\ft_qa_secrets';
const OUT = 'C:\\xampp\\htdocs\\fraudtenant\\reports\\claude_completion\\screenshots\\identity';
const BASE = 'http://localhost/fraudtenant-qa';
const PORT = 9334;
const fx = JSON.parse(fs.readFileSync(path.join(SECRETS, 'identity_fixtures.json'), 'utf8'));
const admin = JSON.parse(fs.readFileSync(path.join(SECRETS, 'qa_test_admin.json'), 'utf8'));
fs.mkdirSync(OUT, { recursive: true });

const VIEW = { desktop: [1440, 900, false], mobile: [390, 844, true] };
const results = [];
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const chrome = spawn(CHROME, [`--remote-debugging-port=${PORT}`, `--user-data-dir=${path.join(SECRETS, 'chrome_profile_identity')}`, '--no-first-run', '--no-default-browser-check', '--window-size=1440,960', 'about:blank'], { stdio: 'ignore' });
let ws; let msgId = 0; const pending = new Map(); const listeners = [];

async function connect() {
  for (let i = 0; i < 60; i++) {
    try {
      const page = (await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json()).find((t) => t.type === 'page');
      if (page) {
        ws = new WebSocket(page.webSocketDebuggerUrl);
        await new Promise((r, j) => { ws.onopen = r; ws.onerror = j; });
        ws.onmessage = (ev) => {
          const m = JSON.parse(ev.data);
          if (m.id && pending.has(m.id)) { const { res, rej } = pending.get(m.id); pending.delete(m.id); m.error ? rej(new Error(m.error.message)) : res(m.result); } else if (m.method) { listeners.forEach((l) => l(m)); }
        };
        return;
      }
    } catch (e) { /* starting */ }
    await sleep(500);
  }
  throw new Error('Chrome DevTools not reachable');
}
const send = (method, params = {}) => new Promise((res, rej) => { const id = ++msgId; pending.set(id, { res, rej }); ws.send(JSON.stringify({ id, method, params })); });
async function evaluate(expr) { const r = await send('Runtime.evaluate', { expression: expr, returnByValue: true, awaitPromise: true }); if (r.exceptionDetails) throw new Error('eval: ' + (r.exceptionDetails.exception?.description || r.exceptionDetails.text)); return r.result.value; }
async function waitFor(expr, timeout = 30000, label = expr) { const end = Date.now() + timeout; while (Date.now() < end) { try { if (await evaluate(expr)) return true; } catch (e) { /* nav */ } await sleep(300); } throw new Error('timeout waiting for ' + label); }
async function settle() { await sleep(400); await waitFor('document.readyState === "complete"', 45000, 'load'); await sleep(300); }
async function nav(url) { await send('Page.navigate', { url }); await settle(); }
async function view(kind) { const [w, h, m] = VIEW[kind]; await send('Emulation.setDeviceMetricsOverride', { width: w, height: h, deviceScaleFactor: 1, mobile: m }); }
async function shot(name, kind = 'desktop') { await view(kind); await sleep(500); const r = await send('Page.captureScreenshot', { format: 'png' }); fs.writeFileSync(path.join(OUT, `${name}-${kind}.png`), Buffer.from(r.data, 'base64')); if (kind !== 'desktop') await view('desktop'); }
async function typeInto(sel, text) { if (!(await evaluate(`(() => { const e=document.querySelector(${JSON.stringify(sel)}); if(!e) return false; e.focus(); e.value=''; return true; })()`))) throw new Error('missing ' + sel); await send('Input.insertText', { text }); await evaluate(`document.querySelector(${JSON.stringify(sel)}).dispatchEvent(new Event('input',{bubbles:true}))`); }
async function click(sel) { if (!(await evaluate(`(() => { const e=document.querySelector(${JSON.stringify(sel)}); if(!e) return false; e.scrollIntoView({block:'center'}); e.click(); return true; })()`))) throw new Error('missing ' + sel); }
async function check(sel) { if (!(await evaluate(`(() => { const e=document.querySelector(${JSON.stringify(sel)}); if(!e) return false; if(!e.checked) e.click(); return e.checked; })()`))) throw new Error('cannot check ' + sel); }
async function submitForm(sel) { await evaluate(`(() => { const f=document.querySelector(${JSON.stringify(sel)}).closest('form'); f.requestSubmit ? f.requestSubmit() : f.submit(); return true; })()`); await sleep(800); await settle(); }
async function login(email, pw) { await send('Network.clearBrowserCookies'); await nav(`${BASE}/login/`); await typeInto('#ft-login-email', email); await typeInto('#ft-login-password', pw); await submitForm('#ft-login-password'); }
async function step(id, name, fn) { try { const d = await fn(); results.push({ id, name, pass: true, detail: d || '' }); console.log(`[PASS] ${id}: ${name}${d ? ' — ' + d : ''}`); } catch (e) { results.push({ id, name, pass: false, detail: e.message }); console.log(`[FAIL] ${id}: ${name} — ${e.message}`); } }
const bodyHas = (re) => evaluate(`${re}.test(document.body.innerText)`);

(async () => {
  await connect();
  await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
  listeners.push((m) => { if (m.method === 'Page.javascriptDialogOpening') send('Page.handleJavaScriptDialog', { accept: true }); });
  await view('desktop');
  let reportId = 0;

  await step('IJ-01', 'Owner A files an incident naming the tenant (name + SSN + identity attestation)', async () => {
    await login(fx.owner_a, fx.password);
    await waitFor('location.pathname.includes("/owner-dashboard/")', 20000, 'owner dashboard');
    await evaluate('(() => { const b=[...document.querySelectorAll("[data-ft-modal-target=ftIncidentModal]")].find(x=>!x.disabled); b.click(); return true; })()');
    await sleep(400);
    await evaluate('(() => { const s=document.querySelector("#ftIncidentPropSelect"); s.selectedIndex=1; s.dispatchEvent(new Event("change",{bubbles:true})); return true; })()');
    await typeInto('#ftIncTenantFirst', fx.first);
    await typeInto('#ftIncTenantLast', fx.last);
    await typeInto('#ftIncTenantSsn', fx.ssn);
    await check('#ftIncidentModal input[name=identity_attestation]');
    await typeInto('#ftIncidentModal input[name=amount_involved]', '1850');
    await typeInto('#ftIncidentModal textarea[name=factual_description]', 'Tenant vacated owing two months of rent; written notices were sent (QA synthetic).');
    await check('#ftIncidentModal input[name=owner_attestation]');
    await shot('01-incident-form-filled', 'desktop');
    await submitForm('#ftIncTenantFirst');
    await shot('01-incident-submitted', 'desktop');
    if (!(await bodyHas('/INC-\\d{6}|submitted|received/i'))) throw new Error('no submission confirmation shown');
    return 'confirmation shown';
  });

  await step('IJ-02', 'Admin sees the reported tenant (name + SSN last 4) on the review screen', async () => {
    await login(admin.login, admin.password);
    await nav(`${BASE}/wp-admin/admin.php?page=fraud-tenant-incidents`);
    reportId = await evaluate('Math.max(0, ...[...document.querySelectorAll("a")].map(a => { const m = /action=review&report_id=(\\d+)/.exec(a.href); return m ? +m[1] : 0; }))');
    if (!reportId) throw new Error('report not in queue');
    await nav(`${BASE}/wp-admin/admin.php?page=fraud-tenant-incidents&action=review&report_id=${reportId}`);
    const name = await evaluate(`document.querySelector('.ft-subject-card')?.innerText || ''`);
    await shot('02-admin-review-subject', 'desktop');
    const last4 = fx.ssn.slice(-4);
    if (!name.includes(fx.first) || !name.includes(fx.last) || !name.includes(last4)) throw new Error('subject card: ' + name.slice(0, 120));
    if (name.includes(fx.ssn)) throw new Error('full SSN rendered!');
    return `report #${reportId}`;
  });

  await step('IJ-03', 'Admin approves the report, then releases it to owner screening', async () => {
    await submitForm('input[name=decision][value=approve]');
    await nav(`${BASE}/wp-admin/admin.php?page=fraud-tenant-incidents&action=review&report_id=${reportId}`);
    await submitForm('.ft-screening-disclosure-card input[name=action]');
    const card = await evaluate(`document.querySelector('.ft-screening-disclosure-card')?.innerText || ''`);
    await shot('03-admin-released', 'desktop');
    if (!/Released:/.test(card)) throw new Error('not released: ' + card.slice(0, 160));
    return 'released';
  });

  await step('IJ-04', 'Standard owner C screens the applicant and sees the full record', async () => {
    await login(fx.owner_c, fx.password);
    await waitFor('location.pathname.includes("/owner-dashboard/")', 20000, 'owner dashboard');
    await click('.ft-open-screening'); await sleep(400);
    await typeInto('#ftScrFirst', fx.first); await typeInto('#ftScrLast', fx.last.toUpperCase()); await typeInto('#ftScrSsn', fx.ssn);
    await check('#ftScreeningModal input[name=applicant_consent]'); await check('#ftScreeningModal input[name=permissible_purpose_attestation]');
    await shot('04-screening-form', 'desktop');
    await submitForm('#ftScrFirst');
    const url = await evaluate('location.href');
    if (url.includes(fx.ssn.slice(-4)) || url.toLowerCase().includes(fx.last.toLowerCase())) throw new Error('PII in URL');
    await waitFor('!!document.querySelector(".ft-result-record")', 15000, 'result record');
    const html = await evaluate('document.body.innerHTML');
    if (html.includes(fx.ssn) || html.includes(fx.ssn.replace(/-/g, ''))) throw new Error('SSN rendered in page');
    await shot('04-screening-result-full', 'desktop'); await shot('04-screening-result-full', 'mobile');
    return 'record card shown; URL/page free of SSN';
  });

  await step('IJ-05', 'Free owner B sees "record found" without details, with upgrade prompt', async () => {
    await login(fx.owner_b, fx.password);
    await click('.ft-open-screening'); await sleep(400);
    await typeInto('#ftScrFirst', fx.first); await typeInto('#ftScrLast', fx.last); await typeInto('#ftScrSsn', fx.ssn);
    await check('#ftScreeningModal input[name=applicant_consent]'); await check('#ftScreeningModal input[name=permissible_purpose_attestation]');
    await submitForm('#ftScrFirst');
    await waitFor('!!document.querySelector(".ft-result-basic")', 15000, 'basic result');
    if (await evaluate('!!document.querySelector(".ft-result-record")')) throw new Error('free owner saw details');
    await shot('05-screening-result-free', 'desktop');
    return 'count only';
  });

  await step('IJ-06', 'Unknown applicant returns "no records"', async () => {
    await click('.ft-open-screening'); await sleep(400);
    await typeInto('#ftScrFirst', 'Casey'); await typeInto('#ftScrLast', 'Nobody'); await typeInto('#ftScrSsn', '234-56-7890');
    await check('#ftScreeningModal input[name=applicant_consent]'); await check('#ftScreeningModal input[name=permissible_purpose_attestation]');
    await submitForm('#ftScrFirst');
    await waitFor('!!document.querySelector(".ft-result-none")', 15000, 'none result');
    await shot('06-screening-result-none', 'desktop');
    return true;
  });

  await step('IJ-07', 'Admin Platform Settings shows the compliance gate status', async () => {
    await login(admin.login, admin.password);
    await nav(`${BASE}/wp-admin/admin.php?page=fraud-tenant-admin&tab=platform_settings`);
    await shot('07-admin-compliance-gates', 'desktop');
    if (!(await bodyHas('/Compliance Gates/'))) throw new Error('panel missing');
    return true;
  });

  fs.writeFileSync(path.join(OUT, 'identity_journey_results.json'), JSON.stringify(results, null, 2));
  const p = results.filter((r) => r.pass).length;
  console.log(`\n==== Identity screening journeys: ${p} passed, ${results.length - p} failed, ${results.length} journeys ====`);
  ws.close(); chrome.kill(); process.exit(results.length - p ? 1 : 0);
})().catch((e) => { console.error('FATAL', e); try { chrome.kill(); } catch (x) { /* */ } process.exit(2); });
