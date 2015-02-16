<?php

	namespace S3Backup\Zip\Exception;

	class ZipFileNotFoundException extends \Exception
	{

		protected $filename;

		public function __construct($filename, $message = '', $code = 0, \Exception $previous = null) {
			$this->filename = $filename;

			if (empty($message))
				$message = 'File "' . $filename . '" not found in archive';

			parent::__construct($message, $code, $previous);
		}

		/**
		 * Gets the name of the file not found
		 * @return string The file path
		 */
		public function getFilename() {
			return $this->filename;
		}

	}