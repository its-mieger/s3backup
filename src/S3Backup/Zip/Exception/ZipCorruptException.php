<?php

	namespace S3Backup\Zip\Exception;

	class ZipCorruptException extends \Exception
	{

		public function __construct($message = '', $code = 0, \Exception $previous = null) {
			if (empty($message))
				$message = 'Archive is corrupt';

			parent::__construct($message, $code, $previous);
		}

	}