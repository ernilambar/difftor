<?php

/**
 * DifftorService
 *
 * @package Difftor
 */

namespace Nilambar\Difftor;

use Jfcherng\Diff\DiffHelper;
use Nilambar\Difftor\Utils\FileUtils;
use Nilambar\Difftor\Utils\HtmlUtils;
use Nilambar\Difftor\Utils\PathUtils;
use Nilambar\Difftor\Utils\ZipUtils;

/**
 * DifftorService Class.
 *
 * Core service class that handles diff generation without CLI dependencies.
 *
 * @since 1.0.0
 */
class DifftorService
{
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
	 * Prepare source (URL, directory, or zip file) for diff comparison.
	 *
	 * Handles three types of sources:
	 * - URLs: Downloads and extracts zip file to temp directory
	 * - Local directories: Returns directory path directly
	 * - Local zip files: Extracts to temp directory
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Path to source (URL, directory, or zip file).
	 * @return array|false Array with 'directory' and 'is_temp' keys, or false on error.
	 */
	public function prepareSource($path)
	{
		if (PathUtils::isUrl($path)) {
			// Handle URL - download and extract zip.
			$dir = ZipUtils::downloadAndExtractZip($path);
			if (false === $dir) {
				return false;
			}
			return [
				'directory' => $dir,
				'is_temp'   => true,
			];
		} elseif (PathUtils::isLocalDirectory($path)) {
			// Handle local directory - use directly.
			return [
				'directory' => $path,
				'is_temp'   => false,
			];
		} elseif (PathUtils::isLocalZip($path)) {
			// Handle local zip file - extract to temp directory.
			$dir = ZipUtils::extractLocalZip($path);
			if (false === $dir) {
				return false;
			}
			return [
				'directory' => $dir,
				'is_temp'   => true,
			];
		}

		return false;
	}

