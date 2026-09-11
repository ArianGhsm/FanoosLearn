<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Crash-safe persistence for the canonical ClassOps subsystem.
 *
 * The data inode is never truncated. A complete temp generation is flushed,
 * decoded and schema-validated before an atomic same-filesystem rename. The
 * lock lives in a separate inode so it remains valid across replacement.
 */

final class DentClassOpsPersistenceException extends RuntimeException
{
    public function __construct(public readonly string $reasonCode, string $message)
    {
        parent::__construct($message);
    }
}

function classops_persistence_request_id(): string
{
    $internalKey = 'DENT_CLASSOPS_REQUEST_ID_INTERNAL';
    $cached = trim((string) ($_SERVER[$internalKey] ?? ''));
    if ($cached !== '' && preg_match('/^[A-Za-z0-9._:-]{8,96}$/', $cached) === 1) {
        return $cached;
    }

    $candidate = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
    if ($candidate !== '' && preg_match('/^[A-Za-z0-9._:-]{8,96}$/', $candidate) === 1) {
        $_SERVER[$internalKey] = $candidate;
        return $candidate;
    }

    $generated = bin2hex(random_bytes(8));
    $_SERVER[$internalKey] = $generated;
    return $generated;
}

function classops_persistence_log(string $level, array $context): void
{
    $record = [
        'event' => 'classops_persistence',
        'level' => $level,
        'requestId' => classops_persistence_request_id(),
        'pid' => getmypid(),
        'action' => dent_clean_text((string) ($context['action'] ?? 'unknown'), 80),
        'storeGeneration' => max(0, (int) ($context['generation'] ?? 0)),
        'lockWaitMs' => round(max(0.0, (float) ($context['lockWaitMs'] ?? 0.0)), 3),
        'oldSize' => max(0, (int) ($context['oldSize'] ?? 0)),
        'oldHash' => preg_match('/^[a-f0-9]{64}$/', (string) ($context['oldHash'] ?? '')) === 1
            ? (string) $context['oldHash'] : '',
        'newSize' => max(0, (int) ($context['newSize'] ?? 0)),
        'newHash' => preg_match('/^[a-f0-9]{64}$/', (string) ($context['newHash'] ?? '')) === 1
            ? (string) $context['newHash'] : '',
        'bytesExpected' => max(0, (int) ($context['bytesExpected'] ?? 0)),
        'bytesWritten' => max(0, (int) ($context['bytesWritten'] ?? 0)),
        'decodeStatus' => dent_clean_text((string) ($context['decodeStatus'] ?? ''), 40),
        'commitResult' => dent_clean_text((string) ($context['commitResult'] ?? ''), 60),
        'elapsedMs' => round(max(0.0, (float) ($context['elapsedMs'] ?? 0.0)), 3),
        'reasonCode' => dent_clean_text((string) ($context['reasonCode'] ?? ''), 100),
    ];
    error_log('DENT_CLASSOPS_STORAGE ' . json_encode($record, JSON_UNESCAPED_SLASHES));
}

function classops_persistence_lock_path(string $path): string
{
    return $path . '.lock';
}

function classops_persistence_open_lock(string $path, int $mode, float &$waitMs)
{
    dent_ensure_directory(dirname($path));
    $handle = @fopen(classops_persistence_lock_path($path), 'c+b');
    if ($handle === false) {
        throw new DentClassOpsPersistenceException('CLASSOPS_LOCK_OPEN_FAILED', 'Unable to open ClassOps lock');
    }
    $started = microtime(true);
    if (!@flock($handle, $mode)) {
        fclose($handle);
        throw new DentClassOpsPersistenceException('CLASSOPS_LOCK_FAILED', 'Unable to acquire ClassOps lock');
    }
    $waitMs = (microtime(true) - $started) * 1000;
    return $handle;
}

