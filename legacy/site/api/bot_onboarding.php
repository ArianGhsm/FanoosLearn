<?php
declare(strict_types=1);

/**
 * The website remains the only identity authority for both bots. Generic
 * onboarding stores verified profile metadata; class authentication creates a
 * permanent one-to-one link only after website OTP or website login.
 */

function dent_bot_onboarding_catalog(): array
{
    $institutions = [
        ['province' => 'هرمزگان', 'name' => 'دانشکده علوم پزشکی شرق هرمزگان'],
        ['province' => 'هرمزگان', 'name' => 'دانشکده علوم پزشکی غرب هرمزگان'],
        ['province' => 'همدان', 'name' => 'دانشکده علوم پزشکی اسدآباد'],
        ['province' => 'خراسان شمالی', 'name' => 'دانشکده علوم پزشکی اسفراین'],
        ['province' => 'خوزستان', 'name' => 'دانشکده علوم پزشکی بهبهان'],
        ['province' => 'چهارمحال و بختیاری', 'name' => 'دانشکده علوم پزشکی بروجن'],
        ['province' => 'خراسان رضوی', 'name' => 'دانشکده علوم پزشکی تربت جام'],
        ['province' => 'سیستان و بلوچستان', 'name' => 'دانشکده علوم پزشکی چابهار'],
        ['province' => 'اردبیل', 'name' => 'دانشکده علوم پزشکی خلخال'],
        ['province' => 'مرکزی', 'name' => 'دانشکده علوم پزشکی خمین'],
        ['province' => 'آذربایجان غربی', 'name' => 'دانشکده علوم پزشکی خوی'],
        ['province' => 'مرکزی', 'name' => 'دانشکده علوم پزشکی ساوه'],
        ['province' => 'آذربایجان شرقی', 'name' => 'دانشکده علوم پزشکی سراب'],
        ['province' => 'سیستان و بلوچستان', 'name' => 'دانشکده علوم پزشکی سراوان'],
        ['province' => 'کرمان', 'name' => 'دانشکده علوم پزشکی سیرجان'],
        ['province' => 'خوزستان', 'name' => 'دانشکده علوم پزشکی شوشتر'],
        ['province' => 'خراسان جنوبی', 'name' => 'دانشکده علوم پزشکی فردوس'],
        ['province' => 'خراسان جنوبی', 'name' => 'دانشکده علوم پزشکی قائنات'],
        ['province' => 'خراسان رضوی', 'name' => 'دانشکده علوم پزشکی کاشمر'],
        ['province' => 'فارس', 'name' => 'دانشکده علوم پزشکی گراش'],
        ['province' => 'فارس', 'name' => 'دانشکده علوم پزشکی لارستان'],
        ['province' => 'آذربایجان شرقی', 'name' => 'دانشکده علوم پزشکی مراغه'],
        ['province' => 'آذربایجان غربی', 'name' => 'دانشکده علوم پزشکی میاندوآب'],
        ['province' => 'تهران', 'name' => 'دانشگاه علوم پزشکی ارتش جمهوری اسلامی ایران'],
        ['province' => 'تهران', 'name' => 'دانشگاه علوم پزشکی بقیةالله (عج)'],
        ['province' => 'مرکزی', 'name' => 'دانشگاه علوم پزشکی اراک'],
        ['province' => 'اردبیل', 'name' => 'دانشگاه علوم پزشکی اردبیل'],
        ['province' => 'آذربایجان غربی', 'name' => 'دانشگاه علوم پزشکی ارومیه'],
        ['province' => 'اصفهان', 'name' => 'دانشگاه علوم پزشکی اصفهان'],
        ['province' => 'البرز', 'name' => 'دانشگاه علوم پزشکی البرز'],
        ['province' => 'خوزستان', 'name' => 'دانشگاه علوم پزشکی جندی‌شاپور اهواز'],
        ['province' => 'تهران', 'name' => 'دانشگاه علوم پزشکی ایران'],
        ['province' => 'سیستان و بلوچستان', 'name' => 'دانشگاه علوم پزشکی ایرانشهر'],
        ['province' => 'ایلام', 'name' => 'دانشگاه علوم پزشکی ایلام'],
        ['province' => 'خوزستان', 'name' => 'دانشگاه علوم پزشکی آبادان'],
        ['province' => 'مازندران', 'name' => 'دانشگاه علوم پزشکی بابل'],
        ['province' => 'خراسان شمالی', 'name' => 'دانشگاه علوم پزشکی خراسان شمالی (بجنورد)'],
        ['province' => 'کرمان', 'name' => 'دانشگاه علوم پزشکی بم'],
        ['province' => 'هرمزگان', 'name' => 'دانشگاه علوم پزشکی هرمزگان (بندرعباس)'],
        ['province' => 'بوشهر', 'name' => 'دانشگاه علوم پزشکی بوشهر'],
        ['province' => 'خراسان جنوبی', 'name' => 'دانشگاه علوم پزشکی بیرجند'],
        ['province' => 'آذربایجان شرقی', 'name' => 'دانشگاه علوم پزشکی تبریز'],
        ['province' => 'خراسان رضوی', 'name' => 'دانشگاه علوم پزشکی تربت حیدریه'],
        ['province' => 'تهران', 'name' => 'دانشگاه علوم پزشکی تهران'],
        ['province' => 'فارس', 'name' => 'دانشگاه علوم پزشکی جهرم'],
        ['province' => 'کرمان', 'name' => 'دانشگاه علوم پزشکی جیرفت'],
        ['province' => 'خوزستان', 'name' => 'دانشگاه علوم پزشکی دزفول'],
        ['province' => 'کرمان', 'name' => 'دانشگاه علوم پزشکی رفسنجان'],
        ['province' => 'سیستان و بلوچستان', 'name' => 'دانشگاه علوم پزشکی زابل'],
        ['province' => 'سیستان و بلوچستان', 'name' => 'دانشگاه علوم پزشکی زاهدان'],
        ['province' => 'زنجان', 'name' => 'دانشگاه علوم پزشکی زنجان'],
        ['province' => 'خراسان رضوی', 'name' => 'دانشگاه علوم پزشکی سبزوار'],
        ['province' => 'سمنان', 'name' => 'دانشگاه علوم پزشکی سمنان'],
        ['province' => 'سمنان', 'name' => 'دانشگاه علوم پزشکی شاهرود'],
        ['province' => 'چهارمحال و بختیاری', 'name' => 'دانشگاه علوم پزشکی شهرکرد'],
        ['province' => 'تهران', 'name' => 'دانشگاه علوم پزشکی شهید بهشتی'],
        ['province' => 'فارس', 'name' => 'دانشگاه علوم پزشکی شیراز'],
        ['province' => 'فارس', 'name' => 'دانشگاه علوم پزشکی فسا'],
        ['province' => 'قزوین', 'name' => 'دانشگاه علوم پزشکی قزوین'],
        ['province' => 'قم', 'name' => 'دانشگاه علوم پزشکی قم'],
        ['province' => 'اصفهان', 'name' => 'دانشگاه علوم پزشکی کاشان'],
        ['province' => 'کردستان', 'name' => 'دانشگاه علوم پزشکی کردستان'],
        ['province' => 'کرمان', 'name' => 'دانشگاه علوم پزشکی کرمان'],
        ['province' => 'کرمانشاه', 'name' => 'دانشگاه علوم پزشکی کرمانشاه'],
        ['province' => 'گلستان', 'name' => 'دانشگاه علوم پزشکی گلستان'],
        ['province' => 'خراسان رضوی', 'name' => 'دانشگاه علوم پزشکی گناباد'],
        ['province' => 'گیلان', 'name' => 'دانشگاه علوم پزشکی گیلان'],
        ['province' => 'لرستان', 'name' => 'دانشگاه علوم پزشکی لرستان'],
        ['province' => 'مازندران', 'name' => 'دانشگاه علوم پزشکی مازندران'],
        ['province' => 'خراسان رضوی', 'name' => 'دانشگاه علوم پزشکی مشهد'],
        ['province' => 'تهران', 'name' => 'دانشگاه مجازی وزارت بهداشت'],
        ['province' => 'خراسان رضوی', 'name' => 'دانشگاه علوم پزشکی نیشابور'],
        ['province' => 'همدان', 'name' => 'دانشگاه علوم پزشکی همدان'],
        ['province' => 'کهگیلویه و بویراحمد', 'name' => 'دانشگاه علوم پزشکی یاسوج'],
        ['province' => 'یزد', 'name' => 'دانشگاه علوم پزشکی یزد'],
    ];
    $institutions = array_map(
        static fn(array $item): array => $item + ['system' => 'public'],
        $institutions
    );
    $azadInstitutions = [
        ['province' => 'آذربایجان شرقی', 'name' => 'دانشگاه آزاد اسلامی واحد تبریز', 'system' => 'azad'],
        ['province' => 'آذربایجان شرقی', 'name' => 'دانشگاه آزاد اسلامی واحد شبستر', 'system' => 'azad'],
        ['province' => 'آذربایجان شرقی', 'name' => 'دانشگاه آزاد اسلامی واحد علوم پزشکی تبریز', 'system' => 'azad'],
        ['province' => 'آذربایجان غربی', 'name' => 'دانشگاه آزاد اسلامی واحد ارومیه', 'system' => 'azad'],
        ['province' => 'اردبیل', 'name' => 'دانشگاه آزاد اسلامی واحد اردبیل', 'system' => 'azad'],
        ['province' => 'اصفهان', 'name' => 'دانشگاه آزاد اسلامی واحد خوراسگان', 'system' => 'azad'],
        ['province' => 'اصفهان', 'name' => 'دانشگاه آزاد اسلامی واحد نجف‌آباد', 'system' => 'azad'],
        ['province' => 'البرز', 'name' => 'دانشگاه آزاد اسلامی واحد کرج', 'system' => 'azad'],
        ['province' => 'تهران', 'name' => 'دانشگاه علوم پزشکی آزاد اسلامی تهران', 'system' => 'azad'],
        ['province' => 'تهران', 'name' => 'دانشگاه آزاد اسلامی واحد علوم و تحقیقات تهران', 'system' => 'azad'],
        ['province' => 'چهارمحال و بختیاری', 'name' => 'دانشگاه آزاد اسلامی واحد شهرکرد', 'system' => 'azad'],
        ['province' => 'خراسان رضوی', 'name' => 'دانشگاه آزاد اسلامی واحد علوم پزشکی مشهد', 'system' => 'azad'],
        ['province' => 'خراسان شمالی', 'name' => 'دانشگاه آزاد اسلامی واحد بجنورد', 'system' => 'azad'],
        ['province' => 'خوزستان', 'name' => 'دانشگاه آزاد اسلامی واحد شوشتر', 'system' => 'azad'],
        ['province' => 'سمنان', 'name' => 'دانشگاه آزاد اسلامی واحد دامغان', 'system' => 'azad'],
        ['province' => 'سمنان', 'name' => 'دانشگاه آزاد اسلامی واحد شاهرود', 'system' => 'azad'],
        ['province' => 'سمنان', 'name' => 'دانشگاه آزاد اسلامی واحد گرمسار', 'system' => 'azad'],
        ['province' => 'سیستان و بلوچستان', 'name' => 'دانشگاه آزاد اسلامی واحد زاهدان', 'system' => 'azad'],
        ['province' => 'فارس', 'name' => 'دانشگاه آزاد اسلامی واحد شیراز', 'system' => 'azad'],
        ['province' => 'فارس', 'name' => 'دانشگاه آزاد اسلامی واحد کازرون', 'system' => 'azad'],
        ['province' => 'قم', 'name' => 'دانشگاه آزاد اسلامی واحد علوم پزشکی قم', 'system' => 'azad'],
        ['province' => 'کردستان', 'name' => 'دانشگاه آزاد اسلامی واحد سنندج', 'system' => 'azad'],
        ['province' => 'کرمان', 'name' => 'دانشگاه آزاد اسلامی واحد بافت', 'system' => 'azad'],
        ['province' => 'کرمان', 'name' => 'دانشگاه آزاد اسلامی واحد کرمان', 'system' => 'azad'],
        ['province' => 'لرستان', 'name' => 'دانشگاه آزاد اسلامی واحد بروجرد', 'system' => 'azad'],
        ['province' => 'مازندران', 'name' => 'دانشگاه آزاد اسلامی واحد آیت‌الله آملی', 'system' => 'azad'],
        ['province' => 'مازندران', 'name' => 'دانشگاه آزاد اسلامی واحد بابل', 'system' => 'azad'],
        ['province' => 'مازندران', 'name' => 'دانشگاه آزاد اسلامی واحد تنکابن', 'system' => 'azad'],
        ['province' => 'مازندران', 'name' => 'دانشگاه آزاد اسلامی واحد ساری', 'system' => 'azad'],
        ['province' => 'هرمزگان', 'name' => 'دانشگاه آزاد اسلامی واحد خودگردان قشم', 'system' => 'azad'],
        ['province' => 'یزد', 'name' => 'دانشگاه آزاد اسلامی واحد یزد', 'system' => 'azad'],
    ];
    $institutions = array_merge($institutions, $azadInstitutions);
    $provinces = array_values(array_unique(array_map(static fn(array $item): string => $item['province'], $institutions)));
    sort($provinces, SORT_STRING);
    return [
        'success' => true,
        'contractVersion' => 'bot-onboarding-v1',
        'majors' => ['دندانپزشکی', 'پزشکی', 'داروسازی'],
        'entryYears' => ['۱۳۹۹', '۱۴۰۰', '۱۴۰۱', '۱۴۰۲', '۱۴۰۳', '۱۴۰۴', '۱۴۰۵'],
        'entryTerms' => ['نیمسال اول', 'نیمسال دوم'],
        'courseTypes' => ['روزانه یا تعهدی', 'شهریه پرداز', 'بین الملل'],
        'admissionTypes' => [
            'نیمسال اول (روزانه یا تعهدی)',
            'نیمسال اول (شهریه پرداز)',
            'نیمسال اول (بین الملل)',
            'نیمسال دوم (روزانه یا تعهدی)',
            'نیمسال دوم (شهریه پرداز)',
            'نیمسال دوم (بین الملل)',
        ],
        'azadAdmissionTypes' => ['نیمسال اول', 'نیمسال دوم'],
        'provinces' => $provinces,
        'institutions' => $institutions,
        'source' => [
            'title' => 'فهرست مراکز علوم پزشکی کشور و ضمیمه واحدهای دانشگاه آزاد آزمون سراسری ۱۴۰۲',
            'institutionCount' => count($institutions),
            'verifiedAt' => '2026-08-27',
        ],
    ];
}

