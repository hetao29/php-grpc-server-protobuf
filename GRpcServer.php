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
	// Unary request limit, applied to both wire payload and decoded payload.
	public const MAX_MESSAGE_LENGTH = 16 * 1024 * 1024;
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
	 * main run method!
	 * @param string $uri = null
	 * @param string $data = null
	 * @param string $content_type = null | json
	 * @param string $grpc_encoding = null | gzip
	 * @param ...$args //service construct params
	 * @return false | binary str
	 */
	public static function run($uri = null, $data = null, $content_type=null, $grpc_encoding=null){
		$uri = $uri ?? $_SERVER['REQUEST_URI'] ?? "";
		$code=0;$msg="";
		try{
			// Validate before deriving any class name or triggering an autoloader.
			if(!is_string($uri) || !preg_match('/\A\/([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*)\/([A-Za-z_][A-Za-z0-9_]*)\z/', $uri, $route)){
				throw new UnexpectedValueException('Invalid RPC route');
			}
			$class_name = str_replace('.', '\\', $route[1]);
			$func_name = $route[2];
			$interface_name = $class_name.self::$interface_suffix;
			$ref = new ReflectionClass($class_name);
			if($ref->implementsInterface($interface_name)){
				$params = $ref->getMethod($func_name)->getParameters();
				if($params){
					$param_type = $params[0]->getType();
					if($param_type){
						$param_name = $param_type->getName();
						$data = $data ?? self::getRawData();
						$request = self::decode($param_name,$data,$content_type,$grpc_encoding);
						if($ref->getConstructor()){
							$class = $ref->newInstanceArgs(array_slice(func_get_args(),4));
						}else{
							$class = $ref->newInstanceWithoutConstructor();
						}
						if(!is_callable([$class, $func_name])){
							throw new UnexpectedValueException('RPC method is not callable');
						}
						$response = $class->$func_name($request);
						return self::encode($response, $content_type);
					}else{
						$code = -1;
						$msg = "grpc-message: The {$params[0]} of $class_name::$func_name() type have not defined";
					}
				}else{
					$code = -2;
					$msg = "grpc-message: The Parameter of $class_name::$func_name() is empty";
				}
			}else{
				$code = -3;
				$msg = "grpc-message: The Class of $class_name is not implements $interface_name";
			}
		}catch(Exception $e){
			$code = -4;
			$msg = "grpc-message: ".$e->getMessage();
		}
		throw new Exception($msg,$code);
	}

	public static function getRawData(){
		$data=null;
		if(isset($GLOBALS['HTTP_RAW_POST_DATA'])){
			$data = $GLOBALS['HTTP_RAW_POST_DATA'];
		}else{
			$data = file_get_contents("php://input");
		}
		return $data;
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

	public static function decode($className, string $body, $content_type=null, $grpc_encoding=null, int $max_message_length=self::MAX_MESSAGE_LENGTH){
		if($max_message_length < 1){
			throw new InvalidArgumentException('Invalid message size limit');
		}
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
			if($array['length'] > $max_message_length){
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
					if(strlen($chunk) > $max_message_length - strlen($decoded)){
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
