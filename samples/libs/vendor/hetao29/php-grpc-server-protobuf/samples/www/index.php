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

try{
	$content_type = (isset($_SERVER['HTTP_CONTENT_TYPE']) && $_SERVER['HTTP_CONTENT_TYPE']=='application/json') ? 'json' : null; //json | null (default)
	if(($r=GRpcServer::run(null,null,$content_type))!==false){
		echo($r);
	}
}catch(Exception $e){
	print_r($e);
}
