<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/navid_store.php';
require_once __DIR__ . '/notifications_store.php';

function navid_should_announce_new_assignment(?array $previous, bool $hasBaseline): bool
{
    return $previous === null && $hasBaseline;
}

function navid_normalize_login_url(?string $value): string
{
    $candidate = trim((string) $value);
    if ($candidate === '') {
        return 'https://navid.tums.ac.ir/account/loginsipadservice';
    }

    if (!preg_match('/^https?:\/\//i', $candidate)) {
        return 'https://navid.tums.ac.ir/account/loginsipadservice';
    }

    return $candidate;
}

function navid_origin_from_login_url(string $loginUrl): string
{
    $parts = parse_url($loginUrl);
    if (!is_array($parts)) {
        return 'https://navid.tums.ac.ir';
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
    $host = strtolower((string) ($parts['host'] ?? 'navid.tums.ac.ir'));
    $port = (int) ($parts['port'] ?? 0);

    $origin = $scheme . '://' . $host;
    if ($port > 0 && !in_array($port, [80, 443], true)) {
        $origin .= ':' . $port;
    }

    return $origin;
}

function navid_absolute_url(string $origin, string $pathOrUrl): string
{
    $value = trim($pathOrUrl);
    if ($value === '') {
        return $origin;
    }

    if (preg_match('/^https?:\/\//i', $value)) {
        return $value;
    }

    if ($value[0] !== '/') {
        $value = '/' . $value;
    }

    return rtrim($origin, '/') . $value;
}

function navid_build_cookie_header(array $cookies): string
{
    $pairs = [];
    foreach ($cookies as $cookie) {
        if (!is_array($cookie)) {
            continue;
        }
        $name = trim((string) ($cookie['name'] ?? ''));
        $value = (string) ($cookie['value'] ?? '');
        if ($name === '') {
            continue;
        }
        $pairs[] = $name . '=' . $value;
    }

    return implode('; ', $pairs);
}

function navid_cookie_key(array $cookie): string
{
    return strtolower((string) ($cookie['name'] ?? ''));
}

function navid_merge_cookies(array $existing, array $incoming): array
{
    $merged = [];
    foreach ($existing as $cookie) {
        if (!is_array($cookie)) {
            continue;
        }
        $key = navid_cookie_key($cookie);
        if ($key === '') {
            continue;
        }
        $merged[$key] = $cookie;
    }

    foreach ($incoming as $cookie) {
        if (!is_array($cookie)) {
            continue;
        }
        $key = navid_cookie_key($cookie);
        if ($key === '') {
            continue;
        }
        $merged[$key] = $cookie;
    }

    return array_values($merged);
}

function navid_parse_set_cookie(string $line): ?array
{
    $value = trim($line);
    if ($value === '') {
        return null;
    }

    $parts = explode(';', $value);
    if (!$parts) {
        return null;
    }

    $nameValue = array_shift($parts);
    if (!is_string($nameValue) || strpos($nameValue, '=') === false) {
        return null;
    }

    [$name, $cookieValue] = explode('=', $nameValue, 2);
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $cookie = [
        'name' => $name,
        'value' => trim($cookieValue),
        'domain' => '',
        'path' => '/',
        'expires' => '',
        'secure' => false,
        'httpOnly' => false,
    ];

    foreach ($parts as $attrRaw) {
        $attr = trim($attrRaw);
        if ($attr === '') {
            continue;
        }

        if (strpos($attr, '=') === false) {
            $flag = strtolower($attr);
            if ($flag === 'secure') {
                $cookie['secure'] = true;
            } elseif ($flag === 'httponly') {
                $cookie['httpOnly'] = true;
            }
            continue;
        }

        [$k, $v] = explode('=', $attr, 2);
        $key = strtolower(trim($k));
        $val = trim($v);
        if ($key === 'domain') {
            $cookie['domain'] = strtolower($val);
        } elseif ($key === 'path') {
            $cookie['path'] = $val;
        } elseif ($key === 'expires') {
            $cookie['expires'] = $val;
        }
    }

    return $cookie;
}

function navid_http_request(string $method, string $url, array $options = []): array
{
    $method = strtoupper(trim($method));
    if ($method === '') {
        $method = 'GET';
    }

    $query = $options['query'] ?? null;
    if (is_array($query) && $query) {
        $separator = strpos($url, '?') === false ? '?' : '&';
        $url .= $separator . http_build_query($query);
    }

    $timeout = (int) ($options['timeoutSec'] ?? 35);
    if ($timeout < 5) {
        $timeout = 5;
    }

    $cookieHeader = navid_build_cookie_header(is_array($options['cookies'] ?? null) ? $options['cookies'] : []);
    $origin = trim((string) ($options['origin'] ?? ''));
    $referer = trim((string) ($options['referer'] ?? ''));

    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,application/json;q=0.8,*/*;q=0.7',
        'Accept-Language: fa-IR,fa;q=0.9,en-US;q=0.7,en;q=0.6',
    ];
    if ($origin !== '') {
        $headers[] = 'Origin: ' . $origin;
    }
    if ($referer !== '') {
        $headers[] = 'Referer: ' . $referer;
    }
    if ($cookieHeader !== '') {
        $headers[] = 'Cookie: ' . $cookieHeader;
    }

    $customHeaders = $options['headers'] ?? null;
    if (is_array($customHeaders)) {
        foreach ($customHeaders as $header) {
            if (is_string($header) && trim($header) !== '') {
                $headers[] = trim($header);
            }
        }
    }

    $setCookies = [];
    $responseHeaders = [];
    $body = '';

    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'status' => 0,
            'body' => '',
            'headers' => [],
            'cookies' => [],
            'error' => 'curl-missing',
            'effectiveUrl' => $url,
        ];
    }

    $curl = curl_init($url);
    if ($curl === false) {
        return [
            'ok' => false,
            'status' => 0,
            'body' => '',
            'headers' => [],
            'cookies' => [],
            'error' => 'curl_init_failed',
            'effectiveUrl' => $url,
        ];
    }

    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, min(25, $timeout));
    curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_ENCODING, '');
    curl_setopt($curl, CURLOPT_PROXY, '');
    if (defined('CURL_IPRESOLVE_V4')) {
        curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    }
    $resolveEntries = navid_curl_resolve_entries($url);
    if ($resolveEntries !== [] && defined('CURLOPT_RESOLVE')) {
        curl_setopt($curl, CURLOPT_RESOLVE, $resolveEntries);
    }

    if (defined('CURLSSLOPT_NO_REVOKE')) {
        @curl_setopt($curl, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NO_REVOKE);
    }

    curl_setopt(
        $curl,
        CURLOPT_HEADERFUNCTION,
        static function ($ch, string $headerLine) use (&$setCookies, &$responseHeaders): int {
            $trimmed = trim($headerLine);
            if ($trimmed !== '') {
                $responseHeaders[] = $trimmed;
                if (stripos($trimmed, 'Set-Cookie:') === 0) {
                    $cookieLine = trim(substr($trimmed, strlen('Set-Cookie:')));
                    $parsed = navid_parse_set_cookie($cookieLine);
                    if ($parsed !== null) {
                        $setCookies[] = $parsed;
                    }
                }
            }
            return strlen($headerLine);
        }
    );

    $jsonPayload = $options['json'] ?? null;
    $formPayload = $options['form'] ?? null;
    $rawBodyProvided = array_key_exists('body', $options);
    $rawBody = $rawBodyProvided ? (string) ($options['body'] ?? '') : null;
    if (is_array($jsonPayload)) {
        $headers[] = 'Content-Type: application/json;charset=UTF-8';
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $encoded = json_encode($jsonPayload);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $encoded === false ? '{}' : $encoded);
    } elseif (is_array($formPayload)) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8';
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($formPayload));
    } elseif ($rawBodyProvided) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $rawBody);
    } elseif (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        $headers[] = 'Content-Length: 0';
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, '');
    }

    $body = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $effectiveUrl = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);

    curl_close($curl);

    if (!is_string($body)) {
        $body = '';
    }

    return [
        'ok' => $error === '',
        'status' => $status,
        'body' => $body,
        'headers' => $responseHeaders,
        'cookies' => $setCookies,
        'error' => $error,
        'effectiveUrl' => $effectiveUrl !== '' ? $effectiveUrl : $url,
    ];
}

function navid_curl_resolve_entries(string $url): array
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if ($host !== 'navid.tums.ac.ir') {
        return [];
    }

    $raw = trim((string) getenv('DENT_NAVID_RESOLVE_ENTRIES'));
    if ($raw === '') {
        return [];
    }

    $entries = [];
    foreach (preg_split('/[\r\n,]+/', $raw) ?: [] as $entry) {
        $resolved = trim((string) $entry);
        if ($resolved === '') {
            continue;
        }
        $entries[] = $resolved;
    }

    return $entries;
}

function navid_decode_json_response(array $response): ?array
{
    $decoded = json_decode((string) ($response['body'] ?? ''), true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return null;
    }
    return $decoded;
}

function navid_extract_anti_forgery_token(string $html): string
{
    if (preg_match('/name=["\']__RequestVerificationToken["\'][^>]*value=["\']([^"\']+)["\']/i', $html, $matches) === 1) {
        return trim((string) ($matches[1] ?? ''));
    }
    return '';
}

function navid_response_header_value(array $headers, string $name): string
{
    $needle = strtolower($name) . ':';
    foreach (array_reverse($headers) as $headerLine) {
        if (!is_string($headerLine)) {
            continue;
        }
        $trimmed = trim($headerLine);
        if (strtolower(substr($trimmed, 0, strlen($needle))) !== $needle) {
            continue;
        }
        return trim(substr($trimmed, strlen($needle)));
    }

    return '';
}

function navid_fetch_login_page(string $loginUrl): array
{
    $currentUrl = navid_normalize_login_url($loginUrl);
    $cookies = [];
    $lastResponse = [
        'ok' => false,
        'status' => 0,
        'body' => '',
        'headers' => [],
        'cookies' => [],
        'error' => '',
        'effectiveUrl' => $currentUrl,
    ];

    for ($attempt = 0; $attempt < 4; $attempt++) {
        $response = navid_http_request('GET', $currentUrl, [
            'cookies' => $cookies,
        ]);
        $cookies = navid_merge_cookies($cookies, $response['cookies'] ?? []);
        $response['cookies'] = $cookies;
        $response['effectiveUrl'] = $currentUrl;
        $lastResponse = $response;

        $status = (int) ($response['status'] ?? 0);
        if ($status < 300 || $status >= 400) {
            return $response;
        }

        $location = navid_response_header_value(is_array($response['headers'] ?? null) ? $response['headers'] : [], 'Location');
        if ($location === '') {
            return $response;
        }

        $currentUrl = navid_absolute_url(navid_origin_from_login_url($currentUrl), $location);
    }

    return $lastResponse;
}

function navid_function_available(string $name): bool
{
    if (!function_exists($name)) {
        return false;
    }

    $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
    return !in_array($name, $disabled, true);
}

function navid_process_tmp_dir(): string
{
    $dir = DENT_TMP_ROOT . DIRECTORY_SEPARATOR . 'navid-process';
    dent_ensure_directory($dir);
    return $dir;
}

function navid_cleanup_temp_paths(array $paths): void
{
    foreach ($paths as $path) {
        if (!is_string($path) || $path === '') {
            continue;
        }
        @unlink($path);
    }
}

function navid_run_process_via_wrapper(string $command, string $stdin, string $cwd): array
{
    if (!navid_function_available('exec') && !navid_function_available('shell_exec')) {
        return [
            'runnerAvailable' => false,
            'method' => 'none',
            'exit' => null,
            'stdout' => '',
            'stderr' => '',
            'error' => 'process_unavailable',
        ];
    }

    $tmpDir = navid_process_tmp_dir();
    $token = bin2hex(random_bytes(8));
    $stdinPath = $tmpDir . DIRECTORY_SEPARATOR . $token . '.stdin';
    $stdoutPath = $tmpDir . DIRECTORY_SEPARATOR . $token . '.stdout';
    $stderrPath = $tmpDir . DIRECTORY_SEPARATOR . $token . '.stderr';
    $exitPath = $tmpDir . DIRECTORY_SEPARATOR . $token . '.exit';
    $wrapperPath = $tmpDir . DIRECTORY_SEPARATOR . $token . (DIRECTORY_SEPARATOR === '\\' ? '.cmd' : '.sh');
    $paths = [$stdinPath, $stdoutPath, $stderrPath, $exitPath, $wrapperPath];

    try {
        file_put_contents($stdinPath, $stdin, LOCK_EX);

        if (DIRECTORY_SEPARATOR === '\\') {
            $wrapper = "@echo off\r\n"
                . "cd /d \"" . str_replace('"', '""', $cwd) . "\" || (echo 111 > \"" . str_replace('"', '""', $exitPath) . "\" & exit /b 111)\r\n"
                . $command . " < \"" . str_replace('"', '""', $stdinPath) . "\" > \"" . str_replace('"', '""', $stdoutPath) . "\" 2> \"" . str_replace('"', '""', $stderrPath) . "\"\r\n"
                . "set EXITCODE=%ERRORLEVEL%\r\n"
                . "> \"" . str_replace('"', '""', $exitPath) . "\" echo %EXITCODE%\r\n"
                . "exit /b %EXITCODE%\r\n";
            file_put_contents($wrapperPath, $wrapper, LOCK_EX);
            $runnerCommand = 'cmd /V:OFF /C ' . escapeshellarg($wrapperPath);
        } else {
            $wrapper = "#!/bin/sh\n"
                . 'cd ' . escapeshellarg($cwd) . " || { printf '%s' 111 > " . escapeshellarg($exitPath) . "; exit 111; }\n"
                . $command . ' < ' . escapeshellarg($stdinPath) . ' > ' . escapeshellarg($stdoutPath) . ' 2> ' . escapeshellarg($stderrPath) . "\n"
                . "status=$?\n"
                . "printf '%s' \"$status\" > " . escapeshellarg($exitPath) . "\n"
                . "exit \"$status\"\n";
            file_put_contents($wrapperPath, $wrapper, LOCK_EX);
            @chmod($wrapperPath, 0700);
            $runnerCommand = '/bin/sh ' . escapeshellarg($wrapperPath);
        }

        $method = navid_function_available('exec') ? 'exec' : 'shell_exec';
        $exit = null;
        $stderr = '';
        if ($method === 'exec') {
            $ignored = [];
            $exitCode = 0;
            @exec($runnerCommand, $ignored, $exitCode);
            $exit = $exitCode;
        } else {
            try {
                @shell_exec($runnerCommand);
            } catch (Throwable $exception) {
                $stderr = $exception->getMessage();
            }
        }

        $stdout = is_file($stdoutPath) ? (string) file_get_contents($stdoutPath) : '';
        $stderr .= is_file($stderrPath) ? (string) file_get_contents($stderrPath) : '';
        if (is_file($exitPath)) {
            $exitText = trim((string) file_get_contents($exitPath));
            if ($exitText !== '' && preg_match('/^-?\d+$/', $exitText) === 1) {
                $exit = (int) $exitText;
            }
        }
        if ($exit === null) {
            $exit = 0;
        }

        return [
            'runnerAvailable' => true,
            'method' => $method,
            'exit' => $exit,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'error' => '',
        ];
    } finally {
        navid_cleanup_temp_paths($paths);
    }
}

function navid_run_process(string $command, string $stdin = '', ?string $cwd = null): array
{
    $cwd = $cwd && trim($cwd) !== '' ? $cwd : DENT_PROJECT_ROOT;

    if (navid_function_available('proc_open')) {
        try {
            $descriptor = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = @proc_open($command, $descriptor, $pipes, $cwd);
            if (is_resource($process)) {
                fwrite($pipes[0], $stdin);
                fclose($pipes[0]);

                $stdout = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[2]);

                $exit = proc_close($process);
                return [
                    'runnerAvailable' => true,
                    'method' => 'proc_open',
                    'exit' => $exit,
                    'stdout' => (string) $stdout,
                    'stderr' => (string) $stderr,
                    'error' => '',
                ];
            }
        } catch (Throwable $exception) {
            $fallback = navid_run_process_via_wrapper($command, $stdin, $cwd);
            if (!empty($fallback['runnerAvailable'])) {
                return $fallback;
            }

            return [
                'runnerAvailable' => false,
                'method' => 'proc_open',
                'exit' => null,
                'stdout' => '',
                'stderr' => $exception->getMessage(),
                'error' => 'process_unavailable',
            ];
        }
    }

    return navid_run_process_via_wrapper($command, $stdin, $cwd);
}

function navid_python_candidates(): array
{
    $configured = trim((string) getenv('DENT_NAVID_PYTHON_BIN'));
    $candidates = [];
    if ($configured !== '') {
        $candidates[] = $configured;
    }
    $candidates[] = 'python';
    $candidates[] = 'python3';

    $normalized = [];
    foreach ($candidates as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate === '' || in_array($candidate, $normalized, true)) {
            continue;
        }
        $normalized[] = $candidate;
    }

    return $normalized;
}

function navid_process_is_missing_command(array $result): bool
{
    $exit = $result['exit'] ?? null;
    if (is_int($exit) && in_array($exit, [127, 9009], true)) {
        return true;
    }

    $stderr = strtolower(trim((string) ($result['stderr'] ?? '')));
    if ($stderr === '') {
        return false;
    }

    return str_contains($stderr, 'not found')
        || str_contains($stderr, 'no such file')
        || str_contains($stderr, 'is not recognized');
}

function navid_run_python_script(string $scriptPath, string $stdin = ''): array
{
    $lastResult = [
        'runnerAvailable' => false,
        'method' => 'none',
        'exit' => null,
        'stdout' => '',
        'stderr' => '',
        'error' => 'process_unavailable',
        'python' => '',
    ];

    foreach (navid_python_candidates() as $python) {
        $command = navid_shell_quote_command($python) . ' ' . navid_shell_quote_argument($scriptPath);
        $result = navid_run_process($command, $stdin, DENT_PROJECT_ROOT);
        $result['python'] = $python;
        $lastResult = $result;
        if (empty($result['runnerAvailable']) || !navid_process_is_missing_command($result)) {
            return $result;
        }
    }

    return $lastResult;
}

function navid_solve_captcha_python(string $captchaBase64): string
{
    $scriptPath = DENT_PROJECT_ROOT . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'navid_captcha_ocr.py';
    if (!is_file($scriptPath)) {
        return '';
    }

    $result = navid_run_python_script($scriptPath, $captchaBase64);
    if (empty($result['runnerAvailable'])) {
        navid_log('warning', 'captcha_solver_runner_unavailable', [
            'stderr' => trim((string) ($result['stderr'] ?? '')),
        ]);
        return '';
    }

    $stdout = (string) ($result['stdout'] ?? '');
    $stderr = (string) ($result['stderr'] ?? '');
    $exit = (int) ($result['exit'] ?? 0);
    if ($exit !== 0) {
        navid_log('warning', 'captcha_solver_failed', [
            'exit' => $exit,
            'python' => (string) ($result['python'] ?? ''),
            'runner' => (string) ($result['method'] ?? ''),
            'stderr' => trim($stderr),
        ]);
        return '';
    }

    $code = strtoupper(trim((string) $stdout));
    $code = preg_replace('/[^A-Z0-9]/', '', $code) ?? '';
    if (strlen($code) < 4) {
        return '';
    }

    return $code;
}

function navid_python_bin(): string
{
    $candidates = navid_python_candidates();
    return $candidates[0] ?? 'python';
}

function navid_shell_quote_argument(string $value): string
{
    if (navid_function_available('escapeshellarg')) {
        return escapeshellarg($value);
    }

    if (DIRECTORY_SEPARATOR === '\\') {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    return "'" . str_replace("'", "'\"'\"'", $value) . "'";
}

function navid_shell_quote_command(string $value): string
{
    return navid_shell_quote_argument(trim($value));
}

function navid_browser_bridge_path(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'navid_browser_bridge.py';
}

function navid_browser_bridge(array $payload): array
{
    $scriptPath = navid_browser_bridge_path();
    if (!is_file($scriptPath)) {
        return [
            'success' => false,
            'error' => 'bridge_missing',
            'message' => 'فایل helper مرورگر نوید روی سرور پیدا نشد.',
        ];
    }

    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $encoded = json_encode($payload, $flags);
    $result = navid_run_python_script($scriptPath, $encoded === false ? '{}' : $encoded);
    if (empty($result['runnerAvailable'])) {
        navid_log('error', 'browser_bridge_runner_unavailable', [
            'stderr' => substr(trim((string) ($result['stderr'] ?? '')), 0, 1500),
        ]);
        return [
            'success' => false,
            'error' => 'bridge_process_unavailable',
            'message' => 'اجرای helper مرورگر نوید روی این سرور در دسترس نیست.',
        ];
    }

    $stdout = (string) ($result['stdout'] ?? '');
    $stderr = (string) ($result['stderr'] ?? '');
    $exit = (int) ($result['exit'] ?? 0);
    $decoded = json_decode((string) $stdout, true);
    if (!is_array($decoded)) {
        navid_log('error', 'browser_bridge_invalid_json', [
            'exit' => $exit,
            'python' => (string) ($result['python'] ?? ''),
            'runner' => (string) ($result['method'] ?? ''),
            'stdout' => substr(trim((string) $stdout), 0, 1500),
            'stderr' => substr(trim((string) $stderr), 0, 1500),
        ]);
        return [
            'success' => false,
            'error' => 'bridge_invalid_response',
            'message' => 'پاسخ helper مرورگر نوید معتبر نبود.',
        ];
    }

    if ($exit !== 0 && empty($decoded['success'])) {
        navid_log('error', 'browser_bridge_exit_nonzero', [
            'exit' => $exit,
            'python' => (string) ($result['python'] ?? ''),
            'runner' => (string) ($result['method'] ?? ''),
            'error' => $decoded['error'] ?? '',
            'message' => $decoded['message'] ?? '',
            'stderr' => substr(trim((string) $stderr), 0, 1500),
        ]);
    }

    return $decoded;
}

function navid_store_manual_challenge(array &$store, array $browserResult): void
{
    $challengeState = is_array($browserResult['challengeState'] ?? null) ? $browserResult['challengeState'] : [];
    $storageState = is_array($browserResult['storageState'] ?? null) ? $browserResult['storageState'] : null;
    $captchaDataUri = trim((string) ($browserResult['captchaDataUri'] ?? ''));

    $challenge = [
        'createdAt' => dent_iso_now(),
        'expiresAt' => date('c', time() + 10 * 60),
        'loginUrl' => navid_normalize_login_url((string) ($challengeState['loginUrl'] ?? ($store['config']['loginUrl'] ?? ''))),
        'requestVerificationToken' => (string) ($challengeState['requestVerificationToken'] ?? ''),
        'storageState' => $storageState,
        'captchaDataUri' => $captchaDataUri,
    ];
    navid_set_challenge($store, $challenge);
}

function navid_challenge_is_active(?array $challenge): bool
{
    if (!is_array($challenge)) {
        return false;
    }

    $expiresAt = strtotime((string) ($challenge['expiresAt'] ?? ''));
    return $expiresAt === false || $expiresAt >= time();
}

function navid_build_fresh_http_manual_challenge(string $loginUrl): ?array
{
    $first = navid_fetch_login_page($loginUrl);
    if (!$first['ok'] || (int) ($first['status'] ?? 0) < 200 || (int) ($first['status'] ?? 0) >= 400) {
        return null;
    }

    $cookies = navid_merge_cookies([], $first['cookies'] ?? []);
    $resolvedLoginUrl = navid_normalize_login_url((string) ($first['effectiveUrl'] ?? $loginUrl));
    $token = navid_extract_anti_forgery_token((string) ($first['body'] ?? ''));
    if ($token === '') {
        return null;
    }

    return navid_build_http_manual_challenge($resolvedLoginUrl, $token, $cookies, false);
}

function navid_build_http_manual_challenge(string $loginUrl, string $requestToken, array $cookies, bool $allowFreshFallback = true): ?array
{
    $token = trim($requestToken);
    if ($token === '') {
        return null;
    }

    $captcha = navid_get_captcha_image($cookies, $loginUrl);
    $mergedCookies = navid_merge_cookies($cookies, $captcha['cookies'] ?? []);
    $captchaDataUri = '';
    if (!empty($captcha['success']) && !empty($captcha['data'])) {
        $captchaDataUri = 'data:' . ($captcha['contentType'] ?? 'image/png') . ';base64,' . $captcha['data'];
    }

    if ($captchaDataUri === '' && $allowFreshFallback) {
        $freshChallenge = navid_build_fresh_http_manual_challenge($loginUrl);
        if (is_array($freshChallenge)) {
            return $freshChallenge;
        }
    }

    return [
        'createdAt' => dent_iso_now(),
        'expiresAt' => date('c', time() + 10 * 60),
        'loginUrl' => navid_normalize_login_url($loginUrl),
        'requestVerificationToken' => $token,
        'cookies' => $mergedCookies,
        'captchaDataUri' => $captchaDataUri,
    ];
}

function navid_prepare_http_manual_challenge(array &$store, string $preferredLoginUrl = ''): ?array
{
    $challenge = navid_get_challenge($store);
    if (navid_challenge_is_active($challenge) && trim((string) ($challenge['captchaDataUri'] ?? '')) !== '') {
        return $challenge;
    }

    $loginUrl = trim($preferredLoginUrl);
    if ($loginUrl === '') {
        $loginUrl = (string) (($challenge['loginUrl'] ?? '') ?: ($store['config']['loginUrl'] ?? ''));
    }
    $loginUrl = navid_normalize_login_url($loginUrl);

    $freshChallenge = navid_build_fresh_http_manual_challenge($loginUrl);
    if (!is_array($freshChallenge)) {
        return null;
    }
    if (trim((string) ($freshChallenge['captchaDataUri'] ?? '')) === '') {
        return $freshChallenge;
    }

    navid_set_challenge($store, $freshChallenge);
    $store['session']['status'] = 'challenge';
    return $freshChallenge;
}

function navid_finalize_http_manual_challenge(
    string $loginUrl,
    string $requestToken,
    array $cookies,
    string $fallbackCaptchaDataUri = ''
): ?array {
    $challenge = navid_build_http_manual_challenge($loginUrl, $requestToken, $cookies);
    if (!is_array($challenge)) {
        if (trim($fallbackCaptchaDataUri) === '') {
            return null;
        }
        return [
            'createdAt' => dent_iso_now(),
            'expiresAt' => date('c', time() + 10 * 60),
            'loginUrl' => navid_normalize_login_url($loginUrl),
            'requestVerificationToken' => trim($requestToken),
            'cookies' => $cookies,
            'captchaDataUri' => trim($fallbackCaptchaDataUri),
        ];
    }

    if (trim((string) ($challenge['captchaDataUri'] ?? '')) === '' && trim($fallbackCaptchaDataUri) !== '') {
        $challenge['captchaDataUri'] = trim($fallbackCaptchaDataUri);
        $challenge['cookies'] = navid_merge_cookies($cookies, is_array($challenge['cookies'] ?? null) ? $challenge['cookies'] : []);
    }

    return $challenge;
}

function navid_reconnect_required_response(array $store, string $message): array
{
    $challenge = navid_get_challenge($store);
    return [
        'success' => false,
        'status' => 'reconnect-required',
        'message' => $message,
        'captchaDataUri' => is_array($challenge) ? (string) ($challenge['captchaDataUri'] ?? '') : '',
        'ownerStatus' => navid_build_owner_status($store),
    ];
}

function navid_browser_error_supports_http_fallback(string $errorCode): bool
{
    return in_array($errorCode, [
        'bridge_missing',
        'bridge_start_failed',
        'bridge_process_unavailable',
        'bridge_invalid_response',
        'playwright_unavailable',
        'bridge_exception',
    ], true);
}

function navid_sync_store_from_browser_result(array &$store, array $browserResult, string $loginUrl, float $startedAt): array
{
    $courses = is_array($browserResult['courses'] ?? null) ? $browserResult['courses'] : [];
    $assignmentsByCourse = is_array($browserResult['assignmentsByCourse'] ?? null) ? $browserResult['assignmentsByCourse'] : [];
    $assignmentErrors = is_array($browserResult['assignmentErrors'] ?? null) ? $browserResult['assignmentErrors'] : [];
    $browserState = is_array($browserResult['storageState'] ?? null) ? $browserResult['storageState'] : [];

    $currentAssignmentsByKey = [];
    $courseSnapshot = [];
    $failedCourses = 0;
    $previousAssignments = is_array($store['snapshot']['assignments'] ?? null) ? $store['snapshot']['assignments'] : [];
    $hasAnnouncementBaseline = trim((string) ($store['state']['lastSuccessAt'] ?? '')) !== '' || count($previousAssignments) > 0;
    $previousCourses = is_array($store['snapshot']['courses'] ?? null) ? $store['snapshot']['courses'] : [];

    foreach ($courses as $course) {
        if (!is_array($course)) {
            continue;
        }
        $courseTemplateId = (int) ($course['courseTemplateId'] ?? 0);
        if ($courseTemplateId <= 0) {
            continue;
        }

        $key = (string) $courseTemplateId;
        $assignmentList = is_array($assignmentsByCourse[$key] ?? null) ? $assignmentsByCourse[$key] : null;
        if ($assignmentList === null) {
            $failedCourses++;
            $preservedKeys = [];
            $previousCourseSnapshot = is_array($previousCourses[$key] ?? null) ? $previousCourses[$key] : [];
            $previousKeys = is_array($previousCourseSnapshot['assignmentKeys'] ?? null) ? $previousCourseSnapshot['assignmentKeys'] : [];
            foreach ($previousKeys as $previousKey) {
                $assignmentKey = (string) $previousKey;
                if ($assignmentKey === '' || !is_array($previousAssignments[$assignmentKey] ?? null)) {
                    continue;
                }
                $currentAssignmentsByKey[$assignmentKey] = $previousAssignments[$assignmentKey];
                $preservedKeys[] = $assignmentKey;
            }
            $courseSnapshot[$key] = [
                'courseTemplateId' => $courseTemplateId,
                'courseTitle' => (string) ($course['courseTitle'] ?? ''),
                'courseUrl' => (string) ($course['courseUrl'] ?? ''),
                'assignmentKeys' => $preservedKeys,
                'assignmentCount' => count($preservedKeys),
                'lastCheckedAt' => dent_iso_now(),
                'fetchStatus' => 'failed',
                'fetchError' => (string) (($assignmentErrors[$key]['message'] ?? $assignmentErrors[$key]['error'] ?? 'fetch_failed')),
            ];
            continue;
        }

        $courseAssignmentKeys = [];
        foreach ($assignmentList as $rawAssignment) {
            if (!is_array($rawAssignment)) {
                continue;
            }
            $normalized = navid_normalize_assignment($course, $rawAssignment, $loginUrl);
            if ($normalized === null) {
                continue;
            }
            $assignmentKey = (string) $normalized['assignmentKey'];
            $courseAssignmentKeys[] = $assignmentKey;
            $currentAssignmentsByKey[$assignmentKey] = $normalized;
        }

        $courseSnapshot[$key] = [
            'courseTemplateId' => $courseTemplateId,
            'courseTitle' => (string) ($course['courseTitle'] ?? ''),
            'courseUrl' => (string) ($course['courseUrl'] ?? ''),
            'assignmentKeys' => $courseAssignmentKeys,
            'assignmentCount' => count($courseAssignmentKeys),
            'lastCheckedAt' => dent_iso_now(),
            'fetchStatus' => 'ok',
            'fetchError' => '',
        ];
    }

    $newEvents = 0;
    foreach ($currentAssignmentsByKey as $assignmentKey => $assignment) {
        $old = is_array($previousAssignments[$assignmentKey] ?? null) ? $previousAssignments[$assignmentKey] : null;
        if ($old === null) {
            if (navid_should_announce_new_assignment($old, $hasAnnouncementBaseline)) {
                $event = array_merge($assignment, [
                    'eventType' => 'created',
                    'detectedAt' => dent_iso_now(),
                    'eventId' => 'navid-' . str_replace(':', '-', $assignmentKey) . '-' . time(),
                ]);
                navid_add_update_if_new($store, $event);
                notifications_enqueue_navid_assignment($assignment);
                $newEvents++;
            }
            continue;
        }
        if ((string) ($old['fingerprint'] ?? '') !== (string) ($assignment['fingerprint'] ?? '')) {
            $event = array_merge($assignment, [
                'eventType' => 'updated',
                'detectedAt' => dent_iso_now(),
                'eventId' => 'navid-' . str_replace(':', '-', $assignmentKey) . '-' . time(),
                'previousEndDateIso' => (string) ($old['endDateIso'] ?? ''),
                'previousEndDateShamsi' => (string) ($old['endDateShamsi'] ?? ''),
            ]);
            navid_add_update_if_new($store, $event);
            $newEvents++;
        }
    }

    $store['snapshot']['courses'] = $courseSnapshot;
    $store['snapshot']['assignments'] = $currentAssignmentsByKey;
    navid_set_session_browser_state($store, $browserState);
    $store['session']['status'] = 'active';
    $store['session']['lastValidatedAt'] = dent_iso_now();

    $store['state']['lastFailedCourses'] = $failedCourses;
    $store['state']['lastSyncDurationMs'] = (int) round((microtime(true) - $startedAt) * 1000);
    $store['state']['requiresReconnect'] = false;
    $store['state']['consecutiveFailures'] = 0;
    if ($failedCourses > 0) {
        $store['state']['lastResult'] = 'partial';
        $store['state']['lastError'] = 'برخی درس‌های نوید در همگام‌سازی آخر دریافت نشدند. تا تکمیل همه درس‌ها، خروجی تایید نمی‌شود.';
    } else {
        $store['state']['lastSuccessAt'] = dent_iso_now();
        $store['state']['lastResult'] = 'ok';
        $store['state']['lastError'] = '';
    }

    navid_clear_challenge($store);
    navid_save_store($store);

    if ($failedCourses > 0) {
        navid_log('warning', 'sync_partial', [
            'courses' => count($courses),
            'assignments' => count($currentAssignmentsByKey),
            'newEvents' => $newEvents,
            'failedCourses' => $failedCourses,
        ]);
        return [
            'success' => false,
            'status' => 'partial',
            'message' => 'همگام‌سازی نوید ناقص بود و بعضی درس‌ها دریافت نشدند.',
            'summary' => [
                'coursesChecked' => count($courses),
                'coursesSucceeded' => max(0, count($courses) - $failedCourses),
                'assignmentsFetched' => count($currentAssignmentsByKey),
                'newEvents' => $newEvents,
                'failedCourses' => $failedCourses,
            ],
            'ownerStatus' => navid_build_owner_status($store),
        ];
    }

    navid_log('info', 'sync_success', [
        'courses' => count($courses),
        'assignments' => count($currentAssignmentsByKey),
        'newEvents' => $newEvents,
        'failedCourses' => $failedCourses,
    ]);
    return [
        'success' => true,
        'status' => 'ok',
        'message' => 'همگام‌سازی نوید انجام شد.',
        'summary' => [
            'coursesChecked' => count($courses),
            'coursesSucceeded' => max(0, count($courses) - $failedCourses),
            'assignmentsFetched' => count($currentAssignmentsByKey),
            'newEvents' => $newEvents,
            'failedCourses' => $failedCourses,
        ],
        'ownerStatus' => navid_build_owner_status($store),
    ];
}

function navid_get_captcha_image(array $cookies, string $loginUrl): array
{
    $origin = navid_origin_from_login_url($loginUrl);
    $response = navid_http_request('POST', navid_absolute_url($origin, '/account/generate'), [
        'cookies' => $cookies,
        'origin' => $origin,
        'referer' => $loginUrl,
        'headers' => ['X-Requested-With: XMLHttpRequest'],
    ]);

    $responseCookies = navid_merge_cookies($cookies, $response['cookies'] ?? []);
    $decoded = navid_decode_json_response($response);
    if (!is_array($decoded) || empty($decoded['customResult']['data'])) {
        return [
            'success' => false,
            'error' => 'captcha_generate_failed',
            'cookies' => $responseCookies,
            'data' => '',
            'contentType' => 'image/png',
        ];
    }

    return [
        'success' => true,
        'cookies' => $responseCookies,
        'data' => (string) ($decoded['customResult']['data'] ?? ''),
        'contentType' => (string) ($decoded['customResult']['contentType'] ?? 'image/png'),
    ];
}

function navid_submit_login(
    string $loginUrl,
    array $cookies,
    string $requestToken,
    string $username,
    string $password,
    string $captchaCode
): array {
    $origin = navid_origin_from_login_url($loginUrl);
    $response = navid_http_request('POST', navid_absolute_url($origin, '/account/submitloginsipadservice'), [
        'cookies' => $cookies,
        'origin' => $origin,
        'referer' => $loginUrl,
        'form' => [
            'ReturnUrl' => '/',
            'Username' => $username,
            'Password' => $password,
            'CaptchaCode' => $captchaCode,
            '__RequestVerificationToken' => $requestToken,
        ],
        'headers' => ['X-Requested-With: XMLHttpRequest'],
    ]);

    $mergedCookies = navid_merge_cookies($cookies, $response['cookies'] ?? []);
    $decoded = navid_decode_json_response($response);
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'error' => 'login_response_invalid',
            'cookies' => $mergedCookies,
            'code' => '',
            'message' => '',
            'nextUrl' => '',
        ];
    }

    return [
        'success' => !empty($decoded['success']),
        'error' => '',
        'cookies' => $mergedCookies,
        'code' => (string) ($decoded['customResult']['code'] ?? ''),
        'message' => (string) ($decoded['message'] ?? ''),
        'nextUrl' => (string) ($decoded['customResult']['url'] ?? ''),
    ];
}

function navid_attempt_login(array &$store): array
{
    $config = is_array($store['config'] ?? null) ? $store['config'] : [];
    $loginUrl = navid_normalize_login_url((string) ($config['loginUrl'] ?? ''));
    $credentials = navid_get_credentials($store);

    if ($credentials === null) {
        return [
            'success' => false,
            'error' => 'credentials_missing',
            'message' => 'اطلاعات ورود نوید ذخیره نشده است.',
            'cookies' => [],
        ];
    }

    $first = navid_fetch_login_page($loginUrl);
    if (!$first['ok'] || (int) ($first['status'] ?? 0) < 200 || (int) ($first['status'] ?? 0) >= 400) {
        return [
            'success' => false,
            'error' => 'login_page_unreachable',
            'message' => 'صفحه ورود نوید در دسترس نیست.',
            'cookies' => [],
        ];
    }

    $cookies = navid_merge_cookies([], $first['cookies'] ?? []);
    $loginUrl = navid_normalize_login_url((string) ($first['effectiveUrl'] ?? $loginUrl));
    $token = navid_extract_anti_forgery_token((string) ($first['body'] ?? ''));
    if ($token === '') {
        return [
            'success' => false,
            'error' => 'token_not_found',
            'message' => 'توکن امنیتی ورود نوید پیدا نشد.',
            'cookies' => $cookies,
        ];
    }

    $captchaStrategy = (string) ($config['captchaStrategy'] ?? 'python_ocr');
    $lastCaptchaDataUri = '';
    if ($captchaStrategy !== 'python_ocr') {
        return [
            'success' => false,
            'error' => 'captcha_manual_required',
            'message' => 'برای این تنظیمات، اتصال مجدد دستی کپچا لازم است.',
            'cookies' => $cookies,
            'challenge' => navid_finalize_http_manual_challenge($loginUrl, $token, $cookies),
        ];
    }

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $captchaResult = navid_get_captcha_image($cookies, $loginUrl);
        $cookies = navid_merge_cookies($cookies, $captchaResult['cookies'] ?? []);
        if (empty($captchaResult['success'])) {
            continue;
        }
        if (!empty($captchaResult['data'])) {
            $lastCaptchaDataUri = 'data:' . ($captchaResult['contentType'] ?? 'image/png') . ';base64,' . $captchaResult['data'];
        }

        $captchaCode = navid_solve_captcha_python((string) $captchaResult['data']);
        if ($captchaCode === '') {
            return [
                'success' => false,
                'error' => 'captcha_solver_unavailable',
                'message' => 'حل خودکار کپچا در این سرور در دسترس نیست. اتصال دستی لازم است.',
                'cookies' => $cookies,
                'challenge' => navid_finalize_http_manual_challenge($loginUrl, $token, $cookies, $lastCaptchaDataUri),
            ];
        }

        $submit = navid_submit_login(
            $loginUrl,
            $cookies,
            $token,
            (string) $credentials['username'],
            (string) $credentials['password'],
            $captchaCode
        );
        $cookies = navid_merge_cookies($cookies, $submit['cookies'] ?? []);

        if (!empty($submit['success'])) {
            $nextUrl = trim((string) ($submit['nextUrl'] ?? ''));
            if ($nextUrl !== '') {
                $origin = navid_origin_from_login_url($loginUrl);
                $nextAbsolute = navid_absolute_url($origin, $nextUrl);
                $final = navid_http_request('GET', $nextAbsolute, [
                    'cookies' => $cookies,
                    'referer' => $loginUrl,
                    'origin' => $origin,
                ]);
                $cookies = navid_merge_cookies($cookies, $final['cookies'] ?? []);
            }

            return [
                'success' => true,
                'error' => '',
                'message' => 'ورود خودکار نوید انجام شد.',
                'cookies' => $cookies,
            ];
        }

        $code = (string) ($submit['code'] ?? '');
        if ($code === 'FailCaptcha') {
            continue;
        }

        if ($code === 'FailLogin') {
            return [
                'success' => false,
                'error' => 'credentials_invalid',
                'message' => 'نام کاربری یا رمز نوید نادرست است.',
                'cookies' => $cookies,
            ];
        }
    }

    return [
        'success' => false,
        'error' => 'captcha_retries_exhausted',
        'message' => 'ورود خودکار نوید پس از چند تلاش کپچا موفق نشد.',
        'cookies' => $cookies,
        'challenge' => navid_finalize_http_manual_challenge($loginUrl, $token, $cookies, $lastCaptchaDataUri),
    ];
}

function navid_fetch_dashboard_payload(array $cookies, string $loginUrl): array
{
    $origin = navid_origin_from_login_url($loginUrl);
    $response = navid_http_request('POST', navid_absolute_url($origin, '/dashboard/getcourseslist'), [
        'cookies' => $cookies,
        'origin' => $origin,
        'referer' => $origin . '/',
        'json' => [
            'searchContent' => '',
            'TermId' => null,
        ],
        'headers' => ['X-Requested-With: XMLHttpRequest'],
    ]);

    $mergedCookies = navid_merge_cookies($cookies, $response['cookies'] ?? []);
    $decoded = navid_decode_json_response($response);
    if (!is_array($decoded) || empty($decoded['success'])) {
        return [
            'success' => false,
            'cookies' => $mergedCookies,
            'payload' => null,
        ];
    }

    return [
        'success' => true,
        'cookies' => $mergedCookies,
        'payload' => $decoded['customResult'] ?? [],
    ];
}

function navid_extract_courses(array $dashboardPayload, string $loginUrl): array
{
    $origin = navid_origin_from_login_url($loginUrl);
    $activeCourses = is_array($dashboardPayload['activeCourses'] ?? null) ? $dashboardPayload['activeCourses'] : [];
    $coursesById = [];

    foreach ($activeCourses as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $instance = is_array($entry['courseInstance'] ?? null) ? $entry['courseInstance'] : [];
        $courseTemplateId = (int) (
            $entry['activeCourseTemplateId']
            ?? $instance['activeCourseTemplateId']
            ?? $entry['courseTemplateId']
            ?? $instance['courseTemplateId']
            ?? 0
        );
        if ($courseTemplateId <= 0) {
            continue;
        }

        $courseInstanceId = (int) (
            $entry['courseInstanceId']
            ?? $instance['id']
            ?? $instance['courseInstanceId']
            ?? 0
        );

        $title = dent_clean_text(
            (string) (
                $entry['courseTitle']
                ?? $instance['courseTitle']
                ?? $instance['title']
                ?? ''
            ),
            180
        );
        if ($title === '') {
            $title = 'درس ' . $courseTemplateId;
        }

        $activeCount = (int) (
            $entry['activeCourseTemplateCount']
            ?? $instance['activeCourseTemplateCount']
            ?? 0
        );

        $termTitle = dent_clean_text(
            (string) (
                $entry['termTitle']
                ?? $instance['termTitle']
                ?? ''
            ),
            120
        );
        $periodTitle = dent_clean_text(
            (string) (
                $entry['periodTitle']
                ?? $instance['periodTitle']
                ?? ''
            ),
            120
        );

        $coursesById[(string) $courseTemplateId] = [
            'courseTemplateId' => $courseTemplateId,
            'courseTitle' => $title,
            'courseInstanceId' => $courseInstanceId,
            'courseUrl' => navid_absolute_url($origin, '/coursetemplate/details/' . $courseTemplateId . '/1/'),
            'activeCourseTemplateCount' => $activeCount,
            'termTitle' => $termTitle,
            'periodTitle' => $periodTitle,
        ];
    }

    ksort($coursesById, SORT_STRING);
    return array_values($coursesById);
}

function navid_fetch_course_assignments(array $cookies, string $loginUrl, int $courseTemplateId): array
{
    $origin = navid_origin_from_login_url($loginUrl);
    $response = navid_http_request('GET', navid_absolute_url($origin, '/Assignment/CourseAssignmentList'), [
        'cookies' => $cookies,
        'origin' => $origin,
        'referer' => navid_absolute_url($origin, '/coursetemplate/details/' . $courseTemplateId . '/1/'),
        'query' => [
            'courseTemplateId' => $courseTemplateId,
            'isTeacherView' => 'false',
            'sessionId' => '',
        ],
        'headers' => ['X-Requested-With: XMLHttpRequest'],
    ]);

    $mergedCookies = navid_merge_cookies($cookies, $response['cookies'] ?? []);
    $decoded = navid_decode_json_response($response);
    if (!is_array($decoded) || empty($decoded['success'])) {
        return [
            'success' => false,
            'cookies' => $mergedCookies,
            'assignments' => [],
        ];
    }

    $list = is_array($decoded['customResult'] ?? null) ? $decoded['customResult'] : [];
    return [
        'success' => true,
        'cookies' => $mergedCookies,
        'assignments' => $list,
    ];
}

function navid_description_text(string $descriptionHtml): string
{
    $decoded = html_entity_decode($descriptionHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strip_tags($decoded);
    return dent_clean_text($text, 1600);
}

function navid_normalize_assignment(array $course, array $raw, string $loginUrl): ?array
{
    $assignmentId = (int) ($raw['id'] ?? 0);
    if ($assignmentId <= 0) {
        return null;
    }

    $courseTemplateId = (int) ($course['courseTemplateId'] ?? 0);
    if ($courseTemplateId <= 0) {
        return null;
    }

    $origin = navid_origin_from_login_url($loginUrl);
    $title = dent_clean_text((string) ($raw['title'] ?? ''), 260);
    $descriptionHtml = (string) ($raw['description'] ?? '');
    $descriptionText = navid_description_text($descriptionHtml);
    $endDateIso = trim((string) ($raw['endDate'] ?? ''));
    $endDateShamsi = dent_clean_text((string) ($raw['endDateString'] ?? ''), 40);
    $proposeDate = dent_clean_text((string) ($raw['proposeDate'] ?? ''), 40);
    $ownerName = dent_clean_text((string) ($raw['owner']['fullname'] ?? ''), 120);

    $files = [];
    $rawFiles = is_array($raw['assignmentFiles'] ?? null) ? $raw['assignmentFiles'] : [];
    foreach ($rawFiles as $file) {
        if (!is_array($file)) {
            continue;
        }
        $fileId = (int) ($file['fileId'] ?? 0);
        $fileName = dent_clean_text((string) ($file['name'] ?? ''), 180);
        $fileUrl = navid_absolute_url($origin, (string) ($file['url'] ?? ''));
        $files[] = [
            'fileId' => $fileId,
            'name' => $fileName,
            'url' => $fileUrl,
            'extension' => dent_clean_text((string) ($file['extension'] ?? ''), 16),
            'size' => (int) ($file['size'] ?? 0),
        ];
    }

    $payloadForHash = [
        'assignmentId' => $assignmentId,
        'title' => $title,
        'descriptionHtml' => $descriptionHtml,
        'endDateIso' => $endDateIso,
        'endDateShamsi' => $endDateShamsi,
        'proposeDate' => $proposeDate,
        'isActive' => !empty($raw['isActive']),
        'isShown' => !empty($raw['isShown']),
        'files' => $files,
    ];
    $fingerprint = hash('sha256', json_encode($payloadForHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

    return [
        'assignmentKey' => $courseTemplateId . ':' . $assignmentId,
        'assignmentId' => $assignmentId,
        'courseTemplateId' => $courseTemplateId,
        'courseTitle' => (string) ($course['courseTitle'] ?? ''),
        'courseUrl' => (string) ($course['courseUrl'] ?? ''),
        'title' => $title,
        'descriptionHtml' => $descriptionHtml,
        'descriptionText' => $descriptionText,
        'proposeDate' => $proposeDate,
        'endDateIso' => $endDateIso,
        'endDateShamsi' => $endDateShamsi,
        'ownerName' => $ownerName,
        'isActive' => !empty($raw['isActive']),
        'isShown' => !empty($raw['isShown']),
        'replyStatusName' => dent_clean_text((string) ($raw['replyStatusName'] ?? ''), 80),
        'files' => $files,
        'fingerprint' => $fingerprint,
        'sourceUpdatedAt' => dent_iso_now(),
    ];
}

function navid_assignments_sorted(array $assignments): array
{
    usort(
        $assignments,
        static function (array $left, array $right): int {
            $leftDate = strtotime((string) ($left['endDateIso'] ?? '')) ?: PHP_INT_MAX;
            $rightDate = strtotime((string) ($right['endDateIso'] ?? '')) ?: PHP_INT_MAX;
            if ($leftDate === $rightDate) {
                return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
            }
            return $leftDate <=> $rightDate;
        }
    );
    return $assignments;
}

function navid_add_update_if_new(array &$store, array $event): void
{
    $updates = is_array($store['updates'] ?? null) ? $store['updates'] : [];
    $key = (string) ($event['assignmentKey'] ?? '');
    $fingerprint = (string) ($event['fingerprint'] ?? '');
    foreach (array_slice($updates, 0, 80) as $old) {
        if (!is_array($old)) {
            continue;
        }
        if ((string) ($old['assignmentKey'] ?? '') === $key && (string) ($old['fingerprint'] ?? '') === $fingerprint) {
            return;
        }
    }

    array_unshift($updates, $event);
    $store['updates'] = array_slice($updates, 0, 300);
}

function navid_sync_action_required(?array $credentials, array $state, bool $enabled): string
{
    if (!$enabled) {
        return 'disabled';
    }

    if ($credentials === null) {
        return 'save-credentials';
    }

    $lastResult = trim((string) ($state['lastResult'] ?? ''));
    if ($lastResult === 'credentials-missing') {
        return 'save-credentials';
    }
    if ($lastResult === 'credentials-invalid') {
        return 'update-credentials';
    }
    if (!empty($state['requiresReconnect'])) {
        return 'manual-reconnect';
    }

    return 'none';
}

function navid_finish_sync_from_dashboard(
    array &$store,
    string $loginUrl,
    array $cookies,
    array $dashboard,
    float $startedAt
): array {
    if (empty($dashboard['success']) || !is_array($dashboard['payload'] ?? null)) {
        $store['state']['lastError'] = 'خواندن داشبورد نوید انجام نشد.';
        $store['state']['consecutiveFailures'] = (int) ($store['state']['consecutiveFailures'] ?? 0) + 1;
        $store['state']['lastResult'] = 'dashboard-failed';
        $store['state']['lastFailedCourses'] = 0;
        navid_set_session_cookies($store, $cookies);
        navid_save_store($store);
        return [
            'success' => false,
            'status' => 'dashboard-failed',
            'message' => 'خواندن داشبورد نوید انجام نشد.',
            'ownerStatus' => navid_build_owner_status($store),
        ];
    }

    $courses = navid_extract_courses((array) $dashboard['payload'], $loginUrl);
    $currentAssignmentsByKey = [];
    $courseSnapshot = [];
    $failedCourses = 0;
    $previousAssignments = is_array($store['snapshot']['assignments'] ?? null) ? $store['snapshot']['assignments'] : [];
    $hasAnnouncementBaseline = trim((string) ($store['state']['lastSuccessAt'] ?? '')) !== '' || count($previousAssignments) > 0;
    $previousCourses = is_array($store['snapshot']['courses'] ?? null) ? $store['snapshot']['courses'] : [];

    foreach ($courses as $course) {
        $courseTemplateId = (int) ($course['courseTemplateId'] ?? 0);
        if ($courseTemplateId <= 0) {
            continue;
        }

        $assignmentResult = navid_fetch_course_assignments($cookies, $loginUrl, $courseTemplateId);
        $cookies = navid_merge_cookies($cookies, $assignmentResult['cookies'] ?? []);

        if (empty($assignmentResult['success'])) {
            $failedCourses++;
            $preservedKeys = [];
            $previousCourseSnapshot = is_array($previousCourses[(string) $courseTemplateId] ?? null)
                ? $previousCourses[(string) $courseTemplateId]
                : [];
            $previousKeys = is_array($previousCourseSnapshot['assignmentKeys'] ?? null)
                ? $previousCourseSnapshot['assignmentKeys']
                : [];
            foreach ($previousKeys as $previousKey) {
                $key = (string) $previousKey;
                if ($key === '' || !is_array($previousAssignments[$key] ?? null)) {
                    continue;
                }
                $currentAssignmentsByKey[$key] = $previousAssignments[$key];
                $preservedKeys[] = $key;
            }
            $courseSnapshot[(string) $courseTemplateId] = [
                'courseTemplateId' => $courseTemplateId,
                'courseTitle' => (string) ($course['courseTitle'] ?? ''),
                'courseUrl' => (string) ($course['courseUrl'] ?? ''),
                'assignmentKeys' => $preservedKeys,
                'assignmentCount' => count($preservedKeys),
                'lastCheckedAt' => dent_iso_now(),
                'fetchStatus' => 'failed',
                'fetchError' => (string) ($assignmentResult['message'] ?? $assignmentResult['error'] ?? 'fetch_failed'),
            ];
            continue;
        }

        $assignmentList = is_array($assignmentResult['assignments'] ?? null) ? $assignmentResult['assignments'] : [];
        $courseAssignmentKeys = [];
        foreach ($assignmentList as $rawAssignment) {
            if (!is_array($rawAssignment)) {
                continue;
            }

            $normalized = navid_normalize_assignment($course, $rawAssignment, $loginUrl);
            if ($normalized === null) {
                continue;
            }

            $key = (string) $normalized['assignmentKey'];
            $courseAssignmentKeys[] = $key;
            $currentAssignmentsByKey[$key] = $normalized;
        }

        $courseSnapshot[(string) $courseTemplateId] = [
            'courseTemplateId' => $courseTemplateId,
            'courseTitle' => (string) ($course['courseTitle'] ?? ''),
            'courseUrl' => (string) ($course['courseUrl'] ?? ''),
            'assignmentKeys' => $courseAssignmentKeys,
            'assignmentCount' => count($courseAssignmentKeys),
            'lastCheckedAt' => dent_iso_now(),
            'fetchStatus' => 'ok',
            'fetchError' => '',
        ];
    }

    $newEvents = 0;

    foreach ($currentAssignmentsByKey as $key => $assignment) {
        $old = is_array($previousAssignments[$key] ?? null) ? $previousAssignments[$key] : null;
        if ($old === null) {
            if (navid_should_announce_new_assignment($old, $hasAnnouncementBaseline)) {
                $event = array_merge($assignment, [
                    'eventType' => 'created',
                    'detectedAt' => dent_iso_now(),
                    'eventId' => 'navid-' . str_replace(':', '-', $key) . '-' . time(),
                ]);
                navid_add_update_if_new($store, $event);
                notifications_enqueue_navid_assignment($assignment);
                $newEvents++;
            }
            continue;
        }

        if ((string) ($old['fingerprint'] ?? '') !== (string) ($assignment['fingerprint'] ?? '')) {
            $event = array_merge($assignment, [
                'eventType' => 'updated',
                'detectedAt' => dent_iso_now(),
                'eventId' => 'navid-' . str_replace(':', '-', $key) . '-' . time(),
                'previousEndDateIso' => (string) ($old['endDateIso'] ?? ''),
                'previousEndDateShamsi' => (string) ($old['endDateShamsi'] ?? ''),
            ]);
            navid_add_update_if_new($store, $event);
            $newEvents++;
        }
    }

    $store['snapshot']['courses'] = $courseSnapshot;
    $store['snapshot']['assignments'] = $currentAssignmentsByKey;

    navid_set_session_cookies($store, $cookies);
    $store['session']['status'] = 'active';
    $store['session']['lastValidatedAt'] = dent_iso_now();

    $store['state']['lastFailedCourses'] = $failedCourses;
    $store['state']['lastSyncDurationMs'] = (int) round((microtime(true) - $startedAt) * 1000);
    $store['state']['requiresReconnect'] = false;
    $store['state']['consecutiveFailures'] = 0;
    if ($failedCourses > 0) {
        $store['state']['lastResult'] = 'partial';
        $store['state']['lastError'] = 'برخی دروس نوید در همگام‌سازی آخر دریافت نشدند. تا تکمیل همه دروس، خروجی تایید نمی‌شود.';
    } else {
        $store['state']['lastSuccessAt'] = dent_iso_now();
        $store['state']['lastResult'] = 'ok';
        $store['state']['lastError'] = '';
    }

    navid_save_store($store);

    if ($failedCourses > 0) {
        navid_log('warning', 'sync_partial', [
            'courses' => count($courses),
            'assignments' => count($currentAssignmentsByKey),
            'newEvents' => $newEvents,
            'failedCourses' => $failedCourses,
        ]);

        return [
            'success' => false,
            'status' => 'partial',
            'message' => 'همگام‌سازی نوید ناقص بود و بعضی دروس دریافت نشدند.',
            'summary' => [
                'coursesChecked' => count($courses),
                'coursesSucceeded' => max(0, count($courses) - $failedCourses),
                'assignmentsFetched' => count($currentAssignmentsByKey),
                'newEvents' => $newEvents,
                'failedCourses' => $failedCourses,
            ],
            'ownerStatus' => navid_build_owner_status($store),
        ];
    }

    navid_log('info', 'sync_success', [
        'courses' => count($courses),
        'assignments' => count($currentAssignmentsByKey),
        'newEvents' => $newEvents,
        'failedCourses' => $failedCourses,
    ]);
    return [
        'success' => true,
        'status' => 'ok',
        'message' => 'همگام‌سازی نوید انجام شد.',
        'summary' => [
            'coursesChecked' => count($courses),
            'coursesSucceeded' => max(0, count($courses) - $failedCourses),
            'assignmentsFetched' => count($currentAssignmentsByKey),
            'newEvents' => $newEvents,
            'failedCourses' => $failedCourses,
        ],
        'ownerStatus' => navid_build_owner_status($store),
    ];
}

function navid_login_failure_meta(string $errorCode): array
{
    switch ($errorCode) {
        case 'credentials_missing':
            return [
                'status' => 'credentials-missing',
                'requiresReconnect' => false,
            ];
        case 'credentials_invalid':
            return [
                'status' => 'credentials-invalid',
                'requiresReconnect' => false,
            ];
        case 'captcha_manual_required':
        case 'captcha_solver_unavailable':
        case 'captcha_retries_exhausted':
            return [
                'status' => 'reconnect-required',
                'requiresReconnect' => true,
            ];
        default:
            return [
                'status' => 'login-failed',
                'requiresReconnect' => true,
            ];
    }
}

function navid_build_owner_status(array $store): array
{
    $credentials = navid_get_credentials($store);
    $config = is_array($store['config'] ?? null) ? $store['config'] : [];
    $session = is_array($store['session'] ?? null) ? $store['session'] : [];
    $state = is_array($store['state'] ?? null) ? $store['state'] : [];
    $snapshot = is_array($store['snapshot'] ?? null) ? $store['snapshot'] : [];
    $assignments = is_array($snapshot['assignments'] ?? null) ? $snapshot['assignments'] : [];
    $courses = is_array($snapshot['courses'] ?? null) ? $snapshot['courses'] : [];
    $enabled = !empty($config['enabled']);
    $actionRequired = navid_sync_action_required($credentials, $state, $enabled);
    $challenge = navid_get_challenge($store);
    $successfulCourses = 0;
    $failedCourses = 0;
    foreach ($courses as $course) {
        if (!is_array($course)) {
            continue;
        }
        if ((string) ($course['fetchStatus'] ?? '') === 'ok') {
            $successfulCourses++;
        } elseif ((string) ($course['fetchStatus'] ?? '') === 'failed') {
            $failedCourses++;
        }
    }

    return [
        'config' => [
            'enabled' => $enabled,
            'loginUrl' => navid_normalize_login_url((string) ($config['loginUrl'] ?? '')),
            'syncIntervalMinutes' => (int) ($config['syncIntervalMinutes'] ?? 30),
            'captchaStrategy' => (string) ($config['captchaStrategy'] ?? 'python_ocr'),
            'credentialUpdatedAt' => (string) ($config['credentialUpdatedAt'] ?? ''),
            'usernameMasked' => $credentials ? navid_mask_value((string) ($credentials['username'] ?? '')) : '',
            'hasCredentials' => $credentials !== null,
        ],
        'session' => [
            'status' => (string) ($session['status'] ?? 'missing'),
            'updatedAt' => (string) ($session['updatedAt'] ?? ''),
            'lastValidatedAt' => (string) ($session['lastValidatedAt'] ?? ''),
            'hasCookies' => !empty($session['cookies']),
            'hasBrowserState' => !empty($session['browserState']),
        ],
        'state' => [
            'lastSyncAt' => (string) ($state['lastSyncAt'] ?? ''),
            'lastSuccessAt' => (string) ($state['lastSuccessAt'] ?? ''),
            'lastError' => (string) ($state['lastError'] ?? ''),
            'consecutiveFailures' => (int) ($state['consecutiveFailures'] ?? 0),
            'requiresReconnect' => !empty($state['requiresReconnect']),
            'lastSyncDurationMs' => (int) ($state['lastSyncDurationMs'] ?? 0),
            'lastFailedCourses' => (int) ($state['lastFailedCourses'] ?? 0),
            'lastResult' => (string) ($state['lastResult'] ?? ''),
            'actionRequired' => $actionRequired,
            'credentialsMissing' => $actionRequired === 'save-credentials',
            'credentialsInvalid' => $actionRequired === 'update-credentials',
            'hasActiveChallenge' => is_array($challenge),
            'challengeExpiresAt' => is_array($challenge) ? (string) ($challenge['expiresAt'] ?? '') : '',
            'captchaDataUri' => is_array($challenge) ? (string) ($challenge['captchaDataUri'] ?? '') : '',
        ],
        'snapshotCounts' => [
            'courses' => count($courses),
            'successfulCourses' => $successfulCourses,
            'assignments' => count($assignments),
            'updates' => count(is_array($store['updates'] ?? null) ? $store['updates'] : []),
            'failedCourses' => max($failedCourses, (int) ($state['lastFailedCourses'] ?? 0)),
        ],
    ];
}

function navid_sync_due(array $store): bool
{
    $config = is_array($store['config'] ?? null) ? $store['config'] : [];
    $state = is_array($store['state'] ?? null) ? $store['state'] : [];

    if (empty($config['enabled'])) {
        return false;
    }

    $intervalMinutes = (int) ($config['syncIntervalMinutes'] ?? 30);
    if ($intervalMinutes < 5) {
        $intervalMinutes = 5;
    }

    $lastAnchor = trim((string) ($state['lastSuccessAt'] ?? ''));
    if ($lastAnchor === '') {
        $lastAnchor = trim((string) ($state['lastSyncAt'] ?? ''));
    }
    if ($lastAnchor === '') {
        return true;
    }

    $lastTs = strtotime($lastAnchor);
    if ($lastTs === false) {
        return true;
    }

    return (time() - $lastTs) >= ($intervalMinutes * 60);
}

function navid_sync(bool $force = false): array
{
    $store = navid_load_store();
    $config = is_array($store['config'] ?? null) ? $store['config'] : [];
    $hadSessionCookies = count(navid_get_session_cookies($store)) > 0;
    $hadBrowserState = is_array(navid_get_session_browser_state($store));
    $hadSuccessfulSnapshot = trim((string) ($store['state']['lastSuccessAt'] ?? '')) !== ''
        && !empty($store['snapshot']['assignments']);
    $hadReusableSession = $hadSuccessfulSnapshot && ($hadSessionCookies || $hadBrowserState);

    if (empty($config['enabled'])) {
        return [
            'success' => false,
            'status' => 'disabled',
            'message' => 'یکپارچه‌سازی نوید غیرفعال است.',
            'ownerStatus' => navid_build_owner_status($store),
        ];
    }

    if (!$force && !navid_sync_due($store)) {
        return [
            'success' => true,
            'status' => 'skipped',
            'message' => 'هنوز زمان همگام‌سازی دوره‌ای نوید نرسیده است.',
            'ownerStatus' => navid_build_owner_status($store),
        ];
    }

    $lockPath = dent_storage_path('navid/sync.lock');
    dent_ensure_directory(dirname($lockPath));
    $lock = fopen($lockPath, 'c+');
    if ($lock === false) {
        return [
            'success' => false,
            'status' => 'lock-failed',
            'message' => 'خطا در ایجاد قفل همگام‌سازی نوید.',
            'ownerStatus' => navid_build_owner_status($store),
        ];
    }

    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        return [
            'success' => true,
            'status' => 'already-running',
            'message' => 'یک همگام‌سازی نوید در حال اجراست.',
            'ownerStatus' => navid_build_owner_status($store),
        ];
    }

    $startedAt = microtime(true);
    $loginUrl = navid_normalize_login_url((string) ($config['loginUrl'] ?? ''));

    if (!empty($store['state']['requiresReconnect'])) {
        $challenge = navid_prepare_http_manual_challenge($store, $loginUrl);
        if (is_array($challenge) && trim((string) ($challenge['captchaDataUri'] ?? '')) !== '') {
            navid_save_store($store);
            $message = trim((string) ($store['state']['lastError'] ?? ''));
            if ($message === '') {
                $message = 'برای ادامه اتصال نوید، کپچا را وارد کن.';
            }
            return navid_reconnect_required_response($store, $message);
        }
    }

    $cookies = navid_get_session_cookies($store);
    $dashboard = navid_fetch_dashboard_payload($cookies, $loginUrl);
    $cookies = navid_merge_cookies($cookies, $dashboard['cookies'] ?? []);

    $store['state']['lastSyncAt'] = dent_iso_now();
    $store['state']['lastResult'] = 'running';
    $store['state']['lastFailedCourses'] = 0;

    try {
        if (empty($dashboard['success'])) {
            $login = navid_attempt_login($store);
            if (empty($login['success'])) {
                $store['state']['lastError'] = (string) ($login['message'] ?? 'ورود نوید انجام نشد.');
                $store['state']['consecutiveFailures'] = (int) ($store['state']['consecutiveFailures'] ?? 0) + 1;
                $loginErrorCode = (string) ($login['error'] ?? '');
                if ($hadReusableSession && in_array($loginErrorCode, ['login_page_unreachable', 'token_not_found'], true)) {
                    $store['state']['requiresReconnect'] = false;
                    $store['state']['lastResult'] = 'upstream-unreachable';
                    $store['state']['lastFailedCourses'] = 0;
                    $store['state']['lastSyncDurationMs'] = (int) round((microtime(true) - $startedAt) * 1000);
                    navid_clear_challenge($store);
                    navid_log('warning', 'sync_upstream_unreachable_preserved', [
                        'error' => $loginErrorCode,
                        'message' => $login['message'] ?? '',
                    ]);
                    navid_save_store($store);
                    return [
                        'success' => false,
                        'status' => 'upstream-unreachable',
                        'message' => 'در این لحظه دسترسی پایدار به نوید برقرار نشد؛ آخرین همگام‌سازی موفق حفظ شد.',
                        'ownerStatus' => navid_build_owner_status($store),
                    ];
                }
                $loginFailureMeta = navid_login_failure_meta($loginErrorCode);
                $store['state']['requiresReconnect'] = !empty($loginFailureMeta['requiresReconnect']);
                $store['state']['lastResult'] = (string) ($loginFailureMeta['status'] ?? 'login-failed');
                $store['state']['lastFailedCourses'] = 0;
                navid_clear_session_cookies($store);
                $challengePayload = is_array($login['challenge'] ?? null) ? $login['challenge'] : null;
                if (!empty($store['state']['requiresReconnect'])
                    && (!is_array($challengePayload) || trim((string) ($challengePayload['captchaDataUri'] ?? '')) === '')
                ) {
                    $preparedChallenge = navid_prepare_http_manual_challenge($store, $loginUrl);
                    if (is_array($preparedChallenge) && trim((string) ($preparedChallenge['captchaDataUri'] ?? '')) !== '') {
                        $challengePayload = $preparedChallenge;
                    }
                }
                if ($challengePayload !== null && !empty($store['state']['requiresReconnect'])) {
                    navid_set_challenge($store, $challengePayload);
                    $store['session']['status'] = 'challenge';
                } else {
                    navid_clear_challenge($store);
                }
                navid_log('error', 'sync_login_failed', ['error' => $login['error'] ?? '', 'message' => $login['message'] ?? '']);
                navid_save_store($store);
                return [
                    'success' => false,
                    'status' => (string) ($loginFailureMeta['status'] ?? 'login-failed'),
                    'message' => (string) ($login['message'] ?? 'ورود نوید انجام نشد.'),
                    'captchaDataUri' => (string) (($challengePayload['captchaDataUri'] ?? '') ?: ''),
                    'ownerStatus' => navid_build_owner_status($store),
                ];
            }

            $cookies = navid_merge_cookies($cookies, $login['cookies'] ?? []);
            navid_set_session_cookies($store, $cookies);
            $store['session']['status'] = 'active';
            $store['state']['requiresReconnect'] = false;

            $dashboard = navid_fetch_dashboard_payload($cookies, $loginUrl);
            $cookies = navid_merge_cookies($cookies, $dashboard['cookies'] ?? []);
        }

        if (empty($dashboard['success']) || !is_array($dashboard['payload'] ?? null)) {
            $store['state']['lastError'] = 'خواندن داشبورد نوید انجام نشد.';
            $store['state']['consecutiveFailures'] = (int) ($store['state']['consecutiveFailures'] ?? 0) + 1;
            $store['state']['lastResult'] = 'dashboard-failed';
            $store['state']['lastFailedCourses'] = 0;
            navid_set_session_cookies($store, $cookies);
            navid_save_store($store);
            return [
                'success' => false,
                'status' => 'dashboard-failed',
                'message' => 'خواندن داشبورد نوید انجام نشد.',
                'ownerStatus' => navid_build_owner_status($store),
            ];
        }

        $courses = navid_extract_courses((array) $dashboard['payload'], $loginUrl);
        $currentAssignmentsByKey = [];
        $courseSnapshot = [];
        $failedCourses = 0;
        $previousAssignments = is_array($store['snapshot']['assignments'] ?? null) ? $store['snapshot']['assignments'] : [];
        $previousCourses = is_array($store['snapshot']['courses'] ?? null) ? $store['snapshot']['courses'] : [];

        foreach ($courses as $course) {
            $courseTemplateId = (int) ($course['courseTemplateId'] ?? 0);
            if ($courseTemplateId <= 0) {
                continue;
            }

            $assignmentResult = navid_fetch_course_assignments($cookies, $loginUrl, $courseTemplateId);
            $cookies = navid_merge_cookies($cookies, $assignmentResult['cookies'] ?? []);

            if (empty($assignmentResult['success'])) {
                $failedCourses++;
                $preservedKeys = [];
                $previousCourseSnapshot = is_array($previousCourses[(string) $courseTemplateId] ?? null)
                    ? $previousCourses[(string) $courseTemplateId]
                    : [];
                $previousKeys = is_array($previousCourseSnapshot['assignmentKeys'] ?? null)
                    ? $previousCourseSnapshot['assignmentKeys']
                    : [];
                foreach ($previousKeys as $previousKey) {
                    $key = (string) $previousKey;
                    if ($key === '' || !is_array($previousAssignments[$key] ?? null)) {
                        continue;
                    }
                    $currentAssignmentsByKey[$key] = $previousAssignments[$key];
                    $preservedKeys[] = $key;
                }
                $courseSnapshot[(string) $courseTemplateId] = [
                    'courseTemplateId' => $courseTemplateId,
                    'courseTitle' => (string) ($course['courseTitle'] ?? ''),
                    'courseUrl' => (string) ($course['courseUrl'] ?? ''),
                    'assignmentKeys' => $preservedKeys,
                    'assignmentCount' => count($preservedKeys),
                    'lastCheckedAt' => dent_iso_now(),
                    'fetchStatus' => 'failed',
                    'fetchError' => (string) ($assignmentResult['message'] ?? $assignmentResult['error'] ?? 'fetch_failed'),
                ];
                continue;
            }

            $assignmentList = is_array($assignmentResult['assignments'] ?? null) ? $assignmentResult['assignments'] : [];
            $courseAssignmentKeys = [];
            foreach ($assignmentList as $rawAssignment) {
                if (!is_array($rawAssignment)) {
                    continue;
                }

                $normalized = navid_normalize_assignment($course, $rawAssignment, $loginUrl);
                if ($normalized === null) {
                    continue;
                }

                $key = (string) $normalized['assignmentKey'];
                $courseAssignmentKeys[] = $key;
                $currentAssignmentsByKey[$key] = $normalized;
            }

            $courseSnapshot[(string) $courseTemplateId] = [
                'courseTemplateId' => $courseTemplateId,
                'courseTitle' => (string) ($course['courseTitle'] ?? ''),
                'courseUrl' => (string) ($course['courseUrl'] ?? ''),
                'assignmentKeys' => $courseAssignmentKeys,
                'assignmentCount' => count($courseAssignmentKeys),
                'lastCheckedAt' => dent_iso_now(),
                'fetchStatus' => 'ok',
                'fetchError' => '',
            ];
        }

        $newEvents = 0;

        foreach ($currentAssignmentsByKey as $key => $assignment) {
            $old = is_array($previousAssignments[$key] ?? null) ? $previousAssignments[$key] : null;
            if ($old === null) {
                $event = array_merge($assignment, [
                    'eventType' => 'created',
                    'detectedAt' => dent_iso_now(),
                    'eventId' => 'navid-' . str_replace(':', '-', $key) . '-' . time(),
                ]);
                navid_add_update_if_new($store, $event);
                notifications_enqueue_navid_assignment($assignment);
                $newEvents++;
                continue;
            }

            if ((string) ($old['fingerprint'] ?? '') !== (string) ($assignment['fingerprint'] ?? '')) {
                $event = array_merge($assignment, [
                    'eventType' => 'updated',
                    'detectedAt' => dent_iso_now(),
                    'eventId' => 'navid-' . str_replace(':', '-', $key) . '-' . time(),
                    'previousEndDateIso' => (string) ($old['endDateIso'] ?? ''),
                    'previousEndDateShamsi' => (string) ($old['endDateShamsi'] ?? ''),
                ]);
                navid_add_update_if_new($store, $event);
                $newEvents++;
            }
        }

        $store['snapshot']['courses'] = $courseSnapshot;
        $store['snapshot']['assignments'] = $currentAssignmentsByKey;

        navid_set_session_cookies($store, $cookies);
        $store['session']['status'] = 'active';
        $store['session']['lastValidatedAt'] = dent_iso_now();

        $store['state']['lastFailedCourses'] = $failedCourses;
        $store['state']['lastSyncDurationMs'] = (int) round((microtime(true) - $startedAt) * 1000);
        $store['state']['requiresReconnect'] = false;
        $store['state']['consecutiveFailures'] = 0;
        if ($failedCourses > 0) {
            $store['state']['lastResult'] = 'partial';
            $store['state']['lastError'] = 'برخی دروس نوید در همگام‌سازی آخر دریافت نشدند. تا تکمیل همه دروس، خروجی تایید نمی‌شود.';
        } else {
            $store['state']['lastSuccessAt'] = dent_iso_now();
            $store['state']['lastResult'] = 'ok';
            $store['state']['lastError'] = '';
        }

        navid_save_store($store);

        if ($failedCourses > 0) {
            navid_log('warning', 'sync_partial', [
                'courses' => count($courses),
                'assignments' => count($currentAssignmentsByKey),
                'newEvents' => $newEvents,
                'failedCourses' => $failedCourses,
            ]);

            return [
                'success' => false,
                'status' => 'partial',
                'message' => 'همگام‌سازی نوید ناقص بود و بعضی دروس دریافت نشدند.',
                'summary' => [
                    'coursesChecked' => count($courses),
                    'assignmentsFetched' => count($currentAssignmentsByKey),
                    'newEvents' => $newEvents,
                    'failedCourses' => $failedCourses,
                ],
                'ownerStatus' => navid_build_owner_status($store),
            ];
        }

        navid_log('info', 'sync_success', [
            'courses' => count($courses),
            'assignments' => count($currentAssignmentsByKey),
            'newEvents' => $newEvents,
            'failedCourses' => $failedCourses,
        ]);

        return [
            'success' => true,
            'status' => 'ok',
            'message' => 'همگام‌سازی نوید انجام شد.',
            'summary' => [
                'coursesChecked' => count($courses),
                'assignmentsFetched' => count($currentAssignmentsByKey),
                'newEvents' => $newEvents,
                'failedCourses' => $failedCourses,
            ],
            'ownerStatus' => navid_build_owner_status($store),
        ];
    } catch (Throwable $exception) {
        $store['state']['lastError'] = 'خطای داخلی همگام‌سازی نوید.';
        $store['state']['consecutiveFailures'] = (int) ($store['state']['consecutiveFailures'] ?? 0) + 1;
        $store['state']['lastFailedCourses'] = 0;
        $store['state']['lastResult'] = 'exception';
        $store['state']['lastSyncDurationMs'] = (int) round((microtime(true) - $startedAt) * 1000);
        navid_save_store($store);
        navid_log('error', 'sync_exception', ['type' => get_class($exception), 'message' => $exception->getMessage()]);

        return [
            'success' => false,
            'status' => 'exception',
            'message' => 'در همگام‌سازی نوید خطای داخلی رخ داد.',
            'ownerStatus' => navid_build_owner_status($store),
        ];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function navid_sync_browser(bool $force = false): array
{
    $store = navid_load_store();
    $config = is_array($store['config'] ?? null) ? $store['config'] : [];

    if (empty($config['enabled'])) {
        return [
            'success' => false,
            'status' => 'disabled',
            'message' => 'یکپارچه‌سازی نوید غیرفعال است.',
            'ownerStatus' => navid_build_owner_status($store),
        ];
    }

    if (!$force && !navid_sync_due($store)) {
        return [
            'success' => true,
            'status' => 'skipped',
            'message' => 'هنوز زمان همگام‌سازی دوره‌ای نوید نرسیده است.',
            'ownerStatus' => navid_build_owner_status($store),
        ];
    }

    $lockPath = dent_storage_path('navid/sync.lock');
    dent_ensure_directory(dirname($lockPath));
    $lock = fopen($lockPath, 'c+');
    if ($lock === false) {
        return [
            'success' => false,
            'status' => 'lock-failed',
            'message' => 'خطا در ایجاد قفل همگام‌سازی نوید.',
            'ownerStatus' => navid_build_owner_status($store),
        ];
    }

    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        return [
            'success' => true,
            'status' => 'already-running',
            'message' => 'یک همگام‌سازی نوید در حال اجراست.',
            'ownerStatus' => navid_build_owner_status($store),
        ];
    }

    $startedAt = microtime(true);
    $loginUrl = navid_normalize_login_url((string) ($config['loginUrl'] ?? ''));

    if (!empty($store['state']['requiresReconnect'])) {
        $challenge = navid_prepare_http_manual_challenge($store, $loginUrl);
        if (is_array($challenge) && trim((string) ($challenge['captchaDataUri'] ?? '')) !== '') {
            navid_save_store($store);
            flock($lock, LOCK_UN);
            fclose($lock);
            $lock = null;
            $message = trim((string) ($store['state']['lastError'] ?? ''));
            if ($message === '') {
                $message = 'برای ادامه اتصال نوید، کپچا را وارد کن.';
            }
            return navid_reconnect_required_response($store, $message);
        }
    }

    $store['state']['lastSyncAt'] = dent_iso_now();
    $store['state']['lastResult'] = 'running';
    $store['state']['lastFailedCourses'] = 0;

    try {
        $credentials = navid_get_credentials($store);
        $browserResult = navid_browser_bridge([
            'action' => 'sync',
            'loginUrl' => $loginUrl,
            'username' => (string) ($credentials['username'] ?? ''),
            'password' => (string) ($credentials['password'] ?? ''),
            'captchaStrategy' => (string) ($config['captchaStrategy'] ?? 'python_ocr'),
            'storageState' => navid_get_session_browser_state($store),
        ]);

        if (empty($browserResult['success'])) {
            $errorCode = (string) ($browserResult['error'] ?? '');
            $message = (string) ($browserResult['message'] ?? 'ورود نوید انجام نشد.');
            if (navid_browser_error_supports_http_fallback($errorCode)) {
                navid_log('warning', 'sync_browser_http_fallback', [
                    'error' => $errorCode,
                    'message' => $message,
                ]);
                flock($lock, LOCK_UN);
                fclose($lock);
                $lock = null;
                return navid_sync($force);
            }
            $store['state']['lastError'] = $message;
            $store['state']['consecutiveFailures'] = (int) ($store['state']['consecutiveFailures'] ?? 0) + 1;
            $store['state']['lastFailedCourses'] = 0;
            $store['state']['lastSyncDurationMs'] = (int) round((microtime(true) - $startedAt) * 1000);

            if ($errorCode === 'manual_challenge_required') {
                navid_store_manual_challenge($store, $browserResult);
                $store['session']['status'] = 'challenge';
                $store['state']['requiresReconnect'] = true;
                $store['state']['lastResult'] = 'reconnect-required';
            } elseif ($errorCode === 'credentials_invalid') {
                navid_clear_challenge($store);
                navid_clear_session_cookies($store);
                $store['session']['status'] = 'invalid';
                $store['state']['requiresReconnect'] = false;
                $store['state']['lastResult'] = 'credentials-invalid';
            } elseif ($errorCode === 'credentials_missing') {
                navid_clear_challenge($store);
                navid_clear_session_cookies($store);
                $store['session']['status'] = 'missing';
                $store['state']['requiresReconnect'] = false;
                $store['state']['lastResult'] = 'credentials-missing';
            } elseif (in_array($errorCode, ['dashboard_failed', 'dashboard_invalid', 'dashboard_empty'], true)) {
                $store['session']['status'] = 'stored';
                $store['state']['requiresReconnect'] = false;
                $store['state']['lastResult'] = 'dashboard-failed';
            } else {
                if (!empty($browserResult['challengeState'])) {
                    navid_store_manual_challenge($store, $browserResult);
                    $store['session']['status'] = 'challenge';
                    $store['state']['requiresReconnect'] = true;
                    $store['state']['lastResult'] = 'reconnect-required';
                } else {
                    navid_clear_session_cookies($store);
                    $store['session']['status'] = 'missing';
                    $store['state']['requiresReconnect'] = false;
                    $store['state']['lastResult'] = 'login-failed';
                }
            }

            navid_log('error', 'sync_browser_failed', [
                'error' => $errorCode,
                'message' => $message,
            ]);
            navid_save_store($store);
            return [
                'success' => false,
                'status' => (string) ($store['state']['lastResult'] ?? 'login-failed'),
                'message' => $message,
                'captchaDataUri' => (string) ($browserResult['captchaDataUri'] ?? ''),
                'ownerStatus' => navid_build_owner_status($store),
            ];
        }

        return navid_sync_store_from_browser_result($store, $browserResult, $loginUrl, $startedAt);
    } catch (Throwable $exception) {
        navid_log('error', 'sync_browser_exception', ['type' => get_class($exception), 'message' => $exception->getMessage()]);
        flock($lock, LOCK_UN);
        fclose($lock);
        $lock = null;
        try {
            return navid_sync($force);
        } catch (Throwable $fallbackException) {
            navid_log('error', 'sync_http_fallback_exception', ['type' => get_class($fallbackException), 'message' => $fallbackException->getMessage()]);
        }

        $store['state']['lastError'] = 'خطای داخلی همگام‌سازی نوید.';
        $store['state']['consecutiveFailures'] = (int) ($store['state']['consecutiveFailures'] ?? 0) + 1;
        $store['state']['lastFailedCourses'] = 0;
        $store['state']['lastResult'] = 'exception';
        $store['state']['lastSyncDurationMs'] = (int) round((microtime(true) - $startedAt) * 1000);
        navid_save_store($store);

        return [
            'success' => false,
            'status' => 'exception',
            'message' => 'در همگام‌سازی نوید خطای داخلی رخ داد.',
            'ownerStatus' => navid_build_owner_status($store),
        ];
    } finally {
        if (is_resource($lock)) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}

function navid_sync_auto(bool $force = false): array
{
    $store = navid_load_store();
    $browserState = navid_get_session_browser_state($store);
    if (is_array($browserState) && !empty($browserState['cookies'])) {
        return navid_sync_browser($force);
    }

    return navid_sync($force);
}

function navid_update_config(array $input): array
{
    $store = navid_load_store();
    $config = is_array($store['config'] ?? null) ? $store['config'] : navid_default_store()['config'];

    if (array_key_exists('enabled', $input)) {
        $config['enabled'] = !empty($input['enabled']);
    }

    if (array_key_exists('loginUrl', $input)) {
        $config['loginUrl'] = navid_normalize_login_url((string) $input['loginUrl']);
    }

    if (array_key_exists('syncIntervalMinutes', $input)) {
        $minutes = (int) $input['syncIntervalMinutes'];
        if ($minutes < 5) {
            $minutes = 5;
        }
        if ($minutes > 720) {
            $minutes = 720;
        }
        $config['syncIntervalMinutes'] = $minutes;
    }

    if (array_key_exists('captchaStrategy', $input)) {
        $strategy = trim((string) $input['captchaStrategy']);
        if (!in_array($strategy, ['python_ocr', 'manual_challenge'], true)) {
            $strategy = 'python_ocr';
        }
        $config['captchaStrategy'] = $strategy;
    }

    $username = trim((string) ($input['username'] ?? ''));
    $password = trim((string) ($input['password'] ?? ''));
    if ($username !== '' || $password !== '') {
        if ($username === '' || $password === '') {
            dent_error('برای به‌روزرسانی ورود نوید، نام کاربری و رمز را کامل وارد کن.', 422);
        }
        navid_set_credentials($store, $username, $password);
        navid_clear_session_cookies($store);
        navid_clear_challenge($store);
        $store['state']['requiresReconnect'] = false;
        $store['state']['lastError'] = '';
        $store['state']['lastFailedCourses'] = 0;
        $lastResult = (string) ($store['state']['lastResult'] ?? '');
        if (in_array($lastResult, ['credentials-missing', 'credentials-invalid'], true)) {
            $store['state']['lastResult'] = 'config-updated';
        }
    }

    if (!empty($config['enabled']) && navid_get_credentials($store) === null) {
        dent_error('برای فعال بودن نوید، باید نام کاربری و رمز را ذخیره کنید.', 422);
    }

    $store['config'] = array_merge($store['config'] ?? [], $config);
    navid_save_store($store);

    return [
        'success' => true,
        'message' => 'تنظیمات نوید ذخیره شد.',
        'ownerStatus' => navid_build_owner_status($store),
    ];
}

function navid_create_captcha_challenge(): array
{
    $store = navid_load_store();
    $loginUrl = navid_normalize_login_url((string) ($store['config']['loginUrl'] ?? ''));

    $first = navid_fetch_login_page($loginUrl);
    if (!$first['ok'] || (int) ($first['status'] ?? 0) >= 400 || (int) ($first['status'] ?? 0) < 200) {
        dent_error('صفحه ورود نوید در دسترس نیست.', 502);
    }

    $cookies = navid_merge_cookies([], $first['cookies'] ?? []);
    $loginUrl = navid_normalize_login_url((string) ($first['effectiveUrl'] ?? $loginUrl));
    $token = navid_extract_anti_forgery_token((string) ($first['body'] ?? ''));
    if ($token === '') {
        dent_error('توکن امنیتی نوید پیدا نشد.', 502);
    }

    $captcha = navid_get_captcha_image($cookies, $loginUrl);
    if (empty($captcha['success'])) {
        dent_error('دریافت کپچای نوید انجام نشد.', 502);
    }
    $cookies = navid_merge_cookies($cookies, $captcha['cookies'] ?? []);

    $challenge = [
        'createdAt' => dent_iso_now(),
        'expiresAt' => date('c', time() + 10 * 60),
        'loginUrl' => $loginUrl,
        'requestVerificationToken' => $token,
        'cookies' => $cookies,
    ];
    navid_set_challenge($store, $challenge);
    navid_save_store($store);

    return [
        'success' => true,
        'message' => 'کپچا دریافت شد.',
        'captchaDataUri' => 'data:' . ($captcha['contentType'] ?? 'image/png') . ';base64,' . $captcha['data'],
        'expiresAt' => (string) $challenge['expiresAt'],
        'ownerStatus' => navid_build_owner_status($store),
    ];
}

function navid_create_captcha_challenge_direct(): array
{
    $store = navid_load_store();
    $challenge = navid_prepare_http_manual_challenge($store);
    if (!is_array($challenge)) {
        dent_error('دریافت کپچای نوید انجام نشد.', 502);
    }
    if (trim((string) ($challenge['captchaDataUri'] ?? '')) === '') {
        dent_error('تصویر کپچای نوید در دسترس نیست. دوباره تلاش کن.', 502);
    }

    $store['state']['requiresReconnect'] = true;
    $store['state']['lastResult'] = 'reconnect-required';
    $store['state']['lastError'] = '';
    $store['session']['status'] = 'challenge';
    navid_save_store($store);

    return [
        'success' => true,
        'message' => 'کپچا دریافت شد.',
        'captchaDataUri' => (string) ($challenge['captchaDataUri'] ?? ''),
        'expiresAt' => (string) ($challenge['expiresAt'] ?? ''),
        'ownerStatus' => navid_build_owner_status($store),
    ];
}

function navid_complete_captcha_challenge(string $captchaCode): array
{
    $code = strtoupper(trim($captchaCode));
    $code = preg_replace('/[^A-Z0-9]/', '', $code) ?? '';
    if (strlen($code) < 4) {
        dent_error('کد کپچا نامعتبر است.', 422);
    }

    $store = navid_load_store();
    $challenge = navid_get_challenge($store);
    if (!is_array($challenge)) {
        dent_error('چالش کپچای فعالی وجود ندارد.', 422);
    }

    $expiresAt = strtotime((string) ($challenge['expiresAt'] ?? ''));
    if ($expiresAt !== false && $expiresAt < time()) {
        navid_clear_challenge($store);
        navid_save_store($store);
        dent_error('زمان اعتبار کپچا تمام شده است. دوباره کپچا بگیر.', 422);
    }

    $credentials = navid_get_credentials($store);
    if ($credentials === null) {
        dent_error('نام کاربری/رمز نوید ذخیره نشده است.', 422);
    }

    $loginUrl = navid_normalize_login_url((string) ($challenge['loginUrl'] ?? ($store['config']['loginUrl'] ?? '')));
    $token = trim((string) ($challenge['requestVerificationToken'] ?? ''));
    $cookies = is_array($challenge['cookies'] ?? null) ? $challenge['cookies'] : [];
    if ($token === '') {
        dent_error('توکن چالش کپچا معتبر نیست.', 422);
    }

    $submit = navid_submit_login(
        $loginUrl,
        $cookies,
        $token,
        (string) $credentials['username'],
        (string) $credentials['password'],
        $code
    );
    if (empty($submit['success'])) {
        dent_error((string) ($submit['message'] ?: 'ورود با کپچا انجام نشد.'), 422);
    }

    $mergedCookies = navid_merge_cookies($cookies, $submit['cookies'] ?? []);
    $nextUrl = trim((string) ($submit['nextUrl'] ?? ''));
    if ($nextUrl !== '') {
        $origin = navid_origin_from_login_url($loginUrl);
        $final = navid_http_request('GET', navid_absolute_url($origin, $nextUrl), [
            'cookies' => $mergedCookies,
            'origin' => $origin,
            'referer' => $loginUrl,
        ]);
        $mergedCookies = navid_merge_cookies($mergedCookies, $final['cookies'] ?? []);
    }

    $dashboard = navid_fetch_dashboard_payload($mergedCookies, $loginUrl);
    $mergedCookies = navid_merge_cookies($mergedCookies, $dashboard['cookies'] ?? []);

    navid_set_session_cookies($store, $mergedCookies);
    navid_clear_challenge($store);
    $store['state']['requiresReconnect'] = false;
    $store['state']['lastError'] = '';
    if (!empty($dashboard['success']) && is_array($dashboard['payload'] ?? null)) {
        $store['session']['status'] = 'active';
        $store['session']['lastValidatedAt'] = dent_iso_now();
    }
    navid_save_store($store);

    return [
        'success' => true,
        'message' => 'اتصال نوید با کپچای دستی انجام شد.',
        'ownerStatus' => navid_build_owner_status($store),
    ];
}

function navid_create_captcha_challenge_browser(): array
{
    $store = navid_load_store();
    $loginUrl = navid_normalize_login_url((string) ($store['config']['loginUrl'] ?? ''));
    $browserResult = navid_browser_bridge([
        'action' => 'create_challenge',
        'loginUrl' => $loginUrl,
    ]);
    if (empty($browserResult['success'])) {
        $errorCode = (string) ($browserResult['error'] ?? '');
        if (navid_browser_error_supports_http_fallback($errorCode)) {
            navid_log('warning', 'challenge_browser_http_fallback', [
                'error' => $errorCode,
                'message' => (string) ($browserResult['message'] ?? ''),
            ]);
            return navid_create_captcha_challenge_direct();
        }
        dent_error((string) ($browserResult['message'] ?? 'دریافت کپچای نوید انجام نشد.'), 502);
    }

    navid_store_manual_challenge($store, $browserResult);
    $store['state']['requiresReconnect'] = true;
    $store['state']['lastResult'] = 'reconnect-required';
    $store['state']['lastError'] = '';
    $store['session']['status'] = 'challenge';
    navid_save_store($store);

    $challenge = navid_get_challenge($store) ?: [];
    return [
        'success' => true,
        'message' => 'کپچا دریافت شد.',
        'captchaDataUri' => (string) ($browserResult['captchaDataUri'] ?? ''),
        'expiresAt' => (string) ($challenge['expiresAt'] ?? ''),
        'ownerStatus' => navid_build_owner_status($store),
    ];
}

function navid_complete_captcha_challenge_browser(string $captchaCode): array
{
    $code = strtoupper(trim($captchaCode));
    $code = preg_replace('/[^A-Z0-9]/', '', $code) ?? '';
    if (strlen($code) < 4) {
        dent_error('کد کپچا نامعتبر است.', 422);
    }

    $store = navid_load_store();
    $challenge = navid_get_challenge($store);
    if (!is_array($challenge)) {
        dent_error('چالش کپچای فعالی وجود ندارد.', 422);
    }
    if (is_array($challenge['cookies'] ?? null) && !is_array($challenge['storageState'] ?? null)) {
        return navid_complete_captcha_challenge($captchaCode);
    }

    $expiresAt = strtotime((string) ($challenge['expiresAt'] ?? ''));
    if ($expiresAt !== false && $expiresAt < time()) {
        navid_clear_challenge($store);
        navid_save_store($store);
        dent_error('زمان اعتبار کپچا تمام شده است. دوباره کپچا بگیر.', 422);
    }

    $credentials = navid_get_credentials($store);
    if ($credentials === null) {
        dent_error('نام کاربری/رمز نوید ذخیره نشده است.', 422);
    }

    $loginUrl = navid_normalize_login_url((string) ($challenge['loginUrl'] ?? ($store['config']['loginUrl'] ?? '')));
    $browserResult = navid_browser_bridge([
        'action' => 'complete_challenge',
        'loginUrl' => $loginUrl,
        'username' => (string) $credentials['username'],
        'password' => (string) $credentials['password'],
        'captchaCode' => $code,
        'challengeState' => [
            'loginUrl' => $loginUrl,
            'requestVerificationToken' => (string) ($challenge['requestVerificationToken'] ?? ''),
        ],
        'storageState' => is_array($challenge['storageState'] ?? null) ? $challenge['storageState'] : null,
    ]);

    if (empty($browserResult['success'])) {
        $errorCode = (string) ($browserResult['error'] ?? '');
        if (navid_browser_error_supports_http_fallback($errorCode)) {
            navid_log('warning', 'challenge_complete_http_fallback', [
                'error' => $errorCode,
                'message' => (string) ($browserResult['message'] ?? ''),
            ]);
            return navid_complete_captcha_challenge($captchaCode);
        }
        if ($errorCode === 'captcha_invalid') {
            navid_store_manual_challenge($store, $browserResult);
            $store['state']['requiresReconnect'] = true;
            $store['state']['lastResult'] = 'reconnect-required';
            $store['state']['lastError'] = (string) ($browserResult['message'] ?? 'کد کپچا اشتباه است.');
            $store['session']['status'] = 'challenge';
            navid_save_store($store);
            dent_error((string) ($browserResult['message'] ?? 'کد کپچا اشتباه است.'), 422, [
                'captchaDataUri' => (string) ($browserResult['captchaDataUri'] ?? ''),
                'expiresAt' => (string) ((navid_get_challenge($store) ?: [])['expiresAt'] ?? ''),
                'ownerStatus' => navid_build_owner_status($store),
            ]);
        }

        if ($errorCode === 'credentials_invalid') {
            navid_clear_challenge($store);
            navid_clear_session_cookies($store);
            $store['state']['requiresReconnect'] = false;
            $store['state']['lastResult'] = 'credentials-invalid';
            $store['state']['lastError'] = (string) ($browserResult['message'] ?? 'نام کاربری یا رمز نوید نادرست است.');
            $store['session']['status'] = 'invalid';
            navid_save_store($store);
            dent_error((string) ($browserResult['message'] ?? 'نام کاربری یا رمز نوید نادرست است.'), 422, [
                'ownerStatus' => navid_build_owner_status($store),
            ]);
        }

        dent_error((string) ($browserResult['message'] ?? 'اتصال مجدد نوید انجام نشد.'), 422);
    }

    return navid_sync_store_from_browser_result($store, $browserResult, $loginUrl, microtime(true));
}

function navid_import_browser_snapshot(array $browserResult): array
{
    if (empty($browserResult['success'])) {
        dent_error('اسنپ‌شات نوید معتبر نیست.', 422);
    }

    $storageState = is_array($browserResult['storageState'] ?? null) ? $browserResult['storageState'] : null;
    $courses = is_array($browserResult['courses'] ?? null) ? $browserResult['courses'] : null;
    if ($storageState === null || $courses === null) {
        dent_error('داده‌ی اسنپ‌شات نوید ناقص است.', 422);
    }

    $store = navid_load_store();
    $config = is_array($store['config'] ?? null) ? $store['config'] : [];
    $loginUrl = navid_normalize_login_url((string) ($config['loginUrl'] ?? ''));
    $store['state']['lastSyncAt'] = dent_iso_now();
    $store['state']['lastResult'] = 'running';
    $store['state']['lastFailedCourses'] = 0;

    return navid_sync_store_from_browser_result($store, $browserResult, $loginUrl, microtime(true));
}

function navid_daily_timezone(): DateTimeZone
{
    $name = trim((string) (getenv('DENT_NAVID_DAILY_TIMEZONE') ?: 'Asia/Tehran'));
    try {
        return new DateTimeZone($name);
    } catch (Throwable $exception) {
        return new DateTimeZone('Asia/Tehran');
    }
}

function navid_daily_date(): string
{
    return (new DateTimeImmutable('now', navid_daily_timezone()))->format('Y-m-d');
}

function navid_daily_validate_date(string $date): string
{
    $date = trim($date);
    $today = navid_daily_date();
    if ($date === '') {
        return $today;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || !hash_equals($today, $date)) {
        dent_error('تاریخ اجرای روزانه نوید معتبر نیست.', 409, ['code' => 'NAVID_DAILY_DATE_MISMATCH']);
    }
    return $date;
}

function navid_daily_public_status(array $store): array
{
    $automation = is_array($store['automation'] ?? null) ? $store['automation'] : [];
    $challenge = navid_get_challenge($store);
    return [
        'date' => (string) ($automation['dailyDate'] ?? ''),
        'challengePending' => navid_challenge_is_active($challenge)
            && trim((string) ($challenge['captchaDataUri'] ?? '')) !== '',
        'challengeExpiresAt' => (string) ($automation['challengeExpiresAt'] ?? ''),
        'completedAt' => (string) ($automation['completedAt'] ?? ''),
        'lastResult' => (string) ($automation['lastResult'] ?? ''),
    ];
}

function navid_daily_start(string $requestedDate = '', bool $refresh = false): array
{
    $date = navid_daily_validate_date($requestedDate);
    $store = navid_load_store();
    $automation = is_array($store['automation'] ?? null) ? $store['automation'] : [];
    if ((string) ($automation['dailyDate'] ?? '') === $date
        && trim((string) ($automation['completedAt'] ?? '')) !== ''
    ) {
        return [
            'success' => true,
            'status' => 'already-completed',
            'message' => 'بررسی امروز نوید قبلاً انجام شده است.',
            'daily' => navid_daily_public_status($store),
        ];
    }

    $challenge = navid_get_challenge($store);
    if (!$refresh
        && (string) ($automation['dailyDate'] ?? '') === $date
        && navid_challenge_is_active($challenge)
        && trim((string) ($challenge['captchaDataUri'] ?? '')) !== ''
    ) {
        return [
            'success' => true,
            'status' => 'challenge-ready',
            'message' => 'کپچای امروز نوید آماده است.',
            'captchaDataUri' => (string) ($challenge['captchaDataUri'] ?? ''),
            'expiresAt' => (string) ($challenge['expiresAt'] ?? ''),
            'daily' => navid_daily_public_status($store),
        ];
    }

    $result = navid_create_captcha_challenge_browser();
    $store = navid_load_store();
    $store['automation'] = array_merge(navid_default_store()['automation'], [
        'dailyDate' => $date,
        'challengeIssuedAt' => dent_iso_now(),
        'challengeExpiresAt' => (string) ($result['expiresAt'] ?? ''),
        'completedAt' => '',
        'lastResult' => 'challenge-ready',
    ]);
    navid_save_store($store);

    return [
        'success' => true,
        'status' => 'challenge-ready',
        'message' => 'کپچای امروز نوید آماده است.',
        'captchaDataUri' => (string) ($result['captchaDataUri'] ?? ''),
        'expiresAt' => (string) ($result['expiresAt'] ?? ''),
        'daily' => navid_daily_public_status($store),
    ];
}

function navid_daily_complete(string $requestedDate, string $captchaCode): array
{
    $date = navid_daily_validate_date($requestedDate);
    $store = navid_load_store();
    $automation = is_array($store['automation'] ?? null) ? $store['automation'] : [];
    if ((string) ($automation['dailyDate'] ?? '') !== $date) {
        dent_error('برای امروز کپچای فعالی ثبت نشده است.', 409, ['code' => 'NAVID_DAILY_CHALLENGE_MISSING']);
    }
    if (trim((string) ($automation['completedAt'] ?? '')) !== '') {
        return [
            'success' => true,
            'status' => 'already-completed',
            'message' => 'بررسی امروز نوید قبلاً انجام شده است.',
            'daily' => navid_daily_public_status($store),
        ];
    }

    $completed = navid_complete_captcha_challenge_browser($captchaCode);
    $sync = !empty($completed['summary']) ? $completed : navid_sync_auto(true);
    if (empty($sync['success'])) {
        return $sync;
    }

    $store = navid_load_store();
    $store['automation'] = array_merge(navid_default_store()['automation'], [
        'dailyDate' => $date,
        'challengeIssuedAt' => (string) ($automation['challengeIssuedAt'] ?? ''),
        'challengeExpiresAt' => '',
        'completedAt' => dent_iso_now(),
        'lastResult' => (string) ($sync['status'] ?? 'ok'),
    ]);
    navid_save_store($store);

    return [
        'success' => true,
        'status' => 'completed',
        'message' => 'بررسی روزانه نوید انجام شد.',
        'summary' => is_array($sync['summary'] ?? null) ? $sync['summary'] : [],
        'daily' => navid_daily_public_status($store),
    ];
}

function navid_feed_payload(bool $ownerView): array
{
    $store = navid_load_store();

    // Opportunistic sync when due; this keeps data fresh even without explicit owner action.
    if (navid_sync_due($store)) {
        navid_sync_auto(false);
        $store = navid_load_store();
    }

    $assignments = is_array($store['snapshot']['assignments'] ?? null) ? array_values($store['snapshot']['assignments']) : [];
    $assignments = navid_assignments_sorted($assignments);
    if (!empty($assignments)) {
        notifications_enqueue_navid_deadline_reminders($assignments);
    }
    $updates = is_array($store['updates'] ?? null) ? array_slice($store['updates'], 0, 40) : [];
    $credentials = navid_get_credentials($store);
    $enabled = !empty($store['config']['enabled']);
    $actionRequired = navid_sync_action_required(
        $credentials,
        is_array($store['state'] ?? null) ? $store['state'] : [],
        $enabled
    );

    $payload = [
        'currentAssignments' => array_slice($assignments, 0, 120),
        'updates' => $updates,
        'publicStatus' => [
            'lastSyncAt' => (string) ($store['state']['lastSyncAt'] ?? ''),
            'lastSuccessAt' => (string) ($store['state']['lastSuccessAt'] ?? ''),
            'lastResult' => (string) ($store['state']['lastResult'] ?? ''),
            'lastFailedCourses' => (int) ($store['state']['lastFailedCourses'] ?? 0),
            'lastError' => (string) ($store['state']['lastError'] ?? ''),
            'requiresReconnect' => !empty($store['state']['requiresReconnect']),
            'enabled' => $enabled,
            'hasCredentials' => $credentials !== null,
            'actionRequired' => $actionRequired,
            'credentialsMissing' => $actionRequired === 'save-credentials',
            'credentialsInvalid' => $actionRequired === 'update-credentials',
        ],
    ];

    if ($ownerView) {
        $payload['ownerStatus'] = navid_build_owner_status($store);
    }

    return [
        'success' => true,
        'message' => '',
        'data' => $payload,
    ];
}
