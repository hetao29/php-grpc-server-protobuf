# php-grpc-server-protobuf
The php grpc server framework with protobuf and DO NOT use any 3rd libraries or use Swoole.
Support protobuf and json request.

# Architecture

## mode with nginx & php-fpm (only support php client and http json request)

1. gRPC Client  => nginx => php-fpm => this framework => custom services => protobuf binary response
2. http/https json request (content-type:application/json) => nginx => php-fpm => this framework => custom services => json response

## mode with swoole (all gRPC Client and http json request)

1. gRPC Client  => Swoole => this framework => custom services => protobuf binary response
2. http/https json request (content-type:application/json)  => Swoole => this framework => custom services => json response

# Usage

1. install with composer

```bash
composer require "hetao29/php-grpc-server-protobuf:dev-main"
```

2. use in php file, like samples/www/index.php (php-fpm mode)

```php
<?php
define("ROOT",				dirname(__FILE__)."/../");
define("ROOT_LIBS",			ROOT."/libs");
define("ROOT_APP",			ROOT."/app");
define("ROOT_PROTO_GENERATED",		ROOT."/proto_generated");
require_once(ROOT_LIBS."/vendor/autoload.php");
spl_autoload_register(function($class){
	$root = ROOT_PROTO_GENERATED."/".str_replace("\\","/",$class).".php";
	if(is_file($root)){
		require_once($root);
	}
});
spl_autoload_register(function($class){
	$root = ROOT_APP."/".str_replace("\\","/",$class).".php";
	if(is_file($root)){
		require_once($root);
	}
});

try{
	$content_type = (isset($_SERVER['HTTP_CONTENT_TYPE']) && $_SERVER['HTTP_CONTENT_TYPE']=='application/json') ? 'json' : null; //json | null (default)
	if(($r=GRpcServer::run(null,null,$content_type))!==false){
		echo($r);
	}
}catch(Exception $e){
	print_r($e);
}
```

3. or swoole server

```php
<?php
require __DIR__ . '/../libs/vendor/autoload.php';

define("ROOT",				__DIR__."/../");
define("ROOT_APP",			__DIR__."/../app");
define("ROOT_PROTO_GENERATED",		__DIR__."/../proto_generated/");
spl_autoload_register(function($class){
	$root = ROOT_PROTO_GENERATED."/".str_replace("\\","/",$class).".php";
	if(is_file($root)){
		require_once($root);
	}
});
spl_autoload_register(function($class){
	$root = ROOT_APP."/".str_replace("\\","/",$class).".php";
	if(is_file($root)){
		require_once($root);
	}
});


$http = new Swoole\Http\Server('0.0.0.0', 50000, SWOOLE_BASE);

$http->on('request', function (Swoole\Http\Request $request, Swoole\Http\Response $response) use ($http) {
	$content_type = (isset($request->header['content-type']) && $request->header['content-type']=='application/json') ? 'json' : null; //json | null (default)
	if($content_type=="json"){
		$response->header('content-type', 'application/json');
	}else{
		$response->header('content-type', 'application/grpc');
	}
	try{
		if(($r=GRpcServer::run($request->server['request_uri'], $request->rawContent(), $content_type))!==false){
			//echo($r);
			$response->header('trailer', 'grpc-status, grpc-message');
			$trailer = [
				"grpc-status" => "0",
				"grpc-message" => ""
			];
			foreach ($trailer as $trailer_name => $trailer_value) {
				$response->trailer($trailer_name, $trailer_value);
			}
			$response->end($r);
	}
	}catch(Exception $e){
		$response->header('trailer', 'grpc-status, grpc-message');
		$trailer = [
			"grpc-status" => $e->getCode(),
			"grpc-message" => $e->getMessage(),
		];
		foreach ($trailer as $trailer_name => $trailer_value) {
			$response->trailer($trailer_name, $trailer_value);
		}
		$response->end();
	}
});
$http->start();

```


# Write App Services 

1. proto and genproto to php files

```bash
cd proto && make
```

2. write gRPC Server in services dir like helloworld

```php
<?php
namespace Test\Helloworld;
class Greeter implements GreeterInterface{
	/**
	 */
	public function SayHello(HelloRequest $request) : HelloReply{
		$reply = new HelloReply();
		$reply->setMessage("Hello2, ".$request->getName()."!");
		return $reply;
	}
}

```

3. config nginx && php-fpm

```conf
server {
	listen 50010 http2; #with http2 is grpc protocol
	#listen 50010; #without http2 is json protocol
	root /data/server/;
	location / {
		if (!-e $request_filename){
			rewrite ^/(.+?)$ /index.php last;
		}
	}
	location ~ \.php$ {
		fastcgi_param REMOTE_ADDR $http_x_real_ip;
		fastcgi_pass   127.0.0.1:9000;
		fastcgi_index  index.php;
		fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
		include        fastcgi_params;
	}
}
```
4. test


```bash
# cli mode
php client/hello.php

# curl command (json request)
# swoole (50000 port)
curl -d '{"name":"xx"}' -v http://127.0.0.1:50000//Test.Helloworld.Greeter/SayHello -H "content-type:application/json"
# nginx php-fpm (50010 port)
curl -d '{"name":"xx"}' -v http://127.0.0.1:50010//Test.Helloworld.Greeter/SayHello -H "content-type:application/json"

# or web browser 

```
