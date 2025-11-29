<?php
/**
 * Zip_Utils_Test
 *
 * @package Difftor_Command
 */

namespace Nilambar\Difftor_Command\Tests\Unit;

use Nilambar\Difftor_Command\Utils\Zip_Utils;
use PHPUnit\Framework\TestCase;

/**
 * Zip_Utils Test Class.
 *
 * @since 1.0.0
 */
class Zip_Utils_Test extends TestCase {

	/**
	 * Test extract_local_zip method.
	 *
	 * @since 1.0.0
	 */
	public function test_extract_local_zip() {
		// Create a temporary zip file.
		$temp_zip = tempnam( sys_get_temp_dir(), 'test_' ) . '.zip';
		$zip      = new \ZipArchive();
		$zip->open( $temp_zip, \ZipArchive::CREATE );
		$zip->addFromString( 'test.txt', 'test content' );
		$zip->addFromString( 'subdir/nested.txt', 'nested content' );
		$zip->close();

		// Extract zip.
		$extracted_dir = Zip_Utils::extract_local_zip( $temp_zip );

		$this->assertNotFalse( $extracted_dir );
		$this->assertTrue( is_dir( $extracted_dir ) );
		$this->assertTrue( is_file( $extracted_dir . DIRECTORY_SEPARATOR . 'test.txt' ) );
		$this->assertTrue( is_file( $extracted_dir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'nested.txt' ) );
		$this->assertEquals( 'test content', file_get_contents( $extracted_dir . DIRECTORY_SEPARATOR . 'test.txt' ) );

		// Cleanup.
		\Nilambar\Difftor_Command\Utils\File_Utils::cleanup_temp_directory( $extracted_dir );
		unlink( $temp_zip );

		// Test with nonexistent file.
		$this->assertFalse( Zip_Utils::extract_local_zip( '/nonexistent/file.zip' ) );

		// Test with empty file.
		$empty_zip = tempnam( sys_get_temp_dir(), 'empty_' ) . '.zip';
		touch( $empty_zip );
		$this->assertFalse( Zip_Utils::extract_local_zip( $empty_zip ) );
		unlink( $empty_zip );
	}
}

