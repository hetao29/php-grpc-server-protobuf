<?php
require dirname(__DIR__) . '/libs/vendor/autoload.php';

use Test\Helloworld\ServerStreamingEchoRequest;
use Test\Helloworld\ServerStreamingEchoResponse;

runSample($argv, static function (string $host, int $port): void {
    $call = new GRpcClient($host, $port, '/Test.Helloworld.Greeter/ServerStreamingEcho', ServerStreamingEchoResponse::class);
    try {
        $call->send(new ServerStreamingEchoRequest([
            'message' => 'server stream', 'message_count' => 3, 'message_interval' => 100,
        ]));
        $call->closeSend();
        while (($reply = $call->recv()) !== null) {
            echo $reply->getMessage() . "\n";
        }
        echo "grpc-status: 0\n";
    } finally {
        $call->close();
    }
});
