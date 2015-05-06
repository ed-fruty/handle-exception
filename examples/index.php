<?php

use Fruty\Exception\ExceptionHintHandler;
use Fruty\Exception\Run;


require __DIR__ . '/../vendor/autoload.php';

ExceptionHintHandler::error(function(Exception $e, $inspector, Run $run)
{
    echo 'asd';
});

ExceptionHintHandler::error(function(Exception $e, $inspector, Run $run)
{
    echo "Something went wrong";
});

class NotFoundException extends Exception implements \Fruty\Exception\HttpExceptionInterface
{
    public $code = 404;

    public $message = 'The page you requested not found';
}

ExceptionHintHandler::withDebug();
ExceptionHintHandler::register();


















////

class ThrowExceptionClass
{
    public function __construct()
    {
        throw new Exception();
    }
}
function b()
{
    new ThrowExceptionClass();
}

b();