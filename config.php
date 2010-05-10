<?php
// Config settings for server.
$config = array('dispatcher' => array(),
                'server'     => array()
                );

$config['server']['CONFIG_PATH']     = __FILE__;
$config['dispatcher']['CONFIG_PATH'] = __FILE__;

// Server values.
$config['server']['SERVER_ADDRESS']      = '0.0.0.0'; //'127.0.0.1'; // Set to 0.0.0.0 to bind to all addresses
$config['server']['SERVER_PORT']         = array(7777);
$config['server']['SSL_SERVER_PORT']     = array();
$config['server']['SSL_CERT_PATH']       = __DIR__ . DIRECTORY_SEPARATOR . 'host.pem';
$config['server']['VERBOSE']             = 0;
$config['server']['LOG_FILE']            = '/tmp/waterspout';
$config['server']['TIMING']              = true;
$config['server']['XHPROF_ROOT']         = '/var/www/html';

// Dispatcher values.
$config['dispatcher']['DEFAULT_FILENAME'] = 'index.html';
$config['dispatcher']['LOG_REQUESTS']     = false;
$config['dispatcher']['LOG_FILE']         = $config['server']['LOG_FILE'];
$config['dispatcher']['HANDLER_PATH']     = __DIR__ . DIRECTORY_SEPARATOR . 'handlers' . DIRECTORY_SEPARATOR;
$config['dispatcher']['DEFAULT_TIMEZONE'] = 'America/New_York';
$config['dispatcher']['404_MESSAGE']      = 'What are you looking for?';
$config['dispatcher']['STATIC_CONTENT']   = __DIR__ . DIRECTORY_SEPARATOR . 'static';
$config['dispatcher']['CLEANUP_INTERVAL'] = 0;
$config['dispatcher']['TIMING']           = $config['server']['TIMING'];
$config['dispatcher']['VERBOSE']          = 0;

// The number of seconds a file should be cached for.
$config['dispatcher']['CACHE_EXPIRATION'] = 3600 * 24;

// Mime type mappings.
$config['dispatcher']['MAGIC_PATH']       = '/usr/share/file/magic.mime';
$config['dispatcher']['MIME']             = @include_once __DIR__ . DIRECTORY_SEPARATOR . 'mimetypes.php';

return $config;
?>