function dent_bot_onboarding_require_contract(array $payload): void
{
    if (!hash_equals('bot-onboarding-v1', (string) ($payload['contractVersion'] ?? ''))) {
        dent_error('نسخه قرارداد ثبت مشخصات ربات معتبر نیست.', 409, ['code' => 'BOT_ONBOARDING_CONTRACT_MISMATCH']);
    }
}

function dent_bot_onboarding_clean_profile(array $input): array
{
    $catalog = dent_bot_onboarding_catalog();
    $firstName = dent_clean_text((string) ($input['firstName'] ?? ''), 64);
    $lastName = dent_clean_text((string) ($input['lastName'] ?? ''), 64);
    $major = dent_clean_text((string) ($input['major'] ?? ''), 40);
    $province = dent_clean_text((string) ($input['province'] ?? ''), 64);
    $institution = dent_clean_text((string) ($input['institution'] ?? ''), 160);
    $entryYear = dent_bot_onboarding_entry_year((string) ($input['entryYear'] ?? ''));
    $admissionType = dent_bot_onboarding_admission_type($input);
    $studentNumber = dent_normalize_student_number((string) ($input['studentNumber'] ?? ''));
    if (dent_utf8_strlen($firstName) < 2 || dent_utf8_strlen($lastName) < 2) {
        dent_error('نام و نام خانوادگی معتبر نیست.', 422);
    }
    if (!in_array($major, $catalog['majors'], true) || !in_array($province, $catalog['provinces'], true)) {
        dent_error('رشته یا استان انتخاب‌شده در فهرست معتبر نیست.', 422, ['code' => 'INVALID_ACADEMIC_SELECTION']);
    }
    $validInstitution = false;
    $institutionSystem = 'public';
    foreach ($catalog['institutions'] as $item) {
        if ($item['province'] === $province && $item['name'] === $institution) {
            $validInstitution = true;
            $institutionSystem = (string) ($item['system'] ?? 'public');
            break;
        }
    }
    if (!$validInstitution) {
        dent_error('دانشگاه انتخاب‌شده با استان آن هم‌خوان نیست.', 422, ['code' => 'INVALID_INSTITUTION']);
    }
    if (!in_array($entryYear, $catalog['entryYears'], true)) {
        dent_error('سال ورود انتخاب‌شده در فهرست معتبر نیست.', 422, ['code' => 'INVALID_ENTRY_YEAR']);
    }
    $allowedAdmissionTypes = $institutionSystem === 'azad'
        ? $catalog['azadAdmissionTypes']
        : $catalog['admissionTypes'];
    if (!in_array($admissionType, $allowedAdmissionTypes, true)) {
        dent_error('نیمسال یا نوع دوره با دانشگاه انتخاب‌شده هم‌خوان نیست.', 422, ['code' => 'INVALID_ADMISSION_TYPE']);
    }
    if ($studentNumber !== '' && preg_match('/^[0-9]{5,20}$/', $studentNumber) !== 1) {
        dent_error('شماره دانشجویی معتبر نیست.', 422, ['code' => 'INVALID_STUDENT_NUMBER']);
    }
    return compact('firstName', 'lastName', 'major', 'province', 'institution', 'entryYear', 'admissionType', 'studentNumber');
}

