<?php

	use Aws\S3\Exception\S3Exception;
	use Aws\S3\S3Client;
	use S3Backup\S3Object;
	use S3Backup\Writer\BucketWriter;

	include_once(__DIR__ . '/../TestCase.php');

	class BucketWriterTest extends TestCase
	{
		protected $testOwner = array();
		protected $testGrants = array();

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
				$cl->headBucket(array('Bucket' => TEST_WRITE_BUCKET));
			}
			catch (S3Exception $ex) {
				if ($ex->getAwsErrorCode() == 'NoSuchBucket') {
					$cl->createBucket(array(
						'Bucket'             => TEST_WRITE_BUCKET,
						'LocationConstraint' => TEST_AWS_REGION
					));
				}
				else {
					throw $ex;
				}
			}


			$response = $cl->listObjects(array(
				'Bucket' => TEST_WRITE_BUCKET,
			));

			if (!empty($response['Contents'])) {
				$obj = array();
				foreach ($response['Contents'] as $curr) {
					$obj[] = array(
						'Key' => $curr['Key']
					);
				}

				$cl->deleteObjects(array(
					'Bucket'  => TEST_WRITE_BUCKET,
					'Delete' => [
						'Objects' => $obj,
			        ]
				));
			}

			$cl->putObject(array(
				'Bucket' => TEST_WRITE_BUCKET,
				'Key' => 'aclGenerator',
				'Body' => 'nothing',
				'ACL' => 'public-read',
			));

			// get ACL from Read-Bucket (we need valid acl)
			$aclResponse      = $cl->getObjectAcl(array(
				'Bucket' => TEST_WRITE_BUCKET,
				'Key'    => 'aclGenerator'
			));
			$this->testOwner  = $aclResponse['Owner'];
			$this->testGrants = $aclResponse['Grants'];
		}

		public function testWriteObject() {
			$wr = new BucketWriter(TEST_WRITE_BUCKET, TEST_AWS_ACCESS_KEY_ID, TEST_AWS_SECRET_ACCESS_KEY, TEST_AWS_REGION);
			$wr->init();

			$body1 = fopen('php://memory', 'w+');
			fwrite($body1, ('das ist ein Test body'));
			fseek($body1, 0);

			$body2 = fopen('php://memory', 'w+');
			fwrite($body2, ('das ist ein Test body2'));
			fseek($body2, 0);

			$body3 = fopen('php://memory', 'w+');
			fwrite($body3, ('das ist ein Test body3'));
			fseek($body3, 0);

			$obj1 = new S3Object('tmp/test/Object1.txt');
			$obj1->setStream($body1);
			$obj1->setMetaData(array(
				'key1' => 'key1Value',
				'key2' => 'key2Value',
			));
			$obj1->setContentType('text/text');
			$obj1->setOwner($this->testOwner);
			$obj1->setGrants($this->testGrants);
			$obj1->setContentEncoding('plain');
			$obj1->setContentDisposition('disposition');
			$obj1->setContentLanguage('en-de');
			$obj1->setCacheControl('max-age=86300');
			$obj1->setExpires(date('c'));
			$obj1->setWebsiteRedirectLocation('http://test.de');

			$obj2 = new S3Object('tmp/Object 2.txt');
			$obj2->setStream($body2);
			$obj2->setOwner($this->testOwner);
			$obj2->setGrants($this->testGrants);

			$obj3 = new S3Object('Object 3.txt');
			$obj3->setStream($body3);
			$obj3->setOwner($this->testOwner);
			$obj3->setGrants($this->testGrants);

			$wr->writeObject($obj1);
			$wr->writeObject($obj2);
			$wr->writeObject($obj3);

			$wr->close();


			fseek($body1, 0);
			fseek($body2, 0);
			fseek($body3, 0);

			$cl = new S3Client([
				'version'     => '2006-03-01',
				'region'      => TEST_AWS_REGION,
				'credentials' => [
					'key'    => TEST_AWS_ACCESS_KEY_ID,
					'secret' => TEST_AWS_SECRET_ACCESS_KEY,
				]
			]);

			// check first object
			$r1Object = $cl->getObject(array(
				'Bucket' => TEST_WRITE_BUCKET,
				'Key'    => $obj1->getKey(),
				'@http'  => ['decode_content' => false], // prevent guzzle from decoding object, since we want to receive the original data
			));
			$r1Acl = $cl->getObjectAcl(array(
				'Bucket' => TEST_WRITE_BUCKET,
				'Key'    => $obj1->getKey()
			));

			$this->assertEquals(fread($obj1->getStream(), 1024), (string)$r1Object['Body']);
			$this->assertEquals($obj1->getMetaData(), $r1Object['Metadata']);
			$this->assertEquals($obj1->getContentType(), $r1Object['ContentType']);
			$this->assertEquals($obj1->getOwner(), $r1Acl['Owner']);
			$this->assertEquals($obj1->getGrants(), $r1Acl['Grants']);
			$this->assertEquals($obj1->getContentEncoding(), $r1Object['ContentEncoding']);
			$this->assertEquals($obj1->getCacheControl(), $r1Object['CacheControl']);
			$this->assertEquals($obj1->getContentLanguage(), $r1Object['ContentLanguage']);
			$this->assertEquals($obj1->getContentDisposition(), $r1Object['ContentDisposition']);
			$this->assertEquals($obj1->getWebsiteRedirectLocation(), $r1Object['WebsiteRedirectLocation']);
			$this->assertNotEmpty($r1Object['Expires']);

			// check second object
			$r2Object = $cl->getObject(array(
				'Bucket' => TEST_WRITE_BUCKET,
				'Key'    => $obj2->getKey(),
				'@http'  => ['decode_content' => false], // prevent guzzle from decoding object, since we want to receive the original data
			));
			$r2Acl    = $cl->getObjectAcl(array(
				'Bucket' => TEST_WRITE_BUCKET,
				'Key'    => $obj2->getKey(),
			));
			$this->assertEquals(fread($obj2->getStream(), 1024), (string)$r2Object['Body']);
			$this->assertEquals($obj2->getOwner(), $r2Acl['Owner']);
			$this->assertEquals($obj2->getGrants(), $r2Acl['Grants']);

			// check third object
			$r3Object = $cl->getObject(array(
				'Bucket' => TEST_WRITE_BUCKET,
				'Key'    => $obj3->getKey(),
				'@http'  => ['decode_content' => false], // prevent guzzle from decoding object, since we want to receive the original data
			));
			$r3Acl    = $cl->getObjectAcl(array(
				'Bucket' => TEST_WRITE_BUCKET,
				'Key'    => $obj3->getKey(),
			));
			$this->assertEquals(fread($obj3->getStream(), 1024), (string)$r3Object['Body']);
			$this->assertEquals($obj3->getOwner(), $r3Acl['Owner']);
			$this->assertEquals($obj3->getGrants(), $r3Acl['Grants']);
		}

	}