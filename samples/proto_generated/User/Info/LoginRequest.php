<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: src/info.proto

namespace User\Info;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>User.Info.LoginRequest</code>
 */
class LoginRequest extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>.common.Error error = 1;</code>
     */
    protected $error = null;
    /**
     * Generated from protobuf field <code>string name = 2;</code>
     */
    protected $name = '';
    /**
     * Generated from protobuf field <code>string password = 3;</code>
     */
    protected $password = '';
    /**
     * Generated from protobuf field <code>string verify_code = 4;</code>
     */
    protected $verify_code = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type \Common\Error $error
     *     @type string $name
     *     @type string $password
     *     @type string $verify_code
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Src\Info::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>.common.Error error = 1;</code>
     * @return \Common\Error|null
     */
    public function getError()
    {
        return $this->error;
    }

    public function hasError()
    {
        return isset($this->error);
    }

    public function clearError()
    {
        unset($this->error);
    }

    /**
     * Generated from protobuf field <code>.common.Error error = 1;</code>
     * @param \Common\Error $var
     * @return $this
     */
    public function setError($var)
    {
        GPBUtil::checkMessage($var, \Common\Error::class);
        $this->error = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>string name = 2;</code>
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Generated from protobuf field <code>string name = 2;</code>
     * @param string $var
     * @return $this
     */
    public function setName($var)
    {
        GPBUtil::checkString($var, True);
        $this->name = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>string password = 3;</code>
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Generated from protobuf field <code>string password = 3;</code>
     * @param string $var
     * @return $this
     */
    public function setPassword($var)
    {
        GPBUtil::checkString($var, True);
        $this->password = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>string verify_code = 4;</code>
     * @return string
     */
    public function getVerifyCode()
    {
        return $this->verify_code;
    }

    /**
     * Generated from protobuf field <code>string verify_code = 4;</code>
     * @param string $var
     * @return $this
     */
    public function setVerifyCode($var)
    {
        GPBUtil::checkString($var, True);
        $this->verify_code = $var;

        return $this;
    }

}

