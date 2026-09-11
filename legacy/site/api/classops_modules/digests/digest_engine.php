<?php
declare(strict_types=1);

const CLASSOPS_DIGEST_CONTRACT_VERSION = 'classops-digest-v1';
const CLASSOPS_DIGEST_RECORD_VERSION = 'classops-digest-record-v1';
const CLASSOPS_DIGEST_TIMEZONE = 'Asia/Tehran';
const CLASSOPS_DIGEST_WEEK_START_ISO = 6; // Saturday, PHP N=1..7.
const CLASSOPS_DIGEST_DEFAULT_MAX_ITEMS = 60;
const CLASSOPS_DIGEST_DEFAULT_MAX_CHARS = 7000;

final class DentClassOpsDigestException extends RuntimeException
{
    public function __construct(public readonly string $reasonCode, string $message)
    {
        parent::__construct($message);
    }
}

function classops_digest_error(string $code, string $message): never
{
    throw new DentClassOpsDigestException($code, $message);
}

function classops_digest_object($value, string $field): array
{
    if (!is_array($value) || array_is_list($value)) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_OBJECT', "$field must be an object");
    }
    return $value;
}

function classops_digest_keys(array $value, array $allowed, string $field): void
{
    $unknown = array_values(array_diff(array_keys($value), $allowed));
    if ($unknown !== []) {
        sort($unknown, SORT_STRING);
        classops_digest_error('CLASSOPS_DIGEST_UNKNOWN_FIELD', "$field contains unknown field(s): " . implode(', ', $unknown));
    }
}

function classops_digest_strlen(string $value): int
{
    $count = preg_match_all('/./us', $value, $matches);
    return $count === false ? strlen($value) : $count;
}

function classops_digest_text($value, string $field, int $max, bool $required = false): string
{
    if (!is_string($value) && $value !== null) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_TEXT', "$field must be text");
    }
    $value = trim((string) $value);
    if ($required && $value === '') {
        classops_digest_error('CLASSOPS_DIGEST_REQUIRED_FIELD', "$field is required");
    }
    if (classops_digest_strlen($value) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_TEXT', "$field is invalid");
    }
    return preg_replace('/\r\n|\r/u', "\n", $value) ?? '';
}

function classops_digest_ref($value, string $field, bool $required = true): ?string
{
    if (($value === null || $value === '') && !$required) {
        return null;
    }
    $value = classops_digest_text($value, $field, 120, $required);
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,119}$/', $value) !== 1) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_REF', "$field is not canonical");
    }
    return $value;
}

function classops_digest_student($value, bool $required = false): ?string
{
    if (($value === null || $value === '') && !$required) {
        return null;
    }
    if (!is_string($value) && !is_int($value)) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_VIEWER_ID', 'studentNumber must be digits');
    }
    $value = strtr(trim((string) $value), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
    if (preg_match('/^\d{5,20}$/', $value) !== 1) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_VIEWER_ID', 'studentNumber must be canonical digits');
    }
    return $value;
}

function classops_digest_utc($value, string $field, bool $required = false): ?string
{
    if (($value === null || $value === '') && !$required) {
        return null;
    }
    if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) !== 1) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_TIMESTAMP', "$field must be canonical UTC ISO-8601");
    }
    try {
        $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
    } catch (Throwable $exception) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_TIMESTAMP', "$field is invalid");
    }
    if ($date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z') !== $value) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_TIMESTAMP', "$field is not canonical UTC");
    }
    return $value;
}

function classops_digest_date($value, string $field, bool $required = false): ?string
{
    if (($value === null || $value === '') && !$required) {
        return null;
    }
    if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_LOCAL_DATE', "$field must be YYYY-MM-DD");
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone(CLASSOPS_DIGEST_TIMEZONE));
    if (!$date || $date->format('Y-m-d') !== $value) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_LOCAL_DATE', "$field is invalid");
    }
    return $value;
}

