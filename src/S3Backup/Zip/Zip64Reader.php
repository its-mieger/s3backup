<?php

	namespace S3Backup\Zip;

	use S3Backup\Zip\Exception\ZipCorruptException;
	use S3Backup\Zip\Exception\ZipFileNotFoundException;
	use S3Backup\Zip\Exception\ZipReadException;

	/**
	 * Reader for Zip64 files
	 * @package S3Backup\Zip
	 */
	class Zip64Reader {


		/** The "extra field" ID for ZIP64 central directory entries */
		const ZIP64_EXTRA_HEADER = 0x0001;

		/** The segment size for the file contents cache */
		const SEGMENT_SIZE = 16384;

		/** The index of the "general field" bit for UTF-8 file names */
		const GENERAL_UTF8 = 11;

		/** The index of the "general field" bit for central directory encryption */
		const GENERAL_CD_ENCRYPTED = 13;



		/** A segmented cache of the file contents */
		protected $buffer;

		/** The cached length of the file, or null if it has not been loaded yet. */
		protected $fileLength;

		/** Stored headers */
		protected $eocdr, $eocdr64, $eocdr64Locator;

		/**
		 * The underlying stream
		 * @var resource
		 */
		protected $baseStream;

		/**
		 * The local headers of the archive
		 * @var array
		 */
		protected $localHeaders = array();

		/**
		 * Indicator if index was already parsed
		 * @var bool
		 */
		protected $indexParsed = false;


		/**
		 * Creates a new instance
		 * @param resource $handle The underlying stream resource to read the zip data from
		 */
		public function __construct($handle) {
			$this->baseStream = $handle;
		}

		/**
		 * Gets the file names of all files in the archive
		 * @return array The file names
		 */
		public function getFiles() {
			if (!$this->indexParsed)
				$this->readIndex();

			return array_keys($this->localHeaders);
		}


		/**
		 * Reads the index of the zip file
		 * @throws ZipCorruptException
		 */
		public function readIndex() {
			$this->readEndOfCentralDirectoryRecord();

			list($offset, $size) = $this->findZip64CentralDirectory();
			$this->readCentralDirectory($offset, $size);

			$this->indexParsed = true;
		}

		/**
		 * Gets the location of an archived file
		 * @param string $filename The name of the archived file
		 * @throws ZipFileNotFoundException
		 * @return array Location. First is the data offset and second the data size
		 */
		public function getFileLocation($filename) {
			if (!$this->indexParsed)
				$this->readIndex();

			if (!isset($this->localHeaders[$filename]))
				throw new ZipFileNotFoundException($filename);

			$headerLocation = $this->localHeaders[$filename];

			return $this->readLocalHeader($headerLocation['headerOffset'], $headerLocation['compressedSize']);
		}


		/**
		 * Gets a file stream to read the data of an archived file
		 * @param string $filename The name of the archived file
		 * @return resource The stream handle
		 * @throws ZipFileNotFoundException
		 */
		public function getFileStream($filename) {
			$location = $this->getFileLocation($filename);

			$context = stream_context_create(array('zip64.read' => array(
				'baseStream'   => $this->baseStream,
				'fileLocation' => $location
			)));

			return fopen('zip64.read://', 'r', false, $context);
		}


		protected function readLocalHeader($offset, $size) {

			$fixedInfo = array(
				'signature'          => array('string', 4),
				'version'            => 2,
				'flags'              => 2,
				'compression method' => 2,
				'mod time'           => 2,
				'mod date'           => 2,
				'crc-32'             => 4,
				'compressed size'    => 4,
				'uncompressed size'  => 4,
				'name length'        => 2,
				'extra field length' => 2,
			);
			$fixedSize = $this->getStructureSize($fixedInfo);

			$header = $this->getBlock($offset, $fixedSize);

			$headerData = $this->unpack($header, $fixedInfo, 0);

			$dataOffset = $offset + $fixedSize + $headerData['name length'] + $headerData['extra field length'];

			return array($dataOffset, $size);
		}

		/**
		 * Find the location of the central directory, as would be seen by a
		 * ZIP64-compliant reader.
		 *
		 * @throws ZipCorruptException
		 * @return array List containing offset, size and end position.
		 */
		protected function findZip64CentralDirectory() {
			// The spec is ambiguous about the exact rules of precedence between the
			// ZIP64 headers and the original headers. Here we follow zip_util.c
			// from OpenJDK 7.
			$size       = $this->eocdr['CD size'];
			$offset     = $this->eocdr['CD offset'];
			$numEntries = $this->eocdr['CD entries total'];
			$endPos     = $this->eocdr['position'];
			if ($size == 0xffffffff
			    || $offset == 0xffffffff
			    || $numEntries == 0xffff
			) {
				$this->readZip64EndOfCentralDirectoryLocator();

				if (isset($this->eocdr64Locator['eocdr64 offset'])) {
					$this->readZip64EndOfCentralDirectoryRecord();
					if (isset($this->eocdr64['CD offset'])) {
						$size   = $this->eocdr64['CD size'];
						$offset = $this->eocdr64['CD offset'];
						$endPos = $this->eocdr64Locator['eocdr64 offset'];
					}
				}
			}
			// Some readers use the EOCDR position instead of the offset field
			// to find the directory, so to be safe, we check if they both agree.
			if ($offset + $size != $endPos)
				throw new ZipCorruptException('the central directory does not immediately precede the end of central directory record');

			return array($offset, $size);
		}

		/**
		 * Read the central directory at the given location
		 * @param int $offset The offset of the central directory
		 * @param int $size The size of the central directory
		 * @throws ZipCorruptException
		 * @throws \Exception
		 */
		protected function readCentralDirectory($offset, $size) {
			$block = $this->getBlock($offset, $size);

			$fixedInfo = array(
				'signature'           => array('string', 4),
				'version made by'     => 2,
				'version needed'      => 2,
				'general bits'        => 2,
				'compression method'  => 2,
				'mod time'            => 2,
				'mod date'            => 2,
				'crc-32'              => 4,
				'compressed size'     => 4,
				'uncompressed size'   => 4,
				'name length'         => 2,
				'extra field length'  => 2,
				'comment length'      => 2,
				'disk number start'   => 2,
				'internal attrs'      => 2,
				'external attrs'      => 4,
				'local header offset' => 4,
			);
			$fixedSize = $this->getStructureSize($fixedInfo);

			$pos = 0;
			while ($pos < $size) {
				$data = $this->unpack($block, $fixedInfo, $pos);
				$pos += $fixedSize;

				if ($data['signature'] !== "PK\x01\x02")
					throw new ZipCorruptException('Invalid signature found in directory entry');


				$variableInfo = array(
					'name'        => array('string', $data['name length']),
					'extra field' => array('string', $data['extra field length']),
					'comment'     => array('string', $data['comment length']),
				);
				$data += $this->unpack($block, $variableInfo, $pos);
				$pos += $this->getStructureSize($variableInfo);

				if ($data['compressed size'] == 0xffffffff
				    || $data['uncompressed size'] == 0xffffffff
				    || $data['local header offset'] == 0xffffffff
				) {
					$zip64Data = $this->unpackZip64Extra($data['extra field']);
					if ($zip64Data) {
						$data = $zip64Data + $data;
					}
				}

				if ($this->testBit($data['general bits'], self::GENERAL_CD_ENCRYPTED))
					throw new ZipCorruptException('Central directory encryption is not supported');


				// Convert the timestamp into MediaWiki format
				// For the format, please see the MS-DOS 2.0 Programmer's Reference,
				// pages 3-5 and 3-6.
				//$time = $data['mod time'];
				//$date = $data['mod date'];

				//$year      = 1980 + ($date >> 9);
				//$month     = ($date >> 5) & 15;
				//$day       = $date & 31;
				//$hour      = ($time >> 11) & 31;
				//$minute    = ($time >> 5) & 63;
				//$second    = ($time & 31) * 2;
				//$timestamp = sprintf("%04d%02d%02d%02d%02d%02d",
				//	$year, $month, $day, $hour, $minute, $second);

				// Convert the character set in the file name
				if (!function_exists('iconv')
				    || $this->testBit($data['general bits'], self::GENERAL_UTF8)
				) {
					$name = $data['name'];
				}
				else {
					$name = iconv('CP437', 'UTF-8', $data['name']);
				}

				// add local header to index
				$this->localHeaders[$name] = array(
					//'mtime'          => $timestamp,
					//'size'           => $data['uncompressed size'],
					'compressedSize' => $data['compressed size'],
					'headerOffset'   => $data['local header offset']
				);

			}
		}


		/**
		 * Read the header called the "ZIP64 end of central directory record". It
		 * may replace the regular "end of central directory record" in ZIP64 files.
		 */
		protected function readZip64EndOfCentralDirectoryRecord() {
			if ($this->eocdr64Locator['eocdr64 start disk'] != 0
			    || $this->eocdr64Locator['number of disks'] != 1
			) {
				throw new ZipCorruptException('More than one disk (in EOCDR64 locator)');
			}

			$info          = array(
				'signature'            => array('string', 4),
				'EOCDR64 size'         => 8,
				'version made by'      => 2,
				'version needed'       => 2,
				'disk'                 => 4,
				'CD start disk'        => 4,
				'CD entries this disk' => 8,
				'CD entries total'     => 8,
				'CD size'              => 8,
				'CD offset'            => 8
			);
			$structSize    = $this->getStructureSize($info);
			$block         = $this->getBlock($this->eocdr64Locator['eocdr64 offset'], $structSize);
			$this->eocdr64 = $data = $this->unpack($block, $info);
			if ($data['signature'] !== "PK\x06\x06") {
				throw new ZipCorruptException('Wrong signature on Zip64 end of central directory record');
			}
			if ($data['disk'] !== 0
			    || $data['CD start disk'] !== 0
			) {
				throw new ZipCorruptException('More than one disk (in EOCDR64)');
			}
		}

		/**
		 * Interpret ZIP64 "extra field" data and return an associative array.
		 * @param string $extraField The extra field data
		 * @throws ZipCorruptException
		 * @throws \Exception
		 * @return array|bool The unpacked data. False if no zip64 data
		 */
		protected function unpackZip64Extra($extraField) {
			$extraHeaderInfo = array(
				'id'   => 2,
				'size' => 2,
			);
			$extraHeaderSize = $this->getStructureSize($extraHeaderInfo);

			$zip64ExtraInfo = array(
				'uncompressed size'   => 8,
				'compressed size'     => 8,
				'local header offset' => 8,
			);

			$extraPos = 0;
			while ($extraPos < strlen($extraField)) {
				$extra = $this->unpack($extraField, $extraHeaderInfo, $extraPos);
				$extraPos += $extraHeaderSize;
				$extra += $this->unpack($extraField,
					array('data' => array('string', $extra['size'])),
					$extraPos);
				$extraPos += $extra['size'];

				if ($extra['id'] == self::ZIP64_EXTRA_HEADER) {
					return $this->unpack($extra['data'], $zip64ExtraInfo);
				}
			}

			return false;
		}

		/**
		 * Read the header called the "ZIP64 end of central directory locator". An
		 * error will be raised if it does not exist.
		 */
		protected function readZip64EndOfCentralDirectoryLocator() {
			$info       = array(
				'signature'          => array('string', 4),
				'eocdr64 start disk' => 4,
				'eocdr64 offset'     => 8,
				'number of disks'    => 4,
			);
			$structSize = $this->getStructureSize($info);

			$block                = $this->getBlock($this->getFileLength() - $this->eocdr['EOCDR size']
			                                        - $structSize, $structSize);
			$this->eocdr64Locator = $data = $this->unpack($block, $info);

			if ($data['signature'] !== "PK\x06\x07") {
				// Note: Java will allow this and continue to read the
				// EOCDR64, so we have to reject the upload, we can't
				// just use the EOCDR header instead.
				throw new ZipCorruptException('Wrong signature on Zip64 end of central directory locator');
			}
		}

		/**
		 * Read the header which is at the end of the central directory,
		 * unimaginatively called the "end of central directory record" by the ZIP
		 * spec.
		 */
		protected function readEndOfCentralDirectoryRecord() {
			$info       = array(
				'signature'            => 4,
				'disk'                 => 2,
				'CD start disk'        => 2,
				'CD entries this disk' => 2,
				'CD entries total'     => 2,
				'CD size'              => 4,
				'CD offset'            => 4,
				'file comment length'  => 2,
			);
			$structSize = $this->getStructureSize($info);
			$startPos   = $this->getFileLength() - 65536 - $structSize;
			if ($startPos < 0) {
				$startPos = 0;
			}

			$block  = $this->getBlock($startPos);
			$sigPos = strrpos($block, "PK\x05\x06");
			if ($sigPos === false)
				throw new ZipCorruptException("Zip file lacks EOCDR signature. It probably isn't a zip file.");


			$this->eocdr               = $this->unpack(substr($block, $sigPos), $info);
			$this->eocdr['EOCDR size'] = $structSize + $this->eocdr['file comment length'];

			if ($structSize + $this->eocdr['file comment length'] != strlen($block) - $sigPos)
				throw new ZipCorruptException('Trailing bytes after the end of the file comment');

			// do not check this here since it is written in zip64 cdr
			//			if ($this->eocdr['disk'] !== 0
			//			    || $this->eocdr['CD start disk'] !== 0
			//			) {
			//				throw new ZipCorruptException('More than one disk (in EOCDR)');
			//			}
			$this->eocdr += $this->unpack(
				$block,
				array('file comment' => array('string', $this->eocdr['file comment length'])),
				$sigPos + $structSize);
			$this->eocdr['position'] = $startPos + $sigPos;
		}

		/**
		 * Get the length of the file.
		 */
		protected function getFileLength() {
			if ($this->fileLength === null) {
				$stat             = fstat($this->baseStream);
				$this->fileLength = $stat['size'];
			}

			return $this->fileLength;
		}

		/**
		 * Get the file contents from a given offset. If there are not enough bytes
		 * in the file to satisfy the request, an exception will be thrown.
		 *
		 * @param int $start The byte offset of the start of the block.
		 * @param int $length The number of bytes to return. If omitted, the remainder
		 *    of the file will be returned.
		 *
		 * @throws ZipCorruptException
		 * @return string
		 */
		protected function getBlock($start, $length = null) {
			$fileLength = $this->getFileLength();
			if ($start >= $fileLength)
				throw new ZipCorruptException("getBlock() requested position $start, file length is $fileLength");

			if ($length === null)
				$length = $fileLength - $start;

			$end = $start + $length;
			if ($end > $fileLength)
				throw new ZipCorruptException("getBlock() requested end position $end, file length is $fileLength");

			$startSeg = floor($start / self::SEGMENT_SIZE);
			$endSeg   = ceil($end / self::SEGMENT_SIZE);

			$block = '';
			for ($segIndex = $startSeg; $segIndex <= $endSeg; $segIndex++) {
				$block .= $this->getSegment($segIndex);
			}

			$block = substr($block, $start - $startSeg * self::SEGMENT_SIZE, $length);

			if (strlen($block) < $length)
				throw new ZipCorruptException('getBlock() returned an unexpectedly small amount of data');


			return $block;
		}

		/**
		 * Get a section of the file starting at position $segIndex * self::SEGSIZE,
		 * of length self::SEGSIZE. The result is cached. This is a helper function
		 * for getBlock().
		 *
		 * If there are not enough bytes in the file to statisfy the request, the
		 * return value will be truncated. If a request is made for a segment beyond
		 * the end of the file, an empty string will be returned.
		 * @param int $segmentSize The size of the segment
		 * @throws ZipReadException
		 * @return
		 */
		protected function getSegment($segmentSize) {
			if (!isset($this->buffer[$segmentSize])) {
				$bytePos = $segmentSize * self::SEGMENT_SIZE;
				if ($bytePos >= $this->getFileLength()) {
					$this->buffer[$segmentSize] = '';

					return '';
				}
				if (fseek($this->baseStream, $bytePos))
					throw new ZipReadException("Seek to $bytePos failed");

				$seg = fread($this->baseStream, self::SEGMENT_SIZE);
				if ($seg === false)
					throw new ZipReadException("Read from $bytePos failed");
				$this->buffer[$segmentSize] = $seg;
			}

			return $this->buffer[$segmentSize];
		}

		/**
		 * Get the size of a structure in bytes. See unpack() for the format of $structure.
		 * @param array $structure The structure to get size of
		 * @return int The size in bytes
		 */
		protected function getStructureSize($structure) {
			$size = 0;
			foreach ($structure as $type) {
				if (is_array($type))
					$size += end($type);
				else
					$size += $type;
			}

			return $size;
		}

		/**
		 * Unpack a binary structure. This is like the built-in unpack() function
		 * except nicer.
		 *
		 * @param string $string The binary data input
		 *
		 * @param array $structure An associative array giving structure members and their
		 *    types. In the key is the field name. The value may be either an
		 *    integer, in which case the field is a little-endian unsigned integer
		 *    encoded in the given number of bytes, or an array, in which case the
		 *    first element of the array is the type name, and the subsequent
		 *    elements are type-dependent parameters. Only one such type is defined:
		 *       - "string": The second array element gives the length of string.
		 *          Not null terminated.
		 *
		 * @param $offset int The offset into the string at which to start unpacking.
		 *
		 * @throws ZipCorruptException
		 * @throws \Exception
		 * @return array Unpacked associative array. Note that large integers in the input
		 *    may be represented as floating point numbers in the return value, so
		 *    the use of weak comparison is advised.
		 */
		protected function unpack($string, $structure, $offset = 0) {
			$size = $this->getStructureSize($structure);
			if ($offset + $size > strlen($string))
				throw new ZipCorruptException('unpack() would run past the end of the supplied string');


			$data = array();
			$pos  = $offset;
			foreach ($structure as $key => $type) {
				if (is_array($type)) {
					list($typeName, $fieldSize) = $type;
					switch ($typeName) {
						case 'string':
							$data[$key] = substr($string, $pos, $fieldSize);
							$pos += $fieldSize;
							break;
						default:
							throw new \Exception(__METHOD__ . ": invalid type \"$typeName\"");
					}
				}
				else {
					// Unsigned little-endian integer
					$length = intval($type);

					// Calculate the value. Use an algorithm which automatically
					// upgrades the value to floating point if necessary.
					$value = 0;
					for ($i = $length - 1; $i >= 0; $i--) {
						$value *= 256;
						$value += ord($string[$pos + $i]);
					}

					// Throw an exception if there was loss of precision
					if ($value > pow(2, 52)) {
						throw new ZipCorruptException('Number too large to be stored in a double. This could happen if we tried to unpack a 64-bit structure at an invalid location.');
					}
					$data[$key] = $value;
					$pos += $length;
				}
			}

			return $data;
		}

		/**
		 * Returns a bit from a given position in an integer value, converted to boolean
		 * @param int $value The integer value
		 * @param int $bitIndex The index of the bit, where 0 is the LSB.
		 * @return bool The bit state
		 */
		protected function testBit($value, $bitIndex) {
			return (bool)(($value >> $bitIndex) & 1);
		}


	}