<?php

namespace Shopware\SitionSooqr\Components;

use DateTime;

class Log
{
	static $instance;

	public static function instance()
	{
		if( empty(static::$instance) )
		{
			static::$instance = new static;
		}

		return static::$instance;
	}

	public static function logDir()
	{
		return __DIR__ . "/../logs/";
	}

	public function log($level, $message)
	{
		$args = func_get_args();
		$level = array_shift($args);

		$today = (new DateTime)->format("Y-m-d");
		$path = static::logDir() . $today . ".log";

		$now = (new DateTime)->format("Y-m-d H:i:s");

		foreach( $args as $key => $arg ) 
		{
			$argString = !is_string($arg) || !is_numeric($arg) ? print_r($arg, true) : $arg;

			$line = $now . " - " . $level . " - " . $argString . "\r\n";
			file_put_contents($path, $line, FILE_APPEND);
		}
	}

	public function info()
	{
		$args = func_get_args();
		array_unshift($args, 'info');
		call_user_func_array([ $this, 'log' ], $args);
	}

	public function error()
	{
		$args = func_get_args();
		array_unshift($args, 'error');
		call_user_func_array([ $this, 'log' ], $args);
	}

	public function warn()
	{
		$args = func_get_args();
		array_unshift($args, 'warn');
		call_user_func_array([ $this, 'log' ], $args);
	}
}