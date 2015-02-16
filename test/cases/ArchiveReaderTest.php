<?php

	use S3Backup\Reader\ArchiveReader;
	use S3Backup\S3Object;
	use S3Backup\Writer\ArchiveWriter;
	use S3Backup\Zip\Zip64Writer;

	include_once(__DIR__ . '/../TestCase.php');

	class ArchiveReaderTest extends TestCase
	{
		public function testInit() {
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


			$zipStream = fopen(TEST_READ_FILE, 'w+');
			$zip = new Zip64Writer($zipStream);

			$s = $zip->beginFile(ArchiveWriter::DATA_DIR . '/' . $obj1->getKey());
			fwrite($s, fread($body1, 1024));
			fclose($s);
			$s = $zip->beginFile(ArchiveWriter::META_DIR . '/' . $obj1->getKey() . '.ser');
			fwrite($s, serialize($obj1->packAdditionalData()));
			fclose($s);

			$s = $zip->beginFile(ArchiveWriter::DATA_DIR . '/' . $obj2->getKey());
			fwrite($s, fread($body2, 1024));
			fclose($s);
			$s = $zip->beginFile(ArchiveWriter::META_DIR . '/' . $obj2->getKey() . '.ser');
			fwrite($s, serialize($obj2->packAdditionalData()));
			fclose($s);

			$s = $zip->beginFile(ArchiveWriter::DATA_DIR . '/' . $obj3->getKey());
			fwrite($s, fread($body3, 1024));
			fclose($s);
			$s = $zip->beginFile(ArchiveWriter::META_DIR . '/' . $obj3->getKey() . '.ser');
			fwrite($s, serialize($obj3->packAdditionalData()));
			fclose($s);

			$zip->flushIndex();
			fclose($zipStream);


			$rdr = new ArchiveReader(TEST_READ_FILE);
			$rdr->init();

			$keys = $rdr->getObjectKeys();

			$this->assertEquals(3, count($keys));
			$this->assertContains($obj1->getKey(), $keys);
			$this->assertContains($obj2->getKey(), $keys);
			$this->assertContains($obj3->getKey(), $keys);

			$rdr->close();

			unlink(TEST_READ_FILE);

		}

		public function testReadObject() {

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


			$zipStream = fopen(TEST_READ_FILE, 'w+');
			$zip       = new Zip64Writer($zipStream);

			$s = $zip->beginFile(ArchiveWriter::DATA_DIR . '/' . $obj1->getKey());
			fwrite($s, fread($body1, 1024));
			fclose($s);
			$s = $zip->beginFile(ArchiveWriter::META_DIR . '/' . $obj1->getKey() . '.ser');
			fwrite($s, serialize($obj1->packAdditionalData()));
			fclose($s);

			$s = $zip->beginFile(ArchiveWriter::DATA_DIR . '/' . $obj2->getKey());
			fwrite($s, fread($body2, 1024));
			fclose($s);
			$s = $zip->beginFile(ArchiveWriter::META_DIR . '/' . $obj2->getKey() . '.ser');
			fwrite($s, serialize($obj2->packAdditionalData()));
			fclose($s);

			$s = $zip->beginFile(ArchiveWriter::DATA_DIR . '/' . $obj3->getKey());
			fwrite($s, fread($body3, 1024));
			fclose($s);
			$s = $zip->beginFile(ArchiveWriter::META_DIR . '/' . $obj3->getKey() . '.ser');
			fwrite($s, serialize($obj3->packAdditionalData()));
			fclose($s);

			$zip->flushIndex();
			fclose($zipStream);


			fseek($body1, 0);
			fseek($body2, 0);
			fseek($body3, 0);


			$rdr = new ArchiveReader(TEST_READ_FILE);
			$rdr->init();



			$r1 = $rdr->readObject(0);
			$r2 = $rdr->readObject(1);
			$r3 = $rdr->readObject(2);

			$this->assertEquals(fread($obj1->getStream(), 1024), fread($r1->getStream(), 1024));
			$this->assertEquals(fread($obj2->getStream(), 1024), fread($r2->getStream(), 1024));
			$this->assertEquals(fread($obj3->getStream(), 1024), fread($r3->getStream(), 1024));

			$rdr->close();


			unlink(TEST_READ_FILE);
		}

	}