<?php

/**
 * ZipUtils
 *
 * @package Difftor
 */

namespace Nilambar\Difftor\Utils;

use Nilambar\Difftor\Exception\DifftorException;
use Nilambar\Difftor\Exception\DownloadFailedException;
use Nilambar\Difftor\Exception\ExtractionLimitException;
use Nilambar\Difftor\Exception\InvalidZipException;
use Nilambar\Difftor\Exception\SourceNotFoundException;
use Throwable;
use ZipArchive;

/**
 * ZipUtils Class.
 *
 * @since 1.0.0
 */
class ZipUtils
{
	/**
	 * Default maximum download size in bytes (500 MB).
	 *
	 * @since 2.0.0
	 */
	public const DEFAULT_MAX_DOWNLOAD_SIZE = 524288000;

	/**
	 * Default maximum uncompressed extracted size in bytes (2 GB).
	 *
	 * @since 2.0.0
	 */
	public const DEFAULT_MAX_EXTRACTED_SIZE = 2147483648;

	/**
	 * Default maximum number of files inside a zip archive.
	 *
	 * @since 2.0.0
	 */
	public const DEFAULT_MAX_FILE_COUNT = 50000;

	/**
	 * Extract local zip file to temporary directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $zip_path           Path to local zip file.
	 * @param int    $max_extracted_size Max uncompressed extracted size in bytes.
	 * @param int    $max_file_count     Max number of entries in the archive.
	 * @return string Temporary directory path.
	 * @throws SourceNotFoundException When the zip file does not exist.
	 * @throws InvalidZipException When the zip is empty, corrupt, or contains unsafe entries.
	 * @throws ExtractionLimitException When the zip exceeds size or count limits.
	 * @throws DifftorException When the temp directory cannot be created.
	 */
	public static function extractLocalZip(
		$zip_path,
		$max_extracted_size = self::DEFAULT_MAX_EXTRACTED_SIZE,
		$max_file_count = self::DEFAULT_MAX_FILE_COUNT
	) {
		if (! is_file($zip_path)) {
			throw new SourceNotFoundException(sprintf('Zip file not found: %s', $zip_path));
		}

		if (0 === filesize($zip_path)) {
			throw new InvalidZipException(sprintf('Zip file is empty: %s', $zip_path));
		}

		$temp_dir = self::createTempDir();

		try {
			self::extractZipTo($zip_path, $temp_dir, $max_extracted_size, $max_file_count);
		} catch (Throwable $e) {
			FileUtils::cleanupTempDirectory($temp_dir);
			throw $e;
		}

		return $temp_dir;
	}

	/**
	 * Download and extract zip file from an http(s) URL.
	 *
	 * Only http and https schemes are permitted. Redirects are followed but
	 * constrained to the same scheme allowlist to prevent SSRF via redirect to
	 * file:// or other schemes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url                URL to zip file.
	 * @param int    $max_download_size  Max download size in bytes.
	 * @param int    $max_extracted_size Max uncompressed extracted size in bytes.
	 * @param int    $max_file_count     Max number of entries in the archive.
	 * @return string Temporary directory path.
	 * @throws DownloadFailedException When the HTTP request fails or exceeds size cap.
	 * @throws InvalidZipException When the downloaded file is empty, corrupt, or contains unsafe entries.
	 * @throws ExtractionLimitException When the zip exceeds size or count limits.
	 * @throws DifftorException When a temp file or directory cannot be created.
	 */
	public static function downloadAndExtractZip(
		$url,
		$max_download_size = self::DEFAULT_MAX_DOWNLOAD_SIZE,
		$max_extracted_size = self::DEFAULT_MAX_EXTRACTED_SIZE,
		$max_file_count = self::DEFAULT_MAX_FILE_COUNT
	) {
		$temp_zip = tempnam(sys_get_temp_dir(), 'difftor_zip_');
		if (false === $temp_zip) {
			throw new DifftorException('Failed to create temporary file for download');
		}

		try {
			self::downloadToFile($url, $temp_zip, $max_download_size);
		} catch (Throwable $e) {
			@unlink($temp_zip);
			throw $e;
		}

		$temp_dir = self::createTempDir();

		try {
			self::extractZipTo($temp_zip, $temp_dir, $max_extracted_size, $max_file_count);
		} catch (Throwable $e) {
			@unlink($temp_zip);
			FileUtils::cleanupTempDirectory($temp_dir);
			throw $e;
		}

		@unlink($temp_zip);

		return $temp_dir;
	}

	/**
	 * Create a fresh temporary directory for extraction.
	 *
	 * @since 2.0.0
	 *
	 * @return string Temporary directory path.
	 * @throws DifftorException When the directory cannot be created.
	 */
	private static function createTempDir()
	{
		$temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'difftor_' . uniqid('', true);
		if (! mkdir($temp_dir, 0755, true)) {
			throw new DifftorException(sprintf('Failed to create temporary directory: %s', $temp_dir));
		}
		return $temp_dir;
	}