function classops_digest_bool($value, string $field): bool
{
    if (!is_bool($value)) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_BOOLEAN', "$field must be boolean");
    }
    return $value;
}

function classops_digest_normalize_viewer($value): array
{
    $v = classops_digest_object($value, 'viewer');
    classops_digest_keys($v, ['scope','cohortKey','studentNumber','canonicalUserId'], 'viewer');
    $scope = classops_digest_text($v['scope'] ?? null, 'viewer.scope', 16, true);
    if (!in_array($scope, ['student','owner'], true)) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_SCOPE', 'viewer.scope is invalid');
    }
    $cohort = classops_digest_text($v['cohortKey'] ?? null, 'viewer.cohortKey', 80, true);
    if (preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $cohort) !== 1) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_COHORT', 'viewer.cohortKey is invalid');
    }
    $student = classops_digest_student($v['studentNumber'] ?? null);
    $user = classops_digest_ref($v['canonicalUserId'] ?? null, 'viewer.canonicalUserId', false);
    if ($scope === 'student' && $student === null && $user === null) {
        classops_digest_error('CLASSOPS_DIGEST_VIEWER_ID_REQUIRED', 'student projection requires canonical identity');
    }
    return ['scope'=>$scope,'cohortKey'=>$cohort,'studentNumber'=>$student,'canonicalUserId'=>$user];
}

function classops_digest_normalize_timing($value): array
{
    $t = classops_digest_object($value, 'record.timing');
    classops_digest_keys($t, ['allDay','localDate','startsAtUtc','endsAtUtc','dueAtUtc','timezone'], 'record.timing');
    if (classops_digest_text($t['timezone'] ?? null, 'record.timing.timezone', 64, true) !== CLASSOPS_DIGEST_TIMEZONE) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_TIMEZONE', 'record timezone must be Asia/Tehran');
    }
    $allDay = classops_digest_bool($t['allDay'] ?? null, 'record.timing.allDay');
    $localDate = classops_digest_date($t['localDate'] ?? null, 'record.timing.localDate');
    $start = classops_digest_utc($t['startsAtUtc'] ?? null, 'record.timing.startsAtUtc');
    $end = classops_digest_utc($t['endsAtUtc'] ?? null, 'record.timing.endsAtUtc');
    $due = classops_digest_utc($t['dueAtUtc'] ?? null, 'record.timing.dueAtUtc');
    if (($allDay && $localDate === null) || (!$allDay && $localDate !== null) || ($start !== null && $end !== null && strcmp($end, $start) < 0)) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_TIMING', 'record timing is inconsistent');
    }
    return ['allDay'=>$allDay,'localDate'=>$localDate,'startsAtUtc'=>$start,'endsAtUtc'=>$end,'dueAtUtc'=>$due,'timezone'=>CLASSOPS_DIGEST_TIMEZONE];
}

function classops_digest_normalize_visibility($value): array
{
    $v = classops_digest_object($value, 'record.visibility');
    classops_digest_keys($v, ['studentAllowed','ownerAllowed','subject'], 'record.visibility');
    $subject = null;
    if (($v['subject'] ?? null) !== null) {
        $s = classops_digest_object($v['subject'], 'record.visibility.subject');
        classops_digest_keys($s, ['kind','value'], 'record.visibility.subject');
        $kind = classops_digest_text($s['kind'] ?? null, 'record.visibility.subject.kind', 32, true);
        if (!in_array($kind, ['studentNumber','canonicalUserId'], true)) {
            classops_digest_error('CLASSOPS_DIGEST_INVALID_SUBJECT', 'visibility subject kind is invalid');
        }
        $subject = ['kind'=>$kind,'value'=>$kind === 'studentNumber' ? classops_digest_student($s['value'] ?? null, true) : classops_digest_ref($s['value'] ?? null, 'record.visibility.subject.value')];
    }
    return ['studentAllowed'=>classops_digest_bool($v['studentAllowed'] ?? null, 'record.visibility.studentAllowed'),'ownerAllowed'=>classops_digest_bool($v['ownerAllowed'] ?? null, 'record.visibility.ownerAllowed'),'subject'=>$subject];
}

