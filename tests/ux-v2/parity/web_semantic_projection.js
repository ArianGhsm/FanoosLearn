'use strict';
const fs = require('node:fs');
const path = require('node:path');

const fixturePath = process.argv[2];
if (!fixturePath) throw new Error('fixture path required');
const fixture = JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
const assets = path.resolve(__dirname, '../../../apps/platform/public/assets');
const domain = require(path.join(assets, 'domain-ux.js'));
require(path.join(assets, 'ui-v2/product-core.js'));
const ui = globalThis.FanoosProductUI;

const courseRows = [{
  id: fixture.course.id,
  course_code: fixture.course.course_code,
  title: fixture.course.title,
  term_name: fixture.course.term_name,
}];
const course = ui.courseGroups(courseRows)[0];
const tz = fixture.workspace.timezone_name;
const schedule = fixture.schedule;

const result = {
  workspace: {
    id: fixture.workspace.id,
    name: fixture.workspace.name,
    timezone: tz,
  },
  course: {
    id: course.id,
    code: course.code,
    title: course.title,
  },
  schedule: {
    title: schedule.title,
    course: schedule.course_title,
    local_date: ui.localDateKey(schedule.starts_at, tz),
    time: domain.formatTime(schedule.starts_at, { timeZone: tz }),
    location: schedule.location_text,
  },
  grade: {
    title: fixture.grade.item_title,
    course: fixture.grade.course_title,
    score: Number(fixture.grade.score),
    max_score: Number(fixture.grade.max_score),
  },
  announcement: {
    title: fixture.announcement.title,
    body: fixture.announcement.body,
  },
  resource: {
    title: fixture.resource.title,
    course: fixture.resource.course_title,
    type: domain.localizeResourceType(fixture.resource.type_key),
    version: Number(fixture.resource.current_version_no),
    protected: fixture.resource.requires_entitlement === true,
  },
  assessment: {
    title: fixture.assessment.title,
    course_id: fixture.assessment.course_id,
    type: domain.localizeAssessmentType(fixture.assessment.assessment_kind),
    max_attempts: Number(fixture.assessment.max_attempts),
  },
  order: {
    title: fixture.order.product_name_snapshot,
    amount: domain.formatMoney(fixture.order.total_minor, fixture.order.currency),
    status: domain.localizeStatus(fixture.order.status),
  },
  payment: {
    status: domain.localizeStatus(fixture.payment.status),
  },
  entitlement: {
    granted: fixture.entitlement.granted === true,
  },
  errors: fixture.errors,
};
process.stdout.write(JSON.stringify(result));
