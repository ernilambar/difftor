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
use ZipArchive;

/**
 * Foldiff_Command Class.
 *
 * @since 1.0.0
 */
class Foldiff_Command {

	/**
	 * List of file extensions to ignore from diff (binary files).
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $ignored_extensions = [
		'7z',
		'ai',
		'avi',
		'bmp',
		'doc',
		'docx',
		'eot',
		'flv',
		'gif',
		'gz',
		'ico',
		'jpeg',
		'jpg',
		'mov',
		'mp3',
		'mp4',
		'otf',
		'pdf',
		'png',
		'ppt',
		'pptx',
		'psd',
		'rar',
		'sketch',
		'svg',
		'tar',
		'ttf',
		'webp',
		'wmv',
		'woff',
		'woff2',
		'xls',
		'xlsx',
		'zip',
	];

	/**
	 * Compares two zip files from URLs and generates an HTML diff file.
	 *
	 * Downloads two zip files from the provided URLs, extracts them to temporary
	 * directories, and generates an HTML diff file showing the differences between
	 * the contents of the two zip files. The HTML file is saved in the WP-CLI cache
	 * directory and can be viewed in a browser.
	 *
	 * ## OPTIONS
	 *
	 * <paths>
	 * : Two URLs separated by pipe (|). First URL is the old/original zip file, second URL is the new/modified zip file. Each URL should point to a zip file.
	 *
	 * [--porcelain]
	 * : Output a single value.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp foldiff view "https://example.com/file1.zip|https://example.com/file2.zip"
	 *     Downloading and extracting zip files...
	 *     Generating diff...
	 *     Success: Diff HTML file generated: /path/to/cache/foldiff-2024-01-15-123456-abc123.html
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Arguments.
	 * @param array $assoc_args Associated arguments.
	 * @when before_wp_load
	 */
	public function view( $args, $assoc_args = [] ) {
		$paths = $args[0];

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

		$porcelain = isset( $assoc_args['porcelain'] );

		// Download and extract first zip.
		$dir1 = $this->download_and_extract_zip( $url1 );
		if ( false === $dir1 ) {
			WP_CLI::error( 'Failed to download and extract first zip file.' );
		}

		// Download and extract second zip.
		$dir2 = $this->download_and_extract_zip( $url2 );
		if ( false === $dir2 ) {
			$this->cleanup_temp_directory( $dir1 );
			WP_CLI::error( 'Failed to download and extract second zip file.' );
		}

		// Get temp directory for HTML file.
		$temp_base   = sys_get_temp_dir();
		$foldiff_dir = $temp_base . DIRECTORY_SEPARATOR . 'foldiff' . DIRECTORY_SEPARATOR;
		if ( ! is_dir( $foldiff_dir ) ) {
			mkdir( $foldiff_dir, 0755, true );
		}

		// Generate HTML diff file.
		$html_file = $this->generate_diff_html( $dir1, $dir2, $foldiff_dir );

		// Cleanup.
		$this->cleanup_temp_directory( $dir1 );
		$this->cleanup_temp_directory( $dir2 );

		if ( $porcelain ) {
			WP_CLI::line( $html_file );
		} else {
			WP_CLI::success( 'Diff HTML file generated: ' . $html_file );
		}
	}

