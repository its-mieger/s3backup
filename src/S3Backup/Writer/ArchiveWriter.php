<?php

	namespace S3Backup\Writer;

	use S3Backup\Exception\ArchiveCloseException;
	use S3Backup\Exception\ArchiveCreateException;
	use S3Backup\Exception\NotInitException;
	use S3Backup\Exception\ObjectWriteException;
	use S3Backup\S3Object;
	use S3Backup\Zip\Zip64Writer;

	class ArchiveWriter extends AbstractWriter {

		const DATA_DIR = '_DATA';
		const META_DIR = '_META';

		protected $file;
		/**
		 * @var Zip64Writer
		 */
		protected $zipWriter;

		/**
		 * @var resource
		 */
		protected $zipStream;

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
			$this->zipStream = fopen($this->file, 'w+');

			if ($this->zipStream === false) {
				$this->zipWriter = null;
				throw new ArchiveCreateException($this->file);
			}

			$this->zipWriter = new Zip64Writer($this->zipStream);
		}

		/**
		 * Writes the specified object
		 * @param S3Object $object The object to write
		 * @throws NotInitException
		 * @throws ObjectWriteException
		 */
		public function writeObject(S3Object $object) {
			if (empty($this->zipWriter))
				throw new NotInitException();

			try {

				// write body
				$bodyStream = $object->getStream();
				fseek($bodyStream, 0);
				$writeStream = $this->zipWriter->beginFile(self::DATA_DIR . '/' . $object->getKey());
				while ($r = fread($bodyStream, 1024 * 1024)) {
					fwrite($writeStream, $r);
				}
				fclose($writeStream);

				// write additional data
				$writeStream = $this->zipWriter->beginFile(self::META_DIR . '/' . $object->getKey() . '.ser');
				fwrite($writeStream, serialize($object->packAdditionalData()));
				fclose($writeStream);
			}
			catch (\Exception $ex) {
				throw new ObjectWriteException($object->getKey(), '', 0, $ex);
			}
		}

		/**
		 * Closes the archive
		 * @throws ArchiveCloseException
		 * @throws NotInitException
		 */
		public function close() {
			if (empty($this->zipWriter))
				throw new NotInitException();

			try {
				$this->zipWriter->flushIndex();
			}
			catch(\Exception $ex) {
				throw new ArchiveCloseException($this->file);
			}

			fclose($this->zipStream);
		}

	}