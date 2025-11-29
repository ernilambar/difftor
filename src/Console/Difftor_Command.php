<?php
/**
 * Difftor_Command
 *
 * @package Difftor
 */

namespace Nilambar\Difftor\Console;

use Nilambar\Difftor\Difftor_Service;
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
class Difftor_Command extends Command {

	/**
	 * Configure the command.
	 *
	 * @since 1.0.0
	 */
	protected function configure() {
		$this->setName( 'difftor' )
			->setDescription( 'Compare two sources (URLs, local directories, or zip files) and generate an HTML diff file.' )
			->setHelp(
				'This command compares two sources and generates an HTML diff file showing the differences.' . "\n\n" .
				'Supports comparing:' . "\n" .
				'  - Two URLs pointing to zip files' . "\n" .
				'  - Two local directories' . "\n" .
				'  - Two local zip files' . "\n" .
				'  - Mixed combinations (e.g., URL and local directory)' . "\n\n" .
				'Examples:' . "\n" .
				'  difftor https://example.com/file1.zip https://example.com/file2.zip' . "\n" .
				'  difftor /path/to/old-folder /path/to/new-folder' . "\n" .
				'  difftor /path/to/old.zip /path/to/new.zip' . "\n" .
				'  difftor https://example.com/old.zip /path/to/new-folder'
			)
			->addArgument(
				'old_source',
				InputArgument::REQUIRED,
				'Path to the old/original source. Can be a URL, local directory, or zip file.'
			)
			->addArgument(
				'new_source',
				InputArgument::REQUIRED,
				'Path to the new/modified source. Can be a URL, local directory, or zip file.'
			)
			->addOption(
				'output-dir',
				'o',
				InputOption::VALUE_REQUIRED,
				'Output directory for the HTML diff file. Defaults to system temp directory.'
			)
			->addOption(
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
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$old_source = $input->getArgument( 'old_source' );
		$new_source = $input->getArgument( 'new_source' );
		$output_dir = $input->getOption( 'output-dir' );
		$porcelain  = $input->getOption( 'porcelain' );

		$service = new Difftor_Service();

		$output->writeln( '<info>Preparing sources...</info>', OutputInterface::VERBOSITY_VERBOSE );

		$html_file = $service->generate_diff( $old_source, $new_source, $output_dir );

		if ( false === $html_file ) {
			$output->writeln( '<error>Failed to generate diff. Please check your sources and try again.</error>' );
			return Command::FAILURE;
		}

		if ( $porcelain ) {
			$output->writeln( $html_file );
		} else {
			$output->writeln( '<info>Diff HTML file generated: ' . $html_file . '</info>' );
		}

		return Command::SUCCESS;
	}
}
