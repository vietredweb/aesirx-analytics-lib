<?php

/**
 * @package     AesirX_Analytics_Library
 *
 * @copyright   Copyright (C) 2016 - 2023 Aesir. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace AesirxAnalyticsLib\Cli;

/**
 * @since 1.0.0
 */
class Env
{
    protected $data = [];

    public function __construct(
        string $license,
        string $user,
        string $password,
        string $dbName,
        string $prefix,
        string $host,
        string $port = null
    ) {
        $this->data = [
            'DBUSER' => $user,
            'DBPASS' => $password,
            'DBNAME' => $dbName,
            'DBTYPE' => 'mysql',
            'LICENSE' => $license,
            'DBPREFIX' => $prefix,
            'DBHOST' => $host,
        ];

        if ($port) {
            $this->data['DBPORT'] = $port;
        }
    }

    public function getData(): array
    {
        return $this->data;
    }
}
