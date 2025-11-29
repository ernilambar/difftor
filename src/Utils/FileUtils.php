<?php

/**
 * FileUtils
 *
 * @package Difftor
 */

namespace Nilambar\Difftor\Utils;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * FileUtils Class.
 *
 * @since 1.0.0
 */
class FileUtils
{
	/**
	 * Get all files in directory recursively.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dir Directory path.
	 * @return array Array of relative paths => absolute paths.
	 */
	public static function getDirectoryFiles($dir)
	{
		$files = [];

		if (! is_dir($dir)) {
			return $files;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($iterator as $file) {
			if ($file->isFile()) {
				$absolute_path = $file->getPathname();
				$relative_path = str_replace($dir . DIRECTORY_SEPARATOR, '', $absolute_path);

				// Skip __MACOSX folder and its contents.
				if ('__MACOSX' === $relative_path || 0 === strpos($relative_path, '__MACOSX' . DIRECTORY_SEPARATOR)) {
					continue;
				}

				// Skip macOS resource fork files (._*).
				$basename = basename($relative_path);
				if ('._' === substr($basename, 0, 2)) {
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
	public static function cleanupTempDirectory($dir)
	{
		if (! is_dir($dir)) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($iterator as $file) {
			if ($file->isDir()) {
				rmdir($file->getPathname());
			} else {
				unlink($file->getPathname());
			}
		}

		rmdir($dir);
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
	public static function shouldIgnoreFile($file_path, $ignored_extensions)
	{
		$extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
		return in_array($extension, $ignored_extensions, true);
	}

	/**
	 * Read file contents and convert to UTF-8 safely.
	 *
	 * Handles encoding conversion errors gracefully by using fallback methods.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path File path to read.
	 * @return string|false UTF-8 encoded file contents, or false on error.
	 */
	public static function readFileAsUtf8($file_path)
	{
		if (! is_file($file_path) || ! is_readable($file_path)) {
			return false;
		}

		$content = file_get_contents($file_path);
		if (false === $content) {
			return false;
		}

		// Check if content is already valid UTF-8.
		if (mb_check_encoding($content, 'UTF-8')) {
			return $content;
		}

		// Try to detect encoding.
		$detected_encoding = mb_detect_encoding($content, [ 'UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII' ], true);
		if (false === $detected_encoding) {
			// If detection fails, try common encodings.
			$detected_encoding = mb_detect_encoding($content, mb_list_encodings(), true);
		}

		// If still not detected, default to ISO-8859-1 (Latin-1) which can represent any byte.
		if (false === $detected_encoding) {
			$detected_encoding = 'ISO-8859-1';
		}

		// Convert to UTF-8 using mb_convert_encoding with error handling.
		$utf8_content = @mb_convert_encoding($content, 'UTF-8', $detected_encoding);
		if (false === $utf8_content || ! is_string($utf8_content)) {
			// Fallback: use iconv with IGNORE flag to skip invalid characters.
			$utf8_content = @iconv($detected_encoding, 'UTF-8//IGNORE', $content);
			if (false === $utf8_content || ! is_string($utf8_content)) {
				// Try with TRANSLIT flag as another fallback.
				$utf8_content = @iconv($detected_encoding, 'UTF-8//TRANSLIT', $content);
				if (false === $utf8_content || ! is_string($utf8_content)) {
					// Last resort: try to sanitize by removing invalid UTF-8 sequences.
					$utf8_content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
					if (false === $utf8_content || ! is_string($utf8_content)) {
						// If all else fails, return original content (may cause issues but better than false).
						return $content;
					}
				}
			}
		}

		// Ensure we always return a string, never false.
		return is_string($utf8_content) ? $utf8_content : $content;
	}
}
