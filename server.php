#!/usr/bin/php
<?php
ini_set('display_errors', true);
error_reporting(E_ALL);
ini_set('memory_limit', '512M');

// Define some paths.
if (!defined('__DIR__'))
{
	define('__DIR__', dirname(__FILE__));
}
define('_BASE', __DIR__);
define('_CONFIG', __DIR__ . DIRECTORY_SEPARATOR . 'config');
define('_LIBS', __DIR__ . DIRECTORY_SEPARATOR . 'libs');

$config = require _CONFIG . DIRECTORY_SEPARATOR . 'config.php';

// Check for verbosity level from the command line.
if (!empty($argc) && $argc >= 3)
{
	if ($argv[1] == '-v' && is_numeric($argv[2]))
	{
		$config['server']['VERBOSE']     = $argv[2];
		$config['dispatcher']['VERBOSE'] = $argv[2];
	}
}

require_once _LIBS . DIRECTORY_SEPARATOR . 'dispatcher.php';
$loop       = IOLoop::singleton();
$dispatcher = new Dispatcher($config['dispatcher']);
$server     = new HTTPServer(array($dispatcher, 'dispatch'), $loop, $config['server']);
$dispatcher->set_server($server);
$server->listen();
$loop->start();
$server->close();
?>