<?php

	use S3Backup\Reader\ArchiveReader;
	use S3Backup\S3Object;
	use S3Backup\Writer\ArchiveWriter;

	include_once(__DIR__ . '/../TestCase.php');

	class ArchiveReaderTest extends TestCase
	{
		public function testStream() {

			// set up the document
			$xml = new XmlWriter();
			$xml->openMemory();
			$xml->startDocument('1.0', 'UTF-8');
			$xml->startElement('mydoc');
			$xml->startElement('myele');

			// CData output
			$xml->startElement('mycdataelement');
			$xml->writeCData("<![CDATA[text for inclusion within CData tags]]>");
			$xml->endElement();

			// end the document and output
			$xml->endElement();
			$xml->endElement();
			var_dump($xml->outputMemory(true));


		}

		public function testInit() {
			$obj1 = new S3Object('tmp/test/Object1.txt');
			$obj1->setBody('das ist ein Test body');
			$obj1->setMetaData(array(
				'key1' => 'key1Value',
				'key2' => 'key2Value',
			));

			$obj2 = new S3Object('tmp/Object 2.txt');
			$obj2->setBody('das ist ein Test body2');

			$obj3 = new S3Object('Object 3.txt');
			$obj3->setBody('das ist ein Test body3');


			$zip = new ZipArchive();
			$zip->open(TEST_READ_FILE, ZipArchive::OVERWRITE);

			$zip->addFromString(ArchiveWriter::DATA_DIR . '/' . $obj1->getKey(), $obj1->getBody());
			$zip->addFromString(ArchiveWriter::META_DIR . '/' . $obj1->getKey() . '.ser', serialize($obj1->packAdditionalData()));
			$zip->addFromString(ArchiveWriter::DATA_DIR . '/' . $obj2->getKey(), $obj2->getBody());
			$zip->addFromString(ArchiveWriter::META_DIR . '/' . $obj2->getKey() . '.ser', serialize($obj2->packAdditionalData()));
			$zip->addFromString(ArchiveWriter::DATA_DIR . '/' . $obj3->getKey(), $obj3->getBody());
			$zip->addFromString(ArchiveWriter::META_DIR . '/' . $obj3->getKey() . '.ser', serialize($obj3->packAdditionalData()));

			$zip->close();


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

			$obj1 = new S3Object('tmp/test/Object1.txt');
			$obj1->setBody('das ist ein Test body');

			$obj2 = new S3Object('tmp/Object 2.txt');
			$obj2->setBody('das ist ein Test body2');

			$obj3 = new S3Object('Object 3.txt');
			$obj3->setBody('das ist ein Test body3');


			$zip = new ZipArchive();
			$zip->open(TEST_READ_FILE, ZipArchive::OVERWRITE);

			$zip->addFromString(ArchiveWriter::DATA_DIR . '/' . $obj1->getKey(), $obj1->getBody());
			$zip->addFromString(ArchiveWriter::META_DIR . '/' . $obj1->getKey() . '.ser', serialize($obj1->packAdditionalData()));
			$zip->addFromString(ArchiveWriter::DATA_DIR . '/' . $obj2->getKey(), $obj2->getBody());
			$zip->addFromString(ArchiveWriter::META_DIR . '/' . $obj2->getKey() . '.ser', serialize($obj2->packAdditionalData()));
			$zip->addFromString(ArchiveWriter::DATA_DIR . '/' . $obj3->getKey(), $obj3->getBody());
			$zip->addFromString(ArchiveWriter::META_DIR . '/' . $obj3->getKey() . '.ser', serialize($obj3->packAdditionalData()));

			$zip->close();



			$rdr = new ArchiveReader(TEST_READ_FILE);
			$rdr->init();



			$r1 = $rdr->readObject(0);
			$r2 = $rdr->readObject(1);
			$r3 = $rdr->readObject(2);

			$this->assertEquals($obj1, $r1);
			$this->assertEquals($obj2, $r2);
			$this->assertEquals($obj3, $r3);

			$rdr->close();


			unlink(TEST_READ_FILE);
		}

	}