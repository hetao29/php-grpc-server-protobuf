<?php
require dirname(__DIR__) . '/libs/vendor/autoload.php';

use Test\Helloworld\GreeterClient;
use Test\Helloworld\HelloRequest;
use Test\Helloworld\ServerStreamingEchoRequest;

/** @param stdClass $status Final native gRPC status, including code and details. */
function checkNativeStatus(stdClass $status): void {
    if ($status->code !== Grpc\STATUS_OK) {
        throw new RuntimeException($status->details, $status->code);
    }
}

try {
    if (!extension_loaded('grpc')) {
        throw new RuntimeException('This example requires the native grpc PHP extension.');
    }
    [$host, $port] = sampleTarget($argv);
    $client = new GreeterClient("{$host}:{$port}", [
        'credentials' => Grpc\ChannelCredentials::createInsecure(),
    ]);
    $options = ['timeout' => 5000000]; // Native call options use microseconds.
    try {
        [$reply, $status] = $client->SayHello(new HelloRequest(['name' => 'Tony']), [], $options)->wait();
        checkNativeStatus($status);
        if ($reply === null) {
            throw new RuntimeException('Missing unary response.');
        }
        echo 'unary: ' . $reply->getMessage() . "\n";

        $call = $client->ServerStreamingEcho(new ServerStreamingEchoRequest([
            'message' => 'native stream', 'message_count' => 3, 'message_interval' => 100,
        ]), [], $options);
        foreach ($call->responses() as $reply) {
            echo 'server-stream: ' . $reply->getMessage() . "\n";
        }
        checkNativeStatus($call->getStatus());

        $call = $client->ClientStreamingEcho([], $options);
        foreach (['Alice', 'Bob', 'Carol'] as $name) {
            $call->write(new HelloRequest(['name' => $name]));
        }
        [$reply, $status] = $call->wait(); // wait() half-closes this native client-stream call.
        checkNativeStatus($status);
        if ($reply === null) {
            throw new RuntimeException('Missing client-stream response.');
        }
        echo 'client-stream: ' . $reply->getMessage() . "\n";

        $call = $client->BidiStreamingEcho([], $options);
        foreach (['Alice', 'Bob', 'Carol'] as $name) {
            echo "bidi send: {$name}\n";
            $call->write(new HelloRequest(['name' => $name]));
            $reply = $call->read();
            if ($reply === null) {
                throw new RuntimeException('Server ended before replying.');
            }
            echo 'bidi recv: ' . $reply->getMessage() . "\n";
        }
        $call->writesDone();
        if ($call->read() !== null) {
            throw new RuntimeException('Unexpected extra bidi response.');
        }
        checkNativeStatus($call->getStatus());
        echo "all native grpc-status: 0\n";
    } finally {
        $client->close();
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
