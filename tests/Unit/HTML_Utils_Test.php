<?php
/**
 * HTML_Utils_Test
 *
 * @package Difftor_Command
 */

namespace Nilambar\Difftor_Command\Tests\Unit;

use Nilambar\Difftor_Command\Utils\HTML_Utils;
use PHPUnit\Framework\TestCase;

/**
 * HTML_Utils Test Class.
 *
 * @since 1.0.0
 */
class HTML_Utils_Test extends TestCase {

	/**
	 * Test generate_file_id method.
	 *
	 * @since 1.0.0
	 */
	public function test_generate_file_id() {
		$file_path = 'path/to/file.txt';
		$file_id   = HTML_Utils::generate_file_id( $file_path );

		$this->assertIsString( $file_id );
		$this->assertStringStartsWith( 'file_', $file_id );
		$this->assertNotEmpty( $file_id );

		// Same path should generate same ID.
		$file_id2 = HTML_Utils::generate_file_id( $file_path );
		$this->assertEquals( $file_id, $file_id2 );

		// Different path should generate different ID.
		$file_id3 = HTML_Utils::generate_file_id( 'different/path.txt' );
		$this->assertNotEquals( $file_id, $file_id3 );
	}

	/**
	 * Test find_diff_id_for_file method.
	 *
	 * @since 1.0.0
	 */
	public function test_find_diff_id_for_file() {
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

		$this->assertEquals( 'file_id_1', HTML_Utils::find_diff_id_for_file( 'file1.txt', $diff_files ) );
		$this->assertEquals( 'file_id_2', HTML_Utils::find_diff_id_for_file( 'file2.txt', $diff_files ) );
		$this->assertEquals( 'file_id_3', HTML_Utils::find_diff_id_for_file( 'old.txt', $diff_files ) );
		$this->assertFalse( HTML_Utils::find_diff_id_for_file( 'nonexistent.txt', $diff_files ) );
	}

	/**
	 * Test generate_table_of_contents method.
	 *
	 * @since 1.0.0
	 */
	public function test_generate_table_of_contents() {
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

		$toc = HTML_Utils::generate_table_of_contents( $diff_files );

		$this->assertIsString( $toc );
		$this->assertStringContainsString( 'Table of Contents', $toc );
		$this->assertStringContainsString( 'file1.txt', $toc );
		$this->assertStringContainsString( 'file2.txt', $toc );
		$this->assertStringContainsString( 'file_id_1', $toc );
		$this->assertStringContainsString( 'file_id_2', $toc );

		// Empty array should return empty string.
		$this->assertEquals( '', HTML_Utils::generate_table_of_contents( [] ) );
	}

	/**
	 * Test build_html_document method.
	 *
	 * @since 1.0.0
	 */
	public function test_build_html_document() {
		$summary_parts = [ '<div>Summary</div>' ];
		$html_parts    = [ '<div>Content</div>' ];
		$diff_files    = [];

		$html = HTML_Utils::build_html_document( $summary_parts, $html_parts, $diff_files );

		$this->assertIsString( $html );
		$this->assertStringContainsString( '<!DOCTYPE html>', $html );
		$this->assertStringContainsString( '<html', $html );
		$this->assertStringContainsString( 'Summary', $html );
		$this->assertStringContainsString( 'Content', $html );
	}
}

