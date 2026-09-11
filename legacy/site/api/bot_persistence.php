<?php
declare(strict_types=1);

/**
 * Atomic JSON persistence used by bot integration state.
 *
 * The lock is deliberately separate from the replaceable data inode. Writers
 * never truncate the active generation: a complete, fsynced and decoded temp
 * file is atomically renamed only after the previous generation is preserved.
 */

final class DentBotPersistenceException extends RuntimeException
{
    public string $reasonCode;

    public function __construct(string $reasonCode, string $message)
    {
        parent::__construct($message);
        $this->reasonCode = $reasonCode;
    }
}

function dent_bot_persistence_request_id(): string
{
    $candidate = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
    if ($candidate !== '' && preg_match('/^[A-Za-z0-9._:-]{8,96}$/', $candidate) === 1) {
        return $candidate;
    }
    try {
        return bin2hex(random_bytes(8));
    } catch (Throwable $exception) {
        return substr(hash('sha256', getmypid() . '|' . microtime(true)), 0, 16);
    }
}

function dent_bot_persistence_log(string $level, array $context): void
{
    $safe = [
        'event' => 'bot_store_persistence',
        'level' => $level,
        'request' => dent_request_method(),
        'action' => dent_clean_text((string) ($context['action'] ?? 'unknown'), 80),
        'requestId' => dent_bot_persistence_request_id(),
        'pid' => getmypid(),
        'store' => basename((string) ($context['path'] ?? '')),
        'generation' => max(0, (int) ($context['generation'] ?? 0)),
        'lockWaitMs' => round(max(0.0, (float) ($context['lockWaitMs'] ?? 0.0)), 3),
        'oldSize' => max(0, (int) ($context['oldSize'] ?? 0)),
        'oldHash' => preg_match('/^[a-f0-9]{64}$/', (string) ($context['oldHash'] ?? '')) === 1
            ? (string) $context['oldHash']
            : '',
        'newSize' => max(0, (int) ($context['newSize'] ?? 0)),
        'newHash' => preg_match('/^[a-f0-9]{64}$/', (string) ($context['newHash'] ?? '')) === 1
            ? (string) $context['newHash']
            : '',
        'bytesExpected' => max(0, (int) ($context['bytesExpected'] ?? 0)),
        'bytesWritten' => max(0, (int) ($context['bytesWritten'] ?? 0)),
        'decodeStatus' => dent_clean_text((string) ($context['decodeStatus'] ?? ''), 40),
        'commitResult' => dent_clean_text((string) ($context['commitResult'] ?? ''), 60),
        'elapsedMs' => round(max(0.0, (float) ($context['elapsedMs'] ?? 0.0)), 3),
        'reasonCode' => dent_clean_text((string) ($context['reasonCode'] ?? ''), 80),
    ];
    error_log('DENT_BOT_STORAGE ' . json_encode($safe, JSON_UNESCAPED_SLASHES));
}

function dent_bot_persistence_lock_path(string $path): string
{
    return $path . '.lock';
}

function dent_bot_persistence_maintenance_path(string $path): string
{
    return $path . '.maintenance';
}

function dent_bot_persistence_assert_available(string $path): void
{
    clearstatcache(true, dent_bot_persistence_maintenance_path($path));
    if (is_file(dent_bot_persistence_maintenance_path($path))) {
        throw new DentBotPersistenceException('BOT_STORE_MAINTENANCE', 'Bot store is in maintenance mode');
    }
}

function dent_bot_persistence_backup_directory(string $path): string
{
    return dirname($path) . DIRECTORY_SEPARATOR . '.generations';
}

