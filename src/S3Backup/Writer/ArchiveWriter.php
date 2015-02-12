<?php

	namespace S3Backup\Writer;

	use S3Backup\Exception\ArchiveCloseException;
	use S3Backup\Exception\ArchiveCreateException;
	use S3Backup\Exception\NotInitException;
	use S3Backup\Exception\ObjectWriteException;
	use S3Backup\S3Object;
	use ZipArchive;

	class ArchiveWriter extends AbstractWriter {

		const DATA_DIR = '_DATA';
		const META_DIR = '_META';

		protected $file;
		/**
		 * @var ZipArchive
		 */
		protected $archive;

		/**
		 * Creates a new instance
		 * @param string $file The file to write to
		 */
		public function __construct($file) {
			$this->file = $file;
		}


		/**
		 * Initializes the writer. Has to be called before first use of the writer.
		 * @throws ArchiveCreateException
		 */
		public function init() {
			$this->archive = new ZipArchive();
			$ret = $this->archive->open($this->file, ZipArchive::OVERWRITE);

			if ($ret !== true) {
				$this->archive = null;
				throw new ArchiveCreateException($this->file, $ret);
			}
		}

		/**
		 * Writes the specified object
		 * @param S3Object $object The object to write
		 * @throws NotInitException
		 * @throws ObjectWriteException
		 */
		public function writeObject(S3Object $object) {
			if (empty($this->archive))
				throw new NotInitException();

			if ($this->archive->addFromString(self::DATA_DIR . '/' .$object->getKey(), $object->getBody()) !== true)
				throw new ObjectWriteException($object->getKey());
			if ($this->archive->addFromString(self::META_DIR . '/' .$object->getKey() . '.ser', serialize($object->packAdditionalData())) !== true)
				throw new ObjectWriteException($object->getKey());
		}

		/**
		 * Closes the archive
		 * @throws ArchiveCloseException
		 * @throws NotInitException
		 */
		public function close() {
			if (empty($this->archive))
				throw new NotInitException();

			if ($this->archive->close() !== true)
				throw new ArchiveCloseException($this->file);
		}

	}