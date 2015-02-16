<?php

	namespace S3Backup\Zip\Exception;

	class ZipBeginFileException extends \Exception
	{

		public function __construct($message = '', $code = 0, \Exception $previous = null) {
			if (empty($message))
				$message = 'Cannot begin new file streaming while another file streaming is running';

			parent::__construct($message, $code, $previous);
		}

	}