	/**
	 * Download and extract zip file from URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url URL to zip file.
	 * @return string|false Temporary directory path or false on error.
	 */
	private function download_and_extract_zip( $url ) {
		// Create temporary file for zip.
		$temp_zip = tempnam( sys_get_temp_dir(), 'foldiff_zip_' );
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
				CURLOPT_USERAGENT      => 'WP-CLI Foldiff Command',
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
		$temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'foldiff_' . uniqid( '', true );
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

	/**
	 * Check if file should be ignored from diff.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path File path.
	 * @return bool True if file should be ignored, false otherwise.
	 */
	private function should_ignore_file( $file_path ) {
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		return in_array( $extension, $this->ignored_extensions, true );
	}

	/**
	 * Generate HTML diff file between two directories.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dir1 First directory path.
	 * @param string $dir2 Second directory path.
	 * @param string $cache_dir Cache directory path.
	 * @return string HTML file path.
	 */
	private function generate_diff_html( $dir1, $dir2, $cache_dir ) {
		$files1 = $this->get_directory_files( $dir1 );
		$files2 = $this->get_directory_files( $dir2 );

		$all_files = array_unique( array_merge( array_keys( $files1 ), array_keys( $files2 ) ) );
		sort( $all_files );

		$added_files   = [];
		$removed_files = [];
		$html_parts    = [];

		foreach ( $all_files as $relative_path ) {
			$file_path1 = $files1[ $relative_path ] ?? null;
			$file_path2 = $files2[ $relative_path ] ?? null;

			// Check if file should be ignored from diff.
			$should_ignore = false;
			if ( $file_path1 && $this->should_ignore_file( $file_path1 ) ) {
				$should_ignore = true;
			} elseif ( $file_path2 && $this->should_ignore_file( $file_path2 ) ) {
				$should_ignore = true;
			}

			if ( $file_path1 && $file_path2 ) {
				// Both files exist.
				if ( $should_ignore ) {
					// Skip binary files from diff - we can't meaningfully diff them.
					continue;
				}

				// Compare text files.
				$content1 = file_get_contents( $file_path1 );
				$content2 = file_get_contents( $file_path2 );

				if ( $content1 === $content2 ) {
					continue; // Skip identical files.
				}

				$diff_html    = DiffHelper::calculate( $content1, $content2, 'Inline' );
				$html_parts[] = '<div class="file-diff">';
				$html_parts[] = '<h2 class="file-name">' . htmlspecialchars( $relative_path, ENT_QUOTES, 'UTF-8' ) . '</h2>';
				$html_parts[] = $diff_html;
				$html_parts[] = '</div>';
			} elseif ( $file_path1 ) {
				// File only exists in first directory.
				$removed_files[] = $relative_path;
			} else {
				// File only exists in second directory.
				$added_files[] = $relative_path;
			}
		}

		// Detect folder renames by checking if only first-level folder name differs.
		$renamed_diffs = [];
		$matched_removed = [];
		$matched_added = [];

		foreach ( $removed_files as $removed_path ) {
			// Normalize path separators.
			$removed_path_normalized = str_replace( '\\', '/', $removed_path );

			// Get path after first directory component.
			$path_parts = explode( '/', $removed_path_normalized );
			if ( count( $path_parts ) < 2 ) {
				continue; // File is in root, not in a folder.
			}

			// Remove first component (folder name).
			$path_after_first = implode( '/', array_slice( $path_parts, 1 ) );

			// Check if any added file has the same path after first component.
			foreach ( $added_files as $added_path ) {
				if ( in_array( $added_path, $matched_added, true ) ) {
					continue; // Already matched.
				}

				// Normalize path separators.
				$added_path_normalized = str_replace( '\\', '/', $added_path );

				$added_parts = explode( '/', $added_path_normalized );
				if ( count( $added_parts ) < 2 ) {
					continue; // File is in root, not in a folder.
				}

				$added_path_after_first = implode( '/', array_slice( $added_parts, 1 ) );

				// If paths after first component match, it's likely a folder rename.
				if ( $path_after_first === $added_path_after_first ) {
					$file1 = $files1[ $removed_path ];
					$file2 = $files2[ $added_path ];

					// Check if file should be ignored from diff.
					$should_ignore = false;
					if ( $this->should_ignore_file( $file1 ) || $this->should_ignore_file( $file2 ) ) {
						$should_ignore = true;
					}

					if ( ! $should_ignore ) {
						// Compare content.
						$content1 = file_get_contents( $file1 );
						$content2 = file_get_contents( $file2 );

						if ( $content1 !== $content2 ) {
							// File was renamed and has content differences.
							$diff_html = DiffHelper::calculate( $content1, $content2, 'Inline' );
							$renamed_diffs[] = [
								'old_path'  => $removed_path,
								'new_path'  => $added_path,
								'diff_html' => $diff_html,
							];
						}
					}

					// Mark as matched.
					$matched_removed[] = $removed_path;
					$matched_added[]   = $added_path;
					break; // Found match, move to next removed file.
				}
			}
		}

		// Remove matched files from added/removed lists.
		$removed_files = array_diff( $removed_files, $matched_removed );
		$added_files   = array_diff( $added_files, $matched_added );
		$removed_files = array_values( $removed_files );
		$added_files   = array_values( $added_files );

		// Add renamed file diffs to HTML parts.
		foreach ( $renamed_diffs as $renamed_diff ) {
			$html_parts[] = '<div class="file-diff renamed-file">';
			$html_parts[] = '<h2 class="file-name">';
			$html_parts[] = '<span class="file-rename-info">';
			$html_parts[] = '<span class="rename-old">' . htmlspecialchars( $renamed_diff['old_path'], ENT_QUOTES, 'UTF-8' ) . '</span>';
			$html_parts[] = ' → ';
			$html_parts[] = '<span class="rename-new">' . htmlspecialchars( $renamed_diff['new_path'], ENT_QUOTES, 'UTF-8' ) . '</span>';
			$html_parts[] = '</span>';
			$html_parts[] = '</h2>';
			$html_parts[] = $renamed_diff['diff_html'];
			$html_parts[] = '</div>';
		}

		// Build summary section for added/removed files.
		$summary_parts = [];
		if ( ! empty( $added_files ) || ! empty( $removed_files ) ) {
			$summary_parts[] = '<div class="file-summary">';
			if ( ! empty( $removed_files ) ) {
				$summary_parts[] = '<div class="summary-section">';
				$summary_parts[] = '<h3 class="summary-title removed">Removed Files (' . count( $removed_files ) . ')</h3>';
				$summary_parts[] = '<ul class="file-list removed">';
				foreach ( $removed_files as $file ) {
					$summary_parts[] = '<li>' . htmlspecialchars( $file, ENT_QUOTES, 'UTF-8' ) . '</li>';
				}
				$summary_parts[] = '</ul>';
				$summary_parts[] = '</div>';
			}
			if ( ! empty( $added_files ) ) {
				$summary_parts[] = '<div class="summary-section">';
				$summary_parts[] = '<h3 class="summary-title added">Added Files (' . count( $added_files ) . ')</h3>';
				$summary_parts[] = '<ul class="file-list added">';
				foreach ( $added_files as $file ) {
					$summary_parts[] = '<li>' . htmlspecialchars( $file, ENT_QUOTES, 'UTF-8' ) . '</li>';
				}
				$summary_parts[] = '</ul>';
				$summary_parts[] = '</div>';
			}
			$summary_parts[] = '</div>';
		}

		// Generate complete HTML document.
		$html_content = $this->build_html_document( $summary_parts, $html_parts );

		// Save HTML file.
		$html_filename = 'foldiff-' . date( 'Y-m-d-His' ) . '-' . uniqid() . '.html';
		$html_file     = $cache_dir . $html_filename;
		file_put_contents( $html_file, $html_content );

		return $html_file;
	}

	/**
	 * Build complete HTML document with styles.
	 *
	 * @since 1.0.0
	 *
	 * @param array $summary_parts Array of summary HTML content parts.
	 * @param array $html_parts Array of HTML content parts.
	 * @return string Complete HTML document.
	 */
	private function build_html_document( $summary_parts, $html_parts ) {
		// Get default CSS from php-diff package.
		$diff_css = DiffHelper::getStyleSheet();

		$html = '<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Folder Diff</title>
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
			margin: 0;
			padding: 20px;
			background-color: #f5f5f5;
		}
		.container {
			max-width: 1400px;
			margin: 0 auto;
			background-color: #fff;
			padding: 20px;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
		}
		h1 {
			margin-top: 0;
			color: #333;
			border-bottom: 2px solid #ddd;
			padding-bottom: 10px;
		}
		.file-summary {
			margin-bottom: 40px;
			border: 1px solid #ddd;
			border-radius: 4px;
			overflow: hidden;
			background-color: #f8f9fa;
		}
		.summary-section {
			padding: 15px;
		}
		.summary-section:not(:last-child) {
			border-bottom: 1px solid #ddd;
		}
		.summary-title {
			margin: 0 0 10px 0;
			font-size: 16px;
			font-weight: 600;
		}
		.summary-title.added {
			color: #155724;
		}
		.summary-title.removed {
			color: #721c24;
		}
		.file-list {
			margin: 0;
			padding-left: 20px;
			list-style-type: disc;
		}
		.file-list li {
			margin: 5px 0;
			font-family: "Courier New", Courier, monospace;
			font-size: 13px;
		}
		.file-list.added li {
			color: #155724;
		}
		.file-list.removed li {
			color: #721c24;
		}
		.file-diff {
			margin-bottom: 40px;
			border: 1px solid #ddd;
			border-radius: 4px;
			overflow: hidden;
		}
		.file-name {
			background-color: #f8f9fa;
			padding: 10px 15px;
			margin: 0;
			font-size: 16px;
			border-bottom: 1px solid #ddd;
			color: #495057;
		}
		.file-status {
			padding: 15px;
			font-weight: bold;
		}
		.file-status.added {
			background-color: #d4edda;
			color: #155724;
		}
		.file-status.removed {
			background-color: #f8d7da;
			color: #721c24;
		}
		.file-diff.renamed-file {
			border-left: 4px solid #856404;
		}
		.file-rename-info {
			display: block;
			font-size: 14px;
			margin-top: 5px;
		}
		.rename-old {
			color: #721c24;
			text-decoration: line-through;
		}
		.rename-new {
			color: #155724;
			font-weight: 600;
		}
		' . $diff_css . '
	</style>
</head>
<body>
	<div class="container">
		<h1>Folder Diff Comparison</h1>
		' . implode( "\n", $summary_parts ) . '
		' . implode( "\n", $html_parts ) . '
	</div>
</body>
</html>';

		return $html;
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
