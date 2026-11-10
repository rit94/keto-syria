<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

/**
 * Base class of everything the AI layer throws on purpose.
 * getMessage() is always translated and safe to show to the user; it never contains
 * the API key, request headers or raw upstream payloads.
 */
abstract class AiException extends RuntimeException {}
