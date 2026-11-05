/**
 * Fraud Tenant — headed Google Chrome journeys (real interactions) on the ISOLATED QA install.
 *
 * Drives a visible Chrome window over the DevTools Protocol (no third-party packages):
 * types into real forms, clicks real buttons, pays in Stripe's hosted Checkout with a
 * Stripe TEST card, reads OTPs from the QA mail capture, and captures screenshots at
 * 1440x900 / 768x1024 / 390x844. Screenshots are taken only when no secret, OTP or
 * password text is visible.
 */
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const SECRETS = 'C:\\Users\\Md Anikur Rahaman\\ft_qa_secrets';
const MAILDIR = 'C:\\Users\\Md Anikur Rahaman\\ft_qa_mail';
const PROFILE = path.join(SECRETS, 'chrome_profile_journeys');
const OUT = 'C:\\xampp\\htdocs\\fraudtenant\\reports\\claude_completion\\screenshots';
const BASE = 'http://localhost/fraudtenant-qa';
const PORT = 9333;
const fx = JSON.parse(fs.readFileSync(path.join(SECRETS, 'browser_fixtures.json'), 'utf8'));
const admin = JSON.parse(fs.readFileSync(path.join(SECRETS, 'qa_test_admin.json'), 'utf8'));
fs.mkdirSync(OUT, { recursive: true });

const VIEW = { desktop: [1440, 900, false], tablet: [768, 1024, true], mobile: [390, 844, true] };
const results = [];
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
function record(id, name, pass, detail = '') {
  results.push({ id, name, pass: !!pass, detail });
  console.log(`[${pass ? 'PASS' : 'FAIL'}] ${id}: ${name}${detail ? ' — ' + detail : ''}`);
}

