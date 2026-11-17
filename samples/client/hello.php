<?php
require dirname(__DIR__) . '/libs/vendor/autoload.php';

use Test\Helloworld\HelloReply;
use Test\Helloworld\HelloRequest;

runSample($argv, static function (string $host, int $port): void {
    $call = new GRpcClient($host, $port, '/Test.Helloworld.Greeter/SayHello', HelloReply::class);
    try {
        $call->send(new HelloRequest(['name' => 'Tony']));
        $call->closeSend();
        $reply = $call->recv();
        if ($reply === null) {
            throw new RuntimeException('Missing unary response.');
        }
        echo $reply->getMessage() . "\n";
        if ($call->recv() !== null) {
            throw new RuntimeException('Unexpected extra unary response.');
        }
        echo "grpc-status: 0\n";
    } finally {
        $call->close();
    }
});
