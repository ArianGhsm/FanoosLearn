<?php
declare(strict_types=1);

require_once __DIR__ . '/source.php';

/** Canonical auth-store adapter; exposes identity/cohort/role only. */
final class DentClassOpsAuthStoreAudienceSource implements DentClassOpsAudienceSourceV1
{
    public function loadAudienceContext(string $cohortKey): array
    {
        foreach (['dent_load_user_store', 'dent_user_cohort_key', 'dent_normalize_student_number', 'dent_normalize_role'] as $function) {
            if (!function_exists($function)) {
                throw new DentClassOpsAudienceSourceException(
                    'CLASSOPS_AUDIENCE_AUTH_SOURCE_UNAVAILABLE',
                    'Canonical auth-store dependency is unavailable'
                );
            }
        }

        $cohortKey = strtolower(trim($cohortKey));
        if ($cohortKey === '' || preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $cohortKey) !== 1) {
            throw new DentClassOpsAudienceSourceException(
                'CLASSOPS_AUDIENCE_AUTH_SOURCE_INVALID_COHORT',
                'Canonical cohort key is invalid'
            );
        }

        $store = dent_load_user_store();
        $users = is_array($store['users'] ?? null) ? $store['users'] : [];
        $roster = [];
        $identityCohorts = [];
        $roles = [];
        $versionRows = [];

        foreach ($users as $key => $user) {
            if (!is_array($user)) {
                continue;
            }
            $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $key));
            if ($studentNumber === '') {
                continue;
            }
            $userCohort = (string) dent_user_cohort_key($user);
            if ($userCohort !== '') {
                $identityCohorts[$studentNumber] = $userCohort;
            }
            if ($userCohort !== $cohortKey) {
                continue;
            }
            $role = (string) dent_normalize_role((string) ($user['role'] ?? 'student'), $studentNumber);
            $roster[] = ['studentNumber' => $studentNumber, 'cohortKey' => $cohortKey];
            $roles[$role]['student:' . $studentNumber] = $studentNumber;
            $versionRows[] = [$studentNumber, $cohortKey, $role];
        }

        usort($roster, static fn(array $a, array $b): int => strcmp($a['studentNumber'], $b['studentNumber']));
        ksort($identityCohorts, SORT_STRING);
        usort($versionRows, static fn(array $a, array $b): int => strcmp(implode('|', $a), implode('|', $b)));

        $selectors = [];
        ksort($roles, SORT_STRING);
        foreach ($roles as $role => $members) {
            $studentNumbers = array_values($members);
            sort($studentNumbers, SORT_STRING);
            $selectors['role:' . $role] = [
                'kind' => 'role',
                'key' => $role,
                'cohortKey' => $cohortKey,
                'studentNumbers' => $studentNumbers,
                'sourceRef' => 'auth_store.role',
            ];
        }

        $versionJson = json_encode(
            ['cohortKey' => $cohortKey, 'rows' => $versionRows],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if (!is_string($versionJson)) {
            throw new DentClassOpsAudienceSourceException(
                'CLASSOPS_AUDIENCE_AUTH_SOURCE_INVALID',
                'Canonical auth-store source version cannot be encoded'
            );
        }

        return [
            'cohortKey' => $cohortKey,
            'roster' => $roster,
            'identityCohorts' => $identityCohorts,
            'selectors' => $selectors,
            'source' => [
                'type' => 'canonical-auth-store-v1',
                'version' => 'sha256:' . hash('sha256', $versionJson),
            ],
        ];
    }
}
