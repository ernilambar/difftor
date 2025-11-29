<?php

/**
 * FileUtilsTest
 *
 * @package Difftor
 */

namespace Nilambar\Difftor\Tests\Unit;

use Nilambar\Difftor\Utils\FileUtils;
use PHPUnit\Framework\TestCase;

/**
 * FileUtils Test Class.
 *
 * @since 1.0.0
 */
class FileUtilsTest extends TestCase
{
	/**
	 * Test getDirectoryFiles method.
	 *
	 * @since 1.0.0
	 */
	public function testGetDirectoryFiles()
	{
		// Create a temporary directory structure.
		$temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'difftor_test_' . uniqid();
		mkdir($temp_dir, 0755, true);
		mkdir($temp_dir . DIRECTORY_SEPARATOR . 'subdir', 0755, true);

		// Create test files.
		file_put_contents($temp_dir . DIRECTORY_SEPARATOR . 'file1.txt', 'content1');
		file_put_contents($temp_dir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'file2.txt', 'content2');

		$files = FileUtils::getDirectoryFiles($temp_dir);

		$this->assertIsArray($files);
		$this->assertCount(2, $files);
		$this->assertArrayHasKey('file1.txt', $files);
		$this->assertArrayHasKey('subdir' . DIRECTORY_SEPARATOR . 'file2.txt', $files);

		// Cleanup.
		FileUtils::cleanupTempDirectory($temp_dir);
	}

	/**
	 * Test shouldIgnoreFile method.
	 *
	 * @since 1.0.0
	 */
	public function testShouldIgnoreFile()
	{
		$ignored_extensions = [ 'jpg', 'png', 'zip' ];

		$this->assertTrue(FileUtils::shouldIgnoreFile('/path/to/image.jpg', $ignored_extensions));
		$this->assertTrue(FileUtils::shouldIgnoreFile('/path/to/image.PNG', $ignored_extensions));
		$this->assertTrue(FileUtils::shouldIgnoreFile('/path/to/archive.zip', $ignored_extensions));
		$this->assertFalse(FileUtils::shouldIgnoreFile('/path/to/file.txt', $ignored_extensions));
		$this->assertFalse(FileUtils::shouldIgnoreFile('/path/to/file.php', $ignored_extensions));
		$this->assertFalse(FileUtils::shouldIgnoreFile('/path/to/file', $ignored_extensions));
	}

	/**
	 * Test cleanupTempDirectory method.
	 *
	 * @since 1.0.0
	 */
	public function testCleanupTempDirectory()
	{
		// Create a temporary directory structure.
		$temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'difftor_test_' . uniqid();
		mkdir($temp_dir, 0755, true);
		mkdir($temp_dir . DIRECTORY_SEPARATOR . 'subdir', 0755, true);

		// Create test files.
		file_put_contents($temp_dir . DIRECTORY_SEPARATOR . 'file1.txt', 'content1');
		file_put_contents($temp_dir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'file2.txt', 'content2');

		// Verify directory exists.
		$this->assertTrue(is_dir($temp_dir));

		// Cleanup.
		FileUtils::cleanupTempDirectory($temp_dir);

		// Verify directory is removed.
		$this->assertFalse(is_dir($temp_dir));
	}
}
