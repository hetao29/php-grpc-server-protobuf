<?php
chdir(dirname(__FILE__));
require_once("../libs/vendor/autoload.php");
define("ROOT_PROTO_GENERATED","../proto_generated/");
spl_autoload_register(function($class){
	$root = ROOT_PROTO_GENERATED."/".str_replace("\\","/",$class).".php";
	if(is_file($root)){
		require_once($root);
	}
});
try{
	//call by grpc
	//https://grpc.github.io/grpc/php/class_grpc_1_1_channel.html
	#swoole
	//curl -d '{"name":"xx"}' -v http://127.0.0.1:50000//Test.Helloworld.Greeter/SayHello -H "content-type:application/json"

	#nginx + php-fpm
	//curl -d '{"name":"xx"}' -v http://127.0.0.1:50010//Test.Helloworld.Greeter/SayHello -H "content-type:application/json"
	$channel = new Grpc\Channel("127.0.0.1:50000",[
		//'credentials' => Grpc\ChannelCredentials::createInsecure(),
		//"force_new"=>false,
	]);
	$client = new Test\Helloworld\GreeterClient(null,null,$channel);
	$request = new Test\Helloworld\HelloRequest();
	$request->setName("Tony2");
	$call = $client->SayHello($request);
	list($reply,$status) = $call->wait();
	echo __FILE__.":".__LINE__.":"; print_r($call->getMetadata());
	if($reply){
		echo __FILE__.":".__LINE__.":"; var_dump($reply->getMessage());
	}
	print_r($status);
	exit;

	$client = new User\Info\InfoClient(null,null,$channel);
	$request = new User\Info\LoginRequest();
	$request->setName("Tony");
	$call = $client->login($request);
	list($reply,$status) = $call->wait();
	echo __FILE__.":".__LINE__.":"; print_r($call->getMetadata());
	if($reply){
		echo __FILE__.":".__LINE__.":"; var_dump($reply->getInfo());
	}
	exit;
	$request = new Test\Helloworld\HelloRequest();
	$request->setName("TonyAbort");
	list($reply,$error) = $client->EchoAbort($request)->wait();
	if($reply){
		echo __FILE__.":".__LINE__.":"; var_dump($reply->getMessage());
	}else{
		echo __FILE__.":".__LINE__.":"; 
		print_r($error);
	}

	$empty = new Test\Helloworld\PBEmpty();
	list($reply,$error) = $client->NoOp($empty)->wait();
	if($reply){
		echo __FILE__.":".__LINE__.",OK\n";
	}else{
		echo __FILE__.":".__LINE__.":"; 
		print_r($error);
	}


	// server streaming call
	$stream_request = new Test\Helloworld\ServerStreamingEchoRequest();
	$stream_request->setMessage("stream message");
	$stream_request->setMessageCount(5);

	$responses = $client->ServerStreamingEcho($stream_request)->responses();
	//echo __FILE__.":".__LINE__.":"; 
	//var_dump($responses);

	foreach ($responses as $response) {
		echo __LINE__.":".$response->getMessage()."\n";
	}



}catch(Exception $e){
	print_r($e);
}
