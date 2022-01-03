<?php
namespace Test\Helloworld;
class Greeter{
	/**
	 * @return Test\Helloworld\HelloReply
	 */
	public function SayHello($data){
		$request = \Grpc\Parser::deserializeMessage([HelloRequest::class, null], $data);
		$reply = new HelloReply();
		$reply->setMessage("Hello, ".$request->getName()."!");
		return $reply;
	}
}
