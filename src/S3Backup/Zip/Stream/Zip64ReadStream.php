<?php

	namespace S3Backup\Zip\Stream;

	use S3Backup\Zip\Zip64Reader;

	class Zip64ReadStream {

		/**
		 * The stream context
		 * @var Resource
		 */
		public $context;

		/**
		 * @var Resource
		 */
		protected $baseStream;
		protected $position = 0;
		protected $fileDataOffset = 0;
		protected $fileDataSize = 0;

		protected $closeBaseStream = true;

		public function stream_open($path, $mode) {
			$lastHashPos = strrpos($path, '#');
			$firstSchemaPos = strpos($path, '://');

			$opt = stream_context_get_options($this->context);
			$zipOpt = (!empty($opt['zip64.read']) ? $opt['zip64.read'] : array());

			// base stream
			if (!empty($zipOpt['baseStream'])) {
				// use base stream from context
				$this->baseStream = $zipOpt['baseStream'];
				$this->closeBaseStream = false;
			}
			else {
				// open base stream from path
				$baseStreamString = substr($path, $firstSchemaPos + 3, $lastHashPos - ($firstSchemaPos + 3));
				$this->baseStream = fopen($baseStreamString, $mode);
			}

			if ($this->baseStream === false)
				return false;

			// file to read from archive
			if (!empty($zipOpt['fileLocation'])) {
				// location from context
				$location = $zipOpt['fileLocation'];
			}
			else {
				// read CDR for specified file's location

				// check if there was an archived file specified
				if ($lastHashPos === false)
					return false;

				$fileToRead = substr($path, $lastHashPos + 1);

				// the file name is specified, so use Zip64Reader to get file location
				$zipReader = new Zip64Reader($this->baseStream);
				$zipReader->readIndex();
				$location = $zipReader->getFileLocation($fileToRead);
				if (empty($location))
					return false;

			}

			// set position and seek to start
			$this->position = $this->fileDataOffset = reset($location);
			$this->fileDataSize   = end($location);
			if (fseek($this->baseStream, $this->fileDataOffset, SEEK_SET) == 0)
				return true;
			else
				return false;
		}

		public function stream_read($count) {
			// seek to position
			if (ftell($this->baseStream) != $this->position) {
				if (fseek($this->baseStream, $this->position, SEEK_SET) !== 0)
					return false;
			}


			// limit to file size
			$count = min($this->fileDataOffset + $this->fileDataSize - $this->position, $count);

			if ($count == 0)
				return false;

			$ret = fread($this->baseStream, $count);

			if ($ret !== false)
				$this->position += strlen($ret);

			return $ret;
		}

		public function stream_seek($offset, $whence) {
			switch ($whence) {
				case SEEK_SET:
					if ($offset < $this->fileDataSize && $offset >= 0)
						$this->position = $this->fileDataOffset + $offset;
					else
						return false;

					break;
				case SEEK_CUR:
					if ($this->position + $offset >= $this->fileDataOffset && $this->position + $offset < $this->fileDataOffset + $this->fileDataSize)
						$this->position += $offset;
					else
						return false;

					break;

				case SEEK_END:
					if ($this->fileDataOffset + $this->fileDataSize + $offset >= $this->fileDataOffset && $offset <= 0)
						$this->position = $this->fileDataOffset + $this->fileDataSize + $offset;
					else
						return false;

					break;

				default:
					return false;
			}

			if (fseek($this->baseStream, $this->position, SEEK_SET) == 0)
				return true;
			else
				return false;
		}

		public function stream_tell() {
			return $this->position - $this->fileDataOffset;
		}

		public function stream_eof() {
			return $this->position >= $this->fileDataOffset + $this->fileDataSize;
		}

		public function stream_stat() {
			$baseStat = fstat($this->baseStream);

			if ($baseStat === false)
				return false;

			$baseStat[7] = $this->fileDataSize;
			$baseStat['size'] = $this->fileDataSize;

			return $baseStat;
		}

		public function stream_close() {
			if ($this->closeBaseStream)
				return fclose($this->baseStream);
			else
				return true;
		}

	}

	// register handler
	stream_wrapper_register("zip64.read", "\\S3Backup\\Zip\\Stream\\Zip64ReadStream");