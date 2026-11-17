<?php

/** One coroutine HTTP/2 RPC, with independent send and receive halves. */
final class GRpcClient {
    private Swoole\Coroutine\Http2\Client $http;
    private Swoole\Coroutine\Channel $incoming;
    private GRpcStream $stream;
    private int $id;
    private bool $sendClosed = false;
    private bool $closed = false;
    private ?Throwable $error = null;
    private float $deadline;
    private ?int $status = null;
    private string $message = '';

    /**
     * @param string $host HTTP/2 server hostname.
     * @param int $port Server port.
     * @param string $path Full /package.Service/Method path.
     * @param string $responseClass Protobuf response class.
     * @param float $timeout Total RPC deadline in seconds, including connection setup.
     * @param bool $tls Enable TLS with the HTTP/2 client defaults.
     * @param array $metadata Additional request headers.
     */
    public function __construct(string $host, int $port, string $path, private string $responseClass,
                                float $timeout = 5, bool $tls = false, array $metadata = []) {
        if (!is_finite($timeout) || $timeout <= 0) {
            throw new InvalidArgumentException('RPC timeout must be positive');
        }
        $this->deadline = microtime(true) + $timeout;
        $this->http = new Swoole\Coroutine\Http2\Client($host, $port, $tls);
        $this->http->set(['timeout' => $timeout, 'http2_stream_mode' => true]);
        $this->incoming = new Swoole\Coroutine\Channel(8);
        if (!$this->http->connect()) {
            throw new RuntimeException('gRPC connect failed');
        }
        $request = new Swoole\Http2\Request();
        $request->method = 'POST';
        $request->path = $path;
        $request->headers = array_merge($metadata, [
            'content-type' => 'application/grpc', 'te' => 'trailers',
            'grpc-timeout' => (string) max(1, min(99999999, (int) ceil($this->remaining() * 1000))) . 'm',
            'grpc-accept-encoding' => 'gzip',
        ]);
        $request->pipeline = true;
        $request->usePipelineRead = true;
        $this->http->set(['timeout' => $this->remaining()]);
        $id = $this->http->send($request);
        if ($id === false) {
            $this->http->close();
            throw new RuntimeException('gRPC headers send failed');
        }
        $this->id = $id;
        $this->stream = new GRpcStream(function () {
            $chunk = $this->incoming->pop($this->remaining());
            if ($chunk === false) {
                throw $this->error ?? new RuntimeException('gRPC deadline or transport failure');
            }
            return $chunk;
        }, function (string $frame) {
            if ($this->sendClosed || $this->closed) {
                return false;
            }
            $this->http->set(['timeout' => $this->remaining()]);
            return $this->http->write($this->id, $frame);
        });
        Swoole\Coroutine::create(function () {
            try {
                do {
                    $response = $this->http->recv($this->remaining());
                    if ($response === false || $response->errCode ||
                        ($response->statusCode > 0 && $response->statusCode !== 200)) {
                        throw new RuntimeException('gRPC HTTP/2 transport failed');
                    }
                    $headers = array_merge($response->headers ?? [], $response->trailers ?? []);
                    if (isset($headers['grpc-encoding'])) {
                        $this->stream->setEncoding($headers['grpc-encoding']);
                    }
                    if (!$response->pipeline) {
                        $status = $response->trailers['grpc-status'] ??
                            ($response->statusCode === 200 && empty($response->data) ? ($headers['grpc-status'] ?? null) : null);
                        if ($status !== null) {
                            if (!preg_match('/\A(?:0|[1-9][0-9]*)\z/', $status) || strlen($status) > 2 || (int) $status > 16) {
                                throw new UnexpectedValueException('Invalid gRPC status');
                            }
                            $this->status = (int) $status;
                            $this->message = rawurldecode($headers['grpc-message'] ?? '');
                        }
                    }
                    $data = $response->data ?? '';
                    if ($data !== '' && !$this->incoming->push($data, $this->remaining())) {
                        throw new RuntimeException('gRPC response consumer stalled');
                    }
                } while ($response->pipeline);
                if ($this->status === null || $this->status !== 0) {
                    throw new RuntimeException($this->message ?: 'Missing or unsuccessful gRPC status', $this->status ?? 2);
                }
                $this->incoming->push('', $this->remaining());
            } catch (Throwable $e) {
                $this->error = $e;
            } finally {
                $this->incoming->close();
                $this->http->close();
            }
        });
    }

    /** @param object $message Protobuf request message. */
    public function send(object $message): void {
        try {
            $this->stream->send($message);
        } catch (Throwable $e) {
            $this->close();
            throw $e;
        }
    }

    /** @return object|null Next response, or null after successful final trailers. */
    public function recv(): ?object {
        try {
            return $this->stream->recv($this->responseClass);
        } catch (Throwable $e) {
            $this->close();
            throw $e;
        }
    }

    /** Half-close the request; responses remain readable. */
    public function closeSend(): void {
        if (!$this->sendClosed) {
            $this->sendClosed = true;
            if (!$this->http->write($this->id, '', true)) {
                throw new RuntimeException('gRPC half-close failed');
            }
        }
    }

    /** Cancel the call and release its dedicated HTTP/2 connection. */
    public function close(): void {
        if (!$this->closed) {
            $this->closed = true;
            $this->incoming->close();
            $this->http->close();
        }
    }

    /** Remaining total budget, never an infinite Swoole timeout. */
    private function remaining(): float {
        $remaining = $this->deadline - microtime(true);
        if ($remaining <= 0) {
            throw new RuntimeException('gRPC deadline exceeded', 4);
        }
        return $remaining;
    }
}
