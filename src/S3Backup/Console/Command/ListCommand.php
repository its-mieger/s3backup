<?php

	namespace S3Backup\Console\Command;

	use fkooman\Ini\IniReader;
	use Knp\Command\Command;
	use S3Backup\Reader\ArchiveReader;
	use S3Backup\Reader\BucketReader;
	use Symfony\Component\Console\Helper\QuestionHelper;
	use Symfony\Component\Console\Input\InputArgument;
	use Symfony\Component\Console\Input\InputInterface;
	use Symfony\Component\Console\Input\InputOption;
	use Symfony\Component\Console\Output\OutputInterface;
	use Symfony\Component\Console\Question\Question;

	class ListCommand extends Command
	{
		/**
		 *
		 * @var IniReader
		 */
		protected $credentialReader;
		protected $lastMessageLength = 0;

		protected function configure() {
			$this
				->setName('list')
				->setDescription('List objects in bucket/archive')
				->addArgument('source', InputArgument::REQUIRED, 'Source bucket or file')
				->addOption('profile', null, InputOption::VALUE_REQUIRED, 'The profile used for credentials')
				->addOption('source-region', null, InputOption::VALUE_REQUIRED, 'The aws region for the source bucket')
				->addOption('prefix', null, InputOption::VALUE_REQUIRED, 'Prefix to match for files to list');
		}

		public function readCredentialFile($fn) {
			try {
				$this->credentialReader = IniReader::fromFile($fn);
			}
			catch (\Exception $ex) {

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
			catch (\Exception $ex) {
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
			catch (\Exception $ex) {
			}


			$source       = $input->getArgument('source');
			$sourceRegion = $input->getOption('source-region');

			if (empty($sourceRegion))
				$sourceRegion = $awsRegion;


			// get prefix
			$prefix       = $input->getOption('prefix');
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

			try {
				$output->writeln('Opening source...');
				$sourceReader->init();


				$numObj = $sourceReader->countObjects();
				$output->writeln($numObj . ' objects in source');

				$output->writeln('');

				for ($i = 0; $i < $numObj; ++$i) {
					$key = $sourceReader->getObjectKey($i);

					if ($prefixLength == 0 || substr($key, 0, $prefixLength) == $prefix) {
						$output->writeln($key);
					}
				}

				$sourceReader->close();
			}
			catch (\Exception $ex) {
				$output->writeln('<error>' . $ex->getMessage() . '</error>');

				return 1;
			}

			return 0;
		}


	}