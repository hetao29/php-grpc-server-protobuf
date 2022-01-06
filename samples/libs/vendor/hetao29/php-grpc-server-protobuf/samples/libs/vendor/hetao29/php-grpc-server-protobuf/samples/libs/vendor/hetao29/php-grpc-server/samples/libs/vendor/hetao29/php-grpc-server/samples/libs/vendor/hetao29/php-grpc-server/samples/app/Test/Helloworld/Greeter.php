<?php
namespace Test\Helloworld;
class Greeter{
	/**
	 */
	public function SayHello(HelloRequest $request) : HelloReply{
		$reply = new HelloReply();
		$reply->setMessage("Hello, ".$request->getName()."!");
		return $reply;
	}
}
