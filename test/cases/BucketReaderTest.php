<?php

	use Aws\S3\Exception\S3Exception;
	use Aws\S3\S3Client;

	include_once(__DIR__ . '/../TestCase.php');

	class BucketReaderTest extends TestCase {

		public $testObjects;

		protected function setUp() {
			parent::setUp();

			$cl = new S3Client([
				'version'     => '2006-03-01',
				'region'      => TEST_AWS_REGION,
				'credentials' => [
					'key'    => TEST_AWS_ACCESS_KEY_ID,
					'secret' => TEST_AWS_SECRET_ACCESS_KEY,
				]
			]);

			try {
				// test if bucket exists
				$cl->headBucket(array('Bucket' => TEST_READ_BUCKET));
			}
			catch(S3Exception $ex) {
				if ($ex->getAwsErrorCode() == 'NoSuchBucket') {
					$cl->createBucket(array(
						'Bucket'             => TEST_READ_BUCKET,
						'LocationConstraint' => TEST_AWS_REGION
					));
				}
				else {
					throw $ex;
				}
			}

			// build test objects
			$this->testObjects = array();
			for ($i = 0; $i < 2500; ++$i) {
				$this->testObjects['group_' . (round($i / 2500, 0)) . '/item' . $i] = $i;
			}

			// get test objects already in bucket
			$existingKeys = array();
			$nextMarker = null;
			do {
				$params = array(
					'Bucket' => TEST_READ_BUCKET,
				);
				if (!empty($nextMarker))
					$params['Marker'] = $nextMarker;

				$response = $cl->listObjects($params);

				$lastKey = null;
				if (!empty($response['Contents'])) {
					foreach ($response['Contents'] as $currObject) {
						if (substr($currObject['Key'], -1) !== '/')
							$existingKeys[] = $lastKey = $currObject['Key'];
					}
				}

				if ($response['IsTruncated'])
					$nextMarker = $lastKey;

			} while ($response['IsTruncated']);

			// upload objects yet not existing
			foreach($this->testObjects as $key => $value) {
				if (!in_array($key, $existingKeys)) {
					$cl->putObject(array(
						'Bucket' => TEST_READ_BUCKET,
						'Key'    => $key,
						'Body'   => ':' . $value,
					    'Metadata' => array(
						    'key1' => 'key1value',
					        'key2' => 'key2value'
					    ),
					    'ContentType' => 'text/text',
					    'CacheControl' => 'max-age=86300',
					    'ContentDisposition' => 'disposition',
					    'ContentEncoding' => 'plain',
					    'ContentLanguage' => 'de-de',
					    'Expires' => date('c'),
					    'WebsiteRedirectLocation' => 'http://test.de',
					));
				}
			}

		}

		public function testInit() {
			$rdr = new \S3Backup\Reader\BucketReader(
				TEST_READ_BUCKET,
				TEST_AWS_ACCESS_KEY_ID,
				TEST_AWS_SECRET_ACCESS_KEY,
				TEST_AWS_REGION
			);

			$rdr->init();

			$obj = $rdr->getObjectKeys();

			foreach($this->testObjects as $key => $value) {
				$this->assertContains($key, $obj);
			}
		}

		public function testReadObject() {
			$rdr = new \S3Backup\Reader\BucketReader(
				TEST_READ_BUCKET,
				TEST_AWS_ACCESS_KEY_ID,
				TEST_AWS_SECRET_ACCESS_KEY,
				TEST_AWS_REGION
			);

			$rdr->init();

			$obj = $rdr->readObject(0);

			$key = $obj->getKey();

			$cl = new S3Client([
				'version'     => '2006-03-01',
				'region'      => TEST_AWS_REGION,
				'credentials' => [
					'key'    => TEST_AWS_ACCESS_KEY_ID,
					'secret' => TEST_AWS_SECRET_ACCESS_KEY,
				]
			]);
			$cl->putObjectAcl(array(
				'Bucket' => TEST_READ_BUCKET,
			    'Key' => $key,
			    'ACL' => 'public-read'
			));

			$modObj = $rdr->readObject(0);

			// key
			$this->assertEquals('group_0/item0', $modObj->getKey());
			// meta data
			$this->assertEquals('key1value', $modObj->getMetaData()['key1']);
			$this->assertEquals('key2value', $modObj->getMetaData()['key2']);
			// owner
			$this->assertNotEmpty($modObj->getOwner());
			// body
			$this->assertEquals(':0', fread($modObj->getStream(), 1024));
			// grants
			$this->assertEquals($modObj->getOwner()['ID'], $modObj->getGrants()[0]['Grantee']['ID']);
			$this->assertEquals('http://acs.amazonaws.com/groups/global/AllUsers', $modObj->getGrants()[1]['Grantee']['URI']);
			$this->assertEquals('READ', $modObj->getGrants()[1]['Permission']);
			// attributes
			$this->assertEquals('text/text', $modObj->getContentType());
			$this->assertEquals('max-age=86300', $modObj->getCacheControl());
			$this->assertEquals('disposition', $modObj->getContentDisposition());
			$this->assertEquals('plain', $modObj->getContentEncoding());
			$this->assertEquals('de-de', $modObj->getContentLanguage());
			$this->assertNotEmpty($modObj->getExpires());
			$this->assertEquals('http://test.de', $modObj->getWebsiteRedirectLocation());

		}

	}