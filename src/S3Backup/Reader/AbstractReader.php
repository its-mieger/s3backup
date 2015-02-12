<?php

	namespace S3Backup\Reader;

	use S3Backup\Exception\IndexOutOfRangeException;
	use S3Backup\Exception\NotInitException;
	use S3Backup\Exception\ObjectReadException;
	use S3Backup\S3Object;

	abstract class AbstractReader {

		/**
		 * Initializes the reader. Has to be called before first use of the reader.
		 */
		public abstract function init();


		/**
		 * Gets the key of the object with specified index
		 * @param int $index Index of the object to get key
		 * @throws IndexOutOfRangeException
		 * @throws NotInitException
		 * @return string The object key
		 */
		public abstract function getObjectKey($index);

		/**
		 * Reads an object with specified index
		 * @param int $index Index of the object to read
		 * @throws IndexOutOfRangeException
		 * @throws NotInitException
		 * @throws ObjectReadException
		 * @return S3Object The read object
		 */
		public abstract function readObject($index);


		/**
		 * Returns the number of objects which can be read from this reader.
		 * @throws NotInitException
		 * @return int The number of objects
		 */
		public abstract function countObjects();


		/**
		 * Closes the reader
		 * @throws NotInitException
		 */
		public abstract function close();
	}