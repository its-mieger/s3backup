<?php

	namespace S3Backup;

	use Guzzle\Http\EntityBody;

	class S3BodyStream
	{

		/**
		 * The stream context
		 * @var Resource
		 */
		public $context;

		/**
		 * @var EntityBody
		 */
		protected $body;

		public function stream_open() {

			$opt    = stream_context_get_options($this->context);
			$tOpt = (!empty($opt["s3backup.s3body"]) ? $opt["s3backup.s3body"] : array());

			if (empty($tOpt['body']))
				return false;

			$this->body = $tOpt['body'];

			return $this->body->rewind();
		}

		public function stream_read($count) {
			return $this->body->read($count);
		}

		public function stream_seek($offset, $whence = SEEK_SET) {
			return $this->body->seek($offset, $whence);
		}

		public function stream_tell() {
			return $this->body->ftell();
		}

		public function stream_eof() {
			return $this->body->feof();
		}

		public function stream_stat() {
			return array(
				0 => 0,
			    1 => 0,
			    2 => 0,
			    3 => 0,
			    4 => 0,
			    5 => 0,
			    6 => 0,
			    7 => $this->body->getSize(),
			    8 => 0,
			    9 => 0,
			    10 => 0,
			    11 => 0,
			    12 => 0,
			    'dev' => 0,
			    'ino' => 0,
			    'mode' => 0,
			    'nlink' => 0,
			    'uid' => 0,
			    'gid' => 0,
			    'rdev' => 0,
			    'size' => $this->body->getSize(),
			    'mtime' => 0,
			    'ctime' => 0,
			    'blksize' => 0,
			    'blocks' => 0,
			);
		}

		public function stream_close() {
			$this->body->close();
		}

	}

	// register handler
	stream_wrapper_register("s3backup.s3body", "\\S3Backup\\S3BodyStream");