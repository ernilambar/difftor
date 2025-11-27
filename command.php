<?php
/**
 * Command
 *
 * @package Foldiff_Command
 */

use Nilambar\Foldiff_Command\Foldiff_Command;

if ( ! class_exists( 'WP_CLI', false ) ) {
	return;
}

$wpcli_foldiff_autoload = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $wpcli_foldiff_autoload ) ) {
	require_once $wpcli_foldiff_autoload;
}

WP_CLI::add_command( 'difftor', Foldiff_Command::class );
