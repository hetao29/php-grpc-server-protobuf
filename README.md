# php-grpc-server-protobuf 2.0.1
A Swoole gRPC server and coroutine client with Protobuf and JSON support.
Binary HTTP/2 supports unary RPCs and all three streaming RPC forms. JSON
requests use HTTP/1 or HTTP/2 and support unary RPCs.

This checkout is version **2.0.1**, pending publication. See the [Chinese manual](MANUAL.md),
[1.x upgrade guide](UPGRADE.md), [changelog](CHANGELOG.md), and
[runnable examples](samples/README.md). The matching interface generator is
`php-grpc-server-protobuf-plugin` **2.0.0**.

# Usage

1. after version 2.0.1 is published, install it with Composer

```bash
composer require "hetao29/php-grpc-server-protobuf:2.0.1"
```

The source version declaration does not publish a package or create a Git tag.
Version 2.0.1 restores large unary responses and preserves error status headers
through proxies; see the [changelog](CHANGELOG.md). Protocols and generated
interfaces are unchanged, so the interface generator remains version 2.0.0.

The runnable examples currently lock published version 2.0.0 under
`samples/libs/vendor`. After 2.0.1 is published, update that dependency with
`composer require "hetao29/php-grpc-server-protobuf:2.0.1"` from `samples/libs`.
Until then, the examples exercise their locked 2.0.0 package, not this checkout.
Composer installation requires both `ext-swoole` and `ext-grpc`; only
`client/native.php` uses native grpc for RPC transport. Protobuf's PHP runtime
is required; its C extension is optional. Streaming requires the patched Swoole
capabilities described below, not merely the presence of the extension.

The package declares `GRpcServer`, `GRpcServerInterface`, `GRpcClient` and
`GRpcStream` in Composer's `autoload.classmap`; they load on demand. PHP entry
scripts include only Composer's `vendor/autoload.php`.

Declare application service and generated-message namespaces in the consuming
project's `composer.json`, then run `composer dump-autoload`:

```json
{
  "autoload": {
    "psr-4": {
      "Test\\Helloworld\\": ["app/Test/Helloworld/", "proto_generated/Test/Helloworld/"],
      "GPBMetadata\\": "proto_generated/GPBMetadata/"
    }
  }
}
```

The runnable examples declare the official package dependency, application
namespaces and helper functions in `samples/libs/composer.json`; see
[samples/README.md](samples/README.md).

2. register the request handler on a Swoole server

```php
<?php
require dirname(__DIR__) . '/vendor/autoload.php';

$server = new Swoole\Http\Server('127.0.0.1', 50051, SWOOLE_BASE);
$server->set([
    'open_http2_protocol' => true,
    'http2_stream_mode' => true,
    'http_compression' => false,
    'package_max_length' => GRpcServer::MAX_MESSAGE_LENGTH + 5,
]);
$server->on('request', [GRpcServer::class, 'serve']);
$server->start();
```

`serve(Swoole\Http\Request $request, Swoole\Http\Response $response, ...$args)`
is the single dispatch entry point. It reads headers and request bytes, validates
HTTP method and content type, resolves the service, passes `$args` to its
constructor, and finishes the response. JSON and binary RPCs share service
resolution and invocation. No surrounding response or exception handler is needed.

Only POST is accepted (otherwise HTTP 405). Supported content types are
`application/json`, `application/grpc` and `application/grpc+proto` (otherwise
HTTP 415); binary requests require HTTP/2 (otherwise HTTP 426). JSON bodies are
limited to `GRpcServer::MAX_JSON_LENGTH` (2 MiB). Dispatch failures return a safe
gRPC status 13: HTTP 500 and a JSON error for JSON, final trailers for binary.
Binary failures also set ordinary `grpc-status` and safe `grpc-message` headers
when response headers are still writable, so empty-body errors retain their
status through proxies that discard trailers after `Content-Length: 0`.
Failures after data has been sent still rely on final trailers.
Only the exception class and code are logged; messages and service data are not
returned. Callers must read the final gRPC status even after receiving messages.

The former `run($uri, $data, ...)` and `getRawData()` entry points have been
removed. Migrate callers to `serve($request, $response, ...$constructorArgs)`;
PHP-FPM dispatch and the former `samples/www/index.php` entry are retired.
Application-specific authentication or body transforms must be handled by the
application's transport adapter before dispatch.

# Write App Services 

1. proto and genproto to php files

```bash
cd samples
make pull
make proto
```

2. write gRPC Server in services dir like helloworld

```php
<?php
namespace Test\Helloworld;
class Greeter implements GreeterInterface{
	/** @param HelloRequest $request Greeting request message. */
	public function SayHello(HelloRequest $request) : HelloReply{
		$reply = new HelloReply();
		$reply->setMessage("Hello2, ".$request->getName()."!");
		return $reply;
	}
}

```

3. run the server and clients from [samples](samples/README.md), or call JSON

```bash
curl -H 'content-type: application/json' -d '{"name":"Tony"}' \
  http://127.0.0.1:50051/Test.Helloworld.Greeter/SayHello
```

# Unary request validation

Binary requests must contain exactly one length-prefixed Protobuf message. The
decoder rejects incomplete headers, flags other than 0 or 1, truncated payloads,
and extra bytes or frames. A zero-length message with its five-byte frame header
is valid; an empty HTTP body is not a binary message.

Flag 0 means uncompressed even when the request advertises `grpc-encoding: gzip`.
Flag 1 requires `grpc-encoding: gzip`. Gzip is decoded incrementally with size
checks; truncated streams, checksum failures, concatenated members and trailing
data are rejected. These single-message checks apply to unary input; streaming
input is parsed one message at a time by `GRpcStream`.

