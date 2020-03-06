<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/2/27
 * Time: 17:31
 */

class SpeedtestAction extends Action
{
    public function index(){
        $this->display();
    }

    public function endpoint(){
        echo time();
    }
}