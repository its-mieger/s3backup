<?php

	use S3Backup\S3Object;
	use S3Backup\Writer\ArchiveWriter;

	include_once(__DIR__ . '/../TestCase.php');

	class ArchiveWriterTest extends TestCase
	{
		public function testWriteObject() {
			$wr = new ArchiveWriter(TEST_WRITE_FILE);
			$wr->init();

			$obj1 = new S3Object('tmp/test/Object1.txt');
			$obj1->setBody('das ist ein Test body');

			$obj2 = new S3Object('tmp/Object 2.txt');
			$obj2->setBody('das ist ein Test body2');

			$obj3 = new S3Object('Object 3.txt');
			$obj3->setBody('das ist ein Test body3');

			$wr->writeObject($obj1);
			$wr->writeObject($obj2);
			$wr->writeObject($obj3);

			$wr->close();


			$zip = new ZipArchive();
			$zip->open(TEST_WRITE_FILE);

			$this->assertEquals($obj1->getBody(), $zip->getFromName('_DATA' . '/tmp/test/Object1.txt'));
			$this->assertEquals($obj2->getBody(), $zip->getFromName('_DATA' . '/tmp/Object 2.txt'));
			$this->assertEquals($obj3->getBody(), $zip->getFromName('_DATA' . '/Object 3.txt'));

			$this->assertEquals(serialize($obj1->packAdditionalData()), $zip->getFromName('_META' . '/tmp/test/Object1.txt.ser'));
			$this->assertEquals(serialize($obj2->packAdditionalData()), $zip->getFromName('_META' . '/tmp/Object 2.txt.ser'));
			$this->assertEquals(serialize($obj3->packAdditionalData()), $zip->getFromName('_META' . '/Object 3.txt.ser'));

			$zip->close();

			unlink(TEST_WRITE_FILE);
		}

	}