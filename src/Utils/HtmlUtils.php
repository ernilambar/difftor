<?php

/**
 * HtmlUtils
 *
 * @package Difftor
 */

namespace Nilambar\Difftor\Utils;

use Jfcherng\Diff\DiffHelper;
use ReflectionClass;

/**
 * HtmlUtils Class.
 *
 * @since 1.0.0
 */
class HtmlUtils
{
	/**
	 * Generate unique ID for file path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path File path.
	 * @return string Unique ID.
	 */
	public static function generateFileId($file_path)
	{
		return 'file_' . md5($file_path);
	}

	/**
	 * Find diff ID for a file path.
	 *
	 * Matches against explicit old_path / new_path fields so that renamed-file
	 * lookups never collide with regular-file paths that appear as substrings
	 * of a rename label.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path File path to search for.
	 * @param array  $diff_files Array of diff files with 'path', 'id', 'old_path', 'new_path' keys.
	 * @return string|false Diff ID if found, false otherwise.
	 */
	public static function findDiffIdForFile($file_path, $diff_files)
	{
		foreach ($diff_files as $diff_file) {
			$old_path = $diff_file['old_path'] ?? null;
			$new_path = $diff_file['new_path'] ?? null;
			if ($file_path === $old_path || $file_path === $new_path) {
				return $diff_file['id'];
			}
		}
		return false;
	}