function classops_digest_normalize_record($value): array
{
    $r = classops_digest_object($value, 'record');
    $fields = ['recordVersion','entityRef','revision','cohortKey','source','itemType','title','description','course','location','importance','status','scheduleRef','timing','visibility','state','change','supersedesRef'];
    classops_digest_keys($r, $fields, 'record');
    if (classops_digest_text($r['recordVersion'] ?? null, 'record.recordVersion', 64, true) !== CLASSOPS_DIGEST_RECORD_VERSION) {
        classops_digest_error('CLASSOPS_DIGEST_RECORD_VERSION_UNSUPPORTED', 'recordVersion is unsupported');
    }
    $revision = filter_var($r['revision'] ?? null, FILTER_VALIDATE_INT);
    if ($revision === false || $revision < 1 || $revision > 1000000000) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_REVISION', 'record.revision is invalid');
    }
    $cohort = classops_digest_text($r['cohortKey'] ?? null, 'record.cohortKey', 80, true);
    if (preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $cohort) !== 1) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_COHORT', 'record.cohortKey is invalid');
    }
    $source = classops_digest_text($r['source'] ?? null, 'record.source', 24, true);
    if (!in_array($source, ['classops','schedule','task','requirement','exam','ack','service'], true)) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_SOURCE', 'record.source is invalid');
    }
    $type = classops_digest_text($r['itemType'] ?? null, 'record.itemType', 32, true);
    if (!in_array($type, ['announcement','event','class_change','deadline','task','requirement','exam','critical_notice','service_reminder','schedule_ref'], true)) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_ITEM_TYPE', 'record.itemType is invalid');
    }
    $importance = classops_digest_text($r['importance'] ?? null, 'record.importance', 16, true);
    $status = classops_digest_text($r['status'] ?? null, 'record.status', 16, true);
    if (!in_array($importance, ['normal','important','critical'], true) || !in_array($status, ['active','pending','completed','cancelled','superseded'], true)) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_STATE', 'record importance/status is invalid');
    }
    $state = classops_digest_object($r['state'] ?? null, 'record.state');
    classops_digest_keys($state, ['taskState','ackState'], 'record.state');
    $taskState = $state['taskState'] ?? null;
    $ackState = $state['ackState'] ?? null;
    if (($taskState !== null && !in_array($taskState, ['pending','overdue','completed'], true)) || ($ackState !== null && !in_array($ackState, ['pending','acked','not_required'], true))) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_STATE', 'record state is invalid');
    }
    $change = classops_digest_object($r['change'] ?? null, 'record.change');
    classops_digest_keys($change, ['kind','changedAtUtc'], 'record.change');
    $changeKind = classops_digest_text($change['kind'] ?? null, 'record.change.kind', 24, true);
    if (!in_array($changeKind, ['none','revised','cancelled','superseded'], true)) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_CHANGE', 'record change is invalid');
    }
    $course = null;
    if (($r['course'] ?? null) !== null) {
        $c = classops_digest_object($r['course'], 'record.course');
        classops_digest_keys($c, ['ref','title'], 'record.course');
        $course = ['ref'=>classops_digest_ref($c['ref'] ?? null, 'record.course.ref', false),'title'=>classops_digest_text($c['title'] ?? '', 'record.course.title', 180)];
        if ($course['ref'] === null && $course['title'] === '') {
            $course = null;
        }
    }
    return [
        'recordVersion'=>CLASSOPS_DIGEST_RECORD_VERSION,
        'entityRef'=>classops_digest_ref($r['entityRef'] ?? null, 'record.entityRef'),
        'revision'=>(int)$revision,
        'cohortKey'=>$cohort,
        'source'=>$source,
        'itemType'=>$type,
        'title'=>classops_digest_text($r['title'] ?? null, 'record.title', 160, true),
        'description'=>classops_digest_text($r['description'] ?? '', 'record.description', 1200),
        'course'=>$course,
        'location'=>classops_digest_text($r['location'] ?? '', 'record.location', 300),
        'importance'=>$importance,
        'status'=>$status,
        'scheduleRef'=>classops_digest_ref($r['scheduleRef'] ?? null, 'record.scheduleRef', false),
        'timing'=>classops_digest_normalize_timing($r['timing'] ?? null),
        'visibility'=>classops_digest_normalize_visibility($r['visibility'] ?? null),
        'state'=>['taskState'=>$taskState,'ackState'=>$ackState],
        'change'=>['kind'=>$changeKind,'changedAtUtc'=>classops_digest_utc($change['changedAtUtc'] ?? null, 'record.change.changedAtUtc')],
        'supersedesRef'=>classops_digest_ref($r['supersedesRef'] ?? null, 'record.supersedesRef', false),
    ];
}