function dent_bot_persistence_open_lock(string $path, int $mode, float &$waitMs)
{
    dent_ensure_directory(dirname($path));
    $lockPath = dent_bot_persistence_lock_path($path);
    $handle = @fopen($lockPath, 'c+b');
    if ($handle === false) {
        throw new DentBotPersistenceException('BOT_STORE_LOCK_OPEN_FAILED', 'Unable to open bot store lock');
    }
    $started = microtime(true);
    if (!@flock($handle, $mode)) {
        fclose($handle);
        throw new DentBotPersistenceException('BOT_STORE_LOCK_FAILED', 'Unable to acquire bot store lock');
    }
    $waitMs = (microtime(true) - $started) * 1000;
    return $handle;
}

function dent_bot_persistence_decode(string $raw, string $reasonCode): array
{
    if (trim($raw) === '') {
        throw new DentBotPersistenceException($reasonCode, 'Bot store is empty');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
        throw new DentBotPersistenceException($reasonCode, 'Bot store JSON decode failed');
    }
    return $decoded;
}

function dent_bot_persistence_preserve_corrupt(string $path, string $raw): string
{
    if ($raw === '') {
        return '';
    }
    $hash = hash('sha256', $raw);
    $directory = dirname($path) . DIRECTORY_SEPARATOR . '.corrupt';
    dent_ensure_directory($directory);
    $target = $directory . DIRECTORY_SEPARATOR . basename($path) . '.' . $hash . '.json';
    if (is_file($target)) {
        return $target;
    }
    $temp = $target . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
    try {
        dent_bot_persistence_write_full($temp, $raw, []);
        if (!@rename($temp, $target) && !is_file($target)) {
            throw new DentBotPersistenceException('BOT_STORE_FORENSIC_COPY_FAILED', 'Unable to preserve corrupt bot store');
        }
    } finally {
        if (is_file($temp)) {
            @unlink($temp);
        }
    }
    return $target;
}

function dent_bot_persistence_read_raw(string $path, bool $allowMissing, array $defaultStore): array
{
    clearstatcache(true, $path);
    if (!is_file($path)) {
        if ($allowMissing) {
            return ['store' => $defaultStore, 'raw' => '', 'exists' => false, 'hash' => '', 'size' => 0];
        }
        throw new DentBotPersistenceException('BOT_STORE_NOT_INITIALIZED', 'Bot store is not initialized');
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw)) {
        throw new DentBotPersistenceException('BOT_STORE_READ_FAILED', 'Unable to read bot store');
    }
    try {
        $store = dent_bot_persistence_decode($raw, 'BOT_STORE_CORRUPT');
    } catch (DentBotPersistenceException $exception) {
        dent_bot_persistence_preserve_corrupt($path, $raw);
        throw $exception;
    }
    return [
        'store' => $store,
        'raw' => $raw,
        'exists' => true,
        'hash' => hash('sha256', $raw),
        'size' => strlen($raw),
    ];
}

function dent_bot_persistence_encode(array $store): string
{
    // This is a machine-owned hot store. Pretty printing inflated the incident
    // recovery generation by ~44%, increased every write, and could exhaust a
    // constrained hosting quota before atomic rename. Compact JSON preserves
    // semantics while reducing both quota pressure and write amplification.
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($store, $flags);
    if (!is_string($json)) {
        throw new DentBotPersistenceException('BOT_STORE_ENCODE_FAILED', 'Unable to encode bot store');
    }
    return $json . PHP_EOL;
}

