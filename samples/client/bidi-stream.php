<?php
require dirname(__DIR__) . '/libs/vendor/autoload.php';

use Test\Helloworld\HelloReply;
use Test\Helloworld\HelloRequest;

runSample($argv, static function (string $host, int $port): void {
    $call = new GRpcClient($host, $port, '/Test.Helloworld.Greeter/BidiStreamingEcho', HelloReply::class);
    try {
        foreach (['Alice', 'Bob', 'Carol'] as $name) {
            echo "send: {$name}\n";
            $call->send(new HelloRequest(['name' => $name]));
            $reply = $call->recv(); // The request stream is still open when this reply arrives.
            if ($reply === null) {
                throw new RuntimeException('Server ended before replying.');
            }
            echo 'recv: ' . $reply->getMessage() . "\n";
        }
        echo "closeSend\n";
        $call->closeSend();
        if ($call->recv() !== null) {
            throw new RuntimeException('Unexpected extra bidi response.');
        }
        echo "grpc-status: 0\n";
    } finally {
        $call->close();
    }
});
