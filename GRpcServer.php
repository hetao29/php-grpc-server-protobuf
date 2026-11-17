<?php
/*{{{LICENSE
+-----------------------------------------------------------------------+
|                             Php Grpc Server                           |
+-----------------------------------------------------------------------+
| This program is free software; you can redistribute it and/or modify  |
| it under the terms of the GNU General Public License as published by  |
| the Free Software Foundation. You should have received a copy of the  |
| GNU General Public License along with this program.  If not, see      |
| http://www.gnu.org/licenses/.                                         |
| Copyright (C) 2008-2009. All Rights Reserved.                         |
+-----------------------------------------------------------------------+
| Supports: https://github.com/hetao29/php-grpc-server-protobuf         |
+-----------------------------------------------------------------------+
}}}*/
/**
 * @package GRpcServer
 */
interface GRpcServerInterface{
}
final class GRpcServer{
	// Per-message limit, applied to both wire payload and decoded payload.
	public const MAX_MESSAGE_LENGTH = 16 * 1024 * 1024;
	public const MAX_JSON_LENGTH = 2 * 1024 * 1024;
	private static $interface_suffix = 'Interface';

	/** Set the suffix of the interface each service must implement; default "Interface". */
	public static function setInterfaceSuffix(string $suffix){
		if(!preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $suffix)){
			throw new InvalidArgumentException('Invalid interface suffix');
		}
		self::$interface_suffix = $suffix;
		return true;
	}

    /**
     * Dispatch unary JSON and binary unary/streaming RPCs and finish the response.
     * HTTP/2 requires http2_stream_mode; JSON remains a single-message contract.
     * @param Swoole\Http\Request $request Current transport request.
     * @param Swoole\Http\Response $response Current transport response.
     * @param mixed ...$args Arguments passed unchanged to the service constructor.
     */
    public static function serve(Swoole\Http\Request $request, Swoole\Http\Response $response, ...$args): void {
        if ($request->getMethod() !== 'POST') {
            $response->status(405);
            $response->header('allow', 'POST');
            $response->end();
            return;
        }
        $mediaType = strtolower(trim(explode(';', $request->header['content-type'] ?? '')[0]));
        $json = $mediaType === 'application/json';
        if (!$json && !in_array($mediaType, ['application/grpc', 'application/grpc+proto'], true)) {
            $response->status(415);
            $response->end();
            return;
        }
        $http2 = ($request->server['server_protocol'] ?? '') === 'HTTP/2';
        if (!$json && !$http2) {
            $response->status(426);
            $response->end('Use an HTTP/2 gRPC client.');
            return;
        }
        $response->header('content-type', $json ? 'application/json' : 'application/grpc');
        try {
            $uri = $request->server['request_uri'] ?? '';
            // Validate before deriving a class name or triggering an autoloader.
            if (!preg_match('/\A\/([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*)\/([A-Za-z_][A-Za-z0-9_]*)\z/', $uri, $route)) {
                throw new UnexpectedValueException('Invalid RPC route');
            }
            $ref = new ReflectionClass(str_replace('.', '\\', $route[1]));
            if (!$ref->implementsInterface($ref->getName() . self::$interface_suffix)) {
                throw new UnexpectedValueException('RPC service interface missing');
            }
            $method = $ref->getMethod($route[2]);
            $type = ($method->getParameters()[0] ?? null)?->getType();
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin() || !$method->isPublic() || $method->isStatic()) {
                throw new UnexpectedValueException('Invalid RPC request type');
            }
            $messageClass = $type->getName();
            $stream = null;
            if ($json) {
                if ($messageClass === GRpcStream::class || $method->isGenerator() || (string) $method->getReturnType() === 'iterable') {
                    throw new UnexpectedValueException('JSON supports unary RPCs only');
                }
                $input = self::decode($messageClass, self::readJson($request, $http2), 'json');
            } else {
                $stream = new GRpcStream(
                    fn() => $request->recv(),
                    fn(string $frame) => $response->write($frame),
                    $request->header['grpc-encoding'] ?? null
                );
                if ($messageClass === GRpcStream::class) {
                    $input = $stream;
                } else {
                    $input = $stream->recv($messageClass);
                    if ($input === null || $stream->recv($messageClass) !== null) {
                        throw new UnexpectedValueException('Expected exactly one gRPC request');
                    }
                }
            }
            $service = $ref->getConstructor() ? $ref->newInstanceArgs($args) : $ref->newInstanceWithoutConstructor();
            $output = $service->{$route[2]}($input);
            if ($json) {
                $body = self::encode($output, 'json');
                $response->header('grpc-status', '0');
                $response->end($body);
                return;
            }
            $body = '';
            if (is_iterable($output)) {
                foreach ($output as $message) {
                    $stream->send($message);
                }
            } elseif ($output !== null) {
                if ($messageClass === GRpcStream::class) {
                    $stream->send($output);
                } else {
                    // Finish unary data and trailers together; older Swoole closes write()+end() before trailers.
                    // Keep unary responses unbounded without relaxing incoming or streaming message limits.
                    $body = self::encode($output);
                }
            }
            $response->trailer('grpc-status', '0');
            $response->trailer('grpc-message', '');
            $response->end($body);
        } catch (Throwable $error) {
            // Log the failure category without exposing service data to clients or logs.
            error_log(get_class($error) . ' (' . $error->getCode() . ')');
            if ($response->isWritable()) {
                if ($json) {
                    $response->status(500);
                    $response->header('grpc-status', '13');
                    $response->end('{"code":13,"message":"Internal server error"}');
                } else {
                    // Empty responses may lose trailers through proxies that honor Content-Length: 0.
                    $response->header('grpc-status', '13');
                    $response->header('grpc-message', 'Internal server error');
                    $response->trailer('grpc-status', '13');
                    $response->trailer('grpc-message', 'Internal server error');
                    $response->end();
                }
            }
        }
    }

    /**
     * @param Swoole\Http\Request $request Current JSON transport request.
     * @param bool $http2 Whether to collect incremental HTTP/2 request bytes.
     * @return string Complete JSON body, limited to MAX_JSON_LENGTH bytes.
     */
    private static function readJson(Swoole\Http\Request $request, bool $http2): string {
        if (!$http2) {
            $body = $request->rawContent();
            if ($body === false || strlen($body) > self::MAX_JSON_LENGTH) {
                throw new LengthException('JSON request closed or exceeds size limit');
            }
            return $body;
        }
        $body = '';
        while (($chunk = $request->recv()) !== '') {
            if ($chunk === false || strlen($chunk) > self::MAX_JSON_LENGTH - strlen($body)) {
                throw new LengthException('JSON request closed or exceeds size limit');
            }
            $body .= $chunk;
        }
        return $body;
    }

	//https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md
	public static function encode($obj, $content_type=null){
		if($content_type=="json"){
			return $obj->serializeToJsonString();
		}else{
			$out= $obj->serializeToString();
			return pack("CN", 0, strlen($out)) . $out;
		}
	}

	/**
	 * @param class-string $className Protobuf message class to instantiate.
	 * @param string $body Complete gRPC frame or JSON message body.
	 * @param string|null $content_type Use 'json' for JSON; otherwise decode a binary frame.
	 * @param string|null $grpc_encoding Incoming compression; compressed frames require 'gzip'.
	 * @return object Decoded Protobuf message.
	 */
	public static function decode($className, string $body, $content_type=null, $grpc_encoding=null){
		if($content_type=="json"){
			$obj = new $className();
			$obj->mergeFromJsonString($body, $ignore_unknown=true);
			return $obj;
		}else{
			$body_length = strlen($body);
			if($body_length < 5){
				throw new UnexpectedValueException('Incomplete gRPC frame header');
			}
			$array = unpack("Cflag/Nlength", $body);
			if($array['flag'] !== 0 && $array['flag'] !== 1){
				throw new UnexpectedValueException('Invalid gRPC compression flag');
			}
			if($array['length'] > self::MAX_MESSAGE_LENGTH){
				throw new LengthException('gRPC message exceeds size limit');
			}
			if($array['length'] !== $body_length - 5){
				throw new UnexpectedValueException('Expected exactly one complete gRPC message');
			}
			$message = substr($body, 5, $array['length']);
			if($array['flag'] === 1){
				if($grpc_encoding !== 'gzip'){
					throw new UnexpectedValueException('Unsupported gRPC compression encoding');
				}
				// Decode incrementally: a small wire payload must not allocate an
				// unbounded decoded string before the size check. Suppress only zlib's
				// expected malformed-input warnings and reject the failed operation.
				$inflate = inflate_init(ZLIB_ENCODING_GZIP);
				$decoded = '';
				for($offset = 0; $offset < $array['length']; $offset += 1024){
					$chunk = @inflate_add($inflate, substr($message, $offset, 1024), ZLIB_SYNC_FLUSH);
					if($chunk === false){
						throw new UnexpectedValueException('Invalid gzip message');
					}
					if(strlen($chunk) > self::MAX_MESSAGE_LENGTH - strlen($decoded)){
						throw new LengthException('Decoded gRPC message exceeds size limit');
					}
					$decoded .= $chunk;
					if(inflate_get_status($inflate) === ZLIB_STREAM_END){
						break;
					}
				}
				if(inflate_get_status($inflate) !== ZLIB_STREAM_END || inflate_get_read_len($inflate) !== $array['length']){
					throw new UnexpectedValueException('Incomplete gzip message or trailing data');
				}
				$message = $decoded;
			}
			$obj = new $className();
			$obj->mergeFromString($message);
		}
		return $obj;

	}
}
