'use strict';
const assert=require('assert');
const fs=require('fs');
const path=require('path');
const root=path.resolve(__dirname,'../..');
const ux=require(path.join(root,'apps/platform/public/assets/domain-ux.js'));
const app=fs.readFileSync(path.join(root,'apps/platform/public/assets/app.js'),'utf8');
const domain=fs.readFileSync(path.join(root,'apps/platform/public/assets/domain-ux.js'),'utf8');
const css=fs.readFileSync(path.join(root,'apps/platform/public/assets/domain-ux.css'),'utf8');

assert.strictEqual(ux.localizeStatus('pending'),'در انتظار');
assert.strictEqual(ux.localizeStatus('paid'),'پرداخت‌شده');
assert.strictEqual(ux.localizeStatus('cancelled'),'لغوشده');
assert.strictEqual(ux.localizeStatus('provider_weird_code'),'وضعیت نامشخص');
assert.strictEqual(ux.localizeResourceType('question_bank'),'بانک سؤال');
assert.strictEqual(ux.localizeAssessmentType('mock_exam'),'آزمون آزمایشی');
assert.ok(/[۰-۹]/.test(ux.formatNumber(12345)),'Persian digits expected');
assert.notStrictEqual(ux.formatDate('2026-09-08'),'—');
assert.strictEqual(ux.formatNumber(null),'—');
assert.strictEqual(ux.formatNumber(undefined),'—');
assert.strictEqual(ux.formatMoney(250000,'IRR'),'۲۵۰٬۰۰۰ ریال');
assert.strictEqual(ux.normalizeText('<img src=x onerror=alert(1)>'),'<img src=x onerror=alert(1)>');
assert.ok(domain.includes('textContent'),'safe DOM textContent must be used');
assert.ok(!/\.innerHTML\s*=/.test(domain),'domain renderer must not write untrusted innerHTML');
assert.ok(!/\.innerHTML\s*=/.test(app),'app flow must not use innerHTML state rendering');
assert.ok(!/\balert\s*\(/.test(app),'normal alert() must not be used');
for(const key of ['schedule','grades','announcements','academics','resources','assessments','forms','orders','search'])assert.strictEqual(typeof ux.renderers[key],'function',`${key} dedicated renderer missing`);
for(const raw of ['workspace_id','source_type','source_id','amount_minor','provider_key','provider_reference','schema_json']){
  const dumpPattern=new RegExp('\\$\\{[^}]*'+raw+'[^}]*\\}\\s*:');
  assert.ok(!dumpPattern.test(domain),`${raw} must not be dumped as a label`);
}
assert.ok(!domain.includes('Object.entries(row)'), 'generic raw row iteration must not return');
assert.ok(domain.includes("dir: 'ltr'")&&/unicode-bidi\s*:\s*isolate\b/.test(css),'mixed LTR token isolation missing');
assert.ok(domain.includes('در حال دریافت اطلاعات')&&domain.includes('دریافت اطلاعات ممکن نشد'),'loading/error Persian states missing');
assert.ok(domain.includes('برای این بازه برنامه‌ای ثبت نشده است.'),'schedule empty state missing');
console.log('worker02 domain UX tests: OK');
