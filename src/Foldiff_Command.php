<?php
/**
 * Foldiff_Command
 *
 * @package Foldiff_Command
 */

namespace Nilambar\Foldiff_Command;

use Jfcherng\Diff\DiffHelper;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WP_CLI;
use WP_Error;
use ZipArchive;

/**
 * Foldiff_Command Class.
 *
 * @since 1.0.0
 */
class Foldiff_Command {

	/**
	 * Compares two zip files from URLs and displays the differences.
	 *
	 * Downloads two zip files from the provided URLs, extracts them to temporary
	 * directories, and generates a unified diff showing the differences between
	 * the contents of the two zip files.
	 *
	 * ## OPTIONS
	 *
	 * [--paths=<paths>]
	 * : Two URLs separated by pipe (|). Each URL should point to a zip file.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp foldiff view --paths="https://example.com/file1.zip|https://example.com/file2.zip"
	 *     Downloading and extracting zip files...
	 *     Generating diff...
	 *
	 *     === File: example.txt ===
	 *     @@ -1,3 +1,3 @@
	 *     -old content
	 *     +new content
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Arguments.
	 * @param array $assoc_args Associated arguments.
	 */
	public function view( $args, $assoc_args = [] ) {
		if ( empty( $assoc_args['paths'] ) ) {
			WP_CLI::error( 'Please provide paths argument with two URLs separated by pipe (|).' );
		}

		$paths = $assoc_args['paths'];

		// Validate paths format.
		if ( false === strpos( $paths, '|' ) ) {
			WP_CLI::error( 'Paths must contain two URLs separated by pipe (|).' );
		}

		$urls = explode( '|', $paths );

		if ( 2 !== count( $urls ) ) {
			WP_CLI::error( 'Paths must contain exactly two URLs separated by pipe (|).' );
		}

		$url1 = trim( $urls[0] );
		$url2 = trim( $urls[1] );

		// Validate URLs.
		if ( false === filter_var( $url1, FILTER_VALIDATE_URL ) ) {
			WP_CLI::error( 'First URL is not valid: ' . $url1 );
		}

		if ( false === filter_var( $url2, FILTER_VALIDATE_URL ) ) {
			WP_CLI::error( 'Second URL is not valid: ' . $url2 );
		}

		WP_CLI::log( 'Downloading and extracting zip files...' );

		// Download and extract first zip.
		$dir1 = $this->download_and_extract_zip( $url1 );
		if ( is_wp_error( $dir1 ) ) {
			WP_CLI::error( $dir1->get_error_message() );
		}

		// Download and extract second zip.
		$dir2 = $this->download_and_extract_zip( $url2 );
		if ( is_wp_error( $dir2 ) ) {
			$this->cleanup_temp_directory( $dir1 );
			WP_CLI::error( $dir2->get_error_message() );
		}

		WP_CLI::log( 'Generating diff...' );

		// Generate diff.
		$this->generate_diff( $dir1, $dir2 );

		// Cleanup.
		$this->cleanup_temp_directory( $dir1 );
		$this->cleanup_temp_directory( $dir2 );

		WP_CLI::success( 'Diff generation completed.' );
	}

	/**
	 * Download and extract zip file from URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url URL to zip file.
	 * @return string|WP_Error Temporary directory path or error.
	 */
	private function download_and_extract_zip( $url ) {
		// Create temporary file for zip.
		$temp_zip = tempnam( sys_get_temp_dir(), 'foldiff_zip_' );
		if ( false === $temp_zip ) {
			return new WP_Error( 'temp_file_error', 'Failed to create temporary file.' );
		}

		// Download zip file.
		$zip_content = file_get_contents( $url );
		if ( false === $zip_content ) {
			unlink( $temp_zip );
			return new WP_Error( 'download_error', 'Failed to download zip file from: ' . $url );
		}

		file_put_contents( $temp_zip, $zip_content );

		// Create temporary directory for extraction.
		$temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'foldiff_' . uniqid( '', true );
		if ( ! mkdir( $temp_dir, 0755, true ) ) {
			unlink( $temp_zip );
			return new WP_Error( 'temp_dir_error', 'Failed to create temporary directory.' );
		}

		// Extract zip file.
		$zip = new ZipArchive();
		$result = $zip->open( $temp_zip );
		if ( true !== $result ) {
			unlink( $temp_zip );
			rmdir( $temp_dir );
			return new WP_Error( 'zip_open_error', 'Failed to open zip file from: ' . $url );
		}

		$zip->extractTo( $temp_dir );
		$zip->close();

		// Clean up temporary zip file.
		unlink( $temp_zip );

		return $temp_dir;
	}

	/**
	 * Generate diff between two directories.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dir1 First directory path.
	 * @param string $dir2 Second directory path.
	 */
	private function generate_diff( $dir1, $dir2 ) {
		$files1 = $this->get_directory_files( $dir1 );
		$files2 = $this->get_directory_files( $dir2 );

		$all_files = array_unique( array_merge( array_keys( $files1 ), array_keys( $files2 ) ) );
		sort( $all_files );

		foreach ( $all_files as $relative_path ) {
			$file_path1 = $files1[ $relative_path ] ?? null;
			$file_path2 = $files2[ $relative_path ] ?? null;

			if ( $file_path1 && $file_path2 ) {
				// Both files exist, compare them.
				$content1 = file_get_contents( $file_path1 );
				$content2 = file_get_contents( $file_path2 );

				if ( $content1 === $content2 ) {
					continue; // Skip identical files.
				}

				WP_CLI::log( "\n=== File: {$relative_path} ===" );
				$diff = DiffHelper::calculate( $content1, $content2, 'Unified' );
				WP_CLI::log( $diff );
			} elseif ( $file_path1 ) {
				// File only exists in first directory.
				WP_CLI::log( "\n=== File: {$relative_path} ===" );
				WP_CLI::log( 'File only exists in first directory (removed).' );
			} else {
				// File only exists in second directory.
				WP_CLI::log( "\n=== File: {$relative_path} ===" );
				WP_CLI::log( 'File only exists in second directory (added).' );
			}
		}
	}

	/**
	 * Get all files in directory recursively.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dir Directory path.
	 * @return array Array of relative paths => absolute paths.
	 */
	private function get_directory_files( $dir ) {
		$files = array();

		if ( ! is_dir( $dir ) ) {
			return $files;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$absolute_path = $file->getPathname();
				$relative_path = str_replace( $dir . DIRECTORY_SEPARATOR, '', $absolute_path );
				$files[ $relative_path ] = $absolute_path;
			}
		}

		return $files;
	}

	/**
	 * Cleanup temporary directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dir Directory path.
	 */
	private function cleanup_temp_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isDir() ) {
				rmdir( $file->getPathname() );
			} else {
				unlink( $file->getPathname() );
			}
		}

		rmdir( $dir );
	}
}