	/**
	 * Download a URL to a file with protocol and size restrictions.
	 *
	 * @since 2.0.0
	 *
	 * @param string $url               URL to download.
	 * @param string $target_file       Path where the response body is written.
	 * @param int    $max_download_size Max download size in bytes.
	 * @throws DownloadFailedException When the request fails, returns non-200, or exceeds size cap.
	 */
	private static function downloadToFile($url, $target_file, $max_download_size)
	{
		$fp = fopen($target_file, 'wb');
		if (false === $fp) {
			throw new DownloadFailedException(sprintf('Failed to open temporary file for writing: %s', $target_file));
		}

		$ch = curl_init($url);
		if (false === $ch) {
			fclose($fp);
			throw new DownloadFailedException(sprintf('Failed to initialize download for URL: %s', $url));
		}

		$protocols = CURLPROTO_HTTP | CURLPROTO_HTTPS;

		curl_setopt_array(
			$ch,
			[
				CURLOPT_FILE            => $fp,
				CURLOPT_FOLLOWLOCATION  => true,
				CURLOPT_MAXREDIRS       => 5,
				CURLOPT_PROTOCOLS       => $protocols,
				CURLOPT_REDIR_PROTOCOLS => $protocols,
				CURLOPT_TIMEOUT         => 300,
				CURLOPT_CONNECTTIMEOUT  => 30,
				CURLOPT_MAXFILESIZE     => $max_download_size,
				CURLOPT_USERAGENT       => 'Difftor',
			]
		);

		$success    = curl_exec($ch);
		$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_errno = curl_errno($ch);
		$curl_error = curl_error($ch);
		fclose($fp);

		if (false === $success || 0 !== $curl_errno) {
			throw new DownloadFailedException(sprintf('Download failed for %s: %s', $url, '' !== $curl_error ? $curl_error : 'unknown error'));
		}

		if (200 !== $http_code) {
			throw new DownloadFailedException(sprintf('Download failed for %s: HTTP %d', $url, $http_code));
		}

		$size = filesize($target_file);

		if (false === $size || 0 === $size) {
			throw new InvalidZipException(sprintf('Downloaded file is empty: %s', $url));
		}

		if ($size > $max_download_size) {
			throw new DownloadFailedException(sprintf('Downloaded file exceeds size cap of %d bytes: %s', $max_download_size, $url));
		}
	}

	/**
	 * Extract a zip file to a target directory with safety guards.
	 *
	 * Guards enforced:
	 *  - Zip slip: entry names containing "..", null bytes, or absolute paths are rejected.
	 *  - Zip bomb (count): archives with more than $max_file_count entries are rejected.
	 *  - Zip bomb (size): running uncompressed size total is checked against $max_extracted_size.
	 *  - macOS metadata (__MACOSX, ._*) is silently skipped.
	 *
	 * @since 2.0.0
	 *
	 * @param string $zip_path           Zip file to extract.
	 * @param string $target_dir         Directory to extract into.
	 * @param int    $max_extracted_size Max uncompressed extracted size in bytes.
	 * @param int    $max_file_count     Max number of entries in the archive.
	 * @throws InvalidZipException When the zip cannot be opened or contains unsafe entries.
	 * @throws ExtractionLimitException When the zip exceeds size or count limits.
	 */
	private static function extractZipTo($zip_path, $target_dir, $max_extracted_size, $max_file_count)
	{
		$zip    = new ZipArchive();
		$result = $zip->open($zip_path);
		if (true !== $result) {
			throw new InvalidZipException(sprintf('Failed to open zip (error code %d): %s', $result, $zip_path));
		}

		if ($zip->numFiles > $max_file_count) {
			$num = $zip->numFiles;
			$zip->close();
			throw new ExtractionLimitException(sprintf('Zip contains %d entries, exceeds limit of %d', $num, $max_file_count));
		}

		$total_uncompressed = 0;

		for ($i = 0; $i < $zip->numFiles; $i++) {
			$entry_name = $zip->getNameIndex($i);
			if (false === $entry_name) {
				continue;
			}

			if (self::isMacOsMetadata($entry_name)) {
				continue;
			}

			if (! self::isSafeZipEntry($entry_name)) {
				$zip->close();
				throw new InvalidZipException(sprintf('Unsafe zip entry rejected: %s', $entry_name));
			}

			$stat = $zip->statIndex($i);
			if (is_array($stat) && isset($stat['size'])) {
				$total_uncompressed += (int) $stat['size'];
				if ($total_uncompressed > $max_extracted_size) {
					$zip->close();
					throw new ExtractionLimitException(sprintf('Zip uncompressed size exceeds limit of %d bytes', $max_extracted_size));
				}
			}

			$zip->extractTo($target_dir, [ $entry_name ]);
		}

		$zip->close();
	}

	/**
	 * Check whether a zip entry name refers to macOS metadata that should be skipped.
	 *
	 * @since 2.0.0
	 *
	 * @param string $entry_name Zip entry name.
	 * @return bool True if the entry is macOS metadata.
	 */
	private static function isMacOsMetadata($entry_name)
	{
		if ('__MACOSX/' === $entry_name || 0 === strpos($entry_name, '__MACOSX/')) {
			return true;
		}

		$basename = basename($entry_name);
		return '._' === substr($basename, 0, 2);
	}

	/**
	 * Validate that a zip entry name cannot escape the extraction directory.
	 *
	 * Rejects entries that:
	 *  - contain null bytes
	 *  - are absolute (Unix or Windows)
	 *  - contain a ".." path component
	 *
	 * @since 2.0.0
	 *
	 * @param string $entry_name Zip entry name.
	 * @return bool True if the entry is safe to extract.
	 */
	private static function isSafeZipEntry($entry_name)
	{
		if ('' === $entry_name) {
			return false;
		}

		if (false !== strpos($entry_name, "\0")) {
			return false;
		}

		$normalized = str_replace('\\', '/', $entry_name);

		// Absolute path (Unix).
		if (0 === strpos($normalized, '/')) {
			return false;
		}

		// Absolute path (Windows drive letter).
		if (1 === preg_match('/^[A-Za-z]:/', $normalized)) {
			return false;
		}

		foreach (explode('/', $normalized) as $part) {
			if ('..' === $part) {
				return false;
			}
		}

		return true;
	}
}
