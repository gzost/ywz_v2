<?php
namespace YunLianHui;

use Throwable;

class ApiException extends \Exception
{
    public function __construct($message,$code,Throwable $previous = null)
    {
        parent::__construct($message,$code,$previous);
    }
}
