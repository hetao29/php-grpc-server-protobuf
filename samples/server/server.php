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
    //'worker_num' => 1,
    'open_http2_protocol' => true,
	#https://wiki.swoole.com/#/question/use?id=swoole%e5%a6%82%e4%bd%95%e6%ad%a3%e7%a1%ae%e7%9a%84%e9%87%8d%e5%90%af%e6%9c%8d%e5%8a%a1
    'max_wait_time' => 60,
    'reload_async' => true,
]);
//$http->on('start', function (Swoole\Server $server) {
//	echo "start\n";
//});
//$http->on('workerStart', function (Swoole\Server $server, $workerId) {
//	echo "workerStart $workerId \n";
//});
//$http->on('workerStop', function (Swoole\Server $server, $workerId) {
//	echo "workerStop $workerId \n";
//});
//$http->on('beforeReload', function (Swoole\Server $server) {
//	echo "beforeReload $workerId \n";
//});
//$http->on('afterReload', function (Swoole\Server $server) {
//	echo "afterReload $workerId \n";
//});
//$http->on('beforeShutdown', function (Swoole\Server $server) {
//	echo "beforeShutdown\n";
//});
//$http->on('shutdown', function (Swoole\Server $server) {
//	echo "shutdown\n";
//});
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
