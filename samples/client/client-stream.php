<?php
require dirname(__DIR__) . '/libs/vendor/autoload.php';

use Test\Helloworld\HelloReply;
use Test\Helloworld\HelloRequest;

runSample($argv, static function (string $host, int $port): void {
    $call = new GRpcClient($host, $port, '/Test.Helloworld.Greeter/ClientStreamingEcho', HelloReply::class);
    try {
        foreach (['Alice', 'Bob', 'Carol'] as $name) {
            echo "send: {$name}\n";
            $call->send(new HelloRequest(['name' => $name]));
        }
        $call->closeSend(); // The server can now finish collecting requests and return its single reply.
        $reply = $call->recv();
        if ($reply === null) {
            throw new RuntimeException('Missing client-stream response.');
        }
        echo $reply->getMessage() . "\n";
        if ($call->recv() !== null) {
            throw new RuntimeException('Unexpected extra client-stream response.');
        }
        echo "grpc-status: 0\n";
    } finally {
        $call->close();
    }
});
