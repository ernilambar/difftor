<?php

/**
 * HtmlUtilsTest
 *
 * @package Difftor
 */

namespace Nilambar\Difftor\Tests\Unit;

use Nilambar\Difftor\Utils\HtmlUtils;
use PHPUnit\Framework\TestCase;

/**
 * HtmlUtils Test Class.
 *
 * @since 1.0.0
 */
class HtmlUtilsTest extends TestCase
{
	/**
	 * Test generateFileId method.
	 *
	 * @since 1.0.0
	 */
	public function testGenerateFileId()
	{
		$file_path = 'path/to/file.txt';
		$file_id   = HtmlUtils::generateFileId($file_path);

		$this->assertIsString($file_id);
		$this->assertStringStartsWith('file_', $file_id);
		$this->assertNotEmpty($file_id);

		// Same path should generate same ID.
		$file_id2 = HtmlUtils::generateFileId($file_path);
		$this->assertEquals($file_id, $file_id2);

		// Different path should generate different ID.
		$file_id3 = HtmlUtils::generateFileId('different/path.txt');
		$this->assertNotEquals($file_id, $file_id3);
	}

	/**
	 * Test findDiffIdForFile method.
	 *
	 * @since 1.0.0
	 */
	public function testFindDiffIdForFile()
	{
		$diff_files = [
			[
				'id'       => 'file_id_1',
				'path'     => 'file1.txt',
				'old_path' => 'file1.txt',
				'new_path' => 'file1.txt',
			],
			[
				'id'       => 'file_id_2',
				'path'     => 'file2.txt',
				'old_path' => 'file2.txt',
				'new_path' => 'file2.txt',
			],
			[
				'id'       => 'file_id_3',
				'path'     => 'old.txt → new.txt',
				'old_path' => 'old.txt',
				'new_path' => 'new.txt',
			],
		];

		$this->assertEquals('file_id_1', HtmlUtils::findDiffIdForFile('file1.txt', $diff_files));
		$this->assertEquals('file_id_2', HtmlUtils::findDiffIdForFile('file2.txt', $diff_files));
		$this->assertEquals('file_id_3', HtmlUtils::findDiffIdForFile('old.txt', $diff_files));
		$this->assertEquals('file_id_3', HtmlUtils::findDiffIdForFile('new.txt', $diff_files));
		$this->assertFalse(HtmlUtils::findDiffIdForFile('nonexistent.txt', $diff_files));
	}

	/**
	 * Regression: substring collisions between file names and rename labels must not match.
	 *
	 * @since 2.0.0
	 */
	public function testFindDiffIdForFileDoesNotMatchSubstring()
	{
		$diff_files = [
			[
				'id'       => 'renamed',
				'path'     => 'old_foo.txt → new_foo.txt',
				'old_path' => 'old_foo.txt',
				'new_path' => 'new_foo.txt',
			],
		];

		$this->assertFalse(HtmlUtils::findDiffIdForFile('foo.txt', $diff_files));
	}

	/**
	 * Test generateTableOfContents method.
	 *
	 * @since 1.0.0
	 */
	public function testGenerateTableOfContents()
	{
		$diff_files = [
			[
				'path' => 'file1.txt',
				'id'   => 'file_id_1',
			],
			[
				'path' => 'file2.txt',
				'id'   => 'file_id_2',
			],
		];

		$toc = HtmlUtils::generateTableOfContents($diff_files);

		$this->assertIsString($toc);
		$this->assertStringContainsString('Table of Contents', $toc);
		$this->assertStringContainsString('file1.txt', $toc);
		$this->assertStringContainsString('file2.txt', $toc);
		$this->assertStringContainsString('file_id_1', $toc);
		$this->assertStringContainsString('file_id_2', $toc);

		// Empty array should return empty string.
		$this->assertEquals('', HtmlUtils::generateTableOfContents([]));
	}

	/**
	 * Test buildHtmlDocument method.
	 *
	 * @since 1.0.0
	 */
	public function testBuildHtmlDocument()
	{
		$summary_parts = [ '<div>Summary</div>' ];
		$html_parts    = [ '<div>Content</div>' ];
		$diff_files    = [];

		$html = HtmlUtils::buildHtmlDocument($summary_parts, $html_parts, $diff_files);

		$this->assertIsString($html);
		$this->assertStringContainsString('<!DOCTYPE html>', $html);
		$this->assertStringContainsString('<html', $html);
		$this->assertStringContainsString('Summary', $html);
		$this->assertStringContainsString('Content', $html);
	}

	/**
	 * Test that buildHtmlDocument interpolates the title label.
	 *
	 * @since 2.0.0
	 */
	public function testBuildHtmlDocumentUsesTitleLabel()
	{
		$html = HtmlUtils::buildHtmlDocument([], [], [], null, 'old.zip → new.zip');

		$this->assertStringContainsString('<title>Difftor: old.zip → new.zip</title>', $html);
		$this->assertStringContainsString('<h1>old.zip → new.zip</h1>', $html);
	}

	/**
	 * Test that buildHtmlDocument renders the stats block when stats are provided.
	 *
	 * @since 2.0.0
	 */
	public function testBuildHtmlDocumentRendersStats()
	{
		$stats = [
			'files_modified' => 3,
			'files_added'    => 2,
			'files_removed'  => 1,
			'lines_added'    => 42,
			'lines_removed'  => 7,
		];

		$html = HtmlUtils::buildHtmlDocument([], [], [], $stats, 'label');

		$this->assertStringContainsString('3 modified', $html);
		$this->assertStringContainsString('2 added', $html);
		$this->assertStringContainsString('1 removed', $html);
		$this->assertStringContainsString('+42', $html);
		$this->assertStringContainsString('−7', $html);
	}

	/**
	 * Test buildDiffLabel with local paths, URLs, and empty inputs.
	 *
	 * @since 2.0.0
	 */
	public function testBuildDiffLabel()
	{
		$this->assertEquals(
			'old.zip → new.zip',
			HtmlUtils::buildDiffLabel('/tmp/old.zip', '/tmp/new.zip')
		);
		$this->assertEquals(
			'old-1.0.zip → new-2.0.zip',
			HtmlUtils::buildDiffLabel('https://example.com/downloads/old-1.0.zip', 'https://example.com/downloads/new-2.0.zip')
		);
		$this->assertEquals('Diff Comparison', HtmlUtils::buildDiffLabel('', ''));
	}
}
