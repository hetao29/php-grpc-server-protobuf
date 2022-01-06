<?php
// GENERATED CODE -- DO NOT EDIT!

namespace User\Info;

/**
 * The greeter service definition.
 */
class InfoClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Sends a greeting
     * @param \User\Info\LoginRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     */
    public function login(\User\Info\LoginRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/User.Info.Info/login',
        $argument,
        ['\User\Info\LoginResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \User\Info\LogoutRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     */
    public function logout(\User\Info\LogoutRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/User.Info.Info/logout',
        $argument,
        ['\User\Info\LogoutResponse', 'decode'],
        $metadata, $options);
    }

}