function dent_bot_onboarding_entry_year(string $value): string
{
    return strtr(trim($value), [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        '٠' => '۰', '١' => '۱', '٢' => '۲', '٣' => '۳', '٤' => '۴',
        '٥' => '۵', '٦' => '۶', '٧' => '۷', '٨' => '۸', '٩' => '۹',
    ]);
}

function dent_bot_onboarding_admission_type(array $profile): string
{
    $current = trim((string) ($profile['admissionType'] ?? ''));
    if (in_array($current, ['نیمسال اول', 'نیمسال دوم'], true)) {
        return $current;
    }
    $term = trim((string) ($profile['entryTerm'] ?? ''));
    $course = trim((string) ($profile['courseType'] ?? ''));
    if ($current !== '' && preg_match('/^(نیمسال (?:اول|دوم)) \((.+)\)$/u', $current, $matches) === 1) {
        $term = (string) $matches[1];
        $course = (string) $matches[2];
    }
    $course = match ($course) {
        'روزانه', 'تعهدی', 'روزانه یا تعهدی' => 'روزانه یا تعهدی',
        'شهریه‌پرداز', 'شهریه پرداز' => 'شهریه پرداز',
        'بین‌الملل', 'بین الملل' => 'بین الملل',
        default => $course,
    };
    return $term !== '' && $course !== '' ? $term . ' (' . $course . ')' : '';
}

function dent_bot_onboarding_otp_purpose(string $identityHash, string $challengeRef): string
{
    return 'bot-onboarding-v1:' . substr($identityHash, 0, 20) . ':' . substr(hash('sha256', $challengeRef), 0, 20);
}

function dent_bot_onboarding_profile_public(array $profile): array
{
    return [
        'firstName' => (string) ($profile['firstName'] ?? ''),
        'lastName' => (string) ($profile['lastName'] ?? ''),
        'major' => (string) ($profile['major'] ?? ''),
        'province' => (string) ($profile['province'] ?? ''),
        'institution' => (string) ($profile['institution'] ?? ''),
        'entryYear' => dent_bot_onboarding_entry_year((string) ($profile['entryYear'] ?? '')),
        'admissionType' => dent_bot_onboarding_admission_type($profile),
        'studentNumber' => (string) ($profile['studentNumber'] ?? ''),
        'phoneMasked' => dent_mask_phone_number((string) ($profile['phoneNumber'] ?? '')),
        'verifiedAt' => (string) ($profile['verifiedAt'] ?? ''),
        'isClassMember' => !empty($profile['isClassMember']),
        'editRequestStatus' => (string) ($profile['editRequestStatus'] ?? ''),
    ];
}