function classops_persistence_decode(string $raw, callable $validator, string $reasonCode): array
{
    if (trim($raw) === '') {
        throw new DentClassOpsPersistenceException($reasonCode, 'Existing ClassOps store is empty');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
        throw new DentClassOpsPersistenceException($reasonCode, 'Existing ClassOps store is malformed');
    }
    try {
        $validator($decoded);
    } catch (DentClassOpsPersistenceException $exception) {
        throw $exception;
    } catch (Throwable $exception) {
        throw new DentClassOpsPersistenceException('CLASSOPS_SCHEMA_INVALID', 'ClassOps store schema validation failed');
    }
    return $decoded;
}

function classops_persistence_write_full(string $path, string $payload, array $testOptions = [], ?int &$writtenOut = null): int
{
    $handle = @fopen($path, 'x+b');
    if ($handle === false) {
        throw new DentClassOpsPersistenceException('CLASSOPS_TEMP_OPEN_FAILED', 'Unable to create ClassOps temp generation');
    }
    $expected = strlen($payload);
    $written = 0;
    $writtenOut = 0;
    $shortAfter = max(0, (int) ($testOptions['shortWriteAfterBytes'] ?? 0));
    try {
        while ($written < $expected) {
            $remaining = substr($payload, $written);
            if ($shortAfter > 0) {
                $remaining = substr($remaining, 0, max(1, min(strlen($remaining), $shortAfter - $written)));
            }
            $count = @fwrite($handle, $remaining);
            if (!is_int($count) || $count <= 0) {
                throw new DentClassOpsPersistenceException('CLASSOPS_SHORT_WRITE', 'ClassOps temp write was incomplete');
            }
            $written += $count;
            $writtenOut = $written;
            if ($shortAfter > 0 && $written >= $shortAfter && $written < $expected) {
                throw new DentClassOpsPersistenceException('CLASSOPS_SHORT_WRITE', 'Simulated short write');
            }
        }
        if (!@fflush($handle)) {
            throw new DentClassOpsPersistenceException('CLASSOPS_FLUSH_FAILED', 'Unable to flush ClassOps temp generation');
        }
        if (function_exists('fsync') && !@fsync($handle)) {
            throw new DentClassOpsPersistenceException('CLASSOPS_FSYNC_FAILED', 'Unable to sync ClassOps temp generation');
        }
    } finally {
        fclose($handle);
    }
    if ($written !== $expected || @filesize($path) !== $expected) {
        throw new DentClassOpsPersistenceException('CLASSOPS_SHORT_WRITE', 'ClassOps temp generation size mismatch');
    }
    return $written;
}

function classops_persistence_forensic_copy(string $path, string $raw): string
{
    $directory = dirname($path) . DIRECTORY_SEPARATOR . '.corrupt';
    dent_ensure_directory($directory);
    $hash = hash('sha256', $raw);
    $target = $directory . DIRECTORY_SEPARATOR . basename($path) . '.' . $hash . '.json';
    if (is_file($target)) {
        return $target;
    }
    $temp = $target . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
    try {
        classops_persistence_write_full($temp, $raw);
        if (!@rename($temp, $target) && !is_file($target)) {
            throw new DentClassOpsPersistenceException('CLASSOPS_FORENSIC_COPY_FAILED', 'Unable to preserve corrupt ClassOps generation');
        }
    } finally {
        if (is_file($temp)) {
            @unlink($temp);
        }
    }
    return $target;
}

