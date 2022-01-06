<?php
require __DIR__ . '/../libs/vendor/autoload.php';

define("ROOT",						__DIR__."/../");
define("ROOT_APP",					__DIR__."/../app");
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
$http->set([
    'log_level' => SWOOLE_LOG_INFO,
    'trace_flags' => 0,
    'worker_num' => 1,
    'open_http2_protocol' => true
]);
$http->on('workerStart', function (Swoole\Http\Server $server) {
});
$http->on('request', function (Swoole\Http\Request $request, Swoole\Http\Response $response) use ($http) {
	try{
		if(($r=GRpcServer::run($request->server['request_uri'], $request->rawContent()))!==false){
			//echo($r);
			$response->header('content-type', 'application/grpc');
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
		print_r($e);
		$response->header('content-type', 'application/grpc');
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
