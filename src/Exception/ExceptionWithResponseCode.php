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
class ExceptionWithResponseCode extends Exception
{
    private $responseCode;

    public function __construct(string $message, int $responseCode, int $code = 0, ?Throwable $previous = null)
    {
        $this->responseCode = $responseCode;
        parent::__construct($message, $code, $previous);
    }

    public function getResponseCode(): int
    {
        return $this->responseCode;
    }
}
