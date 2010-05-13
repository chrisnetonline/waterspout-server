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

// Config settings for server.
// Values are separated into dispatcher and server to allow for fine grained control.
$config = array('dispatcher' => array(),
                'server'     => array()
                );

/**
 * Server values.
 *
 * These values are used by server.php, http*.php and io*.php.
 */

// The address to bind the server to. Set to 0.0.0.0 to bind to all addresses.
$config['server']['SERVER_ADDRESS'] = '0.0.0.0';

// The port(s) to bind to. You need at least one port, but the value must be an array.
$config['server']['SERVER_PORT'] = array(7777);

// The ports(s) for SSL. You may set this value to an empty array if you do not want or
// need to support SSL communication.
$config['server']['SSL_SERVER_PORT'] = array();

// The path to the SSL cert. Leave it as null if you do not want or need to support SSL.
$config['server']['SSL_CERT_PATH'] = null;

// The level of logging for the server.
// 0 = no logging.
// 1 = <not currently used for server>
// 2 = log request headers only
// 3 = log all headers and bodies.
$config['server']['VERBOSE'] = 0;

// The full path to the log file. Make sure this file is writable.
$config['server']['LOG_FILE'] = '/tmp/waterspout';

// Whether or not to use XHProf for profiling. If you set this value to true but do not
// install XHProf, nothing terrible will happen. The code checks for the required
// classes and methods before doing any profiling.
$config['server']['TIMING'] = false;

// The XHProf root directory. This directory should contain xhprof_lib.
$config['server']['XHPROF_ROOT'] = '/var/www/html';

// The file containing blocked IP addresses. The file should return an array of IP
// addresses. If a white list is defined, the black list will be ignored. Updating this
// file requires a restart of the server.
$config['server']['IP_BLACKLIST'] = _CONFIG . DIRECTORY_SEPARATOR . 'ipblacklist.php';

// The file containing white listed IP addresses. The file should return an array of IP
// addresses. If this file returns anything, the black list will be ignored. Updating
// this file, requires a restart of the server.
// $config['server']['IP_WHITELIST'] = _CONFIG . DIRECTORY_SEPARATOR . 'ipwhitelist.php';

// The number of successive request that may be made per connection. Set this value to
// 0 to disable keep-alive connections.
$config['server']['KEEPALIVE_MAX_REQUESTS'] = 10;

// The timeout value for keep alive connections. If the client can't make the next
// request in X seconds, it should open a new connection.
$config['server']['KEEPALIVE_TIMEOUT'] = 10;

/**
 * Dispatcher values.
 *
 * These values are used by dispatcher.php and controllers.
 */

// The default file name to be used when a user requests a directory.
$config['dispatcher']['DEFAULT_FILENAME'] = 'index.html';

// The path to your controllers. The dispatcher will look for your controllers in this
// directory. If it doesn't find the appropriate controller, it will assume the request
// was for content.
$config['dispatcher']['CONTROLLER_PATH'] = _BASE . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR;

// The default timezone for dates in the system.
// See http://www.php.net/date_default_timezone_set
$config['dispatcher']['DEFAULT_TIMEZONE'] = 'America/New_York';

// The path to your content directory. This directory should hold images, HTML,
// PHP files and other content. Controllers should not be in this directory.
$config['dispatcher']['WEBROOT'] = _BASE . DIRECTORY_SEPARATOR . 'htdocs';

// The file containing your 404 message. This can be static or dynamic (a PHP file).
$config['dispatcher']['404_PATH'] = $config['dispatcher']['WEBROOT'] . DIRECTORY_SEPARATOR . '404.html';

// The clean up interval. Every X seconds the dispatcher will send a 408 (request
// timeout) response. This keeps the server running nice and light. Most browsers will
// automatically reestablish the connection without the user ever knowing.
// To disable this feature, set the value to 0.
$config['dispatcher']['CLEANUP_INTERVAL'] = 0;

// See $config['server']['TIMING'].
$config['dispatcher']['TIMING'] = $config['server']['TIMING'];

// The level of logging for the dispatcher.
// 0 = no logging.
// 1 = log summary request data.
$config['dispatcher']['VERBOSE'] = 0;

// The full path to the log file. Make sure this file is writable.
$config['dispatcher']['LOG_FILE'] = $config['server']['LOG_FILE'];

// The file where errors from dynamic pages will be logged. Make sure this file is
// writable. To control what gets logged and what does not, adjust the php.ini settings
// for the DYNAMIC_PHP_INI file.
$config['dispatcher']['ERROR_LOG_FILE'] = $config['dispatcher']['LOG_FILE'];

// The path to the INI file for dynamically generated pages.
$config['dispatcher']['DYNAMIC_PHP_INI'] = PHP_CONFIG_FILE_PATH . DIRECTORY_SEPARATOR . 'php.ini';

// The number of seconds a file should be cached for. This value will be used to set the
// Cache-Expiration header.
$config['dispatcher']['CACHE_EXPIRATION'] = 3600 * 24;

// The path to the magic mime file. This file is used by the finfo_* methods.
$config['dispatcher']['MAGIC_PATH'] = '/usr/share/file/magic.mime';

// Path to mime type mappings. This file should be a PHP file that returns an array as
// the last thing it does. The array should be an associative array with the file
// extension as the key and the mime type as the value.
$config['dispatcher']['MIME'] = @include_once _CONFIG . DIRECTORY_SEPARATOR . 'mimetypes.php';

// This file must return the config array. Don't remove this line.
return $config;
?>