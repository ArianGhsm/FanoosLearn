'use strict';
const assert = require('node:assert/strict');
const path = require('node:path');
const uiRoot = path.resolve(__dirname, '../../../apps/platform/public/assets/ui-v2');
require(path.join(uiRoot, 'product-core.js'));
require(path.join(uiRoot, 'product-learning.js'));
require(path.join(uiRoot, 'product-academic.js'));
require(path.join(uiRoot, 'product-communication.js'));
require(path.join(uiRoot, 'product-account.js'));
const ui = require(path.join(uiRoot, 'product-ui.js'));

assert.deepEqual(ui.routeFromHash('#/home'), {name:'home', subview:'', query:{}});
assert.equal(ui.routeFromHash('#/academics').name, 'courses');
assert.equal(ui.routeFromHash('#/schedule/week').subview, 'week');
assert.equal(ui.routeFromHash('#/resources?q=%D8%A7%D9%86%D8%AF%D9%88&type=note').query.type, 'note');
assert.equal(ui.routeHash('courses',{course:'ENDO-1',tab:'resources'}).startsWith('#/courses?'), true);

const rows = [
  {id:'11111111-1111-4111-8111-111111111111',course_code:'ENDO-1',title:'اندودانتیکس',credit_value:'2',term_name:'ترم شش',session_id:'a',sequence_no:2,session_title:'جلسه دوم',starts_at:'2026-09-12T08:00:00Z'},
  {id:'11111111-1111-4111-8111-111111111111',course_code:'ENDO-1',title:'اندودانتیکس',credit_value:'2',term_name:'ترم شش',session_id:'b',sequence_no:1,session_title:'جلسه اول',starts_at:'2026-09-10T08:00:00Z'},
  {id:'22222222-2222-4222-8222-222222222222',course_code:'PERIO-1',title:'پریودانتیکس',term_name:'ترم شش'}
];
const courses = ui.courseGroups(rows);
assert.equal(courses.length, 2);
assert.equal(ui.findCourse(courses,'ENDO-1').title, 'اندودانتیکس');
assert.equal(ui.findCourse(courses,'ENDO-1').sessions[0].sequence, 1);
assert.equal(ui.courseLabelForId(courses,'22222222-2222-4222-8222-222222222222'), 'پریودانتیکس');
assert.equal(ui.text('\u202eالف\u2066ABC'), 'الفABC');
assert.equal(ui.bounded('x'.repeat(100), 10).length <= 10, true);
assert.equal(ui.pageMeta('grades').title, 'نمرات');
assert.equal(typeof ui.localDateKey('2026-09-09T08:00:00Z','Asia/Tehran'), 'string');
const bounds = ui.weekBounds('Asia/Tehran');
assert.match(bounds.start, /^\d{4}-\d{2}-\d{2}$/);
assert.match(bounds.end, /^\d{4}-\d{2}-\d{2}$/);
console.log('ui-v2 product helpers: PASS');
