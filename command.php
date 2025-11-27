<?php
/**
 * Command
 *
 * @package Difftor_Command
 */

use Nilambar\Difftor_Command\Difftor_Command;

if ( ! class_exists( 'WP_CLI', false ) ) {
	return;
}

$wpcli_difftor_autoload = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $wpcli_difftor_autoload ) ) {
	require_once $wpcli_difftor_autoload;
}

WP_CLI::add_command( 'difftor', Difftor_Command::class );
