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
	 * Relative paths in the returned array always use forward slashes ("/") as
	 * separators, regardless of platform, so that path comparison between two
	 * source trees is stable.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dir Directory path.
	 * @return array Array of relative paths (forward-slash) => absolute paths (native).
	 */
	public static function getDirectoryFiles($dir)
	{
		$files = [];

		if (! is_dir($dir)) {
			return $files;
		}

		$dir_normalized = rtrim(str_replace('\\', '/', $dir), '/') . '/';

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($iterator as $file) {
			// Skip symlinks to prevent following out of the sandbox or entering cycles.
			if ($file->isLink()) {
				continue;
			}

			if (! $file->isFile()) {
				continue;
			}

			$absolute_path       = $file->getPathname();
			$absolute_normalized = str_replace('\\', '/', $absolute_path);
			$relative_path       = substr($absolute_normalized, strlen($dir_normalized));

			if ('__MACOSX' === $relative_path || 0 === strpos($relative_path, '__MACOSX/')) {
				continue;
			}

			if ('._' === substr(basename($relative_path), 0, 2)) {
				continue;
			}

			$files[ $relative_path ] = $absolute_path;
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
	 * Check if file is a system file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path File path.
	 * @return bool True if file is a system file, false otherwise.
	 */
	public static function isSystemFile($file_path)
	{
		$basename = basename($file_path);
		$basename_lower = strtolower($basename);

		$system_files = [
			'.ds_store',
			'ds_store',
			'desktop.ini',
			'thumbs.db',
		];

		return in_array($basename_lower, $system_files, true);
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
		// Check if it's a system file first.
		if (self::isSystemFile($file_path)) {
			return true;
		}

		$extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
		return in_array($extension, $ignored_extensions, true);
	}

	/**
	 * Read file contents and convert to UTF-8.
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

		if (mb_check_encoding($content, 'UTF-8')) {
			return $content;
		}

		$encoding = mb_detect_encoding($content, [ 'UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII' ], true);
		if (false === $encoding) {
			// ISO-8859-1 can represent any byte sequence, so the conversion never fails.
			$encoding = 'ISO-8859-1';
		}

		return mb_convert_encoding($content, 'UTF-8', $encoding);
	}
}
