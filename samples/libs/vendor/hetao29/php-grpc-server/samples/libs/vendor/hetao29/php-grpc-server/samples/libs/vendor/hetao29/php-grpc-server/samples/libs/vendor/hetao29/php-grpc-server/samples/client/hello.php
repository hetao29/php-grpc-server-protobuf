<?php
chdir(dirname(__FILE__));
require_once("../libs/vendor/autoload.php");
define("ROOT_PROTO_GENERATED","../proto_generated/");
spl_autoload_register(function($class){
	$root = ROOT_PROTO_GENERATED."/".str_replace("\\","/",$class).".php";
	require_once($root);
	if(is_file($root)){
		require_once($root);
	}
});
try{
	$credentials = Grpc\ChannelCredentials::createSsl(file_get_contents("/data/www/keys/mxiqi.com.pem"));
	//call by grpc
	$client = new Test\Helloworld\GreeterClient("www.mxiqi.com:50000",[
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

}catch(Exception $e){
	print_r($e);
}
