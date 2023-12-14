<?php
# Generated by the protocol buffer compiler (hetao29/php-grpc-server-protobuf-plugin). DO NOT EDIT!
# source: src/helloworld.proto

namespace Test\Helloworld;


interface GreeterInterface
{
    // GRPC specific service name.
    public const NAME = "Test.Helloworld.Greeter";

    /**
    * @param GRPC\ContextInterface $ctx
    * @param HelloRequest $in
    * @return HelloReply
    *
    * @throws \Exception
    */
    public function SayHello(HelloRequest $request): HelloReply;

    /**
    * @param GRPC\ContextInterface $ctx
    * @param HelloRequest $in
    * @return HelloReply
    *
    * @throws \Exception
    */
    public function EchoAbort(HelloRequest $request): HelloReply;

    /**
    * @param GRPC\ContextInterface $ctx
    * @param ServerStreamingEchoRequest $in
    * @return ServerStreamingEchoResponse
    *
    * @throws \Exception
    */
    public function ServerStreamingEcho(ServerStreamingEchoRequest $request): ServerStreamingEchoResponse;
}
