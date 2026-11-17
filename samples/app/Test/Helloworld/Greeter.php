<?php
namespace Test\Helloworld;

class Greeter implements GreeterInterface {
    /** @param HelloRequest $request One greeting request. */
    public function SayHello(HelloRequest $request): HelloReply {
        return new HelloReply(['message' => 'Hello, ' . $request->getName() . '!']);
    }

    /** @param HelloRequest $request Legacy echo request. */
    public function EchoAbort(HelloRequest $request): HelloReply {
        return new HelloReply(['message' => 'Hello, ' . $request->getName() . '!']);
    }

    /**
     * @param ServerStreamingEchoRequest $request Message, count and delay between replies.
     * @return iterable<ServerStreamingEchoResponse> One reply at a time.
     */
    public function ServerStreamingEcho(ServerStreamingEchoRequest $request): iterable {
        $count = $request->getMessageCount() ?: 3;
        $interval = $request->getMessageInterval();
        if ($count < 1 || $count > 100 || $interval < 0 || $interval > 1000) {
            throw new \InvalidArgumentException('Use count 1-100 and interval 0-1000 milliseconds.');
        }
        for ($i = 1; $i <= $count; $i++) {
            yield new ServerStreamingEchoResponse(['message' => $request->getMessage() . " #{$i}"]);
            if ($interval > 0 && $i < $count) {
                \Swoole\Coroutine::sleep($interval / 1000);
            }
        }
    }

    /** @param \GRpcStream $request Incoming HelloRequest messages, ending at client half-close. */
    public function ClientStreamingEcho(\GRpcStream $request): HelloReply {
        $names = [];
        while (($message = $request->recv(HelloRequest::class)) !== null) {
            $names[] = $message->getName();
        }
        return new HelloReply(['message' => 'Received ' . count($names) . ': ' . implode(', ', $names)]);
    }

    /**
     * @param \GRpcStream $request Incoming HelloRequest messages.
     * @return iterable<HelloReply> Each reply is sent before waiting for another request.
     */
    public function BidiStreamingEcho(\GRpcStream $request): iterable {
        while (($message = $request->recv(HelloRequest::class)) !== null) {
            yield new HelloReply(['message' => 'Echo: ' . $message->getName()]);
        }
    }
}
