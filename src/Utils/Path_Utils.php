<?php
/**
 * Path_Utils
 *
 * @package Difftor
 */

namespace Nilambar\Difftor\Utils;

/**
 * Path_Utils Class.
 *
 * @since 1.0.0
 */
class Path_Utils {

	/**
	 * Normalize path by expanding home directory and resolving relative paths.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Path to normalize.
	 * @return string Normalized path.
	 */
	public static function normalize_path( $path ) {
		// Expand home directory (~).
		if ( '~' === $path || 0 === strpos( $path, '~/' ) ) {
			$home = getenv( 'HOME' );
			if ( false !== $home ) {
				$path = str_replace( '~', $home, $path );
			}
		}

		// Resolve relative paths to absolute.
		if ( ! self::is_url( $path ) && ! self::is_absolute_path( $path ) ) {
			$resolved = realpath( $path );
			if ( false !== $resolved ) {
				$path = $resolved;
			}
			// If realpath fails, keep original path - validation will catch it later.
		}

		return $path;
	}

	/**
	 * Check if path is a URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Path to check.
	 * @return bool True if path is a URL, false otherwise.
	 */
	public static function is_url( $path ) {
		return false !== filter_var( $path, FILTER_VALIDATE_URL );
	}

	/**
	 * Check if path is an absolute path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Path to check.
	 * @return bool True if path is absolute, false otherwise.
	 */
	public static function is_absolute_path( $path ) {
		// Check for Unix absolute path.
		if ( '/' === substr( $path, 0, 1 ) ) {
			return true;
		}

		// Check for Windows absolute path (C:\ or \\server).
		if ( preg_match( '/^[A-Za-z]:\\\\/', $path ) || '\\\\' === substr( $path, 0, 2 ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if path is a local directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Path to check.
	 * @return bool True if path is a directory, false otherwise.
	 */
	public static function is_local_directory( $path ) {
		return is_dir( $path );
	}

	/**
	 * Check if path is a local zip file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Path to check.
	 * @return bool True if path is a zip file, false otherwise.
	 */
	public static function is_local_zip( $path ) {
		if ( ! is_file( $path ) ) {
			return false;
		}

		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		return 'zip' === $extension;
	}
}