function dent_bot_onboarding_status(string $platform, string $platformUserId): array
{
    $identityHash = dent_bot_identity_hash($platform, $platformUserId);
    return dent_bot_store_read(static function (array $store) use ($identityHash): array {
        $profileRef = (string) ($store['onboardingIdentityProfiles'][$identityHash] ?? '');
        $record = is_array($store['onboardingProfiles'][$profileRef] ?? null) ? $store['onboardingProfiles'][$profileRef] : null;
        $plain = is_array($record) ? dent_decrypt_secret_text($record['profileEncrypted'] ?? null) : '';
        $profile = $plain !== '' ? json_decode($plain, true) : null;
        if (is_array($profile)) {
            $latestStatus = '';
            $latestAt = '';
            foreach ($store['onboardingEditRequests'] as $request) {
                if (!is_array($request) || (string) ($request['identityHash'] ?? '') !== $identityHash) continue;
                $updatedAt = (string) ($request['updatedAt'] ?? '');
                if ($updatedAt >= $latestAt) {
                    $latestAt = $updatedAt;
                    $latestStatus = (string) ($request['status'] ?? '');
                }
            }
            $profile['editRequestStatus'] = $latestStatus;
        }
        return [
            'success' => true,
            'contractVersion' => 'bot-onboarding-v1',
            'complete' => is_array($profile),
            'profile' => is_array($profile) ? dent_bot_onboarding_profile_public($profile) : null,
        ];
    });
}

function dent_bot_onboarding_private_profile(string $platform, string $platformUserId): ?array
{
    $identityHash = dent_bot_identity_hash($platform, $platformUserId);
    return dent_bot_store_read(static function (array $store) use ($identityHash): ?array {
        $profileRef = (string) ($store['onboardingIdentityProfiles'][$identityHash] ?? '');
        $record = is_array($store['onboardingProfiles'][$profileRef] ?? null)
            ? $store['onboardingProfiles'][$profileRef]
            : null;
        $plain = is_array($record) ? dent_decrypt_secret_text($record['profileEncrypted'] ?? null) : '';
        $profile = $plain !== '' ? json_decode($plain, true) : null;
        return is_array($profile) ? $profile : null;
    });
}

