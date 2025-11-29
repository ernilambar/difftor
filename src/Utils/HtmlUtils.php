<?php

/**
 * HtmlUtils
 *
 * @package Difftor
 */

namespace Nilambar\Difftor\Utils;

use Jfcherng\Diff\DiffHelper;

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
		// Normalize path separators and create a safe ID.
		$normalized = str_replace([ '\\', '/', ' ', ':', '.', '-', '→' ], '_', $file_path);
		$normalized = preg_replace('/[^a-zA-Z0-9_]/', '', $normalized);
		return 'file_' . md5($file_path) . '_' . $normalized;
	}

	/**
	 * Find diff ID for a file path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path File path to search for.
	 * @param array  $diff_files Array of diff files with 'path' and 'id' keys.
	 * @return string|false Diff ID if found, false otherwise.
	 */
	public static function findDiffIdForFile($file_path, $diff_files)
	{
		foreach ($diff_files as $diff_file) {
			// Check if path matches exactly or is part of a rename path.
			if ($file_path === $diff_file['path'] || false !== strpos($diff_file['path'], $file_path)) {
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
	 * Build complete HTML document with styles.
	 *
	 * @since 1.0.0
	 *
	 * @param array $summary_parts Array of summary HTML content parts.
	 * @param array $html_parts Array of HTML content parts.
	 * @param array $diff_files Array of files with diffs for table of contents.
	 * @return string Complete HTML document.
	 */
	public static function buildHtmlDocument($summary_parts, $html_parts, $diff_files = [])
	{
		// Get default CSS from php-diff package.
		$diff_css = DiffHelper::getStyleSheet();

		$html = '<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Difftor</title>
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
		' . $diff_css . '
	</style>
</head>
<body>
	<div class="container">
		<h1>Folder Diff Comparison</h1>
		' . implode("\n", $summary_parts) . '
		' . self::generateTableOfContents($diff_files) . '
		' . implode("\n", $html_parts) . '
	</div>
</body>
</html>';

		return $html;
	}
}
