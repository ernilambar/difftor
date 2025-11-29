<?php

/**
 * PathUtilsTest
 *
 * @package Difftor
 */

namespace Nilambar\Difftor\Tests\Unit;

use Nilambar\Difftor\Utils\PathUtils;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * PathUtils Test Class.
 *
 * @since 1.0.0
 */
class PathUtilsTest extends TestCase
{
	/**
	 * Test isUrl method.
	 *
	 * @since 1.0.0
	 */
	public function testIsUrl()
	{
		$this->assertTrue(PathUtils::isUrl('https://example.com/file.zip'));
		$this->assertTrue(PathUtils::isUrl('http://example.com/file.zip'));
		$this->assertTrue(PathUtils::isUrl('https://example.com/path/to/file.zip'));
		$this->assertFalse(PathUtils::isUrl('/path/to/file.zip'));
		$this->assertFalse(PathUtils::isUrl('file.zip'));
		$this->assertFalse(PathUtils::isUrl('./file.zip'));
	}

	/**
	 * Test isAbsolutePath method.
	 *
	 * @since 1.0.0
	 */
	public function testIsAbsolutePath()
	{
		// Unix absolute paths.
		$this->assertTrue(PathUtils::isAbsolutePath('/path/to/file'));
		$this->assertTrue(PathUtils::isAbsolutePath('/usr/local/bin'));

		// Windows absolute paths.
		$this->assertTrue(PathUtils::isAbsolutePath('C:\\path\\to\\file'));
		$this->assertTrue(PathUtils::isAbsolutePath('\\\\server\\share'));

		// Relative paths.
		$this->assertFalse(PathUtils::isAbsolutePath('path/to/file'));
		$this->assertFalse(PathUtils::isAbsolutePath('./file'));
		$this->assertFalse(PathUtils::isAbsolutePath('../file'));
	}

	/**
	 * Test isLocalDirectory method.
	 *
	 * @since 1.0.0
	 */
	public function testIsLocalDirectory()
	{
		$temp_dir = sys_get_temp_dir();
		$this->assertTrue(PathUtils::isLocalDirectory($temp_dir));
		$this->assertFalse(PathUtils::isLocalDirectory('/nonexistent/directory'));
		$this->assertFalse(PathUtils::isLocalDirectory('https://example.com'));
	}

	/**
	 * Test isLocalZip method.
	 *
	 * @since 1.0.0
	 */
	public function testIsLocalZip()
	{
		// Create a temporary zip file for testing.
		$temp_zip = tempnam(sys_get_temp_dir(), 'test_') . '.zip';
		$zip      = new ZipArchive();
		$zip->open($temp_zip, ZipArchive::CREATE);
		$zip->addFromString('test.txt', 'test content');
		$zip->close();

		$this->assertTrue(PathUtils::isLocalZip($temp_zip));
		$this->assertFalse(PathUtils::isLocalZip('/nonexistent/file.zip'));
		$this->assertFalse(PathUtils::isLocalZip('https://example.com/file.zip'));

		// Cleanup.
		unlink($temp_zip);
	}

	/**
	 * Test normalizePath method.
	 *
	 * @since 1.0.0
	 */
	public function testNormalizePath()
	{
		// Test URL (should remain unchanged).
		$url = 'https://example.com/file.zip';
		$this->assertEquals($url, PathUtils::normalizePath($url));

		// Test absolute path (should remain unchanged if it exists).
		$temp_dir = sys_get_temp_dir();
		$this->assertEquals(realpath($temp_dir), PathUtils::normalizePath($temp_dir));

		// Test home directory expansion.
		$home = getenv('HOME');
		if (false !== $home) {
			$this->assertEquals($home, PathUtils::normalizePath('~'));
			$this->assertEquals($home . '/test', PathUtils::normalizePath('~/test'));
		}
	}
}
