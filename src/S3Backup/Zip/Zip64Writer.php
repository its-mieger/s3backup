<?php

	namespace S3Backup\Zip;
	use S3Backup\Zip\Exception\ZipBeginFileException;
	use S3Backup\Zip\Exception\ZipNotStreamingException;
	use S3Backup\Zip\Exception\ZipWriteException;

	/**
	 * Writer for Zip64 files
	 * @package S3Backup\Zip
	 */
	class Zip64Writer
	{

		const DATA_DIR = '_DATA';
		const META_DIR = '_META';
		const ZIP_VERSION = 45;

		// files tracked for cdr
		private $files = array();

		// length of the CDR
		private $cdrLength = 0;

		// offset of the CDR
		private $cdrOffset = 0;


		protected $isFileStreaming = false;
		protected $currFileStreamInfo;
		protected $currFileStreamHash = null;
		protected $currFileStreamLength = null;
		protected $currFileStreamCompressedLength = null;


		protected $file;
		/**
		 * @var resource
		 */
		protected $baseStream;

		/**
		 * Creates a new instance
		 * @param resource $handle The underlying stream resource to write the zip data to
		 * @internal param string $file The file to write to
		 */
		public function __construct($handle) {
			$this->baseStream = $handle;
		}


		/**
		 * Begins the streaming of a new file to the archive
		 * @param string $name The file name
		 * @return resource The stream to write file data to
		 * @throws ZipBeginFileException
		 */
		public function beginFile($name) {
			if ($this->isFileStreaming)
				throw new ZipBeginFileException();

			$algo = 'crc32b';
			$opt  = array();
			$meth = 0x00;

			// calculate header attributes
			$this->currFileStreamLength       = gmp_init(0);
			$this->currFileStreamCompressedLength  = gmp_init(0);
			$this->currFileStreamHash     = hash_init($algo);

			// strip leading slashes from file name
			// (fixes bug in windows archive viewer)
			$name  = preg_replace('/^\\/+/', '', $name);
			$extra = pack('vVVVV', 1, 0, 0, 0, 0);

			// create dos timestamp
			$opt['time'] = isset($opt['time']) ? $opt['time'] : time();
			$dts         = $this->dosTime($opt['time']);

			// Sets bit 3, which means CRC-32, uncompressed and compresed length
			// are put in the data descriptor following the data. This gives us time
			// to figure out the correct sizes, etc.
			$genb = 0x08;

			if (mb_check_encoding($name, "UTF-8") && !mb_check_encoding($name, "ASCII")) {
				// Sets Bit 11: Language encoding flag (EFS).  If this bit is set,
				// the filename and comment fields for this file
				// MUST be encoded using UTF-8. (see APPENDIX D)
				$genb = 0x0808;
			}

			// build file header
			$fields = array(
				array('V', 0x04034b50),     // local file header signature
				array('v', self::ZIP_VERSION),  // version needed to extract
				array('v', $genb),          // general purpose bit flag
				array('v', $meth),          // compresion method (deflate or store)
				array('V', $dts),           // dos timestamp
				array('V', 0x00),           // crc32 of data (0x00 because bit 3 set in $genb)
				array('V', 0xFFFFFFFF),     // compressed data length
				array('V', 0xFFFFFFFF),     // uncompressed data length
				array('v', strlen($name)),  // filename length
				array('v', strlen($extra)), // extra data len
			);

			// pack fields and calculate "total" length
			$ret = $this->packFields($fields);

			// print header and filename
			fwrite($this->baseStream, $ret . $name . $extra);

			// Keep track of data for central directory record
			$this->currFileStreamInfo = array(
				$name,
				$opt,
				$meth,
				// 3-5 will be filled in by complete_file_stream()
				6 => (strlen($ret) + strlen($name) + strlen($extra)),
				7 => $genb,
			);

			$this->isFileStreaming = true;


			// create write stream
			$context = stream_context_create(array('zip64.write' => array(
				'writer' => $this
			)));
			return fopen('zip64.write://', 'w', false, $context);
		}

		/**
		 * Writes data to the current stream
		 * @param string $data The data to write
		 * @return int Number of bytes written
		 * @throws ZipNotStreamingException
		 * @throws ZipWriteException
		 */
		public function write($data) {
			if (!$this->isFileStreaming)
				throw new ZipNotStreamingException();

			// update sizes
			$this->currFileStreamLength = gmp_add(gmp_init(strlen($data)), $this->currFileStreamLength);
			$this->currFileStreamCompressedLength = gmp_add(gmp_init(strlen($data)), $this->currFileStreamCompressedLength);

			// update hash
			hash_update($this->currFileStreamHash, $data);

			// write data
			$res = fwrite($this->baseStream, $data);
			if ($res === 0)
				throw new ZipWriteException();

			return $res;
		}

		/**
		 * Closes the current file stream writing data descriptor and closing stream
		 * @throws ZipNotStreamingException
		 */
		public function endFile() {
			if (!$this->isFileStreaming)
				throw new ZipNotStreamingException();

			$this->isFileStreaming = false;

			$crc = hexdec(hash_final($this->currFileStreamHash));

			// build data descriptor
			$fields = array(                // (from V.A of APPNOTE.TXT)
                array('V', 0x08074b50),     // data descriptor
                array('V', $crc),           // crc32 of data
			);

			// convert the 64 bit ints to 2 32bit ints
			list($zlen_low, $zlen_high) = $this->int64Split($this->currFileStreamCompressedLength);
			list($len_low, $len_high) = $this->int64Split($this->currFileStreamLength);

			$fields_len = array(
				array('V', $zlen_low),      // compressed data length (low)
				array('V', $zlen_high),     // compressed data length (high)
				array('V', $len_low),       // uncompressed data length (low)
				array('V', $len_high),      // uncompressed data length (high)
			);

			// pack fields and calculate "total" length
			$ret = $this->packFields($fields) . $this->packFields($fields_len);

			// print header and filename
			fwrite($this->baseStream, $ret);

			// Update cdr for file record
			$this->currFileStreamInfo[3] = $crc;
			$this->currFileStreamInfo[4] = gmp_strval($this->currFileStreamCompressedLength);
			$this->currFileStreamInfo[5] = gmp_strval($this->currFileStreamLength);
			$this->currFileStreamInfo[6] += (1 * gmp_strval(gmp_add(gmp_init(strlen($ret)), $this->currFileStreamCompressedLength)));
			ksort($this->currFileStreamInfo);

			// Add to cdr and increment offset - can't call directly because we pass an array of params
			call_user_func_array(array($this, 'addToCdr'), $this->currFileStreamInfo);
		}

		/**
		 * Flushes all zip headers and indices to the stream. This should always be called before closing the underlying stream.
		 */
		public function flushIndex() {

			foreach ($this->files as $file) {
				$this->addCdrFile($file);
			}

			$this->addCdrEofZip64();
			$this->addCdrEofLocatorZip64();

			$this->addCdrEof();

			// clear
			$this->files     = array();
			$this->cdrOffset = 0;
			$this->cdrLength = 0;
		}

		/**
		 * Convert a UNIX timestamp to a DOS timestamp.
		 * @param int $when unix timestamp
		 * @return string DOS timestamp
		 */
		protected function dosTime($when = 0) {
			// get date array for timestamp
			$d = getdate($when);

			// set lower-bound on dates
			if ($d['year'] < 1980) {
				$d = array('year'  => 1980, 'mon' => 1, 'mday' => 1,
				           'hours' => 0, 'minutes' => 0, 'seconds' => 0
				);
			}

			// remove extra years from 1980
			$d['year'] -= 1980;

			// return date string
			return ($d['year'] << 25) | ($d['mon'] << 21) | ($d['mday'] << 16) |
			       ($d['hours'] << 11) | ($d['minutes'] << 5) | ($d['seconds'] >> 1);
		}

		/**
		 * Split a 64bit integer to two 32bit integers
		 * @param mixed $value integer or gmp resource
		 * @return array containing high and low 32bit integers
		 */
		protected function int64Split($value) {
			// gmp
			if (is_resource($value)) {
				$hex = str_pad(gmp_strval($value, 16), 16, '0', STR_PAD_LEFT);

				$low  = $this->gmpConvert(substr($hex, 0, 8), 16, 10);
				$high = $this->gmpConvert(substr($hex, 8, 8), 16, 10);
			}
			// int
			else {
				$left  = 0xffffffff00000000;
				$right = 0x00000000ffffffff;

				$low  = ($value & $left) >> 32;
				$high = $value & $right;
			}

			return array($high, $low);
		}

		/**
		 * Convert a number between bases via gmp
		 * @param int $num number to convert
		 * @param int $base_a base to convert from
		 * @param int $base_b base to convert to
		 * @throws \Exception
		 * @return string number in string format
		 */
		protected function gmpConvert($num, $base_a, $base_b) {
			$gmp_num = gmp_init($num, $base_a);

			if (!$gmp_num)
				throw new \Exception("gmp_convert could not convert [$num] from base [$base_a] to base [$base_b]");

			return gmp_strval($gmp_num, $base_b);
		}

		/**
		 * Save file attributes for trailing CDR record
		 * @param string $name path / name of the file
		 * @param array $opt array containing time
		 * @param int $meth method of compression to use
		 * @param string $crc computed checksum of the file
		 * @param int $zlen compressed size
		 * @param int $len uncompressed size
		 * @param int $rec_len size of the record
		 * @param int $genb general purpose bit flag
		 */
		protected function addToCdr($name, $opt, $meth, $crc, $zlen, $len, $rec_len, $genb = 0) {
			$this->files[] = array($name, $opt, $meth, $crc, $zlen, $len, $this->cdrOffset, $genb);
			$this->cdrOffset += $rec_len;
		}

		/**
		 * Send CDR record for specified file (zip64 format).
		 *
		 * @param array $args array of args
		 * @see addToCdr() for details of the args
		 */
		protected function addCdrFile($args) {
			list($name, $opt, $meth, $crc, $zlen, $len, $ofs, $genb) = $args;

			// convert the 64 bit ints to 2 32bit ints
			list($zlen_low, $zlen_high) = $this->int64Split($zlen);
			list($len_low, $len_high) = $this->int64Split($len);
			list($ofs_low, $ofs_high) = $this->int64Split($ofs);

			// ZIP64, necessary for files over 4GB (incl. entire archive size)
			$extra_zip64 = '';
			$extra_zip64 .= pack('VV', $len_low, $len_high);
			$extra_zip64 .= pack('VV', $zlen_low, $zlen_high);
			$extra_zip64 .= pack('VV', $ofs_low, $ofs_high);

			$extra = pack('vv', 1, strlen($extra_zip64)) . $extra_zip64;

			// get attributes
			$comment = isset($opt['comment']) ? $opt['comment'] : '';

			// get dos timestamp
			$dts = $this->dosTime($opt['time']);

			$fields = array(                      // (from V,F of APPNOTE.TXT)
			                                      array('V', 0x02014b50),           // central file header signature
			                                      array('v', self::ZIP_VERSION),        // version made by
			                                      array('v', self::ZIP_VERSION),        // version needed to extract
			                                      array('v', $genb),                // general purpose bit flag
			                                      array('v', $meth),                // compresion method (deflate or store)
			                                      array('V', $dts),                 // dos timestamp
			                                      array('V', $crc),                 // crc32 of data
			                                      array('V', 0xFFFFFFFF),           // compressed data length (zip64 - look in extra)
			                                      array('V', 0xFFFFFFFF),           // uncompressed data length (zip64 - look in extra)
			                                      array('v', strlen($name)),        // filename length
			                                      array('v', strlen($extra)),       // extra data len
			                                      array('v', strlen($comment)),     // file comment length
			                                      array('v', 0),                    // disk number start
			                                      array('v', 0),                    // internal file attributes
			                                      array('V', 32),                   // external file attributes
			                                      array('V', 0xFFFFFFFF),           // relative offset of local header (zip64 - look in extra)
			);

			// pack fields, then append name and comment
			$ret = $this->packFields($fields) . $name . $extra . $comment;

			fwrite($this->baseStream, $ret);

			// increment cdr offset
			$this->cdrLength += strlen($ret);
		}

		/**
		 * Create a format string and argument list for pack(), then call pack() and return the result.
		 * @param array $fields being the format string and value being the data to pack
		 * @return string binary packed data returned from pack()
		 */
		protected function packFields($fields) {
			list ($fmt, $args) = array('', array());

			// populate format string and argument list
			foreach ($fields as $field) {
				$fmt .= $field[0];
				$args[] = $field[1];
			}

			// prepend format string to argument list
			array_unshift($args, $fmt);

			// build output string from header and compressed data
			return call_user_func_array('pack', $args);
		}

		/**
		 * Writes Zip64 end of central directory record
		 */
		protected function addCdrEofZip64() {
			$num = count($this->files);

			list($num_low, $num_high) = $this->int64Split($num);
			list($cdr_len_low, $cdr_len_high) = $this->int64Split($this->cdrLength);
			list($cdr_ofs_low, $cdr_ofs_high) = $this->int64Split($this->cdrOffset);

			$fields = array(
				array('V', 0x06064b50),         // zip64 end of central directory signature
				array('V', 44),                 // size of zip64 end of central directory record (low) minus 12 bytes
				array('V', 0),                  // size of zip64 end of central directory record (high)
				array('v', self::ZIP_VERSION),      // version made by
				array('v', self::ZIP_VERSION),      // version needed to extract
				array('V', 0x0000),             // this disk number (only one disk)
				array('V', 0x0000),             // number of disk with central dir
				array('V', $num_low),           // number of entries in the cdr for this disk (low)
				array('V', $num_high),          // number of entries in the cdr for this disk (high)
				array('V', $num_low),           // number of entries in the cdr (low)
				array('V', $num_high),          // number of entries in the cdr (high)
				array('V', $cdr_len_low),       // cdr size (low)
				array('V', $cdr_len_high),      // cdr size (high)
				array('V', $cdr_ofs_low),       // cdr ofs (low)
				array('V', $cdr_ofs_high),      // cdr ofs (high)
			);

			fwrite($this->baseStream, $this->packFields($fields));
		}

		/**
		 * Write location record for ZIP64 central directory
		 */
		protected function addCdrEofLocatorZip64() {
			list($cdr_ofs_low, $cdr_ofs_high) = $this->int64Split($this->cdrLength + $this->cdrOffset);

			$fields = array(
				array('V', 0x07064b50),         // zip64 end of central dir locator signature
				array('V', 0),                  // this disk number
				array('V', $cdr_ofs_low),       // cdr ofs (low)
				array('V', $cdr_ofs_high),      // cdr ofs (high)
				array('V', 1),                  // total number of disks
			);

			fwrite($this->baseStream, $this->packFields($fields));
		}

		/**
		 * Write CDR EOF (Central Directory Record End-of-File) record. Most values
		 * point to the corresponding values in the ZIP64 CDR.
		 */
		protected function addCdrEof() {
			$fields = array(
				array('V', 0x06054b50),         // end of central file header signature
				array('v', 0xFFFF),             // this disk number (0xFFFF to look in zip64 cdr)
				array('v', 0xFFFF),             // number of disk with cdr (0xFFFF to look in zip64 cdr)
				array('v', 0xFFFF),             // number of entries in the cdr on this disk (0xFFFF to look in zip64 cdr))
				array('v', 0xFFFF),             // number of entries in the cdr (0xFFFF to look in zip64 cdr)
				array('V', 0xFFFFFFFF),         // cdr size (0xFFFFFFFF to look in zip64 cdr)
				array('V', 0xFFFFFFFF),         // cdr offset (0xFFFFFFFF to look in zip64 cdr)
				array('v', 0),                  // zip file comment length
			);

			fwrite($this->baseStream, $this->packFields($fields));
		}
	}