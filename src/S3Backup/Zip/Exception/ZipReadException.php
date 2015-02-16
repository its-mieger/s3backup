<?php

	namespace S3Backup\Zip\Exception;

	class ZipReadException extends \Exception
	{

		public function __construct($message = '', $code = 0, \Exception $previous = null) {
			if (empty($message))
				$message = 'Could not read stream';

			parent::__construct($message, $code, $previous);
		}

	}