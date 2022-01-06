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

2. use in php file, like samples/www/index.php (php-fpm mode), or see samples/server/server.php (swoole mode)

```php
<?php
define("ROOT",						dirname(__FILE__)."/../");
define("ROOT_LIBS",					ROOT."/libs");
define("ROOT_APP",					ROOT."/app");
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

if(($r=GRpcServer::run())!==false){
	echo($r);
}
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
class Greeter{
	/**
	 * @return HelloReply
	 */
	public function SayHello(HelloRequest $request) : HelloReply{
		$reply = new HelloReply();
		$reply->setMessage("Hello, ".$request->getName()."!");
		return $reply;
	}
}

```

3. config nginx && php-fpm

```conf
server {
	listen 50000 http2;
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

# curl command
curl -d '{"name":"xx"}' -v http://127.0.0.1:50000//Test.Helloworld.Greeter/SayHello -H "content-type:application/json"

# or web browser 

```
