<?php
/**
 * Path_Utils_Test
 *
 * @package Difftor_Command
 */

namespace Nilambar\Difftor_Command\Tests\Unit;

use Nilambar\Difftor_Command\Utils\Path_Utils;
use PHPUnit\Framework\TestCase;

/**
 * Path_Utils Test Class.
 *
 * @since 1.0.0
 */
class Path_Utils_Test extends TestCase {

	/**
	 * Test is_url method.
	 *
	 * @since 1.0.0
	 */
	public function test_is_url() {
		$this->assertTrue( Path_Utils::is_url( 'https://example.com/file.zip' ) );
		$this->assertTrue( Path_Utils::is_url( 'http://example.com/file.zip' ) );
		$this->assertTrue( Path_Utils::is_url( 'https://example.com/path/to/file.zip' ) );
		$this->assertFalse( Path_Utils::is_url( '/path/to/file.zip' ) );
		$this->assertFalse( Path_Utils::is_url( 'file.zip' ) );
		$this->assertFalse( Path_Utils::is_url( './file.zip' ) );
	}

	/**
	 * Test is_absolute_path method.
	 *
	 * @since 1.0.0
	 */
	public function test_is_absolute_path() {
		// Unix absolute paths.
		$this->assertTrue( Path_Utils::is_absolute_path( '/path/to/file' ) );
		$this->assertTrue( Path_Utils::is_absolute_path( '/usr/local/bin' ) );

		// Windows absolute paths.
		$this->assertTrue( Path_Utils::is_absolute_path( 'C:\\path\\to\\file' ) );
		$this->assertTrue( Path_Utils::is_absolute_path( '\\\\server\\share' ) );

		// Relative paths.
		$this->assertFalse( Path_Utils::is_absolute_path( 'path/to/file' ) );
		$this->assertFalse( Path_Utils::is_absolute_path( './file' ) );
		$this->assertFalse( Path_Utils::is_absolute_path( '../file' ) );
	}

	/**
	 * Test is_local_directory method.
	 *
	 * @since 1.0.0
	 */
	public function test_is_local_directory() {
		$temp_dir = sys_get_temp_dir();
		$this->assertTrue( Path_Utils::is_local_directory( $temp_dir ) );
		$this->assertFalse( Path_Utils::is_local_directory( '/nonexistent/directory' ) );
		$this->assertFalse( Path_Utils::is_local_directory( 'https://example.com' ) );
	}

	/**
	 * Test is_local_zip method.
	 *
	 * @since 1.0.0
	 */
	public function test_is_local_zip() {
		// Create a temporary zip file for testing.
		$temp_zip = tempnam( sys_get_temp_dir(), 'test_' ) . '.zip';
		$zip      = new \ZipArchive();
		$zip->open( $temp_zip, \ZipArchive::CREATE );
		$zip->addFromString( 'test.txt', 'test content' );
		$zip->close();

		$this->assertTrue( Path_Utils::is_local_zip( $temp_zip ) );
		$this->assertFalse( Path_Utils::is_local_zip( '/nonexistent/file.zip' ) );
		$this->assertFalse( Path_Utils::is_local_zip( 'https://example.com/file.zip' ) );

		// Cleanup.
		unlink( $temp_zip );
	}

	/**
	 * Test normalize_path method.
	 *
	 * @since 1.0.0
	 */
	public function test_normalize_path() {
		// Test URL (should remain unchanged).
		$url = 'https://example.com/file.zip';
		$this->assertEquals( $url, Path_Utils::normalize_path( $url ) );

		// Test absolute path (should remain unchanged if it exists).
		$temp_dir = sys_get_temp_dir();
		$this->assertEquals( realpath( $temp_dir ), Path_Utils::normalize_path( $temp_dir ) );

		// Test home directory expansion.
		$home = getenv( 'HOME' );
		if ( false !== $home ) {
			$this->assertEquals( $home, Path_Utils::normalize_path( '~' ) );
			$this->assertEquals( $home . '/test', Path_Utils::normalize_path( '~/test' ) );
		}
	}
}

