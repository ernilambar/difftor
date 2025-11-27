<?php
/**
 * Zip_Utils
 *
 * @package Difftor_Command
 */

namespace Nilambar\Difftor_Command\Utils;

use WP_CLI;
use ZipArchive;

/**
 * Zip_Utils Class.
 *
 * @since 1.0.0
 */
class Zip_Utils {

	/**
	 * Extract local zip file to temporary directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $zip_path Path to local zip file.
	 * @return string|false Temporary directory path or false on error.
	 */
	public static function extract_local_zip( $zip_path ) {
		if ( ! is_file( $zip_path ) ) {
			WP_CLI::warning( 'Zip file does not exist: ' . $zip_path );
			return false;
		}

		// Check if file is empty.
		if ( 0 === filesize( $zip_path ) ) {
			WP_CLI::warning( 'Zip file is empty: ' . $zip_path );
			return false;
		}

		// Create temporary directory for extraction.
		$temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'difftor_' . uniqid( '', true );
		if ( ! mkdir( $temp_dir, 0755, true ) ) {
			WP_CLI::warning( 'Failed to create temporary directory.' );
			return false;
		}

		// Extract zip file.
		$zip    = new ZipArchive();
		$result = $zip->open( $zip_path );
		if ( true !== $result ) {
			rmdir( $temp_dir );
			WP_CLI::warning( 'Failed to open zip file: ' . $zip_path );
			return false;
		}

		// Extract files, skipping __MACOSX folder and macOS metadata files.
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$entry_name = $zip->getNameIndex( $i );
			if ( false === $entry_name ) {
				continue;
			}

			// Skip __MACOSX folder and its contents.
			if ( '__MACOSX/' === $entry_name || 0 === strpos( $entry_name, '__MACOSX/' ) ) {
				continue;
			}

			// Skip macOS resource fork files (._*).
			$basename = basename( $entry_name );
			if ( '._' === substr( $basename, 0, 2 ) ) {
				continue;
			}

			// Extract the entry (file or directory).
			// extractTo will create directory structure automatically.
			$zip->extractTo( $temp_dir, [ $entry_name ] );
		}

		$zip->close();

		return $temp_dir;
	}

	/**
	 * Download and extract zip file from URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url URL to zip file.
	 * @return string|false Temporary directory path or false on error.
	 */
	public static function download_and_extract_zip( $url ) {
		// Create temporary file for zip.
		$temp_zip = tempnam( sys_get_temp_dir(), 'difftor_zip_' );
		if ( false === $temp_zip ) {
			WP_CLI::warning( 'Failed to create temporary file.' );
			return false;
		}

		// Download zip file using cURL for better error handling.
		$ch = curl_init( $url );
		if ( false === $ch ) {
			unlink( $temp_zip );
			WP_CLI::warning( 'Failed to initialize cURL for: ' . $url );
			return false;
		}

		$fp = fopen( $temp_zip, 'wb' );
		if ( false === $fp ) {
			curl_close( $ch );
			unlink( $temp_zip );
			WP_CLI::warning( 'Failed to open temporary file for writing.' );
			return false;
		}

		curl_setopt_array(
			$ch,
			[
				CURLOPT_FILE           => $fp,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_TIMEOUT        => 300,
				CURLOPT_CONNECTTIMEOUT => 30,
				CURLOPT_USERAGENT      => 'WP-CLI Difftor Command',
			]
		);

		$success    = curl_exec( $ch );
		$http_code  = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$curl_error = curl_error( $ch );
		curl_close( $ch );
		fclose( $fp );

		if ( false === $success || ! empty( $curl_error ) ) {
			unlink( $temp_zip );
			WP_CLI::warning( 'Failed to download zip file from: ' . $url . ' - ' . $curl_error );
			return false;
		}

		if ( 200 !== $http_code ) {
			unlink( $temp_zip );
			WP_CLI::warning( 'Failed to download zip file from: ' . $url . ' - HTTP ' . $http_code );
			return false;
		}

		// Check if file is empty.
		if ( 0 === filesize( $temp_zip ) ) {
			unlink( $temp_zip );
			WP_CLI::warning( 'Downloaded zip file is empty from: ' . $url );
			return false;
		}

		// Create temporary directory for extraction.
		$temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'difftor_' . uniqid( '', true );
		if ( ! mkdir( $temp_dir, 0755, true ) ) {
			unlink( $temp_zip );
			WP_CLI::warning( 'Failed to create temporary directory.' );
			return false;
		}

		// Extract zip file.
		$zip    = new ZipArchive();
		$result = $zip->open( $temp_zip );
		if ( true !== $result ) {
			unlink( $temp_zip );
			rmdir( $temp_dir );
			WP_CLI::warning( 'Failed to open zip file from: ' . $url );
			return false;
		}

		// Extract files, skipping __MACOSX folder and macOS metadata files.
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$entry_name = $zip->getNameIndex( $i );
			if ( false === $entry_name ) {
				continue;
			}

			// Skip __MACOSX folder and its contents.
			if ( '__MACOSX/' === $entry_name || 0 === strpos( $entry_name, '__MACOSX/' ) ) {
				continue;
			}

			// Skip macOS resource fork files (._*).
			$basename = basename( $entry_name );
			if ( '._' === substr( $basename, 0, 2 ) ) {
				continue;
			}

			// Extract the entry (file or directory).
			// extractTo will create directory structure automatically.
			$zip->extractTo( $temp_dir, [ $entry_name ] );
		}

		$zip->close();

		// Clean up temporary zip file.
		unlink( $temp_zip );

		return $temp_dir;
	}
}
