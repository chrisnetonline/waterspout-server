<?php
// Config settings for server.
$config = array('dispatcher' => array(),
                'server'     => array()
                );

$config['server']['CONFIG_PATH']     = __FILE__;
$config['dispatcher']['CONFIG_PATH'] = __FILE__;

// Server values.
$config['server']['SERVER_ADDRESS']      = '0.0.0.0'; //'127.0.0.1'; // Set to 0.0.0.0 to bind to all addresses
$config['server']['SERVER_PORT']         = array(7777, 7778);
$config['server']['SSL_SERVER_PORT']     = array(7779);
$config['server']['MAX_CONNECTIONS']     = 128;
$config['server']['NO_KEEP_ALIVE']       = true;
$config['server']['SSL_CERT_PATH']       = __DIR__ . DIRECTORY_SEPARATOR . 'host.pem';
$config['server']['verbose']             = 0;
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
$config['dispatcher']['verbose']          = 0;

// The number of seconds a file should be cached for.
$config['dispatcher']['CACHE_EXPIRATION'] = 3600 * 24;

// Mime type mappings.
$config['dispatcher']['MAGIC_PATH']       = '/usr/share/file/magic.mime';
$config['dispatcher']['MIME']             = array('txt'  => 'text/plain',
												  'htm'  => 'text/html',
												  'html' => 'text/html',
												  'php'  => 'text/html',
												  'css'  => 'text/css',
												  'js'   => 'application/javascript',
												  'json' => 'application/json',
												  'xml'  => 'application/xml',
												  'swf'  => 'application/x-shockwave-flash',
												  'flv'  => 'video/x-flv',
												  // images
												  'png'  => 'image/png',
												  'jpe'  => 'image/jpeg',
												  'jpeg' => 'image/jpeg',
												  'jpg'  => 'image/jpeg',
												  'gif'  => 'image/gif',
												  'bmp'  => 'image/bmp',
												  'ico'  => 'image/vnd.microsoft.icon',
												  'tiff' => 'image/tiff',
												  'tif'  => 'image/tiff',
												  'svg'  => 'image/svg+xml',
												  'svgz' => 'image/svg+xml',
												  // archives
												  'zip'  => 'application/zip',
												  'rar'  => 'application/x-rar-compressed',
												  'exe'  => 'application/x-msdownload',
												  'msi'  => 'application/x-msdownload',
												  'cab'  => 'application/vnd.ms-cab-compressed',
												  // audio/video
												  'mp3'  => 'audio/mpeg',
												  'qt'   => 'video/quicktime',
												  'mov'  => 'video/quicktime',
												  // adobe
												  'pdf'  => 'application/pdf',
												  'psd'  => 'image/vnd.adobe.photoshop',
												  'ai'   => 'application/postscript',
												  'eps'  => 'application/postscript',
												  'ps'   => 'application/postscript',
												  // ms office
												  'doc'  => 'application/msword',
												  'rtf'  => 'application/rtf',
												  'xls'  => 'application/vnd.ms-excel',
												  'ppt'  => 'application/vnd.ms-powerpoint',
												  // open office
												  'odt'  => 'application/vnd.oasis.opendocument.text',
												  'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
												  );

return $config;
?>