`GRpcServer::MAX_MESSAGE_LENGTH` defaults to 16 MiB, excluding the five-byte frame
header, and bounds both the wire payload and decoded payload. Direct decoder
callers may pass a positive byte limit as the optional fifth argument to
`decode()`. JSON decoding retains its existing format and unknown-field behavior.
Binary framing and decompression are validated before unary service construction.
Streaming input is decoded as the service consumes it. `serve()` catches all
`Throwable` failures and finishes with the safe status described above.

# RPC routing conventions

Dispatch follows the service/interface convention, with path and method
validation. No namespace allowlist is required.
The `setServiceNamespaces()` configuration introduced in 1.7.8 has been removed;
remove those calls when upgrading.

Requests must use the exact `/Package.Service/Method` path, with ASCII identifier
segments and no query, extra slash, percent escape or backslash. The path is
validated before deriving or autoloading the service class. For example,
`/Example.Greeter/SayHello` resolves to `Example\Greeter::SayHello`, and that
service must implement `Example\GreeterInterface` by default.

The interface suffix can be configured once at startup, before serving requests:

```php
GRpcServer::setInterfaceSuffix('Contract');
// Example\Greeter must now implement Example\GreeterContract.
```

The default is `Interface`. The suffix must be a nonempty ASCII identifier;
invalid values throw `InvalidArgumentException` without changing the setting.
This configures the implemented interface name, not the service path or class.

The dispatcher requires a public instance method with a named class type for
its first parameter. It decodes the request (or supplies `GRpcStream`), then
constructs the service using arguments after the request and response arguments
of `serve()`. Private, protected and static methods are rejected. Public helper
methods can still be dispatched even when absent from the interface; `NAME`
constants are not checked. Treat service classes and their public instance
methods as RPC entry points. Authentication and authorization remain the
application's responsibility.

## Coroutine streaming RPCs

Binary HTTP/2 supports client streaming, server streaming and bidirectional
streaming with the Swoole `http2_stream_mode` / `Request::recv()` patch. Enable
it explicitly on either `Swoole\Http\Server` (with coroutines enabled) or
`Swoole\Coroutine\Http\Server`:

```php
$server->set(['open_http2_protocol' => true, 'http2_stream_mode' => true]);
$server->handle('/', [GRpcServer::class, 'serve']);
```

For the event server, register the same callback with `on('request', ...)`.
In stream mode, HTTP/2 callbacks start after headers and read the body with
`recv()`; `rawContent()` is not a whole-request snapshot. HTTP/1 is unchanged.
The same `serve()` callback collects HTTP/2 JSON bodies within the size limit.
JSON is unary; streaming uses binary gRPC. Whole-body AES is an application
transport concern and is not implemented by this library.

The interface generator emits these signatures from the `.proto` stream flags.
The sample Makefile generates code using Docker Hub image
`hetao29/docker-protoc:36.2.1` (protoc 36.2 and interface plugin 2.0.0).
Do not edit generated files. Existing unary interface signatures are unchanged.

```php
// rpc Expand(Request) returns (stream Reply)
public function Expand(Request $request): iterable {
    yield new Reply(['value' => $request->getValue()]);
    yield new Reply(['value' => 'next']);
}

// rpc Collect(stream Request) returns (Reply)
public function Collect(GRpcStream $request): Reply {
    $values = [];
    while (($message = $request->recv(Request::class)) !== null) {
        $values[] = $message->getValue();
    }
    return new Reply(['value' => implode(',', $values)]);
}

// rpc Chat(stream Request) returns (stream Reply)
public function Chat(GRpcStream $request): iterable {
    while (($message = $request->recv(Request::class)) !== null) {
        yield new Reply(['value' => $message->getValue()]);
    }
}
```

A single client API handles all RPC forms inside a Swoole coroutine:

```php
$call = new GRpcClient('127.0.0.1', 50051, '/Example.Service/Chat', Reply::class);
try {
    $call->send(new Request(['value' => 'first']));
    $reply = $call->recv(); // The server can reply before request half-close.
    $call->send(new Request(['value' => 'second']));
    $call->closeSend();
    while (($reply = $call->recv()) !== null) {
        // Consume remaining responses.
    }
} finally {
    $call->close();
}
```

`recv()` returns a Protobuf message, or `null` after clean final status 0.
Transport, deadline, malformed messages and unsuccessful/missing final status
throw exceptions; always read through final status before claiming success.
`closeSend()` only ends the sending half; `close()` cancels the call. One call
allows one application reader and one application writer to run concurrently;
do not issue multiple simultaneous reads or multiple simultaneous writes on the same call.
Each call owns one HTTP/2 connection and one receiver coroutine, with a bounded incoming
queue; there is no shared static channel or automatic retry. The constructor's
optional timeout defaults to five seconds and covers the call, not each message.
TLS is selected by the optional sixth argument; metadata is the seventh argument
and contains HTTP/2 wire header values (binary metadata must be base64 encoded).

Message framing accepts fragmented/coalesced HTTP/2 DATA and independent gzip
messages. Wire and decoded messages are each limited to 16 MiB by default;
limits are per message, so streams may transfer more than 16 MiB in total.
Incoming limits and outgoing checks are independent. A server unary response
(single message input and output) retains the legacy behavior without a 16 MiB
outgoing cap. Streaming replies and client sends retain their default cap.
`GRpcStream::send($message, $enforceSizeLimit = true)` can skip the outgoing check
for that call only; receive limits and subsequent sends are unchanged. Receiving
clients must permit the expected response size.
The transport returns stream receive credit as the application consumes bytes.
Timeouts, cancellation and connection closure wake blocked readers/writers.
