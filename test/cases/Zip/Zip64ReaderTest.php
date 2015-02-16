<?php
	use S3Backup\Zip\Zip64Reader;


	include_once(__DIR__ . '/../../TestCase.php');

	class Zip64ReaderTest extends TestCase
	{
		protected $testFile = '';

		protected function setUp() {
			$this->testFile = __DIR__ . '/../../data/testRead.zip';

			parent::setUp();
		}


		public function testReadIndex() {
			$rdr = new Zip64Reader(fopen($this->testFile, 'r'));

			$rdr->readIndex();

			$index = $rdr->getFiles();

			$this->assertContains('_DATA/tmp/test/Object1.txt', $index);
			$this->assertContains('_DATA/tmp/Object 2.txt', $index);
			$this->assertContains('_DATA/Object 3.txt', $index);
		}

		public function testGetStream() {
			$rdr = new Zip64Reader(fopen($this->testFile, 'r'));

			$str1 = $rdr->getFileStream('_DATA/tmp/test/Object1.txt');
			$this->assertEquals('das ist ein Test body', fread($str1, 1024));
			fclose($str1);

			$str2 = $rdr->getFileStream('_DATA/tmp/Object 2.txt');
			$this->assertEquals('das ist ein Test body2', fread($str2, 1024));


			$str3 = $rdr->getFileStream('_DATA/Object 3.txt');
			$this->assertEquals('das ist ein Test body3', fread($str3, 1024));


			fclose($str2);
			fclose($str3);
		}
	}