function dent_bot_persistence_write_full(string $path, string $payload, array $testOptions, ?int &$writtenOut = null): int
{
    $handle = @fopen($path, 'x+b');
    if ($handle === false) {
        throw new DentBotPersistenceException('BOT_STORE_TEMP_OPEN_FAILED', 'Unable to create bot store temp file');
    }
    $expected = strlen($payload);
    $written = 0;
    $writtenOut = 0;
    $shortAfter = max(0, (int) ($testOptions['shortWriteAfterBytes'] ?? 0));
    try {
        while ($written < $expected) {
            $remaining = substr($payload, $written, min(65536, $expected - $written));
            if ($shortAfter > 0) {
                $remaining = substr($remaining, 0, max(1, min(strlen($remaining), $shortAfter - $written)));
            }
            $count = @fwrite($handle, $remaining);
            if (!is_int($count) || $count <= 0) {
                throw new DentBotPersistenceException('BOT_STORE_SHORT_WRITE', 'Bot store temp write was incomplete');
            }
            $written += $count;
            $writtenOut = $written;
            if ($shortAfter > 0 && $written >= $shortAfter && $written < $expected) {
                throw new DentBotPersistenceException('BOT_STORE_SHORT_WRITE', 'Simulated bot store short write');
            }
        }
        if (!@fflush($handle)) {
            throw new DentBotPersistenceException('BOT_STORE_FLUSH_FAILED', 'Unable to flush bot store temp file');
        }
        if (function_exists('fsync') && !@fsync($handle)) {
            throw new DentBotPersistenceException('BOT_STORE_FSYNC_FAILED', 'Unable to fsync bot store temp file');
        }
        // Verify the exact bytes through the open descriptor.  A few shared
        // PHP hosting runtimes expose an unreliable fstat() size for a newly
        // created file, even after a successful flush.  Descriptor readback
        // checks the actual payload without depending on a path stat cache or
        // a filesystem-specific fstat implementation.
        if (@fseek($handle, 0, SEEK_SET) !== 0) {
            throw new DentBotPersistenceException('BOT_STORE_TEMP_VERIFY_FAILED', 'Unable to rewind bot store temp file');
        }
        $roundTrip = stream_get_contents($handle);
        if (!is_string($roundTrip)
            || strlen($roundTrip) !== $expected
            || !hash_equals(hash('sha256', $payload), hash('sha256', $roundTrip))) {
            throw new DentBotPersistenceException('BOT_STORE_SHORT_WRITE', 'Bot store temp readback mismatch');
        }
    } finally {
        fclose($handle);
    }
    if ($written !== $expected) {
        throw new DentBotPersistenceException('BOT_STORE_SHORT_WRITE', 'Bot store temp write count mismatch');
    }
    return $written;
}

function dent_bot_persistence_fsync_directory(string $directory): void
{
    if (!function_exists('fsync') || DIRECTORY_SEPARATOR === '\\') {
        return;
    }
    $handle = @fopen($directory, 'rb');
    if (is_resource($handle)) {
        @fsync($handle);
        fclose($handle);
    }
}

function dent_bot_persistence_preserve_previous(string $path, string $raw, int $generation, array $testOptions): string
{
    if ($raw === '') {
        return '';
    }
    $directory = dent_bot_persistence_backup_directory($path);
    dent_ensure_directory($directory);
    $hash = hash('sha256', $raw);
    $compressed = function_exists('gzencode') ? gzencode($raw, 9) : false;
    $useGzip = is_string($compressed) && $compressed !== '' && function_exists('gzdecode');
    $backupPayload = $useGzip ? $compressed : $raw;
    $extension = $useGzip ? '.json.gz' : '.json';
    $target = $directory . DIRECTORY_SEPARATOR . basename($path) . '.g' . max(0, $generation) . '.' . $hash . $extension;
    if (is_file($target)) {
        return $target;
    }
    $temp = $target . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
    try {
        dent_bot_persistence_write_full($temp, $backupPayload, $testOptions);
        $stored = @file_get_contents($temp);
        $verifiedRaw = $useGzip && is_string($stored) ? gzdecode($stored) : $stored;
        $decoded = is_string($verifiedRaw)
            ? dent_bot_persistence_decode($verifiedRaw, 'BOT_STORE_BACKUP_INVALID')
            : null;
        if (!is_array($decoded) || !is_string($verifiedRaw) || !hash_equals($hash, hash('sha256', $verifiedRaw))) {
            throw new DentBotPersistenceException('BOT_STORE_BACKUP_INVALID', 'Previous bot store generation verification failed');
        }
        if (!@rename($temp, $target)) {
            throw new DentBotPersistenceException('BOT_STORE_BACKUP_COMMIT_FAILED', 'Unable to commit previous bot store generation');
        }
        dent_bot_persistence_fsync_directory($directory);
    } finally {
        if (is_file($temp)) {
            @unlink($temp);
        }
    }
    return $target;
}

