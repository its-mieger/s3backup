<?php

	namespace S3Backup\Exception;

	class ArchiveOpenException extends \Exception
	{

		protected $filename;

		public function __construct($filename, $message = '', $code = 0, \Exception $previous = null) {
			$this->filename  = $filename;

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
	}