<?php

	use S3Backup\S3Object;
	use S3Backup\Writer\ArchiveWriter;
	use S3Backup\Zip\Zip64Reader;

	include_once(__DIR__ . '/../TestCase.php');

	class ArchiveWriterTest extends TestCase
	{
		public function testWriteObject() {
			$wr = new ArchiveWriter(TEST_WRITE_FILE);
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

			$obj2 = new S3Object('tmp/Object 2.txt');
			$obj2->setStream($body2);

			$obj3 = new S3Object('Object 3.txt');
			$obj3->setStream($body3);


			$wr->writeObject($obj1);
			$wr->writeObject($obj2);
			$wr->writeObject($obj3);

			fseek($body1, 0);
			fseek($body2, 0);
			fseek($body3, 0);

			$wr->close();


			$zipStream = fopen(TEST_WRITE_FILE, 'r');
			$zip = new Zip64Reader($zipStream);

			$this->assertEquals(fread($obj1->getStream(), 1024), fread($zip->getFileStream('_DATA/tmp/test/Object1.txt'), 1024));
			$this->assertEquals(fread($obj2->getStream(), 1024), fread($zip->getFileStream('_DATA/tmp/Object 2.txt'), 1024));
			$this->assertEquals(fread($obj3->getStream(), 1024), fread($zip->getFileStream('_DATA/Object 3.txt'), 1024));

			$this->assertEquals(serialize($obj1->packAdditionalData()), fread($zip->getFileStream('_META/tmp/test/Object1.txt.ser'), 1024));
			$this->assertEquals(serialize($obj2->packAdditionalData()), fread($zip->getFileStream('_META/tmp/Object 2.txt.ser'), 1024));
			$this->assertEquals(serialize($obj3->packAdditionalData()), fread($zip->getFileStream('_META/Object 3.txt.ser'), 1024));

			fclose($zipStream);

			unlink(TEST_WRITE_FILE);
		}

	}