<?php
/**
 * File_Utils
 *
 * @package Difftor
 */

namespace Nilambar\Difftor\Utils;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * File_Utils Class.
 *
 * @since 1.0.0
 */
class File_Utils {

	/**
	 * Get all files in directory recursively.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dir Directory path.
	 * @return array Array of relative paths => absolute paths.
	 */
	public static function get_directory_files( $dir ) {
		$files = [];

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

				// Skip __MACOSX folder and its contents.
				if ( '__MACOSX' === $relative_path || 0 === strpos( $relative_path, '__MACOSX' . DIRECTORY_SEPARATOR ) ) {
					continue;
				}

				// Skip macOS resource fork files (._*).
				$basename = basename( $relative_path );
				if ( '._' === substr( $basename, 0, 2 ) ) {
					continue;
				}

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
	public static function cleanup_temp_directory( $dir ) {
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

	/**
	 * Check if file should be ignored from diff.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path File path.
	 * @param array  $ignored_extensions List of ignored extensions.
	 * @return bool True if file should be ignored, false otherwise.
	 */
	public static function should_ignore_file( $file_path, $ignored_extensions ) {
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		return in_array( $extension, $ignored_extensions, true );
	}
}
