<?php
//ob_start();
define("ROOT",						dirname(__FILE__)."/");
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

//if(($r=GRpcServer::run())!==false){
//	echo($r);
//}
//$a = ob_get_contents();
$http = new Swoole\Http\Server('0.0.0.0', 50051, SWOOLE_BASE);
$http->set([
    'log_level' => SWOOLE_LOG_INFO,
    'trace_flags' => 0,
    'worker_num' => 1,
    'open_http2_protocol' => true
]);
$http->on('workerStart', function (Swoole\Http\Server $server) {
    echo "php " . __DIR__ . "/greeter_client.php\n";
});
$http->on('request', function (Swoole\Http\Request $request, Swoole\Http\Response $response) use ($http) {
	GRpcServer::run($request, $response);
	/*
    $path = $request->server['request_uri'];
    $route = [
        '/helloworld.Greeter/SayHello' => function (...$args) {
            [$server, $request, $response] = $args;
            $request_message = Grpc\Parser::deserializeMessage([HelloRequest::class, null], $request->rawContent());
            if ($request_message) {
                $response_message = new HelloReply();
                $response_message->setMessage('Hello ' . $request_message->getName());
                $response->header('content-type', 'application/grpc');
                $response->header('trailer', 'grpc-status, grpc-message');
                $trailer = [
                    "grpc-status" => "0",
                    "grpc-message" => ""
                ];
                foreach ($trailer as $trailer_name => $trailer_value) {
                    $response->trailer($trailer_name, $trailer_value);
                }
                $response->end(Grpc\Parser::serializeMessage($response_message));
                return true;
            }
            return false;
        }
    ];
    if (!(isset($route[$path]) && $route[$path]($http, $request, $response))) {
        $response->status(400);
        $response->end('Bad Request');
    }
 */
});

$http->start();
