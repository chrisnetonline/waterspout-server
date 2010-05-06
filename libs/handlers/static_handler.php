<?php
class Static_Handler extends Handler
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
		$config = $this->dispatcher->get_config();
		$path   = $config['STATIC_CONTENT'] . DIRECTORY_SEPARATOR . str_replace('/static/get/', '', $this->request->get_uri());

		// If a directory was specified, try to load the default file.
		if (is_dir($path))
		{
			if (substr($path, -1) != DIRECTORY_SEPARATOR)
			{
				$path.= DIRECTORY_SEPARATOR;
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

		$this->write($response);
	}

	/**
	 * Returns a 404 response.
	 *
	 * @access private
	 * @param  string  $path
	 * @return httpresponse
	 */
	private function _notfound($path)
	{
		$response = new HTTPResponse(404);

		// Set the custom message if we have one.
		$config = $this->dispatcher->get_config();
		if (isset($config['404_MESSAGE']))
		{
			$response->set_body($config['404_MESSAGE'] . "\r\n" . $path, false);
		}
		else
		{
			$response->set_body("Page not found\r\n" . $path, false);
		}

		return $response;
	}

	/**
	 * Returns a response containing the contents of the requested file.
	 *
	 * @access private
	 * @param  string  $path
	 * @return httpresponse
	 */
	private function _found($path)
	{
		$config = $this->dispatcher->get_config();

		$response = new HTTPResponse(200);

		if (!($mime = $this->_get_mime_type($path)))
		{
			$finfo = finfo_open(FILEINFO_MIME_TYPE, $config['MAGIC_PATH']);
			$mime  = finfo_file($finfo, $path);
		}

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
		return (strtotime(date('D, d M Y H:i:s \G\M\T', filemtime($path))) > strtotime($since));
	}

	/**
	 * Processes the given event.
	 *
	 * @access public
	 * @return void
	 */
	public function process_event(Handler $handler = null)
	{
		return;
	}
}
?>