	/**
	 * Generate table of contents HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param array $diff_files Array of files with diffs.
	 * @return string Table of contents HTML.
	 */
	public static function generateTableOfContents($diff_files)
	{
		if (empty($diff_files)) {
			return '';
		}

		$toc_parts   = [];
		$toc_parts[] = '<div class="table-of-contents">';
		$toc_parts[] = '<h2>Table of Contents</h2>';
		$toc_parts[] = '<ul>';
		foreach ($diff_files as $diff_file) {
			$toc_parts[] = '<li><a href="#' . htmlspecialchars($diff_file['id'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($diff_file['path'], ENT_QUOTES, 'UTF-8') . '</a></li>';
		}
		$toc_parts[] = '</ul>';
		$toc_parts[] = '</div>';

		return implode("\n", $toc_parts);
	}

	/**
	 * Build a human-readable label for a diff based on two source strings.
	 *
	 * Uses the basename of each source. For http(s) URLs, the last path segment
	 * is used. When neither basename can be extracted, falls back to a generic label.
	 *
	 * @since 2.0.0
	 *
	 * @param string $old_source Old source (URL, directory, or zip path).
	 * @param string $new_source New source (URL, directory, or zip path).
	 * @return string Diff label.
	 */
	public static function buildDiffLabel($old_source, $new_source)
	{
		$old_label = self::labelForSource($old_source);
		$new_label = self::labelForSource($new_source);

		if ('' === $old_label && '' === $new_label) {
			return 'Diff Comparison';
		}

		return $old_label . ' → ' . $new_label;
	}

	/**
	 * Reduce a single source string to a display label.
	 *
	 * @since 2.0.0
	 *
	 * @param string $source Source string.
	 * @return string Display label (may be empty).
	 */
	private static function labelForSource($source)
	{
		$source = trim($source);
		if ('' === $source) {
			return '';
		}

		// For URLs, strip query string and use the last path segment.
		if (1 === preg_match('#^https?://#i', $source)) {
			$path = (string) parse_url($source, PHP_URL_PATH);
			$base = basename($path);
			if ('' !== $base) {
				return $base;
			}
			$host = (string) parse_url($source, PHP_URL_HOST);
			return '' !== $host ? $host : $source;
		}

		$base = basename($source);
		return '' !== $base ? $base : $source;
	}

	/**
	 * Render the stats header block.
	 *
	 * @since 2.0.0
	 *
	 * @param array $stats Keys: files_modified, files_added, files_removed, lines_added, lines_removed.
	 * @return string Stats HTML, or empty string if $stats is empty.
	 */
	public static function renderStats($stats)
	{
		if (empty($stats)) {
			return '';
		}

		$files_modified = (int) ($stats['files_modified'] ?? 0);
		$files_added    = (int) ($stats['files_added'] ?? 0);
		$files_removed  = (int) ($stats['files_removed'] ?? 0);
		$lines_added    = (int) ($stats['lines_added'] ?? 0);
		$lines_removed  = (int) ($stats['lines_removed'] ?? 0);

		return '<div class="diff-stats">'
			. '<span class="stat stat-modified">' . $files_modified . ' modified</span>'
			. '<span class="stat stat-added">' . $files_added . ' added</span>'
			. '<span class="stat stat-removed">' . $files_removed . ' removed</span>'
			. '<span class="stat stat-lines-added">+' . $lines_added . '</span>'
			. '<span class="stat stat-lines-removed">−' . $lines_removed . '</span>'
			. '</div>';
	}

	/**
	 * Build complete HTML document with styles.
	 *
	 * @since 1.0.0
	 *
	 * @param array       $summary_parts Array of summary HTML content parts.
	 * @param array       $html_parts Array of HTML content parts.
	 * @param array       $diff_files Array of files with diffs for table of contents.
	 * @param array|null  $stats Diff statistics to render above the summary. Optional.
	 * @param string|null $title_label Human-readable label for the page title / heading. Optional.
	 * @return string Complete HTML document.
	 */
	public static function buildHtmlDocument($summary_parts, $html_parts, $diff_files = [], $stats = null, $title_label = null)
	{
		// Get default CSS from php-diff package.
		// Not using DiffHelper::getStyleSheet() as it relies on realpath(),
		// which fails to resolve phar:// stream paths when run from a phar build.
		$diff_css_path = dirname((new ReflectionClass(DiffHelper::class))->getFileName()) . '/../example/diff-table.css';
		$diff_css      = file_get_contents($diff_css_path);

		$label       = (null === $title_label || '' === trim((string) $title_label)) ? 'Diff Comparison' : (string) $title_label;
		$title_html  = htmlspecialchars('Difftor: ' . $label, ENT_QUOTES, 'UTF-8');
		$heading_html = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
		$stats_html   = self::renderStats($stats ?? []);

		$html = '<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>' . $title_html . '</title>
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
		.table-of-contents {
			margin-bottom: 40px;
			border: 1px solid #ddd;
			border-radius: 4px;
			padding: 15px;
			background-color: #f8f9fa;
		}
		.table-of-contents h2 {
			margin: 0 0 15px 0;
			font-size: 18px;
			color: #333;
		}
		.table-of-contents ul {
			margin: 0;
			padding-left: 20px;
			list-style-type: disc;
		}
		.table-of-contents li {
			margin: 5px 0;
			font-family: "Courier New", Courier, monospace;
			font-size: 13px;
		}
		.table-of-contents a {
			color: #0073aa;
			text-decoration: none;
		}
		.table-of-contents a:hover {
			text-decoration: underline;
		}
		.file-list a {
			color: inherit;
			text-decoration: none;
		}
		.file-list a:hover {
			text-decoration: underline;
		}
		.file-diff {
			scroll-margin-top: 20px;
		}
		.diff-stats {
			display: flex;
			flex-wrap: wrap;
			gap: 12px;
			margin-bottom: 20px;
			padding: 12px 15px;
			background-color: #f8f9fa;
			border: 1px solid #ddd;
			border-radius: 4px;
			font-family: "Courier New", Courier, monospace;
			font-size: 13px;
		}
		.diff-stats .stat {
			padding: 2px 8px;
			border-radius: 3px;
			background-color: #fff;
			border: 1px solid #e1e4e8;
		}
		.diff-stats .stat-added,
		.diff-stats .stat-lines-added {
			color: #155724;
		}
		.diff-stats .stat-removed,
		.diff-stats .stat-lines-removed {
			color: #721c24;
		}
		.diff-stats .stat-modified {
			color: #495057;
		}
		' . $diff_css . '
	</style>
</head>
<body>
	<div class="container">
		<h1>' . $heading_html . '</h1>
		' . $stats_html . '
		' . implode("\n", $summary_parts) . '
		' . self::generateTableOfContents($diff_files) . '
		' . implode("\n", $html_parts) . '
	</div>
</body>
</html>';

		return $html;
	}
}
