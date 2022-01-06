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
| Supports: https://github.com/hetao29/php-grpc-server                  |
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
		$class = dirname($uri);
		$class = str_replace("/","",$class);
		$class = str_replace(".","\\",$class);
		$func = basename($uri);
		if(class_exists($class)){
			$a = new $class();
			if(method_exists($a,$func)){
				$r = $a->$func($data);
				header("grpc-status: 0");
				header('content-type: application/grpc');
				return self::encode($r);
			}else{
				header("HTTP/1.0 401 Not Found");
				return false;
			}
		}else{
			header("HTTP/1.0 402 Not Found");
			return false;
		}
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
