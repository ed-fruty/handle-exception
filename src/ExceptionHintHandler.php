<?php
namespace Fruty\Exception;

use InvalidArgumentException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionObject;
use Whoops\Handler\Handler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PlainTextHandler;

/**
 * Class ExceptionHintHandler
 * @package Fruty\Exception
 */
class ExceptionHintHandler extends Handler
{
    /**
     * @var array
     */
    private static $stack = [];

    /**
     * @var bool
     */
    private static $withDebug;

    /**
     * Handle exception
     *
     * @return int
     */
    public function handle()
    {
        $found = false;
        $lineage = $this->getLineage();

        foreach (array_reverse(self::$stack) as $callback) {
            if (isset($lineage[$this->getListenedClassName($callback)])) {
                $found = true;
                if ($response = $this->run($callback) !== self::DONE) {
                    return $response;
                }
            }
        }
        return $found ? Handler::LAST_HANDLER : Handler::DONE;
    }


    /**
     * Add handler
     *
     * @param callable $callback
     * @param bool|string $name
     */
    public static function error(callable $callback, $name = false)
    {
        if ($name) {
            self::$stack[$name] = $callback;
        } else {
            self::$stack[] = $callback;
        }
    }

    /**
     * Add class as subscriber
     * 
     * @param string|object $class
     */
    public static function subscribe($class)
    {
        foreach (get_class_methods($class) as $method) {
            self::$stack[] = [$class, $method];
        }
    }

    /**
     * Delete subscriber
     *
     * @param string|object $class
     */
    public static function unSubscribe($class)
    {
        foreach (self::$stack as $k => $row) {
            if (is_array($row) && isset($row[0]) && $row[0] == $class) {
                unset(self::$stack[$k]);
            }
        }
    }

    /**
     * Detach handler
     * Only for named
     *
     * @param $name
     */
    public static function detach($name)
    {
        if (isset(self::$stack[$name])) {
            unset(self::$stack[$name]);
        }
    }

    /**
     * Push handler as first
     *
     * @param callable $callback
     */
    public static function asFirst(callable $callback)
    {
        array_unshift(self::$stack, $callback);
    }

    /**
     * Debug status
     *
     * @param bool $value
     */
    public static function withDebug($value = true)
    {
        self::$withDebug = (bool) $value;
    }

    /**
     * @param callable $callback
     */
    public static function register(callable $callback = null)
    {
        $run = new Run();

        if ($callback || self::$withDebug) {
            $pretty = new PrettyPageHandler();
            $json   = new JsonResponseHandler();
            $plain  = new PlainTextHandler();

            if (self::$withDebug) {
                $run->pushHandler($pretty);

                $json->onlyForAjaxRequests(true);
                $run->pushHandler($json);

                $plain->onlyForCommandLine(true);
                $run->pushHandler($plain);
            }
            if ($callback) {
                $callback($run, $pretty, $json, $plain);
            }
        }
        $run->pushHandler(new self());
        $run->register();
    }


    /**
     * Run callback
     *
     * @param callable $callback
     * @return mixed
     */
    private function run(callable $callback)
    {
        $exception = $this->getException();
        $inspector = $this->getInspector();
        $run       = $this->getRun();

        $response = $callback($exception, $inspector, $run);

        if ($exception instanceof HttpExceptionInterface !== false) {
            $run->sendHttpCode($exception->getCode());
            return self::QUIT;
        }
        return $response;
    }

    /**
     * Get callback listened exception class name
     *
     * @param callable $callback
     * @return bool|string
     */
    private function getListenedClassName(callable $callback)
    {
        $params = $this->getReflection($callback)->getParameters();
        if (isset($params[0])) {
            $class = $params[0]->getClass();
            if ($class) {
                return $class->getName();
            }
        }
        return false;
    }

    /**
     * Get callback reflection
     *
     * @param callable|object $callback
     * @return \ReflectionFunction|\ReflectionMethod
     * @throws \InvalidArgumentException
     */
    private function getReflection(callable $callback)
    {
        if ((is_string($callback) && is_callable($callback)) || $callback instanceof \Closure) {
            // Callback is function
            return new ReflectionFunction($callback);
        } elseif (is_array($callback) && is_callable($callback)) {
            // callback is class method
            list($class, $method) = $callback;
            return new ReflectionMethod($class, $method);
        } elseif (is_object($callback)) {
            // callback is object, so try to find __invoke method
            $reflection = new ReflectionObject($callback);
            if ($reflection->hasMethod('__invoke')) {
                return new ReflectionMethod($callback, '__invoke');
            }
        }
        throw new InvalidArgumentException("Unknown callback type", 500);
    }

    /**
     * Get exception lineage
     *
     * @return array
     */
    private function getLineage()
    {
        $exceptionClass = get_class($this->getException());
        return array_merge(
            [$exceptionClass => true],
            class_parents($exceptionClass),
            class_implements($exceptionClass),
            class_uses($exceptionClass)
        );
    }
}