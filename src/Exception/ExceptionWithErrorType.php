<?php

/**
 * @package     AesirX_Analytics_Library
 *
 * @copyright   Copyright (C) 2016 - 2023 Aesir. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace AesirxAnalyticsLib\Exception;

use Exception;
use Throwable;

/**
 * @since 1.0.0
 */
class ExceptionWithErrorType extends Exception
{
    private $errorType;

    public function __construct(
        string $message = "",
        ?string $errorType = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorType = $errorType;
    }

    public function getErrorType(): ?string
    {
        return $this->errorType;
    }
}
