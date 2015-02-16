<?php
	use S3Backup\Zip\Zip64Reader;


	include_once(__DIR__ . '/../../TestCase.php');

	class Zip64ReadStreamTest extends TestCase
	{
		protected $testFile = '';

		protected function setUp() {
			$this->testFile = __DIR__ . '/../../data/testRead.zip';

			parent::setUp();
		}


		public function testStreamOpen() {
			$h = fopen('zip64.read://' . $this->testFile .'#_DATA/Object 3.txt', 'r');

			$ret = fread($h, 1024);

			$this->assertEquals('das ist ein Test body3', $ret);
		}

		public function testStreamOpenContext() {
			$baseStream = fopen($this->testFile, 'r');

			$rdr = new Zip64Reader($baseStream);
			$rdr->readIndex();

			$context = stream_context_create(array('zip64.read' => array(
				'baseStream' => $baseStream,
			    'fileLocation' => $rdr->getFileLocation('_DATA/Object 3.txt')
			)));

			$h = fopen('zip64.read://', 'r', false, $context);

			$ret = fread($h, 1024);

			$this->assertEquals('das ist ein Test body3', $ret);
		}

		public function testStreamRead() {
			$h = fopen('zip64.read://' . $this->testFile . '#_DATA/Object 3.txt', 'r');

			$this->assertEquals('da', fread($h, 2));
			$this->assertEquals('s i', fread($h, 3));
		}

		public function testStreamReadSeek() {
			$baseStream = fopen($this->testFile, 'r');

			$rdr = new Zip64Reader($baseStream);
			$rdr->readIndex();

			$context = stream_context_create(array('zip64.read' => array(
				'baseStream'   => $baseStream,
				'fileLocation' => $rdr->getFileLocation('_DATA/Object 3.txt')
			)
			));

			$h = fopen('zip64.read://', 'r', false, $context);

			fseek($baseStream, 40);

			$this->assertEquals('da', fread($h, 2));
			$this->assertEquals('s i', fread($h, 3));
		}

		public function testStreamSeek() {
			$h = fopen('zip64.read://' . $this->testFile . '#_DATA/Object 3.txt', 'r');

			fseek($h, 4, SEEK_SET);
			$this->assertEquals('ist', fread($h, 3));

			fseek($h, 1, SEEK_CUR);
			$this->assertEquals('ein', fread($h, 3));

			fseek($h, -1, SEEK_END);
			$this->assertEquals('3', fread($h, 3));

			$this->assertEquals(-1, fseek($h, 1, SEEK_END));
			$this->assertEquals(-1, fseek($h, -5, SEEK_SET));
		}
	}