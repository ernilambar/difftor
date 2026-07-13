<?php

/**
 * DifftorCommand
 *
 * @package Difftor
 */

namespace Nilambar\Difftor\Console;

use InvalidArgumentException;
use Nilambar\Difftor\DifftorService;
use Nilambar\Difftor\Exception\DifftorException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Difftor Console Command Class.
 *
 * Symfony Console command for generating diffs.
 *
 * @since 1.0.0
 */
class DifftorCommand extends Command
{
	/**
	 * Configure the command.
	 *
	 * @since 1.0.0
	 */
	protected function configure()
	{
		$this->setName('difftor')->setDescription('Compare two sources (URLs, local directories, or zip files) and generate an HTML diff file.')->setHelp(
			'This command compares two sources and generates an HTML diff file showing the differences.' . "\n\n" .
				'Supports comparing:' . "\n" .
				'  - Two URLs pointing to zip files (http/https only)' . "\n" .
				'  - Two local directories' . "\n" .
				'  - Two local zip files' . "\n" .
				'  - Mixed combinations (e.g., URL and local directory)' . "\n\n" .
				'Examples:' . "\n" .
				'  difftor https://example.com/file1.zip https://example.com/file2.zip' . "\n" .
				'  difftor /path/to/old-folder /path/to/new-folder' . "\n" .
				'  difftor /path/to/old.zip /path/to/new.zip' . "\n" .
				'  difftor https://example.com/old.zip /path/to/new-folder'
		)->addArgument(
			'old_source',
			InputArgument::REQUIRED,
			'Path to the old/original source. Can be an http(s) URL, local directory, or zip file.'
		)->addArgument(
			'new_source',
			InputArgument::REQUIRED,
			'Path to the new/modified source. Can be an http(s) URL, local directory, or zip file.'
		)->addOption(
			'output-dir',
			'o',
			InputOption::VALUE_REQUIRED,
			'Output directory for the HTML diff file. Defaults to system temp directory.'
		)->addOption(
			'max-download-size',
			null,
			InputOption::VALUE_REQUIRED,
			'Maximum download size for remote sources. Accepts bytes or a suffixed value (e.g., 500M, 1G). Default: 500M.'
		)->addOption(
			'porcelain',
			null,
			InputOption::VALUE_NONE,
			'Output only the file path, suitable for parsing.'
		);
	}

	/**
	 * Execute the command.
	 *
	 * @since 1.0.0
	 *
	 * @param InputInterface  $input Input interface.
	 * @param OutputInterface $output Output interface.
	 * @return int Exit code.
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$old_source        = $input->getArgument('old_source');
		$new_source        = $input->getArgument('new_source');
		$output_dir        = $input->getOption('output-dir');
		$max_download_size = $input->getOption('max-download-size');
		$porcelain         = $input->getOption('porcelain');

		$options = [];
		if (null !== $max_download_size) {
			try {
				$options['max_download_size'] = self::parseSize($max_download_size);
			} catch (InvalidArgumentException $e) {
				$output->writeln('<error>Invalid --max-download-size: ' . $e->getMessage() . '</error>');
				return Command::FAILURE;
			}
		}

		$service = new DifftorService($options);

		$output->writeln('<info>Preparing sources...</info>', OutputInterface::VERBOSITY_VERBOSE);

		try {
			$html_file = $service->generateDiff($old_source, $new_source, $output_dir);
		} catch (DifftorException $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return Command::FAILURE;
		}

		if ($porcelain) {
			$output->writeln($html_file);
		} else {
			$output->writeln('<info>Diff HTML file generated: ' . $html_file . '</info>');
		}

		return Command::SUCCESS;
	}

	/**
	 * Parse a human-readable byte size string.
	 *
	 * Accepts a bare integer or an integer suffixed with K, M, G (case-insensitive,
	 * optional trailing B: KB, MB, GB). Values are base-1024.
	 *
	 * @since 2.0.0
	 *
	 * @param string $value Size string to parse.
	 * @return int Size in bytes.
	 * @throws InvalidArgumentException When the format is not recognized.
	 */
	private static function parseSize($value)
	{
		$value = trim((string) $value);
		if ('' === $value) {
			throw new InvalidArgumentException('value cannot be empty');
		}

		if (1 !== preg_match('/^(\d+)\s*([KMG]?)B?$/i', $value, $matches)) {
			throw new InvalidArgumentException(sprintf('unrecognized size format: %s', $value));
		}

		$number = (int) $matches[1];
		$unit   = strtoupper($matches[2]);

		switch ($unit) {
			case 'G':
				return $number * 1024 * 1024 * 1024;
			case 'M':
				return $number * 1024 * 1024;
			case 'K':
				return $number * 1024;
			default:
				return $number;
		}
	}
}
