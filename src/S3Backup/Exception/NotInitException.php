<?php


	namespace S3Backup\Exception;


	class NotInitException extends \Exception {

		public function __construct($message = '', $code = 0, \Exception $previous = null) {
			if (empty($message))
				$message = 'Instance was not init.';

			parent::__construct($message, $code, $previous);
		}
	}