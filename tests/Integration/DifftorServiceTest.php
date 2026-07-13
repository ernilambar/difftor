<?php

/**
 * DifftorServiceTest
 *
 * @package Difftor
 */

namespace Nilambar\Difftor\Tests\Integration;

use Nilambar\Difftor\DifftorService;
use Nilambar\Difftor\Utils\FileUtils;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * End-to-end tests for DifftorService::generateDiff.
 *
 * @since 2.0.0
 */
class DifftorServiceTest extends TestCase
{
	/**
	 * Directories created during a test to clean up in tearDown.
	 *
	 * @since 2.0.0
	 *
	 * @var string[]
	 */
	private $temp_dirs = [];

	/**
	 * Files created during a test to clean up in tearDown.
	 *
	 * @since 2.0.0
	 *
	 * @var string[]
	 */
	private $temp_files = [];

	/**
	 * @inheritDoc
	 */
	protected function tearDown(): void
	{
		foreach ($this->temp_files as $file) {
			if (is_file($file)) {
				unlink($file);
			}
		}
		foreach ($this->temp_dirs as $dir) {
			if (is_dir($dir)) {
				FileUtils::cleanupTempDirectory($dir);
			}
		}
		$this->temp_dirs  = [];
		$this->temp_files = [];
	}

	/**
	 * Identical directories produce an HTML file with no diff blocks and zero stats.
	 *
	 * @since 2.0.0
	 */
	public function testIdenticalDirectoriesProduceNoDiff()
	{
		$dir1 = $this->makeDir([ 'a.txt' => 'same', 'nested/b.txt' => 'same' ]);
		$dir2 = $this->makeDir([ 'a.txt' => 'same', 'nested/b.txt' => 'same' ]);
		$out  = $this->makeOutputDir();

		$service   = new DifftorService();
		$html_file = $service->generateDiff($dir1, $dir2, $out);

		$this->assertFileExists($html_file);
		$html = file_get_contents($html_file);
		$this->assertStringNotContainsString('class="file-diff"', $html);
		$this->assertStringContainsString('0 modified', $html);
		$this->assertStringContainsString('0 added', $html);
		$this->assertStringContainsString('0 removed', $html);
	}

	/**
	 * Modified files show up as diffs with line-level stats.
	 *
	 * @since 2.0.0
	 */
	public function testModifiedFilesProduceDiffAndStats()
	{
		$dir1 = $this->makeDir([ 'a.txt' => "hello\nworld\n" ]);
		$dir2 = $this->makeDir([ 'a.txt' => "hello\nEARTH\n" ]);
		$out  = $this->makeOutputDir();

		$service   = new DifftorService();
		$html_file = $service->generateDiff($dir1, $dir2, $out);

		$html = file_get_contents($html_file);
		$this->assertStringContainsString('class="file-diff"', $html);
		$this->assertStringContainsString('a.txt', $html);
		$this->assertStringContainsString('1 modified', $html);
		$this->assertStringContainsString('+1', $html);
		$this->assertStringContainsString('−1', $html);
	}

	/**
	 * Files present only in the new source appear in the "Added" summary.
	 *
	 * @since 2.0.0
	 */
	public function testAddedFilesAppearInSummary()
	{
		$dir1 = $this->makeDir([ 'keep.txt' => 'x' ]);
		$dir2 = $this->makeDir([ 'keep.txt' => 'x', 'brand-new.txt' => 'y' ]);
		$out  = $this->makeOutputDir();

		$service   = new DifftorService();
		$html_file = $service->generateDiff($dir1, $dir2, $out);

		$html = file_get_contents($html_file);
		$this->assertStringContainsString('Added Files (1)', $html);
		$this->assertStringContainsString('brand-new.txt', $html);
		$this->assertStringContainsString('1 added', $html);
	}

	/**
	 * Files missing from the new source appear in the "Removed" summary.
	 *
	 * @since 2.0.0
	 */
	public function testRemovedFilesAppearInSummary()
	{
		$dir1 = $this->makeDir([ 'keep.txt' => 'x', 'gone.txt' => 'z' ]);
		$dir2 = $this->makeDir([ 'keep.txt' => 'x' ]);
		$out  = $this->makeOutputDir();

		$service   = new DifftorService();
		$html_file = $service->generateDiff($dir1, $dir2, $out);

		$html = file_get_contents($html_file);
		$this->assertStringContainsString('Removed Files (1)', $html);
		$this->assertStringContainsString('gone.txt', $html);
		$this->assertStringContainsString('1 removed', $html);
	}

