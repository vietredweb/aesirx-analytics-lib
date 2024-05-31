<?php

/**
 * @package     AesirX_Analytics_Library
 *
 * @copyright   Copyright (C) 2016 - 2023 Aesir. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace AesirxAnalyticsLib\Cli;

use Aesirx\Component\AesirxAnalytics\Administrator\Cli\wpdb;
use AesirxAnalyticsLib\Exception\ExceptionWithErrorType;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Database;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * @since 1.0.0
 */
class AesirxAnalyticsCli
{
    private $cliPath;

    private $env;

    public function __construct(Env $env, string $cliPath)
    {
        $this->cliPath = $cliPath;
        $this->env = $env;
    }

    public function analyticsCliExists(): bool
    {
        return file_exists($this->cliPath);
    }

    public function getSupportedArch(): string
    {
        $arch = null;
        if (PHP_OS === 'Linux') {
            $uname = php_uname('m');
            if (strpos($uname, 'aarch64') !== false) {
                $arch = 'aarch64';
            } elseif (strpos($uname, 'x86_64') !== false) {
                $arch = 'x86_64';
            }
        }

        if (is_null($arch)) {
            throw new \DomainException("Unsupported architecture " . PHP_OS . " " . PHP_INT_SIZE);
        }

        return $arch;
    }

    /**
     * @throws ExceptionWithErrorType
     */
    public function downloadAnalyticsCli(): void
    {
        $arch = $this->getSupportedArch();
        file_put_contents(
            $this->cliPath,
            fopen("https://github.com/aesirxio/analytics/releases/download/2.2.10/analytics-cli-linux-" . $arch, 'r')
        );
        chmod($this->cliPath, 0755);
        $this->processAnalytics(['migrate']);
    }

    /**
     * @param array $command
     * @param bool  $makeExecutable
     *
     * @return Process
     * @throws ExceptionWithErrorType
     *
     */
    public function processAnalytics(array $command, bool $makeExecutable = true): Process
    {
        if (!$this->analyticsCliExists()) {
            throw new RuntimeException('CLI analytics library not found');
        }

        // Plugin probably updated, we need to make sure it's executable and database is up-to-date
        if ($makeExecutable && 0755 !== (fileperms($this->cliPath) & 0777)) {
            chmod($this->cliPath, 0755);
            if ($command != ['migrate']) {
                $this->processAnalytics(['migrate'], false);
            }
        }

        $process = new Process(array_merge([$this->cliPath], $command), null, $this->env->getData());
        $process->run();
        if (!$process->isSuccessful()) {
            $message = $process->getErrorOutput();
            $decoded = json_decode($message);
            $type = null;

            if (
                json_last_error() === JSON_ERROR_NONE
                && $process->getExitCode() == 65
            ) {
                if (!empty($decoded->message)) {
                    $message = $decoded->message;
                }
                if (!empty($decoded->error_type)) {
                    $type = $decoded->error_type;
                }
            }
            throw new ExceptionWithErrorType($message, $type);
        }

        return $process;
    }
}
