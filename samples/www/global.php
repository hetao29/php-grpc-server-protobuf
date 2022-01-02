<?php
define("ROOT",						dirname(__FILE__)."/../");
define("ROOT_LIBS",					ROOT."/libs");
define("ROOT_APP",					ROOT."/app");
define("ROOT_PROTO_GENERATED.",		ROOT."/proto_generated");
require_once(ROOT_LIBS."/vendor/autoload.php");
//{{{
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
//}}}

$local = dirname(__FILE__)."/local.php";
if(is_file($local)){
	require_once($local);
}
