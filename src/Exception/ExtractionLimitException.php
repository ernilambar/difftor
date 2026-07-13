<?php

/**
 * ExtractionLimitException
 *
 * @package Difftor
 */

namespace Nilambar\Difftor\Exception;

/**
 * Thrown when a zip exceeds configured size or file-count limits (zip-bomb guard).
 *
 * @since 2.0.0
 */
class ExtractionLimitException extends DifftorException
{
}
