<?php
declare(strict_types=1);

require_once __DIR__ . '/provider.php';

final class DentClassOpsAiCopilot
{
    public function __construct(private readonly DentClassOpsAiAvalAiClient $client)
    {
    }

    /** @return array{draft:array,telemetry:array} */
    public function createDraft(string $ownerText, ?string $forwardedText = null, array $trustedContext = []): array
    {
        $ownerText = $this->inputText($ownerText, CLASSOPS_AI_MAX_OWNER_TEXT_BYTES, 'ownerText', true);
        $forwardedText = $forwardedText === null
            ? null
            : $this->inputText($forwardedText, CLASSOPS_AI_MAX_FORWARDED_TEXT_BYTES, 'forwardedText', false);
        $context = classops_ai_validate_context($trustedContext);

        $providerResult = $this->client->createCandidate($ownerText, $forwardedText);
        $candidate = classops_ai_validate_model_candidate($providerResult['candidate']);
        $expectedChanged = [];
        foreach (classops_ai_field_names() as $field) {
            if ($candidate['fields'][$field] !== null) {
                $expectedChanged[] = $field;
            }
        }
        sort($expectedChanged, SORT_STRING);
        $actualChanged = $candidate['changedFields'];
        sort($actualChanged, SORT_STRING);
        if ($expectedChanged !== $actualChanged) {
            classops_ai_error('CLASSOPS_AI_CHANGED_FIELDS_INVALID', 'Create candidate changedFields does not match extracted fields', 503);
        }

        $origins = [];
        foreach (classops_ai_field_names() as $field) {
            $origins[$field] = 1;
        }
        $draft = $this->buildDraft(1, 'create', $context, $candidate, null, $origins);
        return ['draft' => classops_ai_validate_final_draft($draft), 'telemetry' => $this->safeTelemetry($providerResult['telemetry'])];
    }

    /** @return array{draft:array,telemetry:array} */
    public function editDraft(array $priorDraft, string $ownerEditText): array
    {
        $prior = classops_ai_validate_final_draft($priorDraft);
        $ownerEditText = $this->inputText($ownerEditText, CLASSOPS_AI_MAX_EDIT_TEXT_BYTES, 'ownerEditText', true);
        $providerResult = $this->client->editCandidate($prior, $ownerEditText);
        $candidate = classops_ai_validate_model_candidate($providerResult['candidate'], $prior);
        $nextVersion = (int) $prior['draftVersion'] + 1;
        if ($nextVersion > 10000) {
            classops_ai_error('CLASSOPS_AI_DRAFT_VERSION', 'Draft edit chain is too deep');
        }
        $origins = $prior['provenance']['fieldOrigins'];
        foreach ($candidate['changedFields'] as $field) {
            $origins[$field] = $nextVersion;
        }
        $draft = $this->buildDraft(
            $nextVersion,
            'edit',
            $prior['context'],
            $candidate,
            (int) $prior['draftVersion'],
            $origins
        );
        return ['draft' => classops_ai_validate_final_draft($draft), 'telemetry' => $this->safeTelemetry($providerResult['telemetry'])];
    }

    private function buildDraft(
        int $draftVersion,
        string $operation,
        array $context,
        array $candidate,
        ?int $parentDraftVersion,
        array $fieldOrigins
    ): array {
        return [
            'contractVersion' => CLASSOPS_AI_DRAFT_CONTRACT_VERSION,
            'draftVersion' => $draftVersion,
            'operation' => $operation,
            'context' => $context,
            'fields' => $candidate['fields'],
            'changedFields' => $candidate['changedFields'],
            'unresolved' => $candidate['unresolved'],
            'provenance' => [
                'provider' => 'avalai',
                'model' => $this->client->model(),
                'parserVersion' => CLASSOPS_AI_PARSER_VERSION,
                'promptVersion' => CLASSOPS_AI_PROMPT_VERSION,
                'generatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
                'parentDraftVersion' => $parentDraftVersion,
                'fieldOrigins' => $fieldOrigins,
            ],
            'preview' => [
                'required' => true,
                'confirmed' => false,
                'mutationAuthority' => 'none',
                'directSend' => false,
            ],
        ];
    }

    private function inputText(string $value, int $maxBytes, string $field, bool $required): string
    {
        if (strlen($value) > $maxBytes) {
            classops_ai_error('CLASSOPS_AI_INPUT_TOO_LARGE', "{$field} is too large", 413);
        }
        $text = trim(str_replace(["\r\n", "\r"], "\n", $value));
        if ($required && $text === '') {
            classops_ai_error('CLASSOPS_AI_REQUIRED_INPUT', "{$field} is required");
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $text) === 1) {
            classops_ai_error('CLASSOPS_AI_INVALID_INPUT', "{$field} contains control characters");
        }
        return $text;
    }

    private function safeTelemetry($value): array
    {
        if (!is_array($value) || array_is_list($value)) {
            classops_ai_error('CLASSOPS_AI_TELEMETRY_INVALID', 'AI telemetry is invalid', 503);
        }
        $allowed = ['provider', 'model', 'latencyMs', 'promptTokens', 'completionTokens', 'totalTokens', 'estimatedCostUsd'];
        classops_ai_assert_known_keys($value, $allowed, 'telemetry');
        foreach ($allowed as $required) {
            if (!array_key_exists($required, $value)) {
                classops_ai_error('CLASSOPS_AI_TELEMETRY_INVALID', 'AI telemetry is incomplete', 503);
            }
        }
        if ($value['provider'] !== 'avalai' || $value['model'] !== $this->client->model()) {
            classops_ai_error('CLASSOPS_AI_TELEMETRY_INVALID', 'AI telemetry provenance is invalid', 503);
        }
        if (!is_float($value['latencyMs']) && !is_int($value['latencyMs'])) {
            classops_ai_error('CLASSOPS_AI_TELEMETRY_INVALID', 'AI latency telemetry is invalid', 503);
        }
        foreach (['promptTokens', 'completionTokens', 'totalTokens'] as $field) {
            if ($value[$field] !== null && (!is_int($value[$field]) || $value[$field] < 0)) {
                classops_ai_error('CLASSOPS_AI_TELEMETRY_INVALID', 'AI token telemetry is invalid', 503);
            }
        }
        if ($value['estimatedCostUsd'] !== null && (!is_float($value['estimatedCostUsd']) && !is_int($value['estimatedCostUsd']))) {
            classops_ai_error('CLASSOPS_AI_TELEMETRY_INVALID', 'AI cost telemetry is invalid', 503);
        }
        return [
            'provider' => 'avalai',
            'model' => $this->client->model(),
            'latencyMs' => round(max(0.0, (float) $value['latencyMs']), 3),
            'promptTokens' => $value['promptTokens'],
            'completionTokens' => $value['completionTokens'],
            'totalTokens' => $value['totalTokens'],
            'estimatedCostUsd' => $value['estimatedCostUsd'] === null ? null : round(max(0.0, (float) $value['estimatedCostUsd']), 8),
        ];
    }
}
