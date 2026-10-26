const {chromium}=require('playwright');
const fs=require('node:fs');
const assert=require('node:assert/strict');
const path=require('node:path');
const folder=process.env.CONCIERGE_FIXTURE || __dirname;
const html=fs.readFileSync(path.join(folder,'header.html'),'utf8');
const css=fs.readFileSync(path.join(folder,'style.css'),'utf8');
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH});
 try {
  const context=await browser.newContext();
  await context.route('**/*',route=>route.fulfill({contentType:route.request().url().includes('style.css')?'text/css':'text/html',body:route.request().url().includes('style.css')?css:route.request().url().startsWith('https://mikeofalltrades.test/')?html:''}));
  const page=await context.newPage();
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.clock.install();await page.goto('https://mikeofalltrades.test/');
  await page.clock.runFor(10100);
  assert.equal(await page.locator('#mot-ai-button').getAttribute('aria-expanded'),'true');
  await page.getByRole('button',{name:"Don't show for 30 days",exact:true}).click();
  await page.clock.runFor(300);
  assert.equal(await page.locator('#mot-ai-button').getAttribute('aria-expanded'),'false');
  const until=await page.evaluate(()=>Number(localStorage.getItem('motAiPopupSnoozeUntil')));
  const now=await page.evaluate(()=>Date.now());assert(until-now>29*86400000 && until-now<=30*86400000);
  // Reload and new tab have the same durable preference, separate tab session state.
  await page.reload();await page.clock.runFor(11000);
  assert.equal(await page.locator('#mot-ai-button').getAttribute('aria-expanded'),'false');
  await page.getByRole('button',{name:'Open AI assistant'}).click();
  assert.equal(await page.locator('#mot-ai-button').getAttribute('aria-expanded'),'true');
  await page.getByRole('button',{name:'Close assistant',exact:true}).click();
  const other=await context.newPage();await other.clock.install();await other.goto('https://mikeofalltrades.test/about.php');await other.clock.runFor(11000);
  assert.equal(await other.locator('#mot-ai-button').getAttribute('aria-expanded'),'false');
  // Expiry and malformed values permit the invitation on a fresh session.
  for(const value of [String(Date.now()-1),'not-a-time']) {
   await other.evaluate(value=>{localStorage.setItem('motAiPopupSnoozeUntil',value);sessionStorage.clear();},value);
   await other.reload();await other.clock.runFor(11000);
   assert.equal(await other.locator('#mot-ai-button').getAttribute('aria-expanded'),'true');
  }
  await context.close();
  const blocked=await browser.newContext();
  await blocked.route('**/*',route=>route.fulfill({contentType:'text/html',body:route.request().url().startsWith('https://mikeofalltrades.test/')?html:''}));
  await blocked.addInitScript(()=>{for(const name of ['localStorage','sessionStorage'])Object.defineProperty(window,name,{get(){throw new DOMException('Blocked','SecurityError');}});});
  const b=await blocked.newPage();b.on('pageerror',e=>errors.push(e.message));await b.clock.install();await b.goto('https://mikeofalltrades.test/');await b.clock.runFor(11000);
  await b.getByRole('button',{name:"Don't show for 30 days",exact:true}).click();
  assert.match(await b.locator('#mot-ai-preference-status').textContent(),/couldn't save/);
  await b.getByRole('button',{name:'Close assistant',exact:true}).click();
  assert.equal(await b.locator('#mot-ai-button').getAttribute('aria-expanded'),'false');
  assert.deepEqual(errors,[]);
  console.log('PASS: initial timer, 30-day persistence across reload/new tab, manual reopen, expiry, malformed preference and blocked-storage recovery');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
