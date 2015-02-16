<?php

	namespace S3Backup\Zip\Stream;

	use S3Backup\Zip\Zip64Writer;

	class Zip64WriteStream
	{

		/**
		 * The stream context
		 * @var Resource
		 */
		public $context;

		/**
		 * @var Zip64Writer
		 */
		protected $writer;


		public function stream_open() {
			$opt    = stream_context_get_options($this->context);
			$zipOpt = (!empty($opt['zip64.write']) ? $opt['zip64.write'] : array());

			if (empty($zipOpt['writer']))
				return false;

			$this->writer = $zipOpt['writer'];

			return true;
		}

		public function stream_write($data) {
			try {
				return $this->writer->write($data);
			}
			catch(\Exception $ex) {
				return 0;
			}
		}


		public function stream_close() {
			try {
				$this->writer->endFile();
			}
			catch(\Exception $ex) {
			}
		}

	}

	// register handler
	stream_wrapper_register("zip64.write", "\\S3Backup\\Zip\\Stream\\Zip64WriteStream");