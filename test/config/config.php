<?php

	//define('TEST_AWS_ACCESS_KEY_ID', '');
	//define('TEST_AWS_SECRET_ACCESS_KEY', '');
	//define('TEST_AWS_REGION', '');

	include_once(__DIR__ . '/config.private.php');

	// CAUTION: Bucket will be modified for tests
	define('TEST_READ_BUCKET', 's3backup-bucket-read-test');

	// CAUTION: Bucket will be modified for tests
	define('TEST_WRITE_BUCKET', 's3backup-bucket-write-test');

	define('TEST_WRITE_FILE', '/tmp/testS3Backup.zip');
	define('TEST_READ_FILE', '/tmp/testS3Backup.zip');