<?php
class File_Controller extends Controller
{
	/**
	 * The pipe which will contain the dynamic content.
	 *
	 * @access private
	 * @var    resource
	 */
	private $_dynamic_pipes;

	/**
	 * The process that is handling the dynamic content generation.
	 *
	 * @access private
	 * @var    resource
	 */
	private $_dynamic_process;

	/**
	 * The dynamic content.
	 *
	 * @access private
	 * @var    string
	 */
	private $_dynamic_content = '';

	/**
	 * Reads a file and sends the contents to the client.
	 *
	 * @access public
	 * @return void
	 */
	public function get()
	{
		// Make sure the file exists.
		$sanitized_uri = str_replace('/file/get/', '', $this->request->get_uri());
		$config        = $this->dispatcher->get_config();
		$path          = str_replace('/', DIRECTORY_SEPARATOR, $config['WEBROOT'] . DIRECTORY_SEPARATOR . $sanitized_uri);

		// If a directory was specified, try to load the default file.
		if (is_dir($path))
		{
			// If there is no trailing slash, add one.
			if (!empty($sanitized_uri) && substr($sanitized_uri, -1) != '/')
			{
				$response = $this->_301('/' . $sanitized_uri . '/');
				$this->write($response);

				return;
			}

			$path.= $config['DEFAULT_FILENAME'];
		}

		if (!file_exists($path))
		{
			$response = $this->_notfound($path);
		}
		else
		{
			// Check to see if the file has been modified since it was last sent.
			if ($this->request->get_headers()->get('If-Modified-Since') &&
			    !self::_check_modified($this->request->get_headers()->get('If-Modified-Since'), $path)
			    )
			{
				$response = $this->_304($path);
			}
			else
			{
				$response = $this->_found($path);
			}
		}

		if ($response instanceof HTTPResponse)
		{
			$this->write($response);
		}
	}

	/**
	 * Returns a 404 response.
	 *
	 * @access private
	 * @param  string  $path
	 * @return HTTPResponse
	 */
	private function _notfound($path)
	{
		// Set the custom message if we have one.
		$config = $this->dispatcher->get_config();
		if (isset($config['404_PATH']))
		{
			$response = $this->_found($config['404_PATH']);
			$response->set_status(404);
		}
		else
		{
			$response = new HTTPResponse(404);
			$response->set_body("Page not found\r\n" . $path, false);
		}

		return $response;
	}

	/**
	 * Returns a response containing the contents of the requested file.
	 *
	 * @access private
	 * @param  string  $path
	 * @return HTTPResponse
	 */
	private function _found($path)
	{
		$config = $this->dispatcher->get_config();

		if (!($mime = $this->_get_mime_type($path)))
		{
			$finfo = finfo_open(FILEINFO_MIME_TYPE, $config['MAGIC_PATH']);
			$mime  = finfo_file($finfo, $path);
		}

		$ext = substr($path, strrpos($path, '.') + 1);
		if ($ext == 'php')
		{
			$this->_found_dynamic($path, $config, $mime);
		}
		else
		{
			return $this->_found_static($path, $config, $mime);
		}
	}

	/**
	 * Returns the generated contents of a dynamic file.
	 *
	 * @access private
	 * @param  string  $path
	 * @param  array   $config
	 * @param  string  $mime
	 * @return void
	 */
	private function _found_dynamic($path, array $config, $mime)
	{
		// Open a process to execute the content.
		$descriptors = array(0 => array('pipe', 'r'),
		                     1 => array('pipe', 'w'),
		                     2 => array('file', $config['ERROR_LOG_FILE'], 'a')
		                     );

		$cwd = dirname($path);

		// TODO: Figure out what to pass in as $_ENV.
		$env   = array();
		$pipes = array();

		$this->_dynamic_process = proc_open('php-cgi -c ' . $config['DYNAMIC_PHP_INI'], $descriptors, $pipes, $cwd, $env);
		$this->_dynamic_pipes   = $pipes;

		// Make sure it worked.
		if (!is_resource($this->_dynamic_process))
		{
			return $this->_notfound($path);
		}

		// Write the super globals to the process.
		$setup = '<?php $_GET = ' . var_export($this->request->get_get(), true) . '; ';
		$setup.= ' $_POST = ' . var_export($this->request->get_post(), true) . '; ';
		$setup.= ' $_COOKIE = ' . var_export($this->request->get_cookie(), true) . '; ';
		$setup.= ' $_REQUEST = array_merge($_COOKIE, $_POST, $_GET); $_SERVER[\'argv\'][0] = \'' . $path . '\'; $_SERVER[\'argc\'] = 1;?>';

		// Send the contents of the file so that it can be executed.
		fwrite($this->_dynamic_pipes[0], $setup . file_get_contents($path));
		fclose($this->_dynamic_pipes[0]);

		// Put the reading stream into the loop so that we can continue doing other
		// stuff.
		$loop = $this->dispatcher->get_server()->get_loop();
		$loop->add_handler($this->_dynamic_pipes[1], array($this, 'write_dynamic'), IOLoop::READ);
	}

