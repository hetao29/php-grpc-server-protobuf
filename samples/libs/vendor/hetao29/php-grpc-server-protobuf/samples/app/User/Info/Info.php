<?php
namespace User\Info;
class Info{
    public function login(LoginRequest $req){
		$reply = new LoginResponse();
		return $reply;
	}
}