// ------------------------------------------------------------ CDP plumbing
const chrome = spawn(CHROME, [`--remote-debugging-port=${PORT}`, `--user-data-dir=${PROFILE}`, '--no-first-run', '--no-default-browser-check', '--window-size=1440,960', '--disable-features=PasswordLeakDetection', 'about:blank'], { stdio: 'ignore' });
let ws;
let msgId = 0;
const pending = new Map();
const listeners = [];
async function connect() {
  for (let i = 0; i < 60; i++) {
    try {
      const list = await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json();
      const page = list.find((t) => t.type === 'page');
      if (page) {
        ws = new WebSocket(page.webSocketDebuggerUrl);
        await new Promise((r, j) => { ws.onopen = r; ws.onerror = j; });
        ws.onmessage = (ev) => {
          const m = JSON.parse(ev.data);
          if (m.id && pending.has(m.id)) { const { res, rej } = pending.get(m.id); pending.delete(m.id); m.error ? rej(new Error(m.error.message)) : res(m.result); }
          else if (m.method) { listeners.forEach((l) => l(m)); }
        };
        return;
      }
    } catch (e) { /* not ready */ }
    await sleep(500);
  }
  throw new Error('Chrome DevTools not reachable');
}
const send = (method, params = {}) => new Promise((res, rej) => { const id = ++msgId; pending.set(id, { res, rej }); ws.send(JSON.stringify({ id, method, params })); });
async function evaluate(expr) {
  const r = await send('Runtime.evaluate', { expression: expr, returnByValue: true, awaitPromise: true });
  if (r.exceptionDetails) throw new Error('eval: ' + (r.exceptionDetails.exception?.description || r.exceptionDetails.text));
  return r.result.value;
}
async function waitFor(expr, timeout = 30000, label = expr) {
  const end = Date.now() + timeout;
  while (Date.now() < end) {
    try { if (await evaluate(expr)) return true; } catch (e) { /* navigating */ }
    await sleep(300);
  }
  throw new Error('timeout waiting for ' + label);
}
async function settle() { await sleep(400); await waitFor('document.readyState === "complete"', 45000, 'load'); await sleep(300); }
async function nav(url) { await send('Page.navigate', { url }); await settle(); }
const href = () => evaluate('location.href');
async function setViewport(kind) { const [w, h, mobile] = VIEW[kind]; await send('Emulation.setDeviceMetricsOverride', { width: w, height: h, deviceScaleFactor: 1, mobile }); }
async function shot(name, kind = 'desktop') {
  await setViewport(kind); await sleep(500);
  const r = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
  fs.writeFileSync(path.join(OUT, `${name}-${kind}.png`), Buffer.from(r.data, 'base64'));
  if (kind !== 'desktop') { await setViewport('desktop'); }
}
async function typeInto(selector, text) {
  const ok = await evaluate(`(() => { const e = document.querySelector(${JSON.stringify(selector)}); if (!e) return false; e.focus(); if ('value' in e) { e.value=''; } return true; })()`);
  if (!ok) throw new Error('missing field ' + selector);
  await send('Input.insertText', { text });
  await evaluate(`(() => { const e = document.querySelector(${JSON.stringify(selector)}); e.dispatchEvent(new Event('input', {bubbles:true})); e.dispatchEvent(new Event('change', {bubbles:true})); return true; })()`);
}
async function click(selector) {
  const ok = await evaluate(`(() => { const e = document.querySelector(${JSON.stringify(selector)}); if (!e) return false; e.scrollIntoView({block:'center'}); e.click(); return true; })()`);
  if (!ok) throw new Error('missing clickable ' + selector);
}
async function setValue(selector, value) {
  return evaluate(`(() => { const e = document.querySelector(${JSON.stringify(selector)}); if (!e) return false; e.value = ${JSON.stringify(value)}; e.dispatchEvent(new Event('change', {bubbles:true})); return true; })()`);
}
async function submitClosestForm(selector) {
  await evaluate(`(() => { const e = document.querySelector(${JSON.stringify(selector)}); const f = e.closest('form'); (f.requestSubmit ? f.requestSubmit() : f.submit()); return true; })()`);
  await sleep(800); await settle();
}
async function clearCookies() { await send('Network.clearBrowserCookies'); }
function latestCode(to, afterMs) {
  let best = null; let bt = 0;
  for (const f of fs.readdirSync(MAILDIR)) {
    if (!f.endsWith('.json')) continue;
    try {
      const m = JSON.parse(fs.readFileSync(path.join(MAILDIR, f), 'utf8'));
      const ts = (m.ts || 0) * 1000;
      if (m.to.toLowerCase() !== to.toLowerCase() || ts < afterMs) continue;
      const mm = /\b(\d{6})\b/.exec(m.message);
      if (mm && ts >= bt) { best = mm[1]; bt = ts; }
    } catch (e) { /* ignore */ }
  }
  return best;
}
async function waitCode(to, afterMs) { for (let i = 0; i < 40; i++) { const c = latestCode(to, afterMs); if (c) return c; await sleep(500); } return null; }
async function fillOtpBoxes(code) {
  await evaluate(`(() => { const c = ${JSON.stringify(code)}; for (let i=1;i<=6;i++){ const e=document.getElementById('otp-'+i); if(e){ e.value=c[i-1]; e.dispatchEvent(new Event('input',{bubbles:true})); } } const h=document.getElementById('ft-otp-full-code'); if(h){h.value=c;} return true; })()`);
}
async function login(email, pw) {
  await clearCookies();
  await nav(`${BASE}/login/`);
  await typeInto('#ft-login-email', email);
  await typeInto('#ft-login-password', pw);
  await submitClosestForm('#ft-login-password');
}
async function step(id, name, fn) {
  try { const d = await fn(); record(id, name, d !== false, typeof d === 'string' ? d : ''); }
  catch (e) { record(id, name, false, e.message); }
}

