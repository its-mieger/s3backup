<?php

	namespace S3Backup\Reader;

	use S3Backup\Exception\ArchiveCloseException;
	use S3Backup\Exception\ArchiveOpenException;
	use S3Backup\Exception\IndexOutOfRangeException;
	use S3Backup\Exception\NotInitException;
	use S3Backup\Exception\ObjectReadException;
	use S3Backup\S3Object;
	use S3Backup\Writer\ArchiveWriter;
	use ZipArchive;

	class ArchiveReader extends AbstractReader {

		const DATA_DIR = '_DATA';
		const META_DIR = '_META';

		protected $file;
		/**
		 * @var ZipArchive
		 */
		protected $archive;

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
			$this->archive = new ZipArchive();

			$ret = $this->archive->open($this->file);
			if ($ret !== true) {
				$this->archive = null;
				throw new ArchiveOpenException($this->file, $ret);
			}

			$this->objectKeys = array();
			for ($i = 0; $i < $this->archive->numFiles; ++$i) {
				$fn = $this->archive->getNameIndex($i);
				if (substr($fn, 0, strlen(ArchiveWriter::DATA_DIR . '/')) == ArchiveWriter::DATA_DIR . '/')
					$this->objectKeys[] = substr($fn, strlen(ArchiveWriter::DATA_DIR . '/'));
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
			if (empty($this->archive))
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
			if (empty($this->archive))
				throw new NotInitException();
			if (!isset($this->objectKeys[$index]))
				throw new IndexOutOfRangeException($index);

			$key = $this->objectKeys[$index];

			$ret = new S3Object($key);

			$fnBody = ArchiveWriter::DATA_DIR . '/' . $key;
			$fnData = ArchiveWriter::META_DIR . '/' . $key . '.ser';

			// read body
			$bd = $this->archive->getFromName($fnBody);
			if ($bd !== false)
				$ret->setBody($bd);
			else
				throw new ObjectReadException($key);

			// read additional data
			$dat = $this->archive->getFromName($fnData);
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

			if (empty($this->archive))
				throw new NotInitException();

			return count($this->objectKeys);
		}

		/**
		 * Closes the archive
		 * @throws ArchiveCloseException
		 */
		public function close() {
			if (empty($this->archive))
				throw new NotInitException();

			if ($this->archive->close() !== true)
				throw new ArchiveCloseException($this->file);
		}

	}