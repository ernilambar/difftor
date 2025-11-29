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
				'path' => 'file1.txt',
				'id'   => 'file_id_1',
			],
			[
				'path' => 'file2.txt',
				'id'   => 'file_id_2',
			],
			[
				'path' => 'old.txt → new.txt',
				'id'   => 'file_id_3',
			],
		];

		$this->assertEquals('file_id_1', HtmlUtils::findDiffIdForFile('file1.txt', $diff_files));
		$this->assertEquals('file_id_2', HtmlUtils::findDiffIdForFile('file2.txt', $diff_files));
		$this->assertEquals('file_id_3', HtmlUtils::findDiffIdForFile('old.txt', $diff_files));
		$this->assertFalse(HtmlUtils::findDiffIdForFile('nonexistent.txt', $diff_files));
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
}
