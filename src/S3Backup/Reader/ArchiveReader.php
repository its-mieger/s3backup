<?php

	namespace S3Backup\Reader;

	use S3Backup\Exception\ArchiveOpenException;
	use S3Backup\Exception\IndexOutOfRangeException;
	use S3Backup\Exception\NotInitException;
	use S3Backup\Exception\ObjectReadException;
	use S3Backup\S3Object;
	use S3Backup\Writer\ArchiveWriter;
	use S3Backup\Zip\Zip64Reader;

	class ArchiveReader extends AbstractReader {

		const DATA_DIR = '_DATA';
		const META_DIR = '_META';

		protected $file;
		/**
		 * @var Zip64Reader
		 */
		protected $zipReader;

		/**
		 * @var resource
		 */
		protected $zipStream;

		protected $objectKeys = array();

		/**
		 * Creates a new instance
		 * @param string $file The file to read from
		 */
		public function __construct($file) {
			$this->file = $file;
		}

		/**
		 * Gets the object keys in the archive
		 * @return array
		 */
		public function getObjectKeys() {
			return $this->objectKeys;
		}


		/**
		 * Initializes the reader. Has to be called before first use of the reader.
		 */
		public function init() {
			$this->zipStream = fopen($this->file, 'r');
			$this->zipReader = new Zip64Reader($this->zipStream);

			// read index
			try {
				$this->zipReader->readIndex();
			}
			catch(\Exception $ex) {
				$this->zipReader = null;
				throw new ArchiveOpenException($this->file, '', 0, $ex);
			}


			// get object keys
			$this->objectKeys = array();
			$files = $this->zipReader->getFiles();
			foreach($files as $curr) {
				if (substr($curr, 0, strlen(ArchiveWriter::DATA_DIR . '/')) == ArchiveWriter::DATA_DIR . '/')
					$this->objectKeys[] = substr($curr, strlen(ArchiveWriter::DATA_DIR . '/'));
			}
		}

		/**
		 * Gets the key of the object with specified index
		 * @param int $index Index of the object to get key
		 * @throws IndexOutOfRangeException
		 * @throws NotInitException
		 * @return string The object key
		 */
		public function getObjectKey($index) {
			if (empty($this->zipReader))
				throw new NotInitException();
			if (!isset($this->objectKeys[$index]))
				throw new IndexOutOfRangeException($index);

			return $this->objectKeys[$index];
		}


		/**
		 * Reads an object with specified index
		 * @param int $index Index of the object to read
		 * @throws IndexOutOfRangeException
		 * @throws NotInitException
		 * @throws ObjectReadException
		 * @return S3Object The read object
		 */
		public function readObject($index) {
			if (empty($this->zipReader))
				throw new NotInitException();
			if (!isset($this->objectKeys[$index]))
				throw new IndexOutOfRangeException($index);

			$key = $this->objectKeys[$index];

			$ret = new S3Object($key);

			$fnBody = ArchiveWriter::DATA_DIR . '/' . $key;
			$fnData = ArchiveWriter::META_DIR . '/' . $key . '.ser';

			// read body
			try {
				$ret->setStream($this->zipReader->getFileStream($fnBody));
			}
			catch(\Exception $ex) {
				throw new ObjectReadException($key, '', 0, $ex);
			}

			// read additional data
			$additionalDataStream = $this->zipReader->getFileStream($fnData);
			$dat = '';
			while (($r = fread($additionalDataStream, 1024))) {
				$dat .= $r;
			}

			if ($dat !== false && ($dat = unserialize($dat)) !== false)
				$ret->unpackAdditionalData($dat);
			else
				throw new ObjectReadException($key);


			return $ret;

		}

		/**
		 * Returns the number of objects which can be read from this reader.
		 * @throws NotInitException
		 * @return int The number of objects
		 */
		public function countObjects() {

			if (empty($this->zipReader))
				throw new NotInitException();

			return count($this->objectKeys);
		}

		/**
		 * Closes the archive
		 */
		public function close() {
			if (empty($this->zipReader))
				throw new NotInitException();

			fclose($this->zipStream);
		}

	}