# php-grpc-server
The php grpc server framework with protobuf and DO NOT use any 3rd libraries.

# Architecture

gRPC Client  => nginx => php-fpm => this framework => custom services

# Usage

1. install with composer

```bash
composer require "hetao29/php-grpc-server:1.1.2"
```

2. use in php file, like samples/www/index.php

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
php client/hello.php
```
