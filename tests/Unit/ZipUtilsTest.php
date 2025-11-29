<?php

/**
 * ZipUtilsTest
 *
 * @package Difftor
 */

namespace Nilambar\Difftor\Tests\Unit;

use Nilambar\Difftor\Utils\FileUtils;
use Nilambar\Difftor\Utils\ZipUtils;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * ZipUtils Test Class.
 *
 * @since 1.0.0
 */
class ZipUtilsTest extends TestCase
{
	/**
	 * Test extractLocalZip method.
	 *
	 * @since 1.0.0
	 */
	public function testExtractLocalZip()
	{
		// Create a temporary zip file.
		$temp_zip = tempnam(sys_get_temp_dir(), 'test_') . '.zip';
		$zip      = new ZipArchive();
		$zip->open($temp_zip, ZipArchive::CREATE);
		$zip->addFromString('test.txt', 'test content');
		$zip->addFromString('subdir/nested.txt', 'nested content');
		$zip->close();

		// Extract zip.
		$extracted_dir = ZipUtils::extractLocalZip($temp_zip);

		$this->assertNotFalse($extracted_dir);
		$this->assertTrue(is_dir($extracted_dir));
		$this->assertTrue(is_file($extracted_dir . DIRECTORY_SEPARATOR . 'test.txt'));
		$this->assertTrue(is_file($extracted_dir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'nested.txt'));
		$this->assertEquals('test content', file_get_contents($extracted_dir . DIRECTORY_SEPARATOR . 'test.txt'));

		// Cleanup.
		FileUtils::cleanupTempDirectory($extracted_dir);
		unlink($temp_zip);

		// Test with nonexistent file.
		$this->assertFalse(ZipUtils::extractLocalZip('/nonexistent/file.zip'));

		// Test with empty file.
		$empty_zip = tempnam(sys_get_temp_dir(), 'empty_') . '.zip';
		touch($empty_zip);
		$this->assertFalse(ZipUtils::extractLocalZip($empty_zip));
		unlink($empty_zip);
	}
}
