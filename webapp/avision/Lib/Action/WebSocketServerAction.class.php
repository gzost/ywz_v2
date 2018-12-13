<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2018/12/13
 * Time: 10:52
 */
//class SafeAction extends Action {
class WebSocketServerAction extends Action
{
    public function __construct()
    {
        if('Cli'!=MODE_NAME) die("Mus");
    }

    public function test(){
        echo 'websocket test:'.MODE_NAME;
    }
}