<?php

/**
 * ZipUtilsTest
 *
 * @package Difftor
 */

namespace Nilambar\Difftor\Tests\Unit;

use Nilambar\Difftor\Exception\ExtractionLimitException;
use Nilambar\Difftor\Exception\InvalidZipException;
use Nilambar\Difftor\Exception\SourceNotFoundException;
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

		$this->assertIsString($extracted_dir);
		$this->assertTrue(is_dir($extracted_dir));
		$this->assertTrue(is_file($extracted_dir . DIRECTORY_SEPARATOR . 'test.txt'));
		$this->assertTrue(is_file($extracted_dir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'nested.txt'));
		$this->assertEquals('test content', file_get_contents($extracted_dir . DIRECTORY_SEPARATOR . 'test.txt'));

		// Cleanup.
		FileUtils::cleanupTempDirectory($extracted_dir);
		unlink($temp_zip);
	}

	/**
	 * Test that a nonexistent zip file raises SourceNotFoundException.
	 *
	 * @since 2.0.0
	 */
	public function testExtractLocalZipThrowsForMissingFile()
	{
		$this->expectException(SourceNotFoundException::class);
		ZipUtils::extractLocalZip('/nonexistent/file.zip');
	}

	/**
	 * Test that an empty file raises InvalidZipException.
	 *
	 * @since 2.0.0
	 */
	public function testExtractLocalZipThrowsForEmptyFile()
	{
		$empty_zip = tempnam(sys_get_temp_dir(), 'empty_') . '.zip';
		touch($empty_zip);

		try {
			$this->expectException(InvalidZipException::class);
			ZipUtils::extractLocalZip($empty_zip);
		} finally {
			@unlink($empty_zip);
		}
	}

	/**
	 * Test that zip slip attempts are rejected.
	 *
	 * @since 2.0.0
	 */
	public function testExtractLocalZipRejectsZipSlip()
	{
		$temp_zip = tempnam(sys_get_temp_dir(), 'slip_') . '.zip';
		$zip      = new ZipArchive();
		$zip->open($temp_zip, ZipArchive::CREATE);
		$zip->addFromString('legit.txt', 'safe content');
		$zip->addFromString('../../etc/passwd', 'malicious');
		$zip->close();

		try {
			$this->expectException(InvalidZipException::class);
			ZipUtils::extractLocalZip($temp_zip);
		} finally {
			@unlink($temp_zip);
		}
	}

	/**
	 * Test that absolute-path zip entries are rejected.
	 *
	 * @since 2.0.0
	 */
	public function testExtractLocalZipRejectsAbsolutePathEntry()
	{
		$temp_zip = tempnam(sys_get_temp_dir(), 'abs_') . '.zip';
		$zip      = new ZipArchive();
		$zip->open($temp_zip, ZipArchive::CREATE);
		$zip->addFromString('/etc/hosts', 'malicious');
		$zip->close();

		try {
			$this->expectException(InvalidZipException::class);
			ZipUtils::extractLocalZip($temp_zip);
		} finally {
			@unlink($temp_zip);
		}
	}

	/**
	 * Test that the file-count guard trips when the archive exceeds the cap.
	 *
	 * @since 2.0.0
	 */
	public function testExtractLocalZipEnforcesFileCountLimit()
	{
		$temp_zip = tempnam(sys_get_temp_dir(), 'count_') . '.zip';
		$zip      = new ZipArchive();
		$zip->open($temp_zip, ZipArchive::CREATE);
		$zip->addFromString('a.txt', 'a');
		$zip->addFromString('b.txt', 'b');
		$zip->addFromString('c.txt', 'c');
		$zip->close();

		try {
			$this->expectException(ExtractionLimitException::class);
			ZipUtils::extractLocalZip($temp_zip, ZipUtils::DEFAULT_MAX_EXTRACTED_SIZE, 2);
		} finally {
			@unlink($temp_zip);
		}
	}

	/**
	 * Test that the uncompressed-size guard trips when the archive exceeds the cap.
	 *
	 * @since 2.0.0
	 */
	public function testExtractLocalZipEnforcesSizeLimit()
	{
		$temp_zip = tempnam(sys_get_temp_dir(), 'size_') . '.zip';
		$zip      = new ZipArchive();
		$zip->open($temp_zip, ZipArchive::CREATE);
		$zip->addFromString('a.txt', str_repeat('x', 1000));
		$zip->addFromString('b.txt', str_repeat('y', 1000));
		$zip->close();

		try {
			$this->expectException(ExtractionLimitException::class);
			ZipUtils::extractLocalZip($temp_zip, 512, ZipUtils::DEFAULT_MAX_FILE_COUNT);
		} finally {
			@unlink($temp_zip);
		}
	}
}