	/**
	 * A first-level folder rename is detected and rendered as a rename block, not
	 * as separate added/removed entries.
	 *
	 * @since 2.0.0
	 */
	public function testRenamedFolderIsDetected()
	{
		$dir1 = $this->makeDir([ 'plugin-old/main.php' => "old\n" ]);
		$dir2 = $this->makeDir([ 'plugin-new/main.php' => "new\n" ]);
		$out  = $this->makeOutputDir();

		$service   = new DifftorService();
		$html_file = $service->generateDiff($dir1, $dir2, $out);

		$html = file_get_contents($html_file);
		$this->assertStringContainsString('renamed-file', $html);
		$this->assertStringContainsString('plugin-old/main.php', $html);
		$this->assertStringContainsString('plugin-new/main.php', $html);
		$this->assertStringNotContainsString('Added Files', $html);
		$this->assertStringNotContainsString('Removed Files', $html);
	}

	/**
	 * A directory source and a zip source can be diffed together.
	 *
	 * @since 2.0.0
	 */
	public function testMixedDirAndZipSources()
	{
		$dir1 = $this->makeDir([ 'a.txt' => "one\n" ]);
		$zip  = $this->makeZip([ 'a.txt' => "two\n" ]);
		$out  = $this->makeOutputDir();

		$service   = new DifftorService();
		$html_file = $service->generateDiff($dir1, $zip, $out);

		$html = file_get_contents($html_file);
		$this->assertStringContainsString('a.txt', $html);
		$this->assertStringContainsString('1 modified', $html);
	}

	/**
	 * Source basenames appear in the title and heading.
	 *
	 * @since 2.0.0
	 */
	public function testSourceNamesAppearInTitleAndHeading()
	{
		$zip1 = $this->makeZip([ 'a.txt' => 'x' ], 'plugin-1.0');
		$zip2 = $this->makeZip([ 'a.txt' => 'y' ], 'plugin-2.0');
		$out  = $this->makeOutputDir();

		$service   = new DifftorService();
		$html_file = $service->generateDiff($zip1, $zip2, $out);

		$html = file_get_contents($html_file);
		$this->assertStringContainsString(basename($zip1) . ' → ' . basename($zip2), $html);
	}

	/**
	 * Create a fixture directory with the given files. Keys are relative paths
	 * (forward-slash), values are file contents.
	 *
	 * @since 2.0.0
	 *
	 * @param array $files File map.
	 * @return string Absolute path to the created directory.
	 */
	private function makeDir(array $files)
	{
		$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'difftor_it_' . uniqid('', true);
		mkdir($dir, 0755, true);
		$this->temp_dirs[] = $dir;

		foreach ($files as $rel => $content) {
			$path = $dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
			$parent = dirname($path);
			if (! is_dir($parent)) {
				mkdir($parent, 0755, true);
			}
			file_put_contents($path, $content);
		}

		return $dir;
	}

	/**
	 * Create a fixture zip file with the given contents.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $files  File map (relative-path => content).
	 * @param string $prefix Optional filename prefix for the zip.
	 * @return string Absolute path to the created zip.
	 */
	private function makeZip(array $files, $prefix = 'difftor_it')
	{
		$zip_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '_' . uniqid('', true) . '.zip';
		$this->temp_files[] = $zip_path;

		$zip = new ZipArchive();
		$zip->open($zip_path, ZipArchive::CREATE);
		foreach ($files as $rel => $content) {
			$zip->addFromString($rel, $content);
		}
		$zip->close();

		return $zip_path;
	}

	/**
	 * Create an output directory for a generated HTML file.
	 *
	 * @since 2.0.0
	 *
	 * @return string Absolute path.
	 */
	private function makeOutputDir()
	{
		$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'difftor_it_out_' . uniqid('', true) . DIRECTORY_SEPARATOR;
		mkdir($dir, 0755, true);
		$this->temp_dirs[] = $dir;
		return $dir;
	}
}
