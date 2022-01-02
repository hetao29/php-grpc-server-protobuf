<?php
/*{{{LICENSE
+-----------------------------------------------------------------------+
| SlightPHP Framework                                                   |
+-----------------------------------------------------------------------+
| This program is free software; you can redistribute it and/or modify  |
| it under the terms of the GNU General Public License as published by  |
| the Free Software Foundation. You should have received a copy of the  |
| GNU General Public License along with this program.  If not, see      |
| http://www.gnu.org/licenses/.                                         |
| Copyright (C) 2008-2009. All Rights Reserved.                         |
+-----------------------------------------------------------------------+
| Supports: http://www.slightphp.com                                    |
+-----------------------------------------------------------------------+
}}}*/
/**
 * @package GRpcServer
 */
use Google\Protobuf\Internal\Message;
if(!class_exists("GRpcServer",false)):
final class GRpcServer{
	/**
	 * @var string
	 */
	public static $appDir=".";

	/**
	 * @var string
	 */
	public static function setAppDir($dir){
		self::$appDir = $dir;
		return true;
	}

	/**
	 * appDir get
	 * 
	 * @return string
	 */
	public static function getAppDir(){
		return self::$appDir;
	}
	/**
	 * main method!
	 *
	 * @param string $path
	 * @return boolean
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
				header('content-type: application/grpc');
				return self::serializeMessage($r);
			}else{
				header("HTTP/1.0 401 Not Found");
			}
		}else{
			header("HTTP/1.0 402 Not Found");
		}
	}
	public static function getRawData($json_decode=false){
		$data=null;
		if(isset($GLOBALS['HTTP_RAW_POST_DATA'])){
			$data = $GLOBALS['HTTP_RAW_POST_DATA'];
		}else{
			$data = file_get_contents("php://input");
		}
		return $data;
	}

    public static function pack(string $data): string{
        return $data = pack('CN', 0, strlen($data)) . $data;
    }

    public static function unpack(string $data): string{
        // it's the way to verify the package length
        // 1 + 4 + data
        // $len = unpack('N', substr($data, 1, 4))[1];
        // assert(strlen($data) - 5 === $len);
        return $data = substr($data, 5);
    }

    public static function serializeMessage($data)
    {
        if (method_exists($data, 'encode')) {
            $data = $data->encode();
        } elseif (method_exists($data, 'serializeToString')) {
            $data = $data->serializeToString();
        } else {
            /** @noinspection PhpUndefinedMethodInspection */
            $data = $data->serialize();
        }
        return self::pack($data);
    }

    public static function deserializeMessage($deserialize, string $value)
    {
        if (empty($value)) {
            return null;
        } else {
            $value = self::unpack($value);
        }
        if (is_array($deserialize)) {
            [$className, $deserializeFunc] = $deserialize;
            /** @var $obj \Google\Protobuf\Internal\Message */
            $obj = new $className();
            if ($deserializeFunc && method_exists($obj, $deserializeFunc)) {
                $obj->$deserializeFunc($value);
            } else {
                /** @noinspection PhpUndefinedMethodInspection */
                $obj->mergeFromString($value);
            }
            return $obj;
        }

        return call_user_func($deserialize, $value);
    }

    /**
     * @param \Swoole\Http2\Response|null $response
     * @param $deserialize
     * @return Message[]|\Grpc\StringifyAble[]|\Swoole\Http2\Response[]
     */
    public static function parseToResultArray($response, $deserialize): array
    {
        if (!$response) {
            return ['No response', GRPC_ERROR_NO_RESPONSE, $response];
        } elseif ($response->statusCode !== 0 && (($response->statusCode / 100) % 10) !== 2) {
            return ['Http status Error', $response->errCode ?: $response->statusCode, $response];
        } else {
            $grpc_status = (int) ($response->headers['grpc-status'] ?? 0);
            if ($grpc_status !== 0) {
                return [$response->headers['grpc-message'] ?? 'Unknown error', $grpc_status, $response];
            }
            $data = $response->data;
            $reply = self::deserializeMessage($deserialize, $data);
            $status = (int) (($response->headers['grpc-status'] ?? 0) ?: 0);
            return [$reply, $status, $response];
        }
    }
}
endif;
