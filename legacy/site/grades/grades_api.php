<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/auth_store.php';
require_once __DIR__ . '/../api/grades_store.php';

$action = dent_request_action();

if ($action === 'me') {
    $user = dent_grades_require_user();
    dent_json_response(dent_build_grades_payload($user));
}

if ($action === 'ownerCatalog') {
    dent_grades_require_user();
    dent_require_owner();

    dent_json_response([
        'success' => true,
        'courses' => dent_owner_grades_course_catalog(),
    ]);
}

if ($action === 'ownerImportGrades') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد import نمرات نامعتبر است.', 405);
    }

    dent_grades_require_user();
    dent_require_owner();

    $result = null;
    $file = $_FILES['gradesFile'] ?? null;
    if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            dent_error('آپلود فایل نمرات انجام نشد.', 422);
        }
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            dent_error('فایل نمرات نامعتبر است.', 422);
        }
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === 'xlsx') {
            $result = dent_owner_import_grades_from_xlsx($tmpName);
        } else {
            $content = file_get_contents($tmpName);
            if ($content === false) {
                dent_error('خواندن فایل نمرات انجام نشد.', 422);
            }
            $result = dent_owner_import_grades_from_text($content);
        }
    } else {
        $importText = (string) ($_POST['importText'] ?? '');
        if (trim($importText) === '') {
            dent_error('متن import یا فایل نمرات را وارد کن.', 422);
        }
        $result = dent_owner_import_grades_from_text($importText);
    }

    dent_json_response(array_merge($result, [
        'message' => 'Import نمرات انجام شد.',
    ]));
}

if ($action === 'ownerDeleteGradeCourse') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد حذف درس نامعتبر است.', 405);
    }

    dent_grades_require_user();
    dent_require_owner();
    $courseKey = (string) ($_POST['courseKey'] ?? '');
    $result = dent_owner_delete_grade_course($courseKey);

    dent_json_response(array_merge($result, [
        'message' => 'درس از همه کارنامه‌ها حذف شد.',
    ]));
}

if ($action === 'ownerResetGradebook') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ریست کارنامه نامعتبر است.', 405);
    }

    dent_grades_require_user();
    dent_require_owner();
    $confirm = trim((string) ($_POST['confirm'] ?? ''));
    if ($confirm !== 'RESET') {
        dent_error('برای ریست کامل کارنامه تایید معتبر ارسال نشده است.', 422);
    }

    $result = dent_owner_reset_gradebook();
    dent_json_response(array_merge($result, [
        'message' => 'همه نمرات و درس‌ها از کارنامه حذف شدند.',
    ]));
}

dent_error('درخواست نامعتبر است.', 404);
