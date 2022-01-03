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
	$client = new Test\Helloworld\GreeterClient("127.0.0.1:50000",[
		'credentials' => Grpc\ChannelCredentials::createInsecure()
	]);
	$request = new Test\Helloworld\HelloRequest();
	$request->setName("Tony");
	list($reply,$error) = $client->SayHello($request)->wait();
	if($reply){
		echo __FILE__.":".__LINE__.":"; var_dump($reply->getMessage());
	}else{
		echo __FILE__.":".__LINE__.":"; 
		print_r($error);
	}

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
