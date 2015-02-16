<?php

	namespace S3Backup\Zip\Exception;

	class ZipNotStreamingException extends \Exception
	{

		public function __construct($message = '', $code = 0, \Exception $previous = null) {
			if (empty($message))
				$message = 'File streaming must be active for this operation';

			parent::__construct($message, $code, $previous);
		}

	}