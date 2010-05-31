#!/usr/bin/php
<?php
/**
 * This file is part of WaterSpout.
 * 
 * WaterSpout is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * WaterSpout is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with WaterSpout.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package WaterSpout
 * @author  Scott Mattocks <scott@crisscott.com>
 * @author  Chris Lewis <chris@chrisnetonline.com>
 * @license http://www.gnu.org/licenses/lgpl.html LGPL License 3
 * @version SVN: $Id$
 * @link    http://www.spoutserver.com/
 */

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

// Include compatibility fixes.
require_once _LIBS . DIRECTORY_SEPARATOR . 'compat.php';

// Load configuration file.
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