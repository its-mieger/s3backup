<?php

	namespace S3Backup\Exception;

	class BucketCreateException extends \Exception
	{

		protected $bucketName;
		protected $errorCode;

		public function __construct($bucketName, $message = '', $code = 0, \Exception $previous = null) {
			$this->bucketName  = $bucketName;

			if (empty($message))
				$message = 'Could not create bucket ' . $bucketName . '';

			parent::__construct($message, $code, $previous);
		}

		/**
		 * Gets the filename of the archive
		 * @return int The filename
		 */
		public function getBucketName() {
			return $this->bucketName;
		}


	}