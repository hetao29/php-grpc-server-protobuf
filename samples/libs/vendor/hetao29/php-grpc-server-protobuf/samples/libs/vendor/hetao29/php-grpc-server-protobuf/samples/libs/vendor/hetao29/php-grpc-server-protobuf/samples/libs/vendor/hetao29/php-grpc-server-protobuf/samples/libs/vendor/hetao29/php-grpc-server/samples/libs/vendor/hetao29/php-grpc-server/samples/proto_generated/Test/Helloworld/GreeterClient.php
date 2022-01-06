<?php
// GENERATED CODE -- DO NOT EDIT!

// Original file comments:
// Copyright 2015 gRPC authors.
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//     http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.
//
namespace Test\Helloworld;

/**
 * The greeting service definition.
 */
class GreeterClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     *
     * @param \Test\Helloworld\PBEmpty $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     */
    public function NoOp(\Test\Helloworld\PBEmpty $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Test.Helloworld.Greeter/NoOp',
        $argument,
        ['\Test\Helloworld\PBEmpty', 'decode'],
        $metadata, $options);
    }

    /**
     * Sends a greeting
     * @param \Test\Helloworld\HelloRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     */
    public function SayHello(\Test\Helloworld\HelloRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Test.Helloworld.Greeter/SayHello',
        $argument,
        ['\Test\Helloworld\HelloReply', 'decode'],
        $metadata, $options);
    }

    /**
     * Sends back abort status.
     * @param \Test\Helloworld\HelloRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     */
    public function EchoAbort(\Test\Helloworld\HelloRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Test.Helloworld.Greeter/EchoAbort',
        $argument,
        ['\Test\Helloworld\HelloReply', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Test\Helloworld\ServerStreamingEchoRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     */
    public function ServerStreamingEcho(\Test\Helloworld\ServerStreamingEchoRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/Test.Helloworld.Greeter/ServerStreamingEcho',
        $argument,
        ['\Test\Helloworld\ServerStreamingEchoResponse', 'decode'],
        $metadata, $options);
    }

}