	/**
	 * Generate diff HTML file between two sources.
	 *
	 * @since 1.0.0
	 *
	 * @param string $old_source Old source path (URL, directory, or zip).
	 * @param string $new_source New source path (URL, directory, or zip).
	 * @param string $output_dir Output directory for HTML file. Defaults to system temp.
	 * @return string|false HTML file path on success, false on error.
	 */
	public function generateDiff($old_source, $new_source, $output_dir = null)
	{
		// Normalize paths.
		$path1 = PathUtils::normalizePath(trim($old_source));
		$path2 = PathUtils::normalizePath(trim($new_source));

		// Prepare first source (download/extract if needed).
		$result1 = $this->prepareSource($path1);
		if (false === $result1) {
			return false;
		}

		$dir1        = $result1['directory'];
		$is_temp_dir = $result1['is_temp'];

		// Prepare second source (download/extract if needed).
		$result2 = $this->prepareSource($path2);
		if (false === $result2) {
			if ($is_temp_dir) {
				FileUtils::cleanupTempDirectory($dir1);
			}
			return false;
		}

		$dir2         = $result2['directory'];
		$is_temp_dir2 = $result2['is_temp'];

		// Get output directory.
		if (null === $output_dir) {
			$temp_base  = sys_get_temp_dir();
			$output_dir = $temp_base . DIRECTORY_SEPARATOR . 'difftor' . DIRECTORY_SEPARATOR;
		}

		if (! is_dir($output_dir)) {
			mkdir($output_dir, 0755, true);
		}

		// Generate HTML diff file.
		$html_file = $this->generateDiffHtml($dir1, $dir2, $output_dir);

		// Cleanup temporary directories (only if we created them).
		if ($is_temp_dir) {
			FileUtils::cleanupTempDirectory($dir1);
		}
		if ($is_temp_dir2) {
			FileUtils::cleanupTempDirectory($dir2);
		}

		return $html_file;
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
	private function generateDiffHtml($dir1, $dir2, $cache_dir)
	{
		$files1 = FileUtils::getDirectoryFiles($dir1);
		$files2 = FileUtils::getDirectoryFiles($dir2);

		$all_files = array_unique(array_merge(array_keys($files1), array_keys($files2)));
		sort($all_files);

		$added_files   = [];
		$removed_files = [];
		$html_parts    = [];
		$diff_files    = []; // Track files with diffs for TOC.

		foreach ($all_files as $relative_path) {
			$file_path1 = $files1[ $relative_path ] ?? null;
			$file_path2 = $files2[ $relative_path ] ?? null;

			// Check if file should be ignored from diff.
			$should_ignore = false;
			if ($file_path1 && FileUtils::shouldIgnoreFile($file_path1, $this->ignored_extensions)) {
				$should_ignore = true;
			} elseif ($file_path2 && FileUtils::shouldIgnoreFile($file_path2, $this->ignored_extensions)) {
				$should_ignore = true;
			}

			if ($file_path1 && $file_path2) {
				// Both files exist.
				if ($should_ignore) {
					// Skip binary files from diff - we can't meaningfully diff them.
					continue;
				}

				// Compare text files.
				$content1 = file_get_contents($file_path1);
				$content2 = file_get_contents($file_path2);

				if ($content1 === $content2) {
					continue; // Skip identical files.
				}

				$diff_html    = DiffHelper::calculate($content1, $content2, 'Inline');
				$file_id      = HtmlUtils::generateFileId($relative_path);
				$diff_files[] = [
					'path' => $relative_path,
					'id'   => $file_id,
				];
				$html_parts[] = '<div class="file-diff" id="' . htmlspecialchars($file_id, ENT_QUOTES, 'UTF-8') . '">';
				$html_parts[] = '<h2 class="file-name">' . htmlspecialchars($relative_path, ENT_QUOTES, 'UTF-8') . '</h2>';
				$html_parts[] = $diff_html;
				$html_parts[] = '</div>';
			} elseif ($file_path1) {
				// File only exists in first directory.
				$removed_files[] = $relative_path;
			} else {
				// File only exists in second directory.
				$added_files[] = $relative_path;
			}
		}

		// Detect folder renames by checking if only first-level folder name differs.
		$renamed_diffs   = [];
		$matched_removed = [];
		$matched_added   = [];

		foreach ($removed_files as $removed_path) {
			// Normalize path separators.
			$removed_path_normalized = str_replace('\\', '/', $removed_path);

			// Get path after first directory component.
			$path_parts = explode('/', $removed_path_normalized);
			if (count($path_parts) < 2) {
				continue; // File is in root, not in a folder.
			}

			// Remove first component (folder name).
			$path_after_first = implode('/', array_slice($path_parts, 1));

			// Check if any added file has the same path after first component.
			foreach ($added_files as $added_path) {
				if (in_array($added_path, $matched_added, true)) {
					continue; // Already matched.
				}

				// Normalize path separators.
				$added_path_normalized = str_replace('\\', '/', $added_path);

				$added_parts = explode('/', $added_path_normalized);
				if (count($added_parts) < 2) {
					continue; // File is in root, not in a folder.
				}

				$added_path_after_first = implode('/', array_slice($added_parts, 1));

				// If paths after first component match, it's likely a folder rename.
				if ($path_after_first === $added_path_after_first) {
					$file1 = $files1[ $removed_path ];
					$file2 = $files2[ $added_path ];

					// Check if file should be ignored from diff.
					$should_ignore = false;
					if (FileUtils::shouldIgnoreFile($file1, $this->ignored_extensions) || FileUtils::shouldIgnoreFile($file2, $this->ignored_extensions)) {
						$should_ignore = true;
					}

					if (! $should_ignore) {
						// Compare content.
						$content1 = file_get_contents($file1);
						$content2 = file_get_contents($file2);

						if ($content1 !== $content2) {
							// File was renamed and has content differences.
							$diff_html       = DiffHelper::calculate($content1, $content2, 'Inline');
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
		$removed_files = array_diff($removed_files, $matched_removed);
		$added_files   = array_diff($added_files, $matched_added);
		$removed_files = array_values($removed_files);
		$added_files   = array_values($added_files);

		// Add renamed file diffs to HTML parts.
		foreach ($renamed_diffs as $renamed_diff) {
			$file_id      = HtmlUtils::generateFileId($renamed_diff['new_path']);
			$diff_files[] = [
				'path' => $renamed_diff['old_path'] . ' → ' . $renamed_diff['new_path'],
				'id'   => $file_id,
			];
			$html_parts[] = '<div class="file-diff renamed-file" id="' . htmlspecialchars($file_id, ENT_QUOTES, 'UTF-8') . '">';
			$html_parts[] = '<h2 class="file-name">';
			$html_parts[] = '<span class="file-rename-info">';
			$html_parts[] = '<span class="rename-old">' . htmlspecialchars($renamed_diff['old_path'], ENT_QUOTES, 'UTF-8') . '</span>';
			$html_parts[] = ' → ';
			$html_parts[] = '<span class="rename-new">' . htmlspecialchars($renamed_diff['new_path'], ENT_QUOTES, 'UTF-8') . '</span>';
			$html_parts[] = '</span>';
			$html_parts[] = '</h2>';
			$html_parts[] = $renamed_diff['diff_html'];
			$html_parts[] = '</div>';
		}

		// Build summary section for added/removed files.
		$summary_parts = [];
		if (! empty($added_files) || ! empty($removed_files)) {
			$summary_parts[] = '<div class="file-summary">';
			if (! empty($removed_files)) {
				$summary_parts[] = '<div class="summary-section">';
				$summary_parts[] = '<h3 class="summary-title removed">Removed Files (' . count($removed_files) . ')</h3>';
				$summary_parts[] = '<ul class="file-list removed">';
				foreach ($removed_files as $file) {
					// Check if this file has a corresponding diff.
					$file_id = HtmlUtils::findDiffIdForFile($file, $diff_files);
					if ($file_id) {
						$summary_parts[] = '<li><a href="#' . htmlspecialchars($file_id, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '</a></li>';
					} else {
						$summary_parts[] = '<li>' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '</li>';
					}
				}
				$summary_parts[] = '</ul>';
				$summary_parts[] = '</div>';
			}
			if (! empty($added_files)) {
				$summary_parts[] = '<div class="summary-section">';
				$summary_parts[] = '<h3 class="summary-title added">Added Files (' . count($added_files) . ')</h3>';
				$summary_parts[] = '<ul class="file-list added">';
				foreach ($added_files as $file) {
					// Check if this file has a corresponding diff.
					$file_id = HtmlUtils::findDiffIdForFile($file, $diff_files);
					if ($file_id) {
						$summary_parts[] = '<li><a href="#' . htmlspecialchars($file_id, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '</a></li>';
					} else {
						$summary_parts[] = '<li>' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '</li>';
					}
				}
				$summary_parts[] = '</ul>';
				$summary_parts[] = '</div>';
			}
			$summary_parts[] = '</div>';
		}

		// Generate complete HTML document.
		$html_content = HtmlUtils::buildHtmlDocument($summary_parts, $html_parts, $diff_files);

		// Save HTML file.
		$html_filename = 'difftor-' . date('Y-m-d-His') . '-' . uniqid() . '.html';
		$html_file     = $cache_dir . $html_filename;
		file_put_contents($html_file, $html_content);

		return $html_file;
	}
}
