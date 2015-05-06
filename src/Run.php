<?php
namespace Fruty\Exception;

use Whoops\Run as WhoopsRun;

/**
 * Class Run
 * @package Fruty\Exception
 */
class Run extends WhoopsRun
{
    /**
     * @param null $code
     * @return bool|false|int|null
     */
    public function sendHttpCode($code = null)
    {
        if (func_num_args() == 0) {
            return $this->sendHttpCode;
        }
        if (!$code) {
            return $this->sendHttpCode = false;
        }
        if ($code === true) {
            $code = 500;
        }
        return $this->sendHttpCode = $code;
    }
}