function classops_digest_normalize_request(array $request): array
{
    classops_digest_keys($request, ['contractVersion','digestKind','viewer','window','budget','records'], 'request');
    if (classops_digest_text($request['contractVersion'] ?? null, 'contractVersion', 64, true) !== CLASSOPS_DIGEST_CONTRACT_VERSION) {
        classops_digest_error('CLASSOPS_DIGEST_VERSION_UNSUPPORTED', 'digest contract is unsupported');
    }
    $kind = classops_digest_text($request['digestKind'] ?? null, 'digestKind', 16, true);
    if (!in_array($kind, ['tomorrow','weekly'], true)) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_KIND', 'digestKind is invalid');
    }
    $window = classops_digest_object($request['window'] ?? null, 'window');
    classops_digest_keys($window, ['nowUtc','timezone'], 'window');
    if (classops_digest_text($window['timezone'] ?? null, 'window.timezone', 64, true) !== CLASSOPS_DIGEST_TIMEZONE) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_TIMEZONE', 'window timezone must be Asia/Tehran');
    }
    $budgetRaw = $request['budget'] ?? [];
    $budget = $budgetRaw === [] || $budgetRaw === null ? [] : classops_digest_object($budgetRaw, 'budget');
    classops_digest_keys($budget, ['maxItems','maxEstimatedChars'], 'budget');
    $maxItems = filter_var($budget['maxItems'] ?? CLASSOPS_DIGEST_DEFAULT_MAX_ITEMS, FILTER_VALIDATE_INT);
    $maxChars = filter_var($budget['maxEstimatedChars'] ?? CLASSOPS_DIGEST_DEFAULT_MAX_CHARS, FILTER_VALIDATE_INT);
    if ($maxItems === false || $maxItems < 1 || $maxItems > 200 || $maxChars === false || $maxChars < 512 || $maxChars > 50000) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_BUDGET', 'digest budget is invalid');
    }
    $records = $request['records'] ?? null;
    if (!is_array($records) || !array_is_list($records) || count($records) > 5000) {
        classops_digest_error('CLASSOPS_DIGEST_INVALID_RECORDS', 'records must be a bounded list');
    }
    return [
        'kind'=>$kind,
        'viewer'=>classops_digest_normalize_viewer($request['viewer'] ?? null),
        'nowUtc'=>classops_digest_utc($window['nowUtc'] ?? null, 'window.nowUtc', true),
        'budget'=>['maxItems'=>(int)$maxItems,'maxEstimatedChars'=>(int)$maxChars],
        'records'=>array_map('classops_digest_normalize_record', $records),
    ];
}

function classops_digest_canonical($value)
{
    if (!is_array($value)) {
        return $value;
    }
    if (!array_is_list($value)) {
        ksort($value, SORT_STRING);
    }
    foreach ($value as $key => $child) {
        $value[$key] = classops_digest_canonical($child);
    }
    return $value;
}

