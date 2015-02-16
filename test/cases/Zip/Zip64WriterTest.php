<?php

	use S3Backup\Zip\Zip64Writer;

	include_once(__DIR__ . '/../../TestCase.php');

	class ZipWriterTest extends TestCase
	{
		public function testWriteObject() {

			$zip = fopen(TEST_WRITE_FILE, 'w');

			$wtr = new Zip64Writer($zip);

			// write first file
			$file1 = $wtr->beginFile('tmp/test 1/test.txt');
			fwrite($file1, "Line 1\n");
			fwrite($file1, "Line 2\n");
			fclose($file1);

			// write second file
			$file2 = $wtr->beginFile('tmp/test.txt');
			fwrite($file2, "Line 3\n");
			fwrite($file2, "Line 4\n");
			fclose($file2);


			$wtr->flushIndex();

			fclose($zip);

			// verify using reader
			$rdr = new \S3Backup\Zip\Zip64Reader(fopen(TEST_WRITE_FILE, 'r'));
			$this->assertEquals("Line 1\nLine 2\n", fread($rdr->getFileStream('tmp/test 1/test.txt'), 1024));
			$this->assertEquals("Line 3\nLine 4\n", fread($rdr->getFileStream('tmp/test.txt'), 1024));

			unlink(TEST_WRITE_FILE);
		}

	}