<?php
namespace Test\Helloworld;
class Greeter{
	/**
	 * @return Test\Helloworld\HelloReply
	 */
	public function SayHello($data){
		$request = \GRpcServer::decode(HelloRequest::class, $data);
		$reply = new HelloReply();
		$reply->setMessage("Hello, ".$request->getName()."!");
		return $reply;
	}
}
