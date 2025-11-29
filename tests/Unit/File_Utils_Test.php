<?php
/**
 * File_Utils_Test
 *
 * @package Difftor_Command
 */

namespace Nilambar\Difftor_Command\Tests\Unit;

use Nilambar\Difftor_Command\Utils\File_Utils;
use PHPUnit\Framework\TestCase;

/**
 * File_Utils Test Class.
 *
 * @since 1.0.0
 */
class File_Utils_Test extends TestCase {

	/**
	 * Test get_directory_files method.
	 *
	 * @since 1.0.0
	 */
	public function test_get_directory_files() {
		// Create a temporary directory structure.
		$temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'difftor_test_' . uniqid();
		mkdir( $temp_dir, 0755, true );
		mkdir( $temp_dir . DIRECTORY_SEPARATOR . 'subdir', 0755, true );

		// Create test files.
		file_put_contents( $temp_dir . DIRECTORY_SEPARATOR . 'file1.txt', 'content1' );
		file_put_contents( $temp_dir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'file2.txt', 'content2' );

		$files = File_Utils::get_directory_files( $temp_dir );

		$this->assertIsArray( $files );
		$this->assertCount( 2, $files );
		$this->assertArrayHasKey( 'file1.txt', $files );
		$this->assertArrayHasKey( 'subdir' . DIRECTORY_SEPARATOR . 'file2.txt', $files );

		// Cleanup.
		File_Utils::cleanup_temp_directory( $temp_dir );
	}

	/**
	 * Test should_ignore_file method.
	 *
	 * @since 1.0.0
	 */
	public function test_should_ignore_file() {
		$ignored_extensions = [ 'jpg', 'png', 'zip' ];

		$this->assertTrue( File_Utils::should_ignore_file( '/path/to/image.jpg', $ignored_extensions ) );
		$this->assertTrue( File_Utils::should_ignore_file( '/path/to/image.PNG', $ignored_extensions ) );
		$this->assertTrue( File_Utils::should_ignore_file( '/path/to/archive.zip', $ignored_extensions ) );
		$this->assertFalse( File_Utils::should_ignore_file( '/path/to/file.txt', $ignored_extensions ) );
		$this->assertFalse( File_Utils::should_ignore_file( '/path/to/file.php', $ignored_extensions ) );
		$this->assertFalse( File_Utils::should_ignore_file( '/path/to/file', $ignored_extensions ) );
	}

	/**
	 * Test cleanup_temp_directory method.
	 *
	 * @since 1.0.0
	 */
	public function test_cleanup_temp_directory() {
		// Create a temporary directory structure.
		$temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'difftor_test_' . uniqid();
		mkdir( $temp_dir, 0755, true );
		mkdir( $temp_dir . DIRECTORY_SEPARATOR . 'subdir', 0755, true );

		// Create test files.
		file_put_contents( $temp_dir . DIRECTORY_SEPARATOR . 'file1.txt', 'content1' );
		file_put_contents( $temp_dir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'file2.txt', 'content2' );

		// Verify directory exists.
		$this->assertTrue( is_dir( $temp_dir ) );

		// Cleanup.
		File_Utils::cleanup_temp_directory( $temp_dir );

		// Verify directory is removed.
		$this->assertFalse( is_dir( $temp_dir ) );
	}
}

