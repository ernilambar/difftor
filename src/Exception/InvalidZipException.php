<?php

/**
 * InvalidZipException
 *
 * @package Difftor
 */

namespace Nilambar\Difftor\Exception;

/**
 * Thrown when a zip file is corrupt, unreadable, or contains unsafe entries.
 *
 * @since 2.0.0
 */
class InvalidZipException extends DifftorException
{
}
