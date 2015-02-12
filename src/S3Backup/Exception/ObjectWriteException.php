<?php

	namespace S3Backup\Exception;

	class ObjectWriteException extends \Exception
	{

		protected $objectKey;

		public function __construct($objectKey, $message = '', $code = 0, \Exception $previous = null) {
			$this->objectKey  = $objectKey;

			if (empty($message))
				$message = 'Could not write object "' . $objectKey . '"';

			parent::__construct($message, $code, $previous);
		}

		/**
		 * Gets the key of the object which could not be written
		 * @return int The object key
		 */
		public function getObjectKey() {
			return $this->objectKey;
		}
	}