function dent_bot_onboarding_request_otp(string $platform, string $platformUserId, array $payload): array
{
    $identityHash = dent_bot_identity_hash($platform, $platformUserId);
    $profile = dent_bot_onboarding_clean_profile(is_array($payload['profile'] ?? null) ? $payload['profile'] : []);
    $phone = dent_normalize_phone_number((string) ($payload['phoneNumber'] ?? ''));
    if ($phone === '') {
        dent_error('شماره موبایل معتبر نیست.', 422);
    }
    $challengeRef = dent_bot_base64url_encode(random_bytes(24));
    $purpose = dent_bot_onboarding_otp_purpose($identityHash, $challengeRef);
    $expiresAt = time() + 600;
    dent_bot_store_with_lock(static function (array &$store) use ($challengeRef, $identityHash, $platform, $profile, $phone, $purpose, $expiresAt): array {
        $store['onboardingChallenges'][$challengeRef] = [
            'identityHash' => $identityHash,
            'platform' => $platform,
            'profileEncrypted' => dent_encrypt_secret_text((string) json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'phoneEncrypted' => dent_encrypt_secret_text($phone),
            'purpose' => $purpose,
            'expiresAt' => $expiresAt,
            'createdAt' => dent_iso_now(),
            'usedAt' => '',
        ];
        return [];
    });
    $issued = dent_issue_otp_for_phone($purpose, $phone, (string) ($profile['studentNumber'] ?? ''));
    if (empty($issued['success'])) {
        dent_bot_store_with_lock(static function (array &$store) use ($challengeRef): array {
            unset($store['onboardingChallenges'][$challengeRef]);
            return [];
        });
        dent_error((string) ($issued['error'] ?? 'ارسال کد تایید انجام نشد.'), (int) ($issued['statusCode'] ?? 500));
    }
    return [
        'success' => true,
        'contractVersion' => 'bot-onboarding-v1',
        'challengeRef' => $challengeRef,
        'phoneMasked' => (string) ($issued['phoneMasked'] ?? dent_mask_phone_number($phone)),
        'expiresInSeconds' => (int) ($issued['expiresInSeconds'] ?? 180),
        'cooldownSeconds' => (int) ($issued['cooldownSeconds'] ?? 60),
    ];
}

function dent_bot_onboarding_challenge(string $identityHash, string $challengeRef): array
{
    if (preg_match('/^[A-Za-z0-9_-]{20,80}$/', $challengeRef) !== 1) {
        dent_error('درخواست کد تایید معتبر نیست.', 422);
    }
    return dent_bot_store_read(static function (array $store) use ($identityHash, $challengeRef): array {
        $record = is_array($store['onboardingChallenges'][$challengeRef] ?? null) ? $store['onboardingChallenges'][$challengeRef] : null;
        if (!is_array($record) || !hash_equals((string) ($record['identityHash'] ?? ''), $identityHash)
            || (int) ($record['expiresAt'] ?? 0) <= time() || (string) ($record['usedAt'] ?? '') !== '') {
            dent_error('درخواست کد تایید پیدا نشد یا منقضی شده است.', 404);
        }
        return $record;
    });
}

function dent_bot_onboarding_resend_otp(string $platform, string $platformUserId, array $payload): array
{
    $identityHash = dent_bot_identity_hash($platform, $platformUserId);
    $challengeRef = trim((string) ($payload['challengeRef'] ?? ''));
    $record = dent_bot_onboarding_challenge($identityHash, $challengeRef);
    $phone = dent_decrypt_secret_text($record['phoneEncrypted'] ?? null);
    $profileRaw = dent_decrypt_secret_text($record['profileEncrypted'] ?? null);
    $profile = $profileRaw !== '' ? json_decode($profileRaw, true) : null;
    if ($phone === '' || !is_array($profile)) {
        dent_error('داده امن درخواست قابل بازیابی نیست.', 500);
    }
    $issued = dent_issue_otp_for_phone((string) $record['purpose'], $phone, (string) ($profile['studentNumber'] ?? ''));
    if (empty($issued['success'])) {
        dent_error((string) ($issued['error'] ?? 'ارسال مجدد کد انجام نشد.'), (int) ($issued['statusCode'] ?? 500));
    }
    return [
        'success' => true,
        'contractVersion' => 'bot-onboarding-v1',
        'challengeRef' => $challengeRef,
        'phoneMasked' => (string) ($issued['phoneMasked'] ?? dent_mask_phone_number($phone)),
        'expiresInSeconds' => (int) ($issued['expiresInSeconds'] ?? 180),
        'cooldownSeconds' => (int) ($issued['cooldownSeconds'] ?? 60),
    ];
}

function dent_bot_onboarding_verify_otp(string $platform, string $platformUserId, array $payload): array
{
    $identityHash = dent_bot_identity_hash($platform, $platformUserId);
    $challengeRef = trim((string) ($payload['challengeRef'] ?? ''));
    $record = dent_bot_onboarding_challenge($identityHash, $challengeRef);
    $phone = dent_decrypt_secret_text($record['phoneEncrypted'] ?? null);
    $profileRaw = dent_decrypt_secret_text($record['profileEncrypted'] ?? null);
    $profile = $profileRaw !== '' ? json_decode($profileRaw, true) : null;
    if ($phone === '' || !is_array($profile)) {
        dent_error('داده امن درخواست قابل بازیابی نیست.', 500);
    }
    $verified = dent_verify_otp_for_phone(
        (string) $record['purpose'],
        $phone,
        (string) ($payload['code'] ?? ''),
        (string) ($profile['studentNumber'] ?? '')
    );
    if (empty($verified['success'])) {
        dent_error((string) ($verified['error'] ?? 'کد تایید پذیرفته نشد.'), (int) ($verified['statusCode'] ?? 422));
    }
    $profile['phoneNumber'] = $phone;
    $profile['verifiedAt'] = dent_iso_now();
    $phoneHash = hash_hmac('sha256', 'bot-onboarding-phone:' . $phone, dent_auth_secret_key());
    $profileRef = substr($phoneHash, 0, 32);
    dent_bot_store_with_lock(static function (array &$store) use ($challengeRef, $identityHash, $platform, $profile, $profileRef, $phoneHash): array {
        $now = dent_iso_now();
        $store['onboardingProfiles'][$profileRef] = [
            'profileRef' => $profileRef,
            'phoneHash' => $phoneHash,
            'profileEncrypted' => dent_encrypt_secret_text((string) json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'updatedAt' => $now,
        ];
        $store['onboardingIdentityProfiles'][$identityHash] = $profileRef;
        $store['onboardingChallenges'][$challengeRef]['usedAt'] = $now;
        dent_bot_audit($store, 'onboarding-profile-verified', $identityHash, (string) ($profile['studentNumber'] ?? ''), $platform);
        return [];
    });
    return [
        'success' => true,
        'contractVersion' => 'bot-onboarding-v1',
        'complete' => true,
        'profile' => dent_bot_onboarding_profile_public($profile),
        'websiteAccountLinked' => false,
    ];
}

function dent_bot_class_profile(array $user): array
{
    $fullName = trim((string) ($user['name'] ?? ''));
    $parts = preg_split('/\s+/u', $fullName, 2) ?: [];
    return [
        'firstName' => (string) ($parts[0] ?? ''),
        'lastName' => (string) ($parts[1] ?? ''),
        'major' => 'دندانپزشکی',
        'province' => 'تهران',
        'institution' => 'دانشگاه علوم پزشکی تهران',
        'entryYear' => '۱۴۰۲',
        'admissionType' => 'نیمسال اول (روزانه یا تعهدی)',
        'studentNumber' => dent_normalize_student_number((string) ($user['studentNumber'] ?? '')),
        'phoneNumber' => dent_normalize_phone_number((string) ($user['phoneNumber'] ?? '')),
        'verifiedAt' => dent_iso_now(),
        'isClassMember' => true,
    ];
}

function dent_bot_store_profile_for_identity(array &$store, string $identityHash, string $platform, array $profile): array
{
    $phone = dent_normalize_phone_number((string) ($profile['phoneNumber'] ?? ''));
    $studentNumber = dent_normalize_student_number((string) ($profile['studentNumber'] ?? ''));
    $stable = $phone !== '' ? 'phone:' . $phone : 'student:' . $studentNumber;
    $profileHash = hash_hmac('sha256', 'bot-onboarding-profile:' . $stable, dent_auth_secret_key());
    $profileRef = substr($profileHash, 0, 32);
    $store['onboardingProfiles'][$profileRef] = [
        'profileRef' => $profileRef,
        'phoneHash' => $phone !== '' ? hash_hmac('sha256', 'bot-onboarding-phone:' . $phone, dent_auth_secret_key()) : '',
        'profileEncrypted' => dent_encrypt_secret_text((string) json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        'updatedAt' => dent_iso_now(),
    ];
    $store['onboardingIdentityProfiles'][$identityHash] = $profileRef;
    dent_bot_audit($store, 'onboarding-profile-linked', $identityHash, $studentNumber, $platform);
    return $profile;
}

function dent_bot_ensure_linked_profile(string $platform, string $platformUserId, array $user): void
{
    if (dent_user_cohort_key($user) !== dent_primary_cohort_key()) {
        return;
    }
    $identityHash = dent_bot_identity_hash($platform, $platformUserId);
    dent_bot_store_with_lock(static function (array &$store) use ($identityHash, $platform, $user): array {
        $profileRef = (string) ($store['onboardingIdentityProfiles'][$identityHash] ?? '');
        if ($profileRef !== '' && is_array($store['onboardingProfiles'][$profileRef] ?? null)) {
            $plain = dent_decrypt_secret_text($store['onboardingProfiles'][$profileRef]['profileEncrypted'] ?? null);
            $current = $plain !== '' ? json_decode($plain, true) : null;
            if (is_array($current) && !empty($current['isClassMember'])) {
                return [];
            }
        }
        dent_bot_store_profile_for_identity($store, $identityHash, $platform, dent_bot_class_profile($user));
        return [];
    });
}

function dent_bot_class_auth_otp_purpose(string $identityHash, string $challengeRef): string
{
    return 'bot-class-auth-v1:' . substr($identityHash, 0, 20) . ':' . substr(hash('sha256', $challengeRef), 0, 20);
}

function dent_bot_class_auth_otp_start(string $platform, string $platformUserId, array $payload): array
{
    [$platform, $platformUserId] = dent_bot_identity($platform, $platformUserId);
    $studentNumber = dent_normalize_student_number((string) ($payload['studentNumber'] ?? ''));
    $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
    if (!is_array($user) || dent_user_cohort_key($user) !== dent_primary_cohort_key()) {
        dent_error('حسابی از ورودی ۱۴۰۲ دندانپزشکی تهران با این شماره دانشجویی پیدا نشد.', 404, ['code' => 'CLASS_ACCOUNT_NOT_FOUND']);
    }
    if (!dent_user_phone_ready_for_otp($user)) {
        dent_error('شماره این حساب در سایت برای ورود با OTP ثبت و فعال نشده است؛ از ورود امن سایت استفاده کن.', 409, ['code' => 'CLASS_PHONE_OTP_NOT_READY']);
    }
    $identityHash = dent_bot_identity_hash($platform, $platformUserId);
    $existingLink = dent_bot_link_for_identity($platform, $platformUserId);
    $existing = dent_bot_linked_user($platform, $platformUserId);
    if (is_array($existing) && dent_bot_link_auth_complete($existingLink)) {
        return [
            'success' => true,
            'alreadyLinked' => true,
            'authComplete' => true,
            'authVersion' => dent_bot_canonical_auth_version(),
            'user' => dent_bot_public_user($existing),
        ];
    }
    if (is_array($existingLink)
        && dent_normalize_student_number((string) ($existingLink['studentNumber'] ?? '')) !== $studentNumber) {
        dent_error('این حساب ربات قبلاً به حساب دیگری متصل شده است.', 409);
    }
    $challengeRef = dent_bot_base64url_encode(random_bytes(24));
    $purpose = dent_bot_class_auth_otp_purpose($identityHash, $challengeRef);
    $phone = dent_normalize_phone_number((string) ($user['phoneNumber'] ?? ''));
    $expiresAt = time() + 600;
    $platformProfile = dent_bot_telegram_profile($payload);
    dent_bot_store_with_lock(static function (array &$store) use ($challengeRef, $identityHash, $platform, $platformUserId, $studentNumber, $phone, $purpose, $expiresAt, $platformProfile): array {
        $conflict = dent_bot_conflicting_link($store, $identityHash, $platform, $studentNumber);
        if (is_array($conflict)) {
            dent_error('این فرد یا حساب ربات قبلاً اتصال قطعی دیگری دارد؛ تغییر فقط توسط مالک ممکن است.', 409);
        }
        $store['onboardingChallenges'][$challengeRef] = [
            'kind' => 'class-auth',
            'identityHash' => $identityHash,
            'platform' => $platform,
            'platformUserIdEncrypted' => dent_encrypt_secret_text($platformUserId),
            'platformProfileEncrypted' => dent_encrypt_secret_text((string) json_encode($platformProfile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'studentNumber' => $studentNumber,
            'phoneEncrypted' => dent_encrypt_secret_text($phone),
            'purpose' => $purpose,
            'expiresAt' => $expiresAt,
            'createdAt' => dent_iso_now(),
            'usedAt' => '',
        ];
        return [];
    });
    $issued = dent_issue_otp_for_phone($purpose, $phone, $studentNumber);
    if (empty($issued['success'])) {
        dent_bot_store_with_lock(static function (array &$store) use ($challengeRef): array {
            unset($store['onboardingChallenges'][$challengeRef]);
            return [];
        });
        dent_error((string) ($issued['error'] ?? 'ارسال کد تایید انجام نشد.'), (int) ($issued['statusCode'] ?? 500));
    }
    return [
        'success' => true,
        'challengeRef' => $challengeRef,
        'phoneMasked' => (string) ($issued['phoneMasked'] ?? dent_mask_phone_number($phone)),
        'expiresInSeconds' => (int) ($issued['expiresInSeconds'] ?? 180),
        'cooldownSeconds' => (int) ($issued['cooldownSeconds'] ?? 60),
    ];
}

function dent_bot_class_auth_otp_verify(string $platform, string $platformUserId, array $payload): array
{
    [$platform, $platformUserId] = dent_bot_identity($platform, $platformUserId);
    $identityHash = dent_bot_identity_hash($platform, $platformUserId);
    $challengeRef = trim((string) ($payload['challengeRef'] ?? ''));
    $record = dent_bot_onboarding_challenge($identityHash, $challengeRef);
    if ((string) ($record['kind'] ?? '') !== 'class-auth') {
        dent_error('درخواست احراز هویت کلاس معتبر نیست.', 422);
    }
    $studentNumber = dent_normalize_student_number((string) ($record['studentNumber'] ?? ''));
    $phone = dent_decrypt_secret_text($record['phoneEncrypted'] ?? null);
    $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
    if (!is_array($user) || dent_user_cohort_key($user) !== dent_primary_cohort_key() || $phone === '') {
        dent_error('حساب مرجع کلاس قابل بازیابی نیست.', 409);
    }
    $verified = dent_verify_otp_for_phone((string) $record['purpose'], $phone, (string) ($payload['code'] ?? ''), $studentNumber);
    if (empty($verified['success'])) {
        dent_error((string) ($verified['error'] ?? 'کد تایید پذیرفته نشد.'), (int) ($verified['statusCode'] ?? 422));
    }
    $profile = dent_bot_class_profile($user);
    return dent_bot_store_with_lock(static function (array &$store) use ($challengeRef, $identityHash, $platform, $studentNumber, $record, $profile, $user): array {
        $conflict = dent_bot_conflicting_link($store, $identityHash, $platform, $studentNumber);
        if (is_array($conflict)) {
            dent_error('این فرد یا حساب ربات قبلاً اتصال قطعی دیگری دارد؛ تغییر فقط توسط مالک ممکن است.', 409);
        }
        $store['links'][$identityHash] = [
            'identityHash' => $identityHash,
            'platform' => $platform,
            'platformUserIdEncrypted' => $record['platformUserIdEncrypted'] ?? null,
            'platformProfileEncrypted' => $record['platformProfileEncrypted'] ?? null,
            'studentNumber' => $studentNumber,
            'linkedAt' => dent_iso_now(),
            'source' => 'class-site-otp',
            'authVersion' => dent_bot_canonical_auth_version(),
            'authMethod' => 'class-site-otp',
            'authCompletedAt' => dent_iso_now(),
        ];
        $store['onboardingChallenges'][$challengeRef]['usedAt'] = dent_iso_now();
        if (is_array($store['identityClaims'][$identityHash] ?? null)) {
            $store['identityClaims'][$identityHash]['status'] = 'migrated-linked-v2';
            $store['identityClaims'][$identityHash]['updatedAt'] = dent_iso_now();
        }
        dent_bot_store_profile_for_identity($store, $identityHash, $platform, $profile);
        dent_bot_audit($store, 'class-account-linked-otp', $identityHash, $studentNumber);
        return [
            'success' => true,
            'linked' => true,
            'authComplete' => true,
            'authVersion' => dent_bot_canonical_auth_version(),
            'user' => dent_bot_public_user($user),
            'profile' => dent_bot_onboarding_profile_public($profile),
        ];
    });
}

function dent_bot_profile_edit_field_labels(): array
{
    return [
        'firstName' => 'نام', 'lastName' => 'نام خانوادگی', 'major' => 'رشته',
        'province' => 'استان دانشگاه', 'institution' => 'دانشگاه',
        'entryYear' => 'سال ورود', 'admissionType' => 'نوع پذیرش و نیمسال',
        'studentNumber' => 'شماره دانشجویی',
    ];
}

function dent_bot_request_profile_edit(array $user, string $platform, string $platformUserId, array $payload): array
{
    dent_bot_ensure_linked_profile($platform, $platformUserId, $user);
    $identityHash = dent_bot_identity_hash($platform, $platformUserId);
    $field = trim((string) ($payload['field'] ?? ''));
    $value = dent_clean_text((string) ($payload['value'] ?? ''), 180);
    $labels = dent_bot_profile_edit_field_labels();
    if (!isset($labels[$field]) || $value === '') {
        dent_error('بخش یا مقدار درخواستی معتبر نیست.', 422);
    }
    if ($field === 'studentNumber') {
        $value = dent_normalize_student_number($value);
        if (preg_match('/^[0-9]{5,20}$/', $value) !== 1) {
            dent_error('شماره دانشجویی پیشنهادی معتبر نیست.', 422);
        }
    }
    $catalog = dent_bot_onboarding_catalog();
    if ($field === 'major' && !in_array($value, $catalog['majors'], true)) {
        dent_error('رشته پیشنهادی معتبر نیست.', 422);
    }
    if ($field === 'province' && !in_array($value, $catalog['provinces'], true)) {
        dent_error('استان پیشنهادی معتبر نیست.', 422);
    }
    if ($field === 'institution') {
        $validInstitutions = [];
        foreach ($catalog['institutions'] as $institution) {
            if (is_array($institution)) $validInstitutions[] = (string) ($institution['name'] ?? '');
        }
        if (!in_array($value, $validInstitutions, true)) {
            dent_error('دانشگاه پیشنهادی معتبر نیست.', 422);
        }
    }
    if ($field === 'entryYear') {
        $value = dent_bot_onboarding_entry_year($value);
        if (!in_array($value, $catalog['entryYears'], true)) {
            dent_error('سال ورود پیشنهادی معتبر نیست.', 422);
        }
    }
    if ($field === 'admissionType' && !in_array($value, $catalog['admissionTypes'], true)) {
        dent_error('نوع پذیرش پیشنهادی معتبر نیست.', 422);
    }
    $ref = dent_bot_base64url_encode(random_bytes(9));
    return dent_bot_store_with_lock(static function (array &$store) use ($identityHash, $platform, $user, $field, $value, $labels, $ref): array {
        foreach ($store['onboardingEditRequests'] as $request) {
            if (is_array($request) && (string) ($request['identityHash'] ?? '') === $identityHash && (string) ($request['status'] ?? '') === 'pending') {
                dent_error('یک درخواست ویرایش در انتظار بررسی داری.', 409, ['code' => 'PROFILE_EDIT_PENDING']);
            }
        }
        $profileRef = (string) ($store['onboardingIdentityProfiles'][$identityHash] ?? '');
        if ($profileRef === '' || !is_array($store['onboardingProfiles'][$profileRef] ?? null)) {
            dent_error('پروفایل تأییدشده پیدا نشد.', 404);
        }
        $profileRecord = $store['onboardingProfiles'][$profileRef];
        $profilePlain = dent_decrypt_secret_text($profileRecord['profileEncrypted'] ?? null);
        $profile = $profilePlain !== '' ? json_decode($profilePlain, true) : null;
        if (!is_array($profile)) {
            dent_error('پروفایل تأییدشده قابل بازیابی نیست.', 500);
        }
        $previousValue = dent_clean_text((string) ($profile[$field] ?? ''), 180);
        $store['onboardingEditRequests'][$ref] = [
            'ref' => $ref, 'identityHash' => $identityHash, 'platform' => $platform,
            'profileRef' => $profileRef, 'studentNumber' => dent_normalize_student_number((string) ($user['studentNumber'] ?? '')),
            'field' => $field, 'fieldLabel' => $labels[$field],
            'previousValueEncrypted' => dent_encrypt_secret_text($previousValue),
            'valueEncrypted' => dent_encrypt_secret_text($value), 'status' => 'pending',
            'createdAt' => dent_iso_now(), 'updatedAt' => dent_iso_now(),
        ];
        dent_bot_audit($store, 'profile-edit-requested', $identityHash, (string) ($user['studentNumber'] ?? ''), $field);
        return ['success' => true, 'status' => 'pending', 'ref' => $ref, 'fieldLabel' => $labels[$field]];
    });
}

function dent_bot_profile_edit_requests(array $owner): array
{
    dent_bot_require_owner($owner);
    return dent_bot_store_read(static function (array $store): array {
        $items = [];
        foreach ($store['onboardingEditRequests'] as $request) {
            if (!is_array($request) || (string) ($request['status'] ?? '') !== 'pending') continue;
            $studentNumber = dent_normalize_student_number((string) ($request['studentNumber'] ?? ''));
            $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
            $items[] = [
                'ref' => (string) ($request['ref'] ?? ''),
                'name' => is_array($user) ? (string) ($user['name'] ?? '') : 'کاربر',
                'studentNumber' => $studentNumber,
                'platform' => (string) ($request['platform'] ?? ''),
                'fieldLabel' => (string) ($request['fieldLabel'] ?? ''),
                'previousValue' => dent_decrypt_secret_text($request['previousValueEncrypted'] ?? null),
                'value' => dent_decrypt_secret_text($request['valueEncrypted'] ?? null),
                'createdAt' => (string) ($request['createdAt'] ?? ''),
            ];
        }
        return ['success' => true, 'requests' => array_slice($items, 0, 50)];
    });
}

function dent_bot_resolve_profile_edit(array $owner, array $payload): array
{
    dent_bot_require_owner($owner);
    $ref = trim((string) ($payload['requestRef'] ?? ''));
    $decision = trim((string) ($payload['decision'] ?? ''));
    if (preg_match('/^[A-Za-z0-9_-]{12}$/', $ref) !== 1 || !in_array($decision, ['approve', 'reject'], true)) {
        dent_error('درخواست بررسی ویرایش معتبر نیست.', 422);
    }
    return dent_bot_store_with_lock(static function (array &$store) use ($ref, $decision): array {
        $request = is_array($store['onboardingEditRequests'][$ref] ?? null) ? $store['onboardingEditRequests'][$ref] : null;
        if (!is_array($request) || (string) ($request['status'] ?? '') !== 'pending') {
            dent_error('درخواست ویرایش پیدا نشد یا قبلاً بررسی شده است.', 404);
        }
        if ($decision === 'approve') {
            $profileRef = (string) ($request['profileRef'] ?? '');
            $record = is_array($store['onboardingProfiles'][$profileRef] ?? null) ? $store['onboardingProfiles'][$profileRef] : null;
            $plain = is_array($record) ? dent_decrypt_secret_text($record['profileEncrypted'] ?? null) : '';
            $profile = $plain !== '' ? json_decode($plain, true) : null;
            if (!is_array($profile)) dent_error('پروفایل مرجع قابل بازیابی نیست.', 500);
            $field = (string) ($request['field'] ?? '');
            $currentValue = dent_clean_text((string) ($profile[$field] ?? ''), 180);
            $previousValue = dent_decrypt_secret_text($request['previousValueEncrypted'] ?? null);
            if ($currentValue !== $previousValue) {
                dent_error('این مشخصه بعد از ثبت درخواست تغییر کرده است؛ درخواست قدیمی برای جلوگیری از بازنویسی ناخواسته اعمال نشد.', 409, ['code' => 'PROFILE_EDIT_STALE']);
            }
            $profile[$field] = dent_decrypt_secret_text($request['valueEncrypted'] ?? null);
            $profile['updatedAt'] = dent_iso_now();
            $store['onboardingProfiles'][$profileRef]['profileEncrypted'] = dent_encrypt_secret_text((string) json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $store['onboardingProfiles'][$profileRef]['updatedAt'] = dent_iso_now();
        }
        $store['onboardingEditRequests'][$ref]['status'] = $decision === 'approve' ? 'approved' : 'rejected';
        $store['onboardingEditRequests'][$ref]['updatedAt'] = dent_iso_now();
        dent_bot_audit($store, 'profile-edit-' . $decision, (string) ($request['identityHash'] ?? ''), (string) ($request['studentNumber'] ?? ''), (string) ($request['field'] ?? ''));
        return ['success' => true, 'status' => $store['onboardingEditRequests'][$ref]['status']];
    });
}

function dent_bot_normalize_identity_auth_v2(array $owner): array
{
    dent_bot_require_owner($owner);
    return dent_bot_store_with_lock(static function (array &$store): array {
        $result = [
            'pendingRejected' => 0,
            'approvedMigrated' => 0,
            'approvedLinkedFromClaim' => 0,
            'approvedRejectedWithoutLink' => 0,
            'approvedRejectedConflict' => 0,
            'candidatesRetired' => 0,
            'profilesEnsured' => 0,
            'classEntryYearsEnsured' => 0,
        ];
        foreach ($store['identityClaims'] as $identityHash => &$claim) {
            if (!is_array($claim)) continue;
            $status = (string) ($claim['status'] ?? '');
            $hasLink = is_array($store['links'][$identityHash] ?? null);
            if ($status === 'approved' && $hasLink) {
                $claim['status'] = 'migrated-linked-v2';
                $result['approvedMigrated']++;
            } elseif ($status === 'approved') {
                $studentNumber = dent_normalize_student_number((string) ($claim['studentNumber'] ?? ''));
                $platform = (string) ($claim['platform'] ?? '');
                $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
                $conflict = is_array($user) ? dent_bot_conflicting_link($store, (string) $identityHash, $platform, $studentNumber) : null;
                if (!is_array($user)) {
                    $claim['status'] = 'rejected-auth-v2-no-link';
                    $result['approvedRejectedWithoutLink']++;
                } elseif (is_array($conflict)) {
                    $claim['status'] = 'rejected-auth-v2-conflict';
                    $result['approvedRejectedConflict']++;
                } else {
                    $store['links'][$identityHash] = [
                        'identityHash' => (string) $identityHash,
                        'platform' => $platform,
                        'platformUserIdEncrypted' => $claim['platformUserIdEncrypted'] ?? null,
                        'studentNumber' => $studentNumber,
                        'linkedAt' => dent_iso_now(),
                        'source' => 'legacy-approved-claim-migrated-v2',
                    ];
                    if (dent_user_cohort_key($user) === dent_primary_cohort_key()) {
                        dent_bot_store_profile_for_identity($store, (string) $identityHash, $platform, dent_bot_class_profile($user));
                    }
                    $claim['status'] = 'migrated-linked-v2';
                    $result['approvedLinkedFromClaim']++;
                }
            } elseif ($status === 'pending') {
                $claim['status'] = 'rejected-auth-v2';
                $result['pendingRejected']++;
            } else {
                continue;
            }
            $claim['updatedAt'] = dent_iso_now();
        }
        unset($claim);
        foreach ($store['links'] as $identityHash => $link) {
            if (!is_array($link)) continue;
            $profileRef = (string) ($store['onboardingIdentityProfiles'][$identityHash] ?? '');
            $studentNumber = dent_normalize_student_number((string) ($link['studentNumber'] ?? ''));
            $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
            if (!is_array($user) || dent_user_cohort_key($user) !== dent_primary_cohort_key()) continue;
            if ($profileRef !== '' && is_array($store['onboardingProfiles'][$profileRef] ?? null)) {
                $plain = dent_decrypt_secret_text($store['onboardingProfiles'][$profileRef]['profileEncrypted'] ?? null);
                $profile = $plain !== '' ? json_decode($plain, true) : null;
                if (is_array($profile) && dent_bot_onboarding_entry_year((string) ($profile['entryYear'] ?? '')) !== '۱۴۰۲') {
                    $profile['entryYear'] = '۱۴۰۲';
                    $profile['updatedAt'] = dent_iso_now();
                    $store['onboardingProfiles'][$profileRef]['profileEncrypted'] = dent_encrypt_secret_text((string) json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    $store['onboardingProfiles'][$profileRef]['updatedAt'] = dent_iso_now();
                    $result['classEntryYearsEnsured']++;
                }
                continue;
            }
            dent_bot_store_profile_for_identity(
                $store,
                (string) $identityHash,
                (string) ($link['platform'] ?? ''),
                dent_bot_class_profile($user)
            );
            $result['profilesEnsured']++;
        }
        foreach ($store['identityCandidates'] as &$candidate) {
            if (!is_array($candidate)) continue;
            $status = (string) ($candidate['status'] ?? '');
            if (!in_array($status, ['linked-secure-site', 'linked', 'retired-auth-v2'], true)) {
                $candidate['status'] = 'retired-auth-v2';
                $candidate['updatedAt'] = dent_iso_now();
                $result['candidatesRetired']++;
            }
        }
        unset($candidate);
        dent_bot_audit($store, 'identity-auth-v2-normalized', 'system', '', (string) json_encode($result));
        return ['success' => true, 'result' => $result];
    });
}

function dent_bot_identity_auth_v2_status(array $owner, string $platform): array
{
    dent_bot_require_owner($owner);
    return dent_bot_store_read(static function (array $store) use ($platform): array {
        $pendingManualClaims = 0;
        foreach ($store['identityClaims'] as $claim) {
            if (is_array($claim)
                && (string) ($claim['platform'] ?? '') === $platform
                && (string) ($claim['status'] ?? '') === 'pending') {
                $pendingManualClaims++;
            }
        }
        $mappings = 0;
        foreach ($store['links'] as $link) {
            if (is_array($link) && (string) ($link['platform'] ?? '') === $platform) {
                $mappings++;
            }
        }
        $pendingProfileEdits = 0;
        foreach ($store['onboardingEditRequests'] as $request) {
            if (is_array($request)
                && (string) ($request['platform'] ?? '') === $platform
                && (string) ($request['status'] ?? '') === 'pending') {
                $pendingProfileEdits++;
            }
        }
        return [
            'success' => true,
            'contractVersion' => 'identity-auth-v2',
            'platform' => $platform,
            'pendingManualClaims' => $pendingManualClaims,
            'mappings' => $mappings,
            'pendingProfileEdits' => $pendingProfileEdits,
        ];
    });
}