function dent_bot_persistence_prune_previous(string $path, string $keepTarget): void
{
    $directory = dent_bot_persistence_backup_directory($path);
    $pattern = $directory . DIRECTORY_SEPARATOR . basename($path) . '.g*.json*';
    foreach (glob($pattern) ?: [] as $candidate) {
        if (!is_string($candidate) || $candidate === $keepTarget || !is_file($candidate)) {
            continue;
        }
        @unlink($candidate);
    }
}

function dent_bot_persistence_read(
    string $path,
    array $defaultStore,
    callable $normalize,
    callable $callback,
    string $action = 'read'
): array {
    $started = microtime(true);
    $waitMs = 0.0;
    $lock = dent_bot_persistence_open_lock($path, LOCK_SH, $waitMs);
    try {
        dent_bot_persistence_assert_available($path);
        $snapshot = dent_bot_persistence_read_raw($path, false, $defaultStore);
        try {
            $store = $normalize($snapshot['store']);
        } catch (DentBotPersistenceException $exception) {
            dent_bot_persistence_preserve_corrupt($path, (string) $snapshot['raw']);
            throw $exception;
        }
        $result = $callback($store, [
            'exists' => (bool) $snapshot['exists'],
            'sha256' => (string) $snapshot['hash'],
            'size' => (int) $snapshot['size'],
            'generation' => max(0, (int) (($store['_storage']['generation'] ?? 0))),
        ]);
        return is_array($result) ? $result : [];
    } catch (DentBotPersistenceException $exception) {
        dent_bot_persistence_log('error', [
            'action' => $action,
            'path' => $path,
            'lockWaitMs' => $waitMs,
            'oldSize' => isset($snapshot) ? (int) $snapshot['size'] : 0,
            'oldHash' => isset($snapshot) ? (string) $snapshot['hash'] : '',
            'decodeStatus' => $exception->reasonCode === 'BOT_STORE_CORRUPT' ? 'failed' : 'unknown',
            'commitResult' => 'not-attempted',
            'reasonCode' => $exception->reasonCode,
            'elapsedMs' => (microtime(true) - $started) * 1000,
        ]);
        throw $exception;
    } finally {
        @flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function dent_bot_persistence_update(
    string $path,
    array $defaultStore,
    callable $normalize,
    callable $callback,
    bool $allowInitialize = false,
    string $action = 'write',
    array $testOptions = []
): array {
    $started = microtime(true);
    $waitMs = 0.0;
    $lock = dent_bot_persistence_open_lock($path, LOCK_EX, $waitMs);
    $temp = '';
    $written = 0;
    try {
        dent_bot_persistence_assert_available($path);
        $snapshot = dent_bot_persistence_read_raw($path, $allowInitialize, $defaultStore);
        try {
            $store = $normalize($snapshot['store']);
        } catch (DentBotPersistenceException $exception) {
            if (!empty($snapshot['exists'])) {
                dent_bot_persistence_preserve_corrupt($path, (string) $snapshot['raw']);
            }
            throw $exception;
        }
        $oldGeneration = max(0, (int) (($store['_storage']['generation'] ?? 0)));
        $before = dent_bot_persistence_encode($store);
        $result = $callback($store, [
            'exists' => (bool) $snapshot['exists'],
            'sha256' => (string) $snapshot['hash'],
            'size' => (int) $snapshot['size'],
            'generation' => $oldGeneration,
        ]);
        $store = $normalize($store);
        $afterWithoutGeneration = dent_bot_persistence_encode($store);
        if ($snapshot['exists'] && hash_equals(hash('sha256', $before), hash('sha256', $afterWithoutGeneration))) {
            return is_array($result) ? $result : [];
        }

        $store['_storage'] = [
            'format' => 'dent-atomic-json-v1',
            'generation' => $oldGeneration + 1,
            'previousSha256' => (string) $snapshot['hash'],
            'committedAt' => gmdate('c'),
        ];
        $payload = dent_bot_persistence_encode($store);
        if (($testOptions['invalidTempJson'] ?? false) === true) {
            $payload .= '{invalid';
        }
        $temp = $path . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(6));
        $written = dent_bot_persistence_write_full($temp, $payload, $testOptions, $written);
        $tempRaw = @file_get_contents($temp);
        if (!is_string($tempRaw)) {
            throw new DentBotPersistenceException('BOT_STORE_TEMP_READ_FAILED', 'Unable to re-read bot store temp file');
        }
        dent_bot_persistence_decode($tempRaw, 'BOT_STORE_TEMP_INVALID');
        $newHash = hash('sha256', $tempRaw);
        if (($testOptions['interruptBeforeCommit'] ?? false) === true) {
            throw new DentBotPersistenceException('BOT_STORE_INTERRUPTED', 'Simulated interruption before commit');
        }
        $previousTarget = dent_bot_persistence_preserve_previous($path, (string) $snapshot['raw'], $oldGeneration, []);
        if (!@rename($temp, $path)) {
            throw new DentBotPersistenceException('BOT_STORE_COMMIT_FAILED', 'Atomic bot store rename failed');
        }
        $temp = '';
        dent_bot_persistence_fsync_directory(dirname($path));
        if ($previousTarget !== '') {
            dent_bot_persistence_prune_previous($path, $previousTarget);
        }
        dent_bot_persistence_log('info', [
            'action' => $action,
            'path' => $path,
            'generation' => $oldGeneration + 1,
            'lockWaitMs' => $waitMs,
            'oldSize' => (int) $snapshot['size'],
            'oldHash' => (string) $snapshot['hash'],
            'newSize' => strlen($tempRaw),
            'newHash' => $newHash,
            'bytesExpected' => strlen($payload),
            'bytesWritten' => $written,
            'decodeStatus' => 'valid',
            'commitResult' => 'atomic-rename',
            'elapsedMs' => (microtime(true) - $started) * 1000,
        ]);
        return is_array($result) ? $result : [];
    } catch (DentBotPersistenceException $exception) {
        dent_bot_persistence_log('error', [
            'action' => $action,
            'path' => $path,
            'generation' => isset($oldGeneration) ? $oldGeneration : 0,
            'lockWaitMs' => $waitMs,
            'oldSize' => isset($snapshot) ? (int) $snapshot['size'] : 0,
            'oldHash' => isset($snapshot) ? (string) $snapshot['hash'] : '',
            'newSize' => isset($payload) ? strlen($payload) : 0,
            'newHash' => isset($tempRaw) && is_string($tempRaw) ? hash('sha256', $tempRaw) : '',
            'bytesExpected' => isset($payload) ? strlen($payload) : 0,
            'bytesWritten' => $written,
            'decodeStatus' => $exception->reasonCode === 'BOT_STORE_TEMP_INVALID' ? 'failed' : 'unknown',
            'commitResult' => 'failed-before-commit',
            'reasonCode' => $exception->reasonCode,
            'elapsedMs' => (microtime(true) - $started) * 1000,
        ]);
        throw $exception;
    } finally {
        if ($temp !== '' && is_file($temp)) {
            @unlink($temp);
        }
        @flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function dent_bot_persistence_initialize(
    string $path,
    array $defaultStore,
    callable $normalize,
    string $action = 'explicit-initialize'
): void {
    dent_bot_persistence_update(
        $path,
        $defaultStore,
        $normalize,
        static fn(array &$store): array => ['initialized' => true],
        true,
        $action
    );
}