function classops_digest_canonical_json($value): string
{
    $json = json_encode(classops_digest_canonical($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        classops_digest_error('CLASSOPS_DIGEST_UNENCODABLE', 'digest data cannot be encoded');
    }
    return $json;
}

function classops_digest_visible(array $record, array $viewer): bool
{
    if ($record['cohortKey'] !== $viewer['cohortKey']) {
        return false;
    }
    $v = $record['visibility'];
    if ($viewer['scope'] === 'owner') {
        return $v['ownerAllowed'] === true;
    }
    if ($v['studentAllowed'] !== true) {
        return false;
    }
    $subject = $v['subject'];
    if ($subject === null) {
        return true;
    }
    $actual = $subject['kind'] === 'studentNumber' ? $viewer['studentNumber'] : $viewer['canonicalUserId'];
    return $actual !== null && hash_equals((string)$actual, (string)$subject['value']);
}

function classops_digest_dedupe(array $records): array
{
    $latest = [];
    foreach ($records as $record) {
        $ref = $record['entityRef'];
        if (!isset($latest[$ref]) || $record['revision'] > $latest[$ref]['revision']) {
            $latest[$ref] = $record;
        } elseif ($record['revision'] === $latest[$ref]['revision'] && classops_digest_canonical_json($record) !== classops_digest_canonical_json($latest[$ref])) {
            classops_digest_error('CLASSOPS_DIGEST_CONFLICTING_REVISION', 'same entity/revision has conflicting payloads');
        }
    }
    foreach ($latest as $record) {
        if ($record['supersedesRef'] !== null && $record['supersedesRef'] !== $record['entityRef']) {
            unset($latest[$record['supersedesRef']]);
        }
    }
    ksort($latest, SORT_STRING);
    return array_values($latest);
}

function classops_digest_window(string $kind, string $nowUtc): array
{
    $tz = new DateTimeZone(CLASSOPS_DIGEST_TIMEZONE);
    $now = (new DateTimeImmutable($nowUtc, new DateTimeZone('UTC')))->setTimezone($tz);
    $today = new DateTimeImmutable($now->format('Y-m-d') . ' 00:00:00', $tz);
    if ($kind === 'tomorrow') {
        $start = $today->modify('+1 day');
        $end = $start->modify('+1 day');
    } else {
        $days = (CLASSOPS_DIGEST_WEEK_START_ISO - (int)$today->format('N') + 7) % 7;
        $start = $today->modify('+' . ($days === 0 ? 7 : $days) . ' days');
        $end = $start->modify('+7 days');
    }
    return [
        'localStart'=>$start,
        'localEnd'=>$end,
        'startUtc'=>$start->setTimezone(new DateTimeZone('UTC')),
        'endUtc'=>$end->setTimezone(new DateTimeZone('UTC')),
        'localStartDate'=>$start->format('Y-m-d'),
        'localEndDateExclusive'=>$end->format('Y-m-d'),
    ];
}

function classops_digest_overlaps(array $timing, array $window): bool
{
    if ($timing['allDay']) {
        return $timing['localDate'] !== null && $timing['localDate'] >= $window['localStartDate'] && $timing['localDate'] < $window['localEndDateExclusive'];
    }
    $utc = new DateTimeZone('UTC');
    if ($timing['startsAtUtc'] !== null) {
        $start = new DateTimeImmutable($timing['startsAtUtc'], $utc);
        if ($timing['endsAtUtc'] === null) {
            return $start >= $window['startUtc'] && $start < $window['endUtc'];
        }
        $end = new DateTimeImmutable($timing['endsAtUtc'], $utc);
        return $start < $window['endUtc'] && $end > $window['startUtc'];
    }
    if ($timing['dueAtUtc'] !== null) {
        $due = new DateTimeImmutable($timing['dueAtUtc'], $utc);
        return $due >= $window['startUtc'] && $due < $window['endUtc'];
    }
    return false;
}

function classops_digest_pending(array $r): bool
{
    return $r['status'] === 'pending' || in_array($r['state']['taskState'], ['pending','overdue'], true);
}

function classops_digest_critical_ack(array $r): bool
{
    return $r['state']['ackState'] === 'pending' && ($r['importance'] === 'critical' || $r['itemType'] === 'critical_notice');
}

function classops_digest_relevant(array $r, string $kind, array $window): bool
{
    if (classops_digest_critical_ack($r) || (in_array($r['itemType'], ['task','requirement'], true) && classops_digest_pending($r))) {
        return true;
    }
    if ($r['itemType'] === 'service_reminder' && $r['status'] !== 'completed' && $r['timing']['startsAtUtc'] === null && $r['timing']['dueAtUtc'] === null && !$r['timing']['allDay']) {
        return true;
    }
    return classops_digest_overlaps($r['timing'], $window);
}

function classops_digest_priority(array $r): int
{
    if (classops_digest_critical_ack($r)) {
        return 1000;
    }
    $p = match ($r['itemType']) {
        'exam'=>800,'deadline'=>760,'task','requirement'=>$r['state']['taskState'] === 'overdue' ? 740 : 700,
        'class_change'=>680,'schedule_ref'=>660,'event'=>640,'critical_notice'=>620,'service_reminder'=>500,default=>400,
    };
    $p += $r['importance'] === 'critical' ? 100 : ($r['importance'] === 'important' ? 40 : 0);
    $p += in_array($r['status'], ['cancelled','superseded'], true) || in_array($r['change']['kind'], ['cancelled','superseded'], true) ? 120 : ($r['change']['kind'] === 'revised' ? 30 : 0);
    return $p;
}

function classops_digest_effective(array $r): ?string
{
    if ($r['timing']['dueAtUtc'] !== null) return $r['timing']['dueAtUtc'];
    if ($r['timing']['startsAtUtc'] !== null) return $r['timing']['startsAtUtc'];
    if ($r['timing']['allDay'] && $r['timing']['localDate'] !== null) {
        $local = new DateTimeImmutable($r['timing']['localDate'] . ' 00:00:00', new DateTimeZone(CLASSOPS_DIGEST_TIMEZONE));
        return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
    return $r['change']['changedAtUtc'];
}

function classops_digest_change_label(array $r): ?string
{
    if ($r['status'] === 'cancelled' || $r['change']['kind'] === 'cancelled') return 'لغوشده';
    if ($r['status'] === 'superseded' || $r['change']['kind'] === 'superseded') return 'جایگزین‌شده';
    return $r['change']['kind'] === 'revised' ? 'اصلاح‌شده' : null;
}

function classops_digest_section(array $r, string $kind): string
{
    if (classops_digest_critical_ack($r)) return 'critical_ack';
    if (in_array($r['status'], ['cancelled','superseded'], true) || in_array($r['change']['kind'], ['cancelled','superseded'], true) || ($kind === 'weekly' && $r['change']['kind'] === 'revised')) return 'changes';
    return match ($r['itemType']) {
        'schedule_ref','event','class_change'=>'schedule','deadline'=>'deadlines','task','requirement'=>$kind === 'weekly' ? 'outstanding_tasks' : 'tasks_requirements','exam'=>'exams','service_reminder'=>'service_reminders',default=>'other',
    };
}

function classops_digest_sections(string $kind): array
{
    return $kind === 'tomorrow' ? [
        'critical_ack'=>'تأییدهای فوری','schedule'=>'برنامه و تغییرات کلاس','deadlines'=>'ددلاین‌ها','tasks_requirements'=>'تکلیف‌ها و الزامات باز','exams'=>'آزمون‌ها','changes'=>'لغو و جایگزینی','service_reminders'=>'یادآوری‌های خدماتی','other'=>'سایر موارد',
    ] : [
        'critical_ack'=>'تأییدهای فوری حل‌نشده','schedule'=>'رویدادها و برنامه هفته آینده','deadlines'=>'ددلاین‌های هفته آینده','exams'=>'آزمون‌های هفته آینده','outstanding_tasks'=>'تکلیف‌ها و الزامات باز','changes'=>'موارد اصلاح‌شده یا لغوشده','service_reminders'=>'یادآوری‌های خدماتی','other'=>'سایر موارد مهم',
    ];
}

function classops_digest_project(array $r): array
{
    return [
        'entityRef'=>$r['entityRef'],'source'=>$r['source'],'itemType'=>$r['itemType'],'title'=>$r['title'],'description'=>$r['description'],'course'=>$r['course'],'location'=>$r['location'],'importance'=>$r['importance'],'status'=>$r['status'],'revision'=>$r['revision'],'scheduleRef'=>$r['scheduleRef'],'timing'=>$r['timing'],'state'=>$r['state'],
        'flags'=>['pending'=>classops_digest_pending($r),'overdue'=>$r['state']['taskState'] === 'overdue','criticalAck'=>classops_digest_critical_ack($r),'changed'=>$r['change']['kind'] !== 'none','cancelled'=>$r['status'] === 'cancelled' || $r['change']['kind'] === 'cancelled','superseded'=>$r['status'] === 'superseded' || $r['change']['kind'] === 'superseded'],
        'changeLabel'=>classops_digest_change_label($r),'priority'=>classops_digest_priority($r),'effectiveAtUtc'=>classops_digest_effective($r),
    ];
}

function classops_digest_sort(array &$items): void
{
    usort($items, static function(array $a, array $b): int {
        return ((int)$b['priority'] <=> (int)$a['priority'])
            ?: strcmp((string)($a['effectiveAtUtc'] ?? '9999-12-31T23:59:59Z'), (string)($b['effectiveAtUtc'] ?? '9999-12-31T23:59:59Z'))
            ?: strcmp($a['title'], $b['title'])
            ?: strcmp($a['entityRef'], $b['entityRef']);
    });
}

function classops_digest_time_label(array $e): string
{
    $t = $e['timing'];
    if ($t['allDay']) return $t['localDate'] . ' | تمام‌روز';
    $tz = new DateTimeZone(CLASSOPS_DIGEST_TIMEZONE);
    if ($t['dueAtUtc'] !== null) return (new DateTimeImmutable($t['dueAtUtc'], new DateTimeZone('UTC')))->setTimezone($tz)->format('Y-m-d H:i') . ' | مهلت';
    if ($t['startsAtUtc'] === null) return '';
    $s = (new DateTimeImmutable($t['startsAtUtc'], new DateTimeZone('UTC')))->setTimezone($tz);
    $label = $s->format('Y-m-d H:i');
    if ($t['endsAtUtc'] !== null) {
        $end = (new DateTimeImmutable($t['endsAtUtc'], new DateTimeZone('UTC')))->setTimezone($tz);
        $label .= '–' . $end->format($end->format('Y-m-d') === $s->format('Y-m-d') ? 'H:i' : 'Y-m-d H:i');
    }
    return $label;
}

function classops_digest_line(array $e): string
{
    $parts = [($e['changeLabel'] !== null ? '['.$e['changeLabel'].'] ' : '') . $e['title']];
    $time = classops_digest_time_label($e); if ($time !== '') $parts[] = $time;
    if (is_array($e['course']) && ($e['course']['title'] ?? '') !== '') $parts[] = $e['course']['title'];
    if ($e['location'] !== '') $parts[] = $e['location'];
    return '• ' . implode(' — ', $parts);
}

function classops_digest_budget(array $sections, array $budget, string $title): array
{
    $chars = classops_digest_strlen($title) + 2; $count = 0; $total = 0; $omitted = [];
    foreach ($sections as $s) $total += count($s['items']);
    foreach ($sections as &$s) {
        $kept = []; $header = false;
        foreach ($s['items'] as $e) {
            $cost = classops_digest_strlen(classops_digest_line($e)) + 1 + ($header ? 0 : classops_digest_strlen($s['label']) + 3);
            if ($count >= $budget['maxItems'] || $chars + $cost > $budget['maxEstimatedChars']) { $omitted[$s['key']] = ($omitted[$s['key']] ?? 0) + 1; continue; }
            $chars += $cost; $count++; $header = true; $kept[] = $e;
        }
        $s['total'] = count($s['items']); $s['omitted'] = $s['total'] - count($kept); $s['items'] = $kept;
    }
    unset($s); ksort($omitted, SORT_STRING);
    return ['sections'=>$sections,'budget'=>['maxItems'=>$budget['maxItems'],'maxEstimatedChars'=>$budget['maxEstimatedChars'],'estimatedChars'=>$chars,'totalItems'=>$total,'visibleItems'=>$count,'truncated'=>$count < $total,'omittedItems'=>$total-$count,'omittedBySection'=>$omitted]];
}

function classops_digest_build(array $request): array
{
    $n = classops_digest_normalize_request($request); $window = classops_digest_window($n['kind'], $n['nowUtc']);
    $visible = array_values(array_filter($n['records'], static fn(array $r): bool => classops_digest_visible($r, $n['viewer'])));
    $defs = classops_digest_sections($n['kind']); $groups = array_fill_keys(array_keys($defs), []);
    foreach (classops_digest_dedupe($visible) as $r) {
        if (!classops_digest_relevant($r, $n['kind'], $window) || ($r['status'] === 'completed' && !classops_digest_critical_ack($r))) continue;
        $groups[classops_digest_section($r, $n['kind'])][] = classops_digest_project($r);
    }
    $sections = [];
    foreach ($defs as $key=>$label) { classops_digest_sort($groups[$key]); $sections[] = ['key'=>$key,'label'=>$label,'items'=>$groups[$key]]; }
    $title = $n['kind'] === 'tomorrow' ? 'خلاصه فردا | '.$window['localStartDate'] : 'خلاصه هفتگی | '.$window['localStartDate'].' تا '.$window['localEnd']->modify('-1 day')->format('Y-m-d');
    $bounded = classops_digest_budget($sections, $n['budget'], $title);
    $lines = [$title]; $has = false;
    foreach ($bounded['sections'] as $s) { if ($s['items'] === []) continue; $has = true; $lines[]=''; $lines[]=$s['label']; foreach ($s['items'] as $e) $lines[] = classops_digest_line($e); }
    if (!$has) { $lines[]=''; $lines[]='برای این بازه موردی ثبت نشده است.'; }
    elseif ($bounded['budget']['truncated']) { $lines[]=''; $lines[]='… '.$bounded['budget']['omittedItems'].' مورد دیگر در این خروجی نمایش داده نشده است.'; }
    return [
        'contractVersion'=>CLASSOPS_DIGEST_CONTRACT_VERSION,'locale'=>'fa-IR','timezone'=>CLASSOPS_DIGEST_TIMEZONE,'digestKind'=>$n['kind'],'projectionScope'=>$n['viewer']['scope'],'cohortKey'=>$n['viewer']['cohortKey'],
        'window'=>['startUtc'=>$window['startUtc']->format('Y-m-d\TH:i:s\Z'),'endUtcExclusive'=>$window['endUtc']->format('Y-m-d\TH:i:s\Z'),'localStartDate'=>$window['localStartDate'],'localEndDateExclusive'=>$window['localEndDateExclusive'],'weekStartsOn'=>'saturday'],
        'title'=>$title,'empty'=>$bounded['budget']['totalItems']===0,'sections'=>$bounded['sections'],'budget'=>$bounded['budget'],'plainText'=>implode("\n", $lines),
    ];
}

function classops_digest_semantic_fingerprint(array $viewModel): string
{
    classops_digest_keys($viewModel, ['contractVersion','locale','timezone','digestKind','projectionScope','cohortKey','window','title','empty','sections','budget','plainText'], 'viewModel');
    return hash('sha256', classops_digest_canonical_json($viewModel));
}
