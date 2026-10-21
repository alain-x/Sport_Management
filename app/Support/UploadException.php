<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/** Thrown when an uploaded image fails validation or cannot be stored. */
class UploadException extends RuntimeException {}
