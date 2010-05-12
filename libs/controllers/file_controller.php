<?php
class File_Controller extends Controller
{
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

		$this->_path = $path;

		// If a directory was specified, try to load the default file.
		if (is_dir($this->_path))
		{
			// If there is no trailing slash, add one.
			if (!empty($sanitized_uri) && substr($sanitized_uri, -1) != '/')
			{
				$response = $this->_301('/' . $sanitized_uri . '/');
				$this->write($response);

				return;
			}

			$this->_path.= $config['DEFAULT_FILENAME'];
		}

		if (!file_exists($this->_path))
		{
			$response = $this->_notfound();
		}
		else
		{
			// Check to see if the file has been modified since it was last sent.
			if ($this->request->get_headers()->get('If-Modified-Since') &&
			    !$this->_check_modified($this->request->get_headers()->get('If-Modified-Since'), $this->_path)
			    )
			{
				$response = $this->_304();
			}
			else
			{
				$response = $this->_found();
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
	 * @return HTTPResponse
	 */
	private function _notfound()
	{
		// Set the custom message if we have one.
		$config = $this->dispatcher->get_config();
		if (isset($config['404_PATH']))
		{
			$this->_path  = $config['404_PATH'];
			$this->status = 404;
			$this->_found();
		}
		else
		{
			$response = new HTTPResponse(404);
			$response->set_body("Page not found\r\n" . $this->_path, false);

			return $response;
		}
	}

	/**
	 * Returns a response containing the contents of the requested file.
	 *
	 * @access private
	 * @return HTTPResponse
	 */
	private function _found()
	{
		$config = $this->dispatcher->get_config();

		if (!($mime = $this->_get_mime_type()))
		{
			$finfo = finfo_open(FILEINFO_MIME_TYPE, $config['MAGIC_PATH']);
			$mime  = finfo_file($finfo, $this->_path);
		}

		$this->_mime = $mime;

		$ext = substr($this->_path, strrpos($this->_path, '.') + 1);
		if ($ext == 'php')
		{
			return $this->_found_dynamic();
		}
		else
		{
			return $this->_found_static();
		}
	}

	/**
	 * Returns the generated contents of a dynamic file.
	 *
	 * @access private
	 * @return mixed
	 */
	private function _found_dynamic()
	{
		// Background the process.
		$config  = $this->dispatcher->get_config();
		$command = 'php-cgi -c ' . $config['DYNAMIC_PHP_INI'];
		$pipe    = $this->background($command, true);

		// If we couldn't open the process, freak out.
		if (!is_resource($pipe))
		{
			return $this->_notfound();
		}

		// Write the super globals to the process.
		$setup = '<?php $_GET = ' . var_export($this->request->get_get(), true) . '; ';
		$setup.= ' $_POST = '     . var_export($this->request->get_post(), true) . '; ';
		$setup.= ' $_COOKIE = '   . var_export($this->request->get_cookie(), true) . '; ';
		$setup.= ' $_REQUEST = array_merge($_COOKIE, $_POST, $_GET); ';
		$setup.= ' $_SERVER[\'SCRIPT_NAME\'] = \'' . basename($this->_path) . '\';?>';

		// Send the contents of the file so that it can be executed.
		fwrite($pipe, $setup . file_get_contents($this->_path));
		fclose($pipe);
	}

	/**
	 * Returns the contents of a static file.
	 *
	 * @access private
	 * @return mixed
	 */
	private function _found_static()
	{
		// Background the process.
		// The command for windows is different.
		$command = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'type' : 'cat';
		$pipe    = $this->background($command . ' ' . $this->_path);

		// If we couldn't open the process, freak out.
		if (!is_resource($pipe))
		{
			return $this->_notfound();
		}

		// Close the pipe.
		fclose($pipe);
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
	 * @return httpresponse
	 */
	private function _304()
	{
		$config = $this->dispatcher->get_config();

		$response = new HTTPResponse(304);

		$headers = array('Date'          => date('D, d M Y H:i:s \G\M\T'),
		                 'Expires'       => date('D, d M Y H:i:s \G\M\T', time() + $config['CACHE_EXPIRATION']),
		                 'Last-Modified' => date('D, d M Y H:i:s \G\M\T', filemtime($this->_path)),
		                 'Cache-Control' => 'max-age=' . $config['CACHE_EXPIRATION']
		                 );
		$response->add_headers($headers);

		return $response;
	}

	/**
	 * Determines content type from extension.
	 *
	 * @access private
	 * @return string
	 */
	private function _get_mime_type()
	{
		$config = $this->dispatcher->get_config();
		$types  = $config['MIME'];

		$ext = substr($this->_path, strrpos($this->_path, '.') + 1);

		if (array_key_exists($ext, $types))
		{
			return $types[$ext];
		}

		return null;
	}

	/**
	 * Compares the modified since header with the modification time of the file.
	 *
	 * @access private
	 * @param  string  $since
	 * @return boolean
	 */
	private function _check_modified($since)
	{
		$ext = substr($this->_path, strrpos($this->_path, '.') + 1);
		return ($ext != 'php' &&
		        strtotime(date('D, d M Y H:i:s \G\M\T', filemtime($this->_path))) > strtotime($since)
		        );
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