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
final class GRpcServer{

	/**
	 * main run method!
	 * @param string $uri = null
	 * @param string $data = null
	 * @param string $content_type = null | json
	 * @param string $grpc_encoding = null | gzip
	 * @return false | binary str
	 */
	public static function run($uri = null, $data = null, $content_type=null, $grpc_encoding=null){
		$data = $data ?? self::getRawData();
		$uri = $uri ?? $_SERVER['REQUEST_URI'] ?? "";
		$class_name = str_replace(["/","."],["","\\"],dirname($uri));
		$func_name = basename($uri);
		$code=0;$msg="";
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
						$request = self::decode($param_name,$data,$content_type,$grpc_encoding);
						$response = $class->$func_name($request);
						if(method_exists($response,"serializeToString")){
							return self::encode($response, $content_type);
						}else{
							$type = gettype($response);
							$code = -1;
							$msg = "grpc-message: The Return value type $type of $class_name::$func_name() is wrong";
						}
					}else{
						$code = -2;
						$msg = "grpc-message: The {$params[0]} of $class_name::$func_name() type is wrong";
					}
				}else{
					$code = -3;
					$msg = "grpc-message: The {$params[0]} of $class_name::$func_name() type have not defined";
				}
			}else{
				$code = -4;
				$msg = "grpc-message: The Parameter of $class_name::$func_name() is empty";
			}
		}catch(Exception $e){
			$code = -5;
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

	public static function decode($className, string $body, string $content_type=null, string $grpc_encoding=null){
		if(empty($body)){
			return false;
		}
		$obj = new $className();
		if($content_type=="json"){
			$obj->mergeFromJsonString($body);
			return $obj;
		}else{
			$array = unpack("Cflag/Nlength", $body);
			if($array==false){
				return false;
			}
			$message = substr($body, 5, $array['length']);
			if($grpc_encoding=="gzip"){
				$message = gzdecode($message);
			}
			$obj->mergeFromString($message);
		}
		return $obj;

	}
}
