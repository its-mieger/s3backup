<?php

	use S3Backup\Console\Command\CopyCommand;
	use S3Backup\Console\Command\DiffCommand;
	use S3Backup\Console\Command\ListCommand;

	set_time_limit(0);

	$app = require_once(__DIR__ . '/src/S3Backup/Console/bootstrap.php');


	/** @var Knp\Console\Application $application */
	$application = $app['console'];


	$application->add(new CopyCommand());
	$application->add(new ListCommand());
	$application->add(new DiffCommand());


	$application->run();