function classops_persistence_read_unlocked(string $path, bool $allowMissing, array $defaultStore, callable $validator): array
{
    clearstatcache(true, $path);
    if (!is_file($path)) {
        if ($allowMissing) {
            $validator($defaultStore);
            return ['store' => $defaultStore, 'raw' => '', 'exists' => false, 'size' => 0, 'hash' => ''];
        }
        throw new DentClassOpsPersistenceException('CLASSOPS_NOT_INITIALIZED', 'ClassOps store is not initialized');
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw)) {
        throw new DentClassOpsPersistenceException('CLASSOPS_READ_FAILED', 'Unable to read ClassOps store');
    }
    try {
        $store = classops_persistence_decode($raw, $validator, 'CLASSOPS_STORE_CORRUPT');
    } catch (DentClassOpsPersistenceException $exception) {
        classops_persistence_forensic_copy($path, $raw);
        classops_persistence_log('error', [
            'action' => 'read', 'oldSize' => strlen($raw), 'oldHash' => hash('sha256', $raw),
            'decodeStatus' => 'failed', 'commitResult' => 'preserved-source', 'reasonCode' => $exception->reasonCode,
        ]);
        throw $exception;
    }
    return [
        'store' => $store,
        'raw' => $raw,
        'exists' => true,
        'size' => strlen($raw),
        'hash' => hash('sha256', $raw),
    ];
}

