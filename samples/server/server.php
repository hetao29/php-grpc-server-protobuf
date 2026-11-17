<?php
require dirname(__DIR__) . '/libs/vendor/autoload.php';

if (!method_exists(Swoole\Http\Request::class, 'recv')) {
    fwrite(STDERR, "Load the Swoole extension with http2_stream_mode / Request::recv() support.\n");
    exit(1);
}

try {
    [$host, $port] = sampleTarget($argv);
    $mode = $argv[3] ?? 'coroutine';
    if (!in_array($mode, ['coroutine', 'event'], true)) {
        throw new InvalidArgumentException('Server mode must be coroutine or event.');
    }
    $start = static function () use ($host, $port, $mode): void {
        $server = $mode === 'event'
            ? new Swoole\Http\Server($host, $port, SWOOLE_BASE)
            : new Swoole\Coroutine\Http\Server($host, $port);
        $server->set([
            'open_http2_protocol' => true,
            'http2_stream_mode' => true,
            'http_compression' => false,
            'package_max_length' => GRpcServer::MAX_MESSAGE_LENGTH + 5,
        ]);
        if ($mode === 'event') {
            $server->on('request', [GRpcServer::class, 'serve']);
        } else {
            $server->handle('/', [GRpcServer::class, 'serve']);
        }
        echo "gRPC {$mode} server: {$host}:{$port}\n";
        $server->start();
    };
    if ($mode === 'event') {
        $start();
    } else {
        Swoole\Coroutine\run($start);
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
