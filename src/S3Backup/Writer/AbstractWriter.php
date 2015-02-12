<?php

	namespace S3Backup\Writer;

	use S3Backup\Exception\NotInitException;
	use S3Backup\Exception\ObjectWriteException;
	use S3Backup\S3Object;

	abstract class AbstractWriter {

		/**
		 * Initializes the writer. Has to be called before first use of the writer.
		 */
		public abstract function init();

		/**
		 * Writes the specified object
		 * @param S3Object $object The object to write
		 * @throws NotInitException
		 * @throws ObjectWriteException
		 */
		public abstract function writeObject(S3Object $object);

		/**
		 * Closes the writer
		 * @throws NotInitException
		 */
		public abstract function close();
	}