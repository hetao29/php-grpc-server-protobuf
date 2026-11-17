<?php
namespace Test\Helloworld;
class Greeter implements GreeterInterface{
	/**
	 */
	public function SayHello(HelloRequest $request) : HelloReply{
		$reply = new HelloReply();
		$reply->setMessage("Hello2, ".$request->getName()."!");
		return $reply;
	}
	public function NoOp(PBEmpty $argument){
		$reply = new PBEmpty();
		return $reply;
	}
    public function EchoAbort(HelloRequest $request): HelloReply{
		$reply = new HelloReply();
		$reply->setMessage("Hello, ".$request->getName()."!");
		return $reply;
	}
    public function ServerStreamingEcho(ServerStreamingEchoRequest $request): ServerStreamingEchoResponse{
		$reply = new ServerStreamingEchoResponse();
		$reply->setMessage("Hello, ".$request->getMessage().",".$request->getMessageCount()."!");
		return $reply;
	}
}