	/**
	 * Returns the contents of a static file.
	 *
	 * @access private
	 * @param  string  $path
	 * @param  array   $config
	 * @param  string  $mime
	 * @return HTTPResponse
	 */
	private function _found_static($path, array $config, $mime)
	{
		$response = new HTTPResponse(200);

		// Add the headers first so that we can set the right content length.
		$headers = array('Content-Type'  => $mime,
		                 'Date'          => date('D, d M Y H:i:s \G\M\T'),
		                 'Expires'       => date('D, d M Y H:i:s \G\M\T', time() + $config['CACHE_EXPIRATION']),
		                 'Last-Modified' => date('D, d M Y H:i:s \G\M\T', filemtime($path)),
		                 'Cache-Control' => 'max-age=' . $config['CACHE_EXPIRATION']
		                 );
		$response->add_headers($headers);

		$response->set_body(file_get_contents($path), false);

		return $response;
	}

	/**
	 * Sends a 301 redirect.
	 *
	 * @access private
	 * @param  string $url
	 * @return httpresponse
	 */
	private function _301($url)
	{
		$response = new HTTPResponse(301);
		$response->add_header('Location', $url);

		return $response;
	}

	/**
	 * Sends a 304 not modified response.
	 *
	 * @access private
	 * @param  string $path
	 * @return httpresponse
	 */
	private function _304($path)
	{
		$config = $this->dispatcher->get_config();

		$response = new HTTPResponse(304);

		$headers = array('Date'          => date('D, d M Y H:i:s \G\M\T'),
		                 'Expires'       => date('D, d M Y H:i:s \G\M\T', time() + $config['CACHE_EXPIRATION']),
		                 'Last-Modified' => date('D, d M Y H:i:s \G\M\T', filemtime($path)),
		                 'Cache-Control' => 'max-age=' . $config['CACHE_EXPIRATION']
		                 );
		$response->add_headers($headers);

		return $response;
	}

	/**
	 * Determines content type from extension.
	 *
	 * @access private
	 * @param  string  $path
	 * @return string
	 */
	private function _get_mime_type($path)
	{
		$config = $this->dispatcher->get_config();
		$types  = $config['MIME'];

		$ext = substr($path, strrpos($path, '.') + 1);

		if (array_key_exists($ext, $types))
		{
			return $types[$ext];
		}

		return null;
	}

	/**
	 * Compares the modified since header with the modification time of the file.
	 *
	 * @static
	 * @access private
	 * @param  string  $since
	 * @param  string  $path
	 * @return boolean
	 */
	private static function _check_modified($since, $path)
	{
		$ext = substr($path, strrpos($path, '.') + 1);
		return ($ext != 'php' &&
		        strtotime(date('D, d M Y H:i:s \G\M\T', filemtime($path))) > strtotime($since)
		        );
	}

	/**
	 * Writes the dynamic contents to the response.
	 *
	 * @access public
	 * @return void
	 */
	public function write_dynamic()
	{
		$attempts = 0;

		while ($attempts++ <= 10)
		{
			$chunk = fread($this->_dynamic_pipes[1], IOStream::MAX_BUFFER_SIZE);

			if (!empty($chunk))
			{
				break;
			}
		}

		if (empty($chunk) && empty($this->_dynamic_content))
		{
			// Write an error response.
			$response = new HTTPResponse(500);
			$response->set_body('Trouble generating content.');
		}
		else
		{
			$this->_dynamic_content.= $chunk;

			// Check to see if the process has finished.
			$info = proc_get_status($this->_dynamic_process);

			if (!$info['running'])
			{
				// Make sure the proccess finished successfully.
				if ($info['exitcode'] !== 0)
				{
					$response = new HTTPResponse(500);
				}
				else
				{
					$response = new HTTPResponse(200);
				}

				// Split the chunk into headers and content.
				list($headers, $content) = explode("\r\n\r\n", $this->_dynamic_content, 2);

				// Set the headers.
				foreach (explode("\r\n", $headers) as $header)
				{
					list($h, $v) = explode(':', $header);
					$response->add_header($h, trim($v));
				}

				// Add the body content.
				$response->set_body($content, false);//$info['exitcode'] === 0);
			}
		}

		if (isset($response) && $response instanceof HTTPResponse)
		{
			// Send the response.
			$this->write($response);

			// Remove the handler.
			$loop = $this->dispatcher->get_server()->get_loop();
			$loop->remove_handler($this->_dynamic_pipes[1]);

			// Close all the pipes.
			foreach ($this->_dynamic_pipes as $pipe)
			{
				if (is_resource($pipe))
				{
					fclose($pipe);
				}
			}

			// Close the process.
			proc_close($this->_dynamic_process);
		}
	}

	/**
	 * Processes the given event.
	 *
	 * @access public
	 * @return void
	 */
	public function process_event(Controller $controller = null)
	{
		return;
	}
}
?>