function classops_persistence_read(string $path, bool $allowMissing, array $defaultStore, callable $validator): array
{
    $waitMs = 0.0;
    $lock = classops_persistence_open_lock($path, LOCK_SH, $waitMs);
    try {
        return classops_persistence_read_unlocked($path, $allowMissing, $defaultStore, $validator);
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function classops_persistence_encode(array $store): string
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($store, $flags);
    if (!is_string($json)) {
        throw new DentClassOpsPersistenceException('CLASSOPS_ENCODE_FAILED', 'Unable to encode ClassOps store');
    }
    return $json . PHP_EOL;
}

function classops_persistence_previous_directory(string $path): string
{
    return dirname($path) . DIRECTORY_SEPARATOR . '.generations';
}

function classops_persistence_preserve_previous(string $path, string $raw, int $generation): string
{
    if ($raw === '') {
        return '';
    }
    $directory = classops_persistence_previous_directory($path);
    dent_ensure_directory($directory);
    $hash = hash('sha256', $raw);
    $target = $directory . DIRECTORY_SEPARATOR . basename($path) . '.g' . max(0, $generation) . '.' . $hash . '.json';
    if (is_file($target)) {
        return $target;
    }
    $temp = $target . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
    try {
        classops_persistence_write_full($temp, $raw);
        if (@file_get_contents($temp) !== $raw) {
            throw new DentClassOpsPersistenceException('CLASSOPS_BACKUP_INVALID', 'Previous ClassOps generation verification failed');
        }
        if (!@rename($temp, $target)) {
            throw new DentClassOpsPersistenceException('CLASSOPS_BACKUP_COMMIT_FAILED', 'Unable to preserve previous ClassOps generation');
        }
    } finally {
        if (is_file($temp)) {
            @unlink($temp);
        }
    }
    return $target;
}

function classops_persistence_prune_previous(string $path, int $keep = 5): void
{
    $files = glob(classops_persistence_previous_directory($path) . DIRECTORY_SEPARATOR . basename($path) . '.g*.json') ?: [];
    usort($files, static fn(string $left, string $right): int => (@filemtime($right) ?: 0) <=> (@filemtime($left) ?: 0));
    foreach (array_slice($files, max(1, $keep)) as $file) {
        @unlink($file);
    }
}

function classops_persistence_fsync_directory(string $directory): void
{
    if (!function_exists('fsync') || DIRECTORY_SEPARATOR === '\\') {
        return;
    }
    $handle = @fopen($directory, 'rb');
    if (is_resource($handle)) {
        @fsync($handle);
        @fclose($handle);
    }
}

/**
 * @return array{result:mixed,written:bool,generation:int}
 */
function classops_persistence_transaction(
    string $path,
    bool $initializeIfMissing,
    array $defaultStore,
    callable $validator,
    callable $mutator,
    string $action,
    array $testOptions = []
): array {
    $started = microtime(true);
    $waitMs = 0.0;
    $lock = classops_persistence_open_lock($path, LOCK_EX, $waitMs);
    $temp = '';
    $written = 0;
    $snapshot = ['raw' => '', 'size' => 0, 'hash' => '', 'exists' => false, 'store' => $defaultStore];
    $generation = 0;
    try {
        $snapshot = classops_persistence_read_unlocked($path, $initializeIfMissing, $defaultStore, $validator);
        $store = $snapshot['store'];
        $generation = max(0, (int) ($store['_storage']['generation'] ?? 0));
        $before = classops_persistence_encode($store);
        $result = $mutator($store);
        $validator($store);
        $afterMutation = classops_persistence_encode($store);
        if (hash_equals(hash('sha256', $before), hash('sha256', $afterMutation))) {
            return ['result' => $result, 'written' => false, 'generation' => $generation];
        }

        $store['_storage'] = [
            'format' => 'classops-atomic-json-v1',
            'generation' => $generation + 1,
            'previousSha256' => (string) ($snapshot['hash'] ?? ''),
            'committedAt' => dent_iso_now(),
        ];
        $validator($store);
        $encoded = classops_persistence_encode($store);
        $tempPayload = !empty($testOptions['invalidTempJson']) ? "{\n" : $encoded;
        $temp = $path . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(6));
        classops_persistence_write_full($temp, $tempPayload, $testOptions, $written);

        $verifiedRaw = @file_get_contents($temp);
        if (!is_string($verifiedRaw) || strlen($verifiedRaw) !== strlen($encoded)) {
            throw new DentClassOpsPersistenceException('CLASSOPS_TEMP_INVALID', 'ClassOps temp generation length validation failed');
        }
        classops_persistence_decode($verifiedRaw, $validator, 'CLASSOPS_TEMP_INVALID');
        $newHash = hash('sha256', $verifiedRaw);
        if (!empty($testOptions['interruptBeforeCommit'])) {
            throw new DentClassOpsPersistenceException('CLASSOPS_TEST_INTERRUPTED', 'Simulated interruption before ClassOps commit');
        }

        classops_persistence_preserve_previous($path, (string) ($snapshot['raw'] ?? ''), $generation);
        if (!@rename($temp, $path)) {
            throw new DentClassOpsPersistenceException('CLASSOPS_COMMIT_FAILED', 'Atomic ClassOps generation commit failed');
        }
        $temp = '';
        classops_persistence_fsync_directory(dirname($path));
        classops_persistence_prune_previous($path);
        classops_persistence_log('info', [
            'action' => $action,
            'generation' => $generation + 1,
            'lockWaitMs' => $waitMs,
            'oldSize' => (int) ($snapshot['size'] ?? 0),
            'oldHash' => (string) ($snapshot['hash'] ?? ''),
            'newSize' => strlen($encoded),
            'newHash' => $newHash,
            'bytesExpected' => strlen($encoded),
            'bytesWritten' => $written,
            'decodeStatus' => 'valid',
            'commitResult' => 'atomic-rename',
            'elapsedMs' => (microtime(true) - $started) * 1000,
        ]);
        return ['result' => $result, 'written' => true, 'generation' => $generation + 1];
    } catch (Throwable $exception) {
        classops_persistence_log('error', [
            'action' => $action,
            'generation' => $generation,
            'lockWaitMs' => $waitMs,
            'oldSize' => (int) ($snapshot['size'] ?? 0),
            'oldHash' => (string) ($snapshot['hash'] ?? ''),
            'bytesExpected' => isset($encoded) ? strlen($encoded) : 0,
            'bytesWritten' => $written,
            'decodeStatus' => 'failed',
            'commitResult' => 'not-committed',
            'elapsedMs' => (microtime(true) - $started) * 1000,
            'reasonCode' => $exception instanceof DentClassOpsPersistenceException ? $exception->reasonCode : 'CLASSOPS_TRANSACTION_FAILED',
        ]);
        throw $exception;
    } finally {
        if ($temp !== '' && is_file($temp)) {
            @unlink($temp);
        }
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}
