<?php

	namespace S3Backup\Console\Command;

	use fkooman\Ini\IniReader;
	use Knp\Command\Command;
	use S3Backup\Reader\ArchiveReader;
	use S3Backup\Reader\BucketReader;
	use S3Backup\Writer\ArchiveWriter;
	use S3Backup\Writer\BucketWriter;
	use Symfony\Component\Console\Helper\QuestionHelper;
	use Symfony\Component\Console\Input\InputArgument;
	use Symfony\Component\Console\Input\InputInterface;
	use Symfony\Component\Console\Input\InputOption;
	use Symfony\Component\Console\Output\OutputInterface;
	use Symfony\Component\Console\Question\Question;

	class CopyCommand extends Command
	{
		/**
		 *
		 * @var IniReader
		 */
		protected $credentialReader;
		protected $lastMessageLength = 0;

		protected function configure() {
			$this
				->setName('copy')
				->setDescription('Copy bucket objects')
				->addArgument('source', InputArgument::REQUIRED, 'Source bucket or file')
				->addArgument('target', InputArgument::REQUIRED, 'Target bucket or file')
				->addOption('profile', null, InputOption::VALUE_REQUIRED, 'The profile used for credentials')
				->addOption('source-region', null, InputOption::VALUE_REQUIRED, 'The aws region for the source bucket')
				->addOption('target-region', null, InputOption::VALUE_REQUIRED, 'The aws region for the target bucket')
				->addOption('prefix', null, InputOption::VALUE_REQUIRED, 'Prefix to match for files to copy')
				->addOption('std', 's', InputOption::VALUE_NONE, 'Read keys to copy from stdin instead of copying all keys')
			;
		}

		public function readCredentialFile($fn) {
			try {
				$this->credentialReader = IniReader::fromFile($fn);
			}
			catch(\Exception $ex) {

			}
		}

		protected function execute(InputInterface $input, OutputInterface $output) {
			/**
			 * @var QuestionHelper $dialog
			 */
			$dialog = $this->getHelper('question');

			// get credentials file
			$this->readCredentialFile($_SERVER['HOME'] . '/.aws/credentials');
			if (empty($this->credentialReader))
				$this->readCredentialFile($_SERVER['HOME'] . '/.aws/config');
			while (empty($this->credentialReader)) {
				$output->writeln('<error>Could not read AWS credentials file.</error>');
				$fn = $dialog->ask(
					$input,
					$output,
					new Question("AWS credentials file: ")
				);

				$this->readCredentialFile($fn);
			}


			// read configuration
			$profile = $input->getOption('profile');
			if (empty($profile))
				$profile = 'default';
			try {
				$awsAccessKey = $this->credentialReader->v($profile, 'aws_access_key_id');
			}
			catch(\Exception $ex) {
				$output->writeln('<error>aws_access_key_id not found</error>');
				return 1;
			}
			try {
				$awsSecretKey = $this->credentialReader->v($profile, 'aws_secret_access_key');
			}
			catch (\Exception $ex) {
				$output->writeln('<error>aws_secret_access_key not found</error>');

				return 1;
			}
			$awsRegion = null;
			try {
				$awsRegion = $this->credentialReader->v($profile, 'region');
			}
			catch (\Exception $ex) {}


			$source = $input->getArgument('source');
			$target = $input->getArgument('target');
			$sourceRegion = $input->getOption('source-region');
			$targetRegion = $input->getOption('target-region');

			if (empty($sourceRegion))
				$sourceRegion = $awsRegion;
			if (empty($targetRegion))
				$targetRegion = $awsRegion;


			// get prefix
			$prefix = $input->getOption('prefix');
			$prefixLength = (!is_null($prefix) ? strlen($prefix) : 0);


			// setup reader
			if (substr($source, 0, 5) == 's3://') {
				if (empty($sourceRegion)) {
					$output->writeln('<error>no region for source bucket configured</error>');

					return 1;
				}

				$sourceReader = new BucketReader(substr($source, 5), $awsAccessKey, $awsSecretKey, $sourceRegion);
			}
			else {
				$sourceReader = new ArchiveReader($source);
			}

			// setup writer
			if (substr($target, 0, 5) == 's3://') {
				if (empty($targetRegion)) {
					$output->writeln('<error>no region for source bucket configured</error>');

					return 1;
				}

				$targetWriter = new BucketWriter(substr($target, 5), $awsAccessKey, $awsSecretKey, $targetRegion);
			}
			else {
				$targetWriter = new ArchiveWriter($target);
			}

			$errors = array();
			try {
				$output->writeln('Opening source...');
				$sourceReader->init();

				$output->writeln('Opening target...');
				$targetWriter->init();


				$numObj = $sourceReader->countObjects();
				$output->writeln($numObj . ' objects in source');

				$output->writeln('');

				$stdIn = fopen('php://stdin', 'r');

				if ($input->getOption('std')) {
					// read keys from std in


					$sourceKeys = array();
					$numObj     = $sourceReader->countObjects();
					for ($i = 0; $i < $numObj; ++$i) {
						$sourceKeys[] = $sourceReader->getObjectKey($i);
					}

					$notFound = 0;
					$successCount = 0;
					$i = 0;
					while ($ln = fgets($stdIn)) {
						$key = trim($ln);
						$sr = array_search($key, $sourceKeys);
						if ($sr !== false) {

							$output->write('[' . $i . '] ' . $key . "\n Object " . ($i + 1) . "\r");

							try {
								$targetWriter->writeObject($sourceReader->readObject($sr));
								++$successCount;
							}
							catch (\Exception $ex) {
								$output->writeln('  <error>' . $ex->getMessage() . '</error>');
								$errors[$sourceReader->getObjectKey($sr)] = $ex;
							}
						}
						else {
							$output->writeln('  <error> Key ' . $key . ' not found in source</error>');
							++$notFound;
						}
						++$i;
					}

					$output->writeln('');
					$output->writeln('');
					$output->writeln('Successful: ' . ($successCount));
					$output->writeln('Not found:  ' . $notFound);
					if (count($errors) > 0)
						$output->writeln('Errors:     <error>' . count($errors) . '</error>');
					else
						$output->writeln('Errors:     ' . count($errors));
				}
				else {
					// copy all keys

					$skippedCount = 0;
					for ($i = 0; $i < $numObj; ++$i) {
						$key = $sourceReader->getObjectKey($i);

						if ($prefixLength == 0 || substr($key, 0, $prefixLength) == $prefix) {
							$output->write('[' . ($i - $skippedCount) . '] ' . $key . "\n Object " . ($i + 1) . ' of ' . $numObj . ' (' . number_format(($i / $numObj) * 100, 2) . "%)\r");

							try {
								$targetWriter->writeObject($sourceReader->readObject($i));
							}
							catch (\Exception $ex) {
								$output->writeln('  <error>' . $ex->getMessage() . '</error>');
								$errors[$sourceReader->getObjectKey($i)] = $ex;
							}
						}
						else {
							++$skippedCount;
							$output->write("Object " . ($i + 1) . ' of ' . $numObj . ' (' . number_format(($i / $numObj) * 100, 2) . "%) Skipped: " . $skippedCount . "\r");
						}
					}


					$output->writeln('');
					$output->writeln('');
					$output->writeln('Successful: ' . ($numObj - count($errors) - $skippedCount));
					$output->writeln('Skipped:    ' . $skippedCount);
					if (count($errors) > 0)
						$output->writeln('Errors:     <error>' . count($errors) . '</error>');
					else
						$output->writeln('Errors:     ' . count($errors));

				}

				if (count($errors) > 0) {
					$output->writeln('');
					$output->writeln('Error files:');
					foreach ($errors as $key => $exception) {
						$output->writeln($key);
					}
				}

				$sourceReader->close();
				$targetWriter->close();
			}
			catch(\Exception $ex) {
				$output->writeln('<error>' . $ex->getMessage() . '</error>');
				return 1;
			}

			return 0;
		}



	}