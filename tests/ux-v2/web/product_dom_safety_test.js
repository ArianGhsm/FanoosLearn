'use strict';
const assert = require('node:assert/strict');
const path = require('node:path');

class FakeText { constructor(text){ this.nodeType=3; this.textContent=String(text); this.children=[]; } }
class FakeElement {
  constructor(tag){ this.tagName=String(tag).toLowerCase(); this.children=[]; this.attributes={}; this.textContent=''; this.className=''; this.listeners={}; this.hidden=false; this.value=''; this.type=''; this.childNodes=this.children; }
  append(...nodes){ nodes.filter(Boolean).forEach(n=>this.children.push(n)); }
  appendChild(node){ this.children.push(node); return node; }
  replaceChildren(...nodes){ this.children.length=0; this.children.push(...nodes.filter(Boolean)); this.textContent=''; }
  setAttribute(name,value){ this.attributes[name]=String(value); if(name==='class') this.className=String(value); if(name==='type') this.type=String(value); if(name==='value') this.value=String(value); }
  addEventListener(name,fn){ this.listeners[name]=fn; }
  removeAttribute(name){ delete this.attributes[name]; }
  get dataset(){ return {}; }
}
global.document = { createElement: tag => new FakeElement(tag), createTextNode: text => new FakeText(text) };
global.location = { origin:'https://fanoos.test', hash:'#/home' };
const uiRoot = path.resolve(__dirname, '../../../apps/platform/public/assets/ui-v2');
require(path.join(uiRoot, 'product-core.js'));
require(path.join(uiRoot, 'product-learning.js'));
require(path.join(uiRoot, 'product-academic.js'));
require(path.join(uiRoot, 'product-communication.js'));
require(path.join(uiRoot, 'product-account.js'));
const ui = require(path.join(uiRoot, 'product-ui.js'));
const c = () => new FakeElement('div');
const flatten = node => [String(node.textContent||''), ...((node.children||[]).map(flatten))].join('\n');
const tags = node => [node.tagName, ...((node.children||[]).flatMap(tags))];
const malicious = '<img src=x onerror=alert(1)><script>boom()</script>';
const ctx={workspaceTimezone:'Asia/Tehran',workspaceName:'فضای تست'};
const handlers={retry(){},navigate(){},openResource(){},openAssessment(){},openAnnouncement(){},openForm(){},switchWorkspace(){}};

let target=c();
ui.renderResources(target,{resources:{ok:true,data:[{id:'a',title:malicious,type:'note',description:malicious,workspace_id:'secret-uuid'}]},academics:{ok:true,data:{courses:[]}},route:{query:{}},context:ctx,handlers});
assert.equal(tags(target).includes('script'),false);
assert.equal(tags(target).includes('img'),false);
assert.equal(flatten(target).includes('secret-uuid'),false);

target=c();
ui.renderGrades(target,{grades:{ok:true,data:[{course_title:'ترمیمی',item_title:malicious,score:null,max_score:null,updated_at:'bad'}]},context:ctx,handlers});
assert.equal(tags(target).includes('script'),false);
assert.match(flatten(target),/ترمیمی/);

target=c();
ui.renderOrders(target,{orders:{ok:true,data:[{title:'سفارش',status:'pending',amount_minor:'bad',provider_reference:'secret-ref'}]},context:ctx,handlers});
assert.equal(flatten(target).includes('secret-ref'),false);
assert.equal(tags(target).includes('input'),false, 'orders page must not expose product-id input');

for (const fn of ['renderResources','renderAssessments','renderGrades','renderAnnouncements','renderForms','renderOrders']) {
  target=c();
  const key=fn.replace(/^render/,'').toLowerCase();
  const model={route:{query:{}},context:ctx,handlers,academics:{ok:true,data:{courses:[]}}};
  model[key]={ok:true,data:[]};
  assert.doesNotThrow(()=>ui[fn](target,model), fn);
  assert.ok(flatten(target).length>0, `${fn} empty state must render`);
}
console.log('ui-v2 DOM safety: PASS');