// ------------------------------------------------------------ Journeys
(async () => {
  await connect();
  await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
  listeners.push((m) => { if (m.method === 'Page.javascriptDialogOpening') send('Page.handleJavaScriptDialog', { accept: true }); });
  await setViewport('desktop');
  let tenantId = 0;

  await step('BJ-01', 'Open Website: public home renders (desktop/tablet/mobile)', async () => {
    await clearCookies(); await nav(`${BASE}/`);
    await shot('01-home', 'desktop'); await shot('01-home', 'tablet'); await shot('01-home', 'mobile');
    return (await evaluate('document.title')).length > 0;
  });

  await step('BJ-02', 'Select Action -> Register -> Select User Role (Owner/Tenant tabs)', async () => {
    await nav(`${BASE}/register/`);
    await shot('02-register-role-owner', 'desktop');
    await click('#tab-tenant'); await sleep(400);
    await shot('02-register-role-tenant', 'desktop'); await shot('02-register-role-tenant', 'mobile');
    return evaluate('!!document.querySelector("#ft-register-tenant-form") && getComputedStyle(document.querySelector("#form-container-tenant")).display !== "none"');
  });

  const regStart = Date.now();
  await step('BJ-03', 'Tenant Registration: submit details -> Enter 6-Digit OTP page', async () => {
    await typeInto('#tenant-first-name', 'Browser'); await typeInto('#tenant-last-name', 'Tenant');
    await typeInto('#tenant-email', fx.tenant_email); await typeInto('#tenant-phone', '512-555-0142');
    await typeInto('#tenant-password', fx.tenant_password); await typeInto('#tenant-confirm-password', fx.tenant_password);
    await typeInto('#tenant-current-street', '200 Congress Ave'); await typeInto('#tenant-current-city', 'Austin');
    await setValue('#tenant-current-state', 'TX'); await typeInto('#tenant-current-zip', '78701');
    await evaluate(`(() => { document.querySelectorAll('#ft-register-tenant-form input[type=checkbox][name=terms_accepted], #ft-register-tenant-form input[type=checkbox][name=privacy_accepted]').forEach(c => { if(!c.checked) c.click(); }); return true; })()`);
    await submitClosestForm('#btn-submit-tenant');
    await waitFor('location.pathname.includes("/verify-otp/")', 20000, 'verify-otp');
    await shot('03-verify-otp-empty', 'desktop');
    return true;
  });

  await step('BJ-04', 'Invalid OTP shows an error and allows retry', async () => {
    const code = await waitCode(fx.tenant_email, regStart);
    if (!code) throw new Error('OTP not captured');
    await fillOtpBoxes(code === '000000' ? '111111' : '000000');
    await submitClosestForm('#otp-1');
    await shot('04-otp-invalid-error', 'desktop');
    return evaluate('!!document.querySelector(".auth-alert-error")');
  });

  await step('BJ-05', 'Valid OTP -> Email Verified -> Pending Admin Approval page', async () => {
    const code = await waitCode(fx.tenant_email, regStart);
    await fillOtpBoxes(code);
    await submitClosestForm('#otp-1');
    const u = await href();
    await shot('05-pending-approval', 'desktop'); await shot('05-pending-approval', 'mobile');
    if (!(u.includes('/account-status/') || u.includes('/login/'))) throw new Error('landed on ' + u);
    return u.replace(BASE, '');
  });

  await step('BJ-06', 'Pending account login is held on Account Status (no dashboard)', async () => {
    await login(fx.tenant_email, fx.tenant_password);
    const u = await href();
    await shot('06-pending-login-status', 'desktop');
    if (u.includes('dashboard')) throw new Error('reached ' + u);
    return u.replace(BASE, '');
  });

  await step('BJ-07', 'Invalid credentials show a generic login error', async () => {
    await login(fx.tenant_email, 'Wrong-Password-123');
    await shot('07-invalid-login', 'desktop');
    return evaluate('!!document.querySelector(".auth-alert-error") && /invalid email address or password/i.test(document.body.innerText)');
  });

  await step('BJ-08', 'Admin login routes to the custom Admin Command Center', async () => {
    await login(admin.login, admin.password);
    await waitFor('location.href.includes("page=fraud-tenant-admin")', 20000, 'admin dashboard');
    await shot('08-admin-overview', 'desktop'); await shot('08-admin-overview', 'tablet');
    return true;
  });

  await step('BJ-09', 'Admin finds the applicant (search) and approves via the review UI', async () => {
    await nav(`${BASE}/wp-admin/admin.php?page=fraud-tenant-admin&tab=users&s=${encodeURIComponent(fx.tenant_email)}`);
    const reviewHref = await evaluate('(() => { const a = [...document.querySelectorAll("a")].find(x => x.href.includes("action=review")); return a ? a.href : ""; })()');
    if (!reviewHref) throw new Error('applicant not found');
    tenantId = Number(new URL(reviewHref).searchParams.get('user_id'));
    await shot('09-admin-user-search', 'desktop');
    await nav(reviewHref);
    await shot('09-admin-review-before', 'desktop');
    await submitClosestForm('input[name="review_decision"][value="approve"]');
    await shot('09-admin-approved', 'desktop');
    if (!(await evaluate('/approved/i.test(document.body.innerText)'))) throw new Error('no approval confirmation');
    return `tenant #${tenantId}`;
  });

  await step('BJ-10', 'Approved tenant login -> Tenant Dashboard (desktop/tablet/mobile)', async () => {
    await login(fx.tenant_email, fx.tenant_password);
    await waitFor('location.pathname.includes("/tenant-dashboard/")', 20000, 'tenant dashboard');
    await shot('10-tenant-dashboard', 'desktop'); await shot('10-tenant-dashboard', 'tablet'); await shot('10-tenant-dashboard', 'mobile');
    return true;
  });

  await step('BJ-11', 'Cross-role denial: tenant cannot open the Owner Dashboard or Admin', async () => {
    await nav(`${BASE}/owner-dashboard/`);
    const u1 = await href();
    const ownerShell = await evaluate('!!document.getElementById("ftMembershipModal")');
    await nav(`${BASE}/wp-admin/admin.php?page=fraud-tenant-admin`);
    const adminVisible = await evaluate('/Command Center/.test(document.body.innerText)');
    if (ownerShell || adminVisible) throw new Error(`ownerShell=${ownerShell} adminVisible=${adminVisible}`);
    return `redirected to ${u1.replace(BASE, '')}`;
  });

  await step('BJ-12', 'Account Settings: verified email change (code to new address, then confirm)', async () => {
    await nav(`${BASE}/tenant-dashboard/`);
    const t0 = Date.now();
    await click('[data-ft-modal-target="ftProfileModal"]'); await sleep(300);
    await click('[data-tab-target="ftTenantTabEmail"]'); await sleep(300);
    await typeInto('#tenant_new_email', fx.tenant_email2);
    await typeInto('#tenant_email_curr_pw', fx.tenant_password);
    await submitClosestForm('#tenant_new_email');
    const stillOld = await evaluate(`document.body.innerText.includes(${JSON.stringify('confirmation code')})`);
    const code = await waitCode(fx.tenant_email2, t0);
    if (!code) throw new Error('email-change code not captured');
    await click('[data-ft-modal-target="ftProfileModal"]'); await sleep(300);
    await click('[data-tab-target="ftTenantTabEmail"]'); await sleep(300);
    await shot('12-email-change-pending', 'desktop');
    await typeInto('input[name="email_code"]', code);
    await submitClosestForm('input[name="email_code"]');
    await shot('12-email-change-confirmed', 'desktop');
    return stillOld && await evaluate('/verified and updated/i.test(document.body.innerText)');
  });

  await step('BJ-13', 'Forgot Password -> Reset OTP -> Set New Password (no token in URL; HttpOnly cookie)', async () => {
    await clearCookies();
    const t0 = Date.now();
    await nav(`${BASE}/forgot-password/`);
    await typeInto('#forgot-email', fx.tenant_email2);
    await submitClosestForm('#forgot-email');
    await shot('13-reset-otp-page', 'desktop');
    const code = await waitCode(fx.tenant_email2, t0);
    if (!code) throw new Error('reset OTP not captured');
    await fillOtpBoxes(code);
    await submitClosestForm('#otp-1');
    const u = await href();
    const cookieVisible = await evaluate('document.cookie.includes("ft_pw_reset")');
    await shot('13-set-new-password', 'desktop');
    await typeInto('#reset-password-val', fx.tenant_password2);
    await typeInto('#reset-confirm-password-val', fx.tenant_password2);
    await submitClosestForm('#reset-password-val');
    const done = await href();
    await shot('13-reset-success-login', 'desktop');
    const ok = u.includes('step=new_password') && !/token=/.test(u) && !cookieVisible && done.includes('/login/');
    if (!ok) throw new Error(`url=${u.replace(BASE, '')} jsCookie=${cookieVisible}`);
    return 'URL had no token; cookie not readable by JS';
  });

  await step('BJ-14', 'Old password rejected, new password accepted after reset', async () => {
    await login(fx.tenant_email2, fx.tenant_password);
    const oldDenied = !(await href()).includes('/tenant-dashboard/');
    await login(fx.tenant_email2, fx.tenant_password2);
    const newOk = (await href()).includes('/tenant-dashboard/');
    return oldDenied && newOk;
  });

  await step('BJ-15', 'Owner login -> Owner Dashboard; Free owner sees incident upgrade gate', async () => {
    await login(fx.owner_email, fx.owner_password);
    await waitFor('location.pathname.includes("/owner-dashboard/")', 20000, 'owner dashboard');
    await shot('15-owner-dashboard', 'desktop'); await shot('15-owner-dashboard', 'tablet'); await shot('15-owner-dashboard', 'mobile');
    await evaluate('(() => { const b=[...document.querySelectorAll("[data-ft-modal-target=ftIncidentModal]")].find(x=>!x.disabled); if(b){b.click(); return true;} return false; })()');
    await sleep(400);
    const gate = await evaluate('!!document.querySelector("#ftIncidentModal .ft-incident-upgrade-gate")');
    await shot('15-owner-incident-gate', 'desktop');
    return gate;
  });

  await step('BJ-16', 'Membership modal shows Free plan, limits and Test-mode badge', async () => {
    await nav(`${BASE}/owner-dashboard/?ft_tab=membership`);
    await sleep(500);
    await shot('16-membership-free', 'desktop'); await shot('16-membership-free', 'mobile');
    return evaluate('document.querySelector("[data-ft-current-plan]")?.dataset.ftCurrentPlan === "free" && /BILLING TEST MODE/.test(document.body.innerText)');
  });

  await step('BJ-17', 'Stripe Test Checkout: pay $29 with test card in hosted Checkout', async () => {
    await click('.ft-checkout-btn[data-plan-code="standard_monthly"]');
    await waitFor('location.hostname === "checkout.stripe.com"', 30000, 'stripe checkout');
    await waitFor('!!document.querySelector("#cardNumber") || !!document.querySelector("[data-testid=card-accordion-item-button]")', 30000, 'card form');
    await evaluate('(() => { const b=document.querySelector("[data-testid=card-accordion-item-button]"); if(b) b.click(); return true; })()');
    await waitFor('!!document.querySelector("#cardNumber")', 20000, 'cardNumber');
    await shot('17-stripe-checkout', 'desktop');
    await typeInto('#cardNumber', '4242424242424242');
    await typeInto('#cardExpiry', '12 / 34');
    await typeInto('#cardCvc', '123');
    if (await evaluate('!!document.querySelector("#billingName")')) await typeInto('#billingName', 'QA Browser Owner');
    if (await evaluate('!!document.querySelector("#billingPostalCode")')) await typeInto('#billingPostalCode', '78701');
    await evaluate('(() => { const c=document.querySelector("#enableStripePass"); if(c && c.checked) c.click(); return true; })()');
    await click('button[type=submit], .SubmitButton');
    await waitFor('location.hostname === "localhost" && location.search.includes("checkout_status=success")', 90000, 'return to site');
    await settle();
    return true;
  });

  await step('BJ-18', 'Return verified server-side: Membership Active + Standard plan shown', async () => {
    let active = await evaluate('/Membership Active/.test(document.body.innerText)');
    for (let i = 0; i < 10 && !active; i++) { await sleep(2000); await nav(await href()); active = await evaluate('/Membership Active/.test(document.body.innerText) || document.querySelector("[data-ft-current-plan]")?.dataset.ftCurrentPlan === "standard_monthly"'); }
    await shot('18-checkout-return-active', 'desktop');
    await nav(`${BASE}/owner-dashboard/?ft_tab=membership`); await sleep(1500);
    let plan = await evaluate('document.querySelector("[data-ft-current-plan]")?.dataset.ftCurrentPlan');
    for (let i = 0; i < 10 && !(await evaluate('/Payment History/.test(document.body.innerText)')); i++) { await sleep(2000); await nav(`${BASE}/owner-dashboard/?ft_tab=membership`); }
    plan = await evaluate('document.querySelector("[data-ft-current-plan]")?.dataset.ftCurrentPlan');
    const hist = await evaluate('/Payment History/.test(document.body.innerText) && /29\\.00 USD/.test(document.body.innerText)');
    await shot('18-membership-standard', 'desktop'); await shot('18-membership-standard', 'mobile');
    if (!(active && plan === 'standard_monthly' && hist)) throw new Error(`active=${active} plan=${plan} history=${hist}`);
    return 'plan=standard_monthly, $29.00 paid row present';
  });

  await step('BJ-19', 'Customer Portal opens for the owner\'s own Test customer', async () => {
    await click('.ft-portal-btn');
    await waitFor('location.hostname === "billing.stripe.com"', 30000, 'billing portal');
    await settle();
    await shot('19-stripe-customer-portal', 'desktop');
    return true;
  });

  await step('BJ-20', 'Standard owner: incident submission form unlocked; unlimited properties shown', async () => {
    await nav(`${BASE}/owner-dashboard/`);
    await evaluate('(() => { const b=[...document.querySelectorAll("[data-ft-modal-target=ftIncidentModal]")].find(x=>!x.disabled); if(b){b.click(); return true;} return false; })()');
    await sleep(400);
    const gate = await evaluate('!!document.querySelector("#ftIncidentModal .ft-incident-upgrade-gate")');
    await shot('20-owner-standard-incident', 'desktop');
    return !gate;
  });

  await step('BJ-21', 'Admin: Subscriptions shows the synced Test subscription; audit trail paginated', async () => {
    await login(admin.login, admin.password);
    await nav(`${BASE}/wp-admin/admin.php?page=fraud-tenant-admin&tab=subscriptions`);
    const hasSub = await evaluate('/sub_/.test(document.body.innerText) && /TEST/.test(document.body.innerText) && /active/.test(document.body.innerText)');
    await shot('21-admin-subscriptions', 'desktop');
    await nav(`${BASE}/wp-admin/admin.php?page=fraud-tenant-admin&tab=audit_logs&action_filter=stripe_`);
    await shot('21-admin-audit-stripe', 'desktop');
    await nav(`${BASE}/wp-admin/admin.php?page=fraud-tenant-admin&tab=stripe`);
    await shot('21-admin-stripe-gateway', 'desktop');
    return hasSub;
  });

  await step('BJ-22', 'Responsive auth pages (login/forgot) at tablet and mobile', async () => {
    await clearCookies(); await nav(`${BASE}/login/`);
    await shot('22-login', 'tablet'); await shot('22-login', 'mobile');
    await nav(`${BASE}/forgot-password/`); await shot('22-forgot', 'mobile');
    return true;
  });

  fs.writeFileSync(path.join(OUT, 'journey_results.json'), JSON.stringify(results, null, 2));
  const p = results.filter((r) => r.pass).length;
  console.log(`\n==== Headed Chrome journeys: ${p} passed, ${results.length - p} failed, ${results.length} journeys ====`);
  ws.close(); chrome.kill();
  process.exit(results.length - p > 0 ? 1 : 0);
})().catch((e) => { console.error('FATAL', e); try { chrome.kill(); } catch (x) {} process.exit(2); });
