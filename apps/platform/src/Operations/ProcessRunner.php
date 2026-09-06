<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

use RuntimeException;

final class ProcessRunner
{
    /** @param non-empty-list<string> $command */
    public static function run(array $command, ?string $stdoutPath = null, ?string $stdinPath = null): string
    {
        $descriptors = [
            0 => $stdinPath === null ? ['pipe', 'r'] : ['file', $stdinPath, 'rb'],
            1 => $stdoutPath === null ? ['pipe', 'w'] : ['file', $stdoutPath, 'wb'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('Operational process could not be started.');
        }

        if ($stdinPath === null) {
            fclose($pipes[0]);
        }
        $stdout = $stdoutPath === null ? stream_get_contents($pipes[1]) : '';
        if ($stdoutPath === null) {
            fclose($pipes[1]);
        }
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $detail = trim(is_string($stderr) ? $stderr : '');
            throw new RuntimeException('Operational process failed' . ($detail === '' ? '.' : ': ' . $detail));
        }

        return is_string($stdout) ? $stdout : '';
    }
}
