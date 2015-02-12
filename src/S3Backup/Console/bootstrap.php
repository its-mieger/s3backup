<?php
	use Knp\Provider\ConsoleServiceProvider;

	date_default_timezone_set('Europe/Berlin');

	if (empty($loader)) {
		$loader = require(__DIR__ . '/../../../vendor/autoload.php');
	}


	$app = new Silex\Application();

	$app->register(
		new ConsoleServiceProvider(),
		array(
			'console.name'              => 'SchuhmobilIsotopeBits',
			'console.version'           => '0.1.0',
			'console.project_directory' => __DIR__ . "/.."
		)
	);

	return $app;