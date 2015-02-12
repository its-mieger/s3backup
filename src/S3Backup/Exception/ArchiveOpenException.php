<?php

	namespace S3Backup\Exception;

	class ArchiveOpenException extends \Exception
	{

		protected $filename;
		protected $errorCode;

		public function __construct($filename, $errorCode, $message = '', $code = 0, \Exception $previous = null) {
			$this->filename  = $filename;
			$this->errorCode = $errorCode;

			if (empty($message))
				$message = 'Could not open archive ' . $filename . '';

			parent::__construct($message, $code, $previous);
		}

		/**
		 * Gets the filename of the archive
		 * @return int The filename
		 */
		public function getFilename() {
			return $this->filename;
		}

		/**
		 * Gets the error code
		 * @return int The error code
		 */
		public function getErrorCode() {
			return $this->errorCode;
		}


	}