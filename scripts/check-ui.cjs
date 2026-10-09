const { chromium } = require('@playwright/test');
const fs = require('node:fs/promises');
const path = require('node:path');
const assert = require('node:assert/strict');
const AxeBuilder = require('@axe-core/playwright').default;
(async () => {
  const browser = await chromium.launch({channel:'msedge',headless:true});
  const context = await browser.newContext({viewport:{width:1440,height:1000},colorScheme:'light'});
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', message => { if (message.type() === 'error' && /Content Security Policy|Refused|Alpine Expression/.test(message.text())) errors.push(message.text()); });
  const base = 'http://127.0.0.1:8000';
  await fs.mkdir('storage/app/ui-preview/screenshots',{recursive:true});
  await page.route('**/*', async route => {
    const url = new URL(route.request().url());
    if (url.pathname.endsWith('/slots')) return route.fulfill({json:{slots:[{time:'10:00',available:true,message:'Còn chỗ'},{time:'10:30',available:false,message:'Không trống'}],message:'Preview fixture'}});
    if (url.pathname.endsWith('/availability')) return route.fulfill({json:{available:true,message:'Còn nhân viên phù hợp.'}});
    const asset = /^\/(css|js|vendor)\/[a-zA-Z0-9_./-]+$/.test(url.pathname);
    const name = url.pathname.split('/').pop() || 'explore';
    const file = asset ? path.join('public',url.pathname) : path.join('storage/app/ui-preview',`${name}.html`);
    try {
      const body = await fs.readFile(file);
      const headers = asset ? {} : JSON.parse(await fs.readFile(path.join('storage/app/ui-preview',`${name}.headers.json`),'utf8'));
      const contentType = file.endsWith('.css')?'text/css':file.endsWith('.js')?'application/javascript':file.endsWith('.woff2')?'font/woff2':file.endsWith('.woff')?'font/woff':'text/html';
      return route.fulfill({body,contentType,headers});
    } catch { return route.fulfill({status:404,body:'Preview route not found'}); }
  });
  const visit = async name => { await page.goto(`${base}/${name}`); await page.locator('.site-header').waitFor(); await page.evaluate(() => document.fonts.ready); };
  for (const width of [1440,390]) {
    await page.setViewportSize({width,height:1000});
    for (const name of ['explore','booking','account','appointments','ticket','operations','payments','catalog','staff','vouchers']) {
      await visit(name);
      await page.screenshot({path:`storage/app/ui-preview/screenshots/${name}-${width}.png`,fullPage:true});
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1),false,`${name} overflows at ${width}`);
    }
  }
  await visit('booking');
  await page.getByRole('button',{name:'Tiếp tục'}).click();
  await page.getByText('Vui lòng chọn ít nhất một dịch vụ.').waitFor();
  await page.locator('[name="service_ids[]"]').check();
  assert.match(await page.locator('[data-summary-total]').textContent(),/350/);
  await page.locator('[data-open-summary]').click();
  assert.equal(await page.locator('[data-summary-dialog]').isVisible(),true);
  await page.getByRole('button',{name:'Đóng tóm tắt'}).click();
  await page.getByRole('button',{name:'Tiếp tục'}).click();
  await page.locator('[data-date-choice]').nth(3).click();
  await page.getByRole('button',{name:'Xem giờ còn trống'}).click();
  await page.getByRole('button',{name:'10:00: Còn chỗ',exact:true}).click();
  assert.equal(await page.locator('#time').inputValue(),'10:00');
  await page.getByRole('button',{name:'Kiểm tra khung giờ'}).click();
  await page.locator('[data-availability-status][data-state=available]').waitFor();
  await page.screenshot({path:'storage/app/ui-preview/screenshots/booking-time-mobile.png',fullPage:true});
  await page.getByRole('button',{name:'Tiếp tục'}).click();
  await page.locator('#voucher_code').fill('WELCOME');
  await page.getByRole('button',{name:'Tiếp tục'}).click();
  assert.match(await page.locator('[data-review-voucher]').textContent(),/WELCOME/);
  await page.getByRole('button',{name:'Đổi giao diện sáng tối'}).click();
  assert.equal(await page.locator('html').getAttribute('data-theme'),'dark');
  await page.screenshot({path:'storage/app/ui-preview/screenshots/booking-review-dark.png',fullPage:true});
  await page.reload();
  assert.equal(await page.locator('html').getAttribute('data-theme'),'dark');
  await page.getByRole('button',{name:'Mở điều hướng'}).click();
  await page.locator('#mobile-nav').getByRole('link',{name:'Tài khoản',exact:true}).waitFor();
  await visit('operations');
  await page.locator('[data-reschedule-open]').first().click();
  assert.equal(await page.locator('[data-reschedule-dialog]').isVisible(),true);
  assert.equal(await page.locator('#move-time').inputValue(),'10:00');
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('[data-reschedule-dialog]').isVisible(),false);
  await page.setViewportSize({width:1440,height:1000});
  await visit('operations');
  const track = page.locator('[data-drop-staff]').first();
  const bounds = await track.boundingBox();
  await page.locator('[data-reschedule-drag]').first().dragTo(track,{targetPosition:{x:bounds.width/2,y:35}});
  assert.equal(await page.locator('[data-reschedule-dialog]').isVisible(),true);
  assert.equal(await page.locator('#move-time').inputValue(),'12:00');
  await page.keyboard.press('Escape');
  for (const theme of ['light','dark']) {
    await page.evaluate(value => localStorage.setItem('glowbook-theme',value),theme);
    for (const name of ['explore','booking','operations','ticket','account']) {
      await visit(name);
      const result = await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
      const violations = result.violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.map(n=>n.target)}));
      console.log(`Accessibility ${theme}/${name}: ${JSON.stringify(violations)}`);
      assert.deepEqual(violations,[]);
    }
  }
  assert.deepEqual(errors,[]);
  console.log('PASS: 20 viewport checks, booking/summary/slots/theme/navigation, rescheduling modal and drag/drop, 10 axe scans, no JS/CSP errors. Browser availability responses are mocked; Laravel tests cover real API behavior.');
  await browser.close();
})().catch(error => {console.error(error);process.exit(1);});
