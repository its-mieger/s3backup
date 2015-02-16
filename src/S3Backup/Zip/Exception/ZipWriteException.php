<?php

	namespace S3Backup\Zip\Exception;

	class ZipWriteException extends \Exception
	{

		public function __construct($message = '', $code = 0, \Exception $previous = null) {
			if (empty($message))
				$message = 'Could not write to archive';

			parent::__construct($message, $code, $previous);
		}

	}