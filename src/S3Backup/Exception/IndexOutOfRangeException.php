<?php

	namespace S3Backup\Exception;

	class IndexOutOfRangeException extends \Exception {

		protected $index;

		public function __construct($objectKey, $message = '', $code = 0, \Exception $previous = null) {
			$this->index = $objectKey;

			if (empty($message))
				$message = 'Index ' . $objectKey . ' is out of range';

			parent::__construct($message, $code, $previous);
		}

		/**
		 * Gets the index which was out of range
		 * @return int The index
		 */
		public function getIndex() {
			return $this->index;
		}


	}