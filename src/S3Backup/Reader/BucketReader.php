<?php

	namespace S3Backup\Reader;

	use Aws\CloudFront\Exception\Exception;
	use Aws\S3\S3Client;
	use S3Backup\Exception\IndexOutOfRangeException;
	use S3Backup\Exception\NotInitException;
	use S3Backup\Exception\ObjectReadException;
	use S3Backup\S3Object;

	class BucketReader extends AbstractReader {

		protected $objectKeys = array();

		protected $bucketName;
		protected $isInit = false;

		/**
		 * @var S3Client
		 */
		protected $s3Client;

		/**
		 * Creates a new instance
		 * @param string $bucketName The name of the bucket to read
		 * @param string $awsAccessKey The aws access key id
		 * @param string $awsSecretKey The aws secret access key
		 * @param string $awsRegion The aws region
		 */
		public function __construct($bucketName, $awsAccessKey, $awsSecretKey, $awsRegion) {
			$this->bucketName = $bucketName;

			$this->s3Client = S3Client::factory(array(
				'key'    => $awsAccessKey,
				'secret' => $awsSecretKey,
				'region' => $awsRegion,
			));
		}


		/**
		 * Gets the object keys in the bucket
		 * @return array
		 */
		public function getObjectKeys() {
			return $this->objectKeys;
		}


		/**
		 * Gets the bucket name
		 * @return string
		 */
		public function getBucketName() {
			return $this->bucketName;
		}


		/**
		 * Initializes the reader. Has to be called before first use of the reader.
		 */
		public function init() {

			// get all object keys from bucket
			$nextMarker = null;
			do {
				$params = array(
					'Bucket'    => $this->bucketName,
				);
				if (!empty($nextMarker))
					$params['Marker'] = $nextMarker;

				$response = $this->s3Client->listObjects($params);

				$lastKey = null;
				if (!empty($response['Contents'])) {
					foreach ($response['Contents'] as $currObject) {
						if (substr($currObject['Key'], -1) !== '/')
							$this->objectKeys[] = $lastKey = $currObject['Key'];
					}
				}

				if ($response['IsTruncated'])
					$nextMarker = $lastKey;
				
			} while($response['IsTruncated']);

			$this->isInit = true;
		}

		/**
		 * Gets the key of the object with specified index
		 * @param int $index Index of the object to get key
		 * @throws IndexOutOfRangeException
		 * @throws NotInitException
		 * @return string The object key
		 */
		public function getObjectKey($index) {
			if (!$this->isInit)
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

			if (!$this->isInit)
				throw new NotInitException();
			if (!isset($this->objectKeys[$index]))
				throw new IndexOutOfRangeException($index);

			$key = $this->objectKeys[$index];

			$ret = new S3Object($key);

			try {
				// get object
				$objResponse = $this->s3Client->getObject(array(
					'Bucket' => $this->bucketName,
					'Key'    => $key
				));

				$context = stream_context_create(array('s3backup.s3body' => array(
					'body' => $objResponse['Body']
				)));
				$ret->setStream(fopen('s3backup.s3body://', 'r', false, $context));

				if (!empty($objResponse['Metadata'])) {
					$metaData = array();

					foreach ($objResponse['Metadata'] as $metaKey => $value) {
						$metaData[preg_replace('/^x-amz-meta-/', '', $metaKey)] = $value;
					}

					$ret->setMetaData($metaData);
				}
				if (!empty($objResponse['ContentType']))
					$ret->setContentType($objResponse['ContentType']);


				// read acl
				$aclResponse = $this->s3Client->getObjectAcl(array(
					'Bucket' => $this->bucketName,
					'Key'    => $key
				));
				$ret->setOwner($aclResponse['Owner']);
				$ret->setGrants($aclResponse['Grants']);
			}
			catch(Exception $ex) {
				throw new ObjectReadException($key, '', 0, $ex);
			}

			return $ret;
		}

		/**
		 * Returns the number of objects which can be read from this reader.
		 * @throws NotInitException
		 * @return int The number of objects
		 */
		public function countObjects() {
			if (!$this->isInit)
				throw new NotInitException();

			return count($this->objectKeys);

		}

		/**
		 * Closes the reader
		 */
		public function close() { }
	}
