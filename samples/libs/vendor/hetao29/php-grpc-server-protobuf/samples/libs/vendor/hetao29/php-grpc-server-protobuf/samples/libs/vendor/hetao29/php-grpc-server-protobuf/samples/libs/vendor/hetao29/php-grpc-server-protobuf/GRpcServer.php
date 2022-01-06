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
use Google\Protobuf\Internal\Message;
if(!class_exists("GRpcServer",false)):
	final class GRpcServer{

		/**
		 * main method!
		 *
		 * @return false | binary str
		 */
		public static function run(){
			$data = self::getRawData();
			$uri = $_SERVER['REQUEST_URI'] ?? "";
			$class_name = str_replace(["/","."],["","\\"],dirname($uri));
			$func_name = basename($uri);
			header('content-type: application/grpc+proto');
			try{
				$ref = new ReflectionClass($class_name);
				$params = $ref->getMethod($func_name)->getParameters();
				if($params){
					$param_type = $params[0]->getType();
					if($param_type){
						$param_name= $param_type->getName();;
						$ref_param = new ReflectionClass($param_name);
						if($ref_param->hasMethod("mergeFromString")){
							$class = new $class_name();
							$request = self::decode($param_name,$data);
							$response = $class->$func_name($request);
							if(method_exists($response,"serializeToString")){
								header("grpc-status: 0");
								return self::encode($response);
							}else{
								$type = gettype($response);
								header("grpc-message: The Return value type $type of $class_name::$func_name() is wrong");
							}
						}else{
							header("grpc-message: The {$params[0]} of $class_name::$func_name() type is wrong");
						}
					}else{
						header("grpc-message: The {$params[0]} of $class_name::$func_name() type have not defined");
					}
				}else{
					header("grpc-message: The Parameter of $class_name::$func_name() is empty");
				}
			}catch(ReflectionException $e){
				header("grpc-message: ".$e->getMessage());
			}
			header("grpc-status: 2");
			header('HTTP/1.1 502 Internal Server Error');
			return false;
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
		public static function encode($obj){
			$out= $obj->serializeToString();
			return pack("CN", 0, strlen($out)) . $out;
		}

		public static function decode($className, string $body){
			if(empty($body)){
				return false;
			}
			$array = unpack("Cflag/Nlength", $body);

			if($array==false){
				return false;
			}
			$message = substr($body, 5, $array['length']);

			$obj = new $className();
			$obj->mergeFromString($message);
			return $obj;
		}
	}
endif;
