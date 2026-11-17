<?php

/**
 * @param string[] $arguments CLI arguments: script, optional host, optional port.
 * @return array{string, int} Server host and validated TCP port.
 */
function sampleTarget(array $arguments): array {
    $host = $arguments[1] ?? '127.0.0.1';
    $port = filter_var($arguments[2] ?? 50051, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    if ($host === '' || $port === false) {
        throw new InvalidArgumentException('Usage: php script.php [host] [port:1-65535]');
    }
    return [$host, $port];
}

/**
 * @param string[] $arguments CLI arguments with optional host and port.
 * @param Closure $example Coroutine callback receiving the server host and port.
 */
function runSample(array $arguments, Closure $example): void {
    try {
        if (!method_exists(Swoole\Http\Request::class, 'recv')) {
            throw new RuntimeException('Load the patched Swoole extension; see samples/README.md.');
        }
        [$host, $port] = sampleTarget($arguments);
        Swoole\Coroutine\run(static function () use ($host, $port, $example): void {
            $example($host, $port);
        });
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . "\n");
        exit(1);
    }
}
