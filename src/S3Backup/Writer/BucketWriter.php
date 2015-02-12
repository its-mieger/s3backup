<?php

	namespace S3Backup\Writer;

	use Aws\CloudFront\Exception\Exception;
	use Aws\S3\Exception\NoSuchBucketException;
	use Aws\S3\S3Client;
	use Guzzle\Http\EntityBody;
	use S3Backup\Exception\BucketCreateException;
	use S3Backup\Exception\NotInitException;
	use S3Backup\Exception\ObjectWriteException;
	use S3Backup\S3Object;

	class BucketWriter extends AbstractWriter
	{
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
		 * Gets the bucket name
		 * @return string
		 */
		public function getBucketName() {
			return $this->bucketName;
		}


		/**
		 * Initializes the writer. Has to be called before first use of the writer.
		 * @throws BucketCreateException
		 */
		public function init() {
			try {
				try {
					// test if bucket exists
					$this->s3Client->headBucket(array('Bucket' => $this->bucketName));
				}
				catch (NoSuchBucketException $ex) {
					$this->s3Client->createBucket(array(
						'Bucket'             => $this->bucketName,
						'LocationConstraint' => $this->s3Client->getRegion()
					));
				}
			}
			catch(\Exception $ex) {
				throw new BucketCreateException($this->bucketName, $ex->getMessage(), 0, $ex);
			}

			$this->isInit = true;
		}

		/**
		 * Writes the specified object
		 * @param S3Object $object The object to write
		 * @throws NotInitException
		 * @throws ObjectWriteException
		 */
		public function writeObject(S3Object $object) {
			if (!$this->isInit)
				throw new NotInitException();

			$params = array(
				'Bucket'      => $this->bucketName,
				'Key'         => $object->getKey(),
				'Body' => EntityBody::factory($object->getBody()),
			    'Metadata' => $object->getMetaData(),
			);
			$ct = $object->getContentType();
			if (!empty($ct))
				$params['ContentType'] = $ct;

			try {
				$this->s3Client->putObject($params);

				// make sure type is set
				$grants = $object->getGrants();
				foreach($grants as &$currGrant) {
					if (isset($currGrant['Grantee']['ID']))
						$currGrant['Grantee']['Type'] = 'CanonicalUser';
					elseif (isset($currGrant['Grantee']['EmailAddress']))
						$currGrant['Grantee']['Type'] = 'AmazonCustomerByEmail';
					elseif (isset($currGrant['Grantee']['URI']))
						$currGrant['Grantee']['Type'] = 'Group';
				}

				$this->s3Client->putObjectAcl(array(
					'Bucket' => $this->bucketName,
					'Key'    => $object->getKey(),
					'Owner'  => $object->getOwner(),
					'Grants' => $grants
				));
			}
			catch(Exception $ex) {
				throw new ObjectWriteException($object->getKey(), '', 0, $ex);
			}
		}

		/**
		 * Closes the writer
		 */
		public function close() { }

	}