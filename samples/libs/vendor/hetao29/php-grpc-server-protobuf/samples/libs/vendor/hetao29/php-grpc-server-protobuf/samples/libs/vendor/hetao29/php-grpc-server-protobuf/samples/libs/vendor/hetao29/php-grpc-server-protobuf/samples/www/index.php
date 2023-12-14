<?php
ob_start();
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
	if(($r=GRpcServer::run())!==false){
		echo($r);
	}
}catch(Exception $e){
}
