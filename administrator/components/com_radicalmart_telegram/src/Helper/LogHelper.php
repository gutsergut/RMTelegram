<?php
namespace Joomla\Component\RadicalMartTelegram\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Log\Log;

class LogHelper
{
    private static ?bool $logsEnabled = null;
    private static array $loggerCategories = [];

    public static function isEnabled(): bool
    {
        if (self::$logsEnabled === null) {
            try {
                $params = ComponentHelper::getParams('com_radicalmart_telegram');
                self::$logsEnabled = ((int) $params->get('logs_enabled', 0) === 1);
            } catch (\Throwable $e) {
                self::$logsEnabled = false;
            }
        }
        return self::$logsEnabled;
    }

    private static function initLogger(string $category = 'com_radicalmart_telegram'): void
    {
        if (isset(self::$loggerCategories[$category])) {
            return;
        }
        try {
            Log::addLogger(
                ['text_file' => 'com_radicalmart.telegram.php'],
                Log::ALL,
                [$category]
            );
            self::$loggerCategories[$category] = true;
        } catch (\Throwable $e) {
            // Ignore logger initialization errors
        }
    }

    public static function debug(string $message, string $category = 'com_radicalmart_telegram'): void
    {
        if (!self::isEnabled()) {
            return;
        }
        self::initLogger($category);
        Log::add($message, Log::DEBUG, $category);
    }

    public static function info(string $message, string $category = 'com_radicalmart_telegram'): void
    {
        if (!self::isEnabled()) {
            return;
        }
        self::initLogger($category);
        Log::add($message, Log::INFO, $category);
    }

    public static function warning(string $message, string $category = 'com_radicalmart_telegram'): void
    {
        self::initLogger($category);
        Log::add($message, Log::WARNING, $category);
    }

    public static function error(string $message, string $category = 'com_radicalmart_telegram'): void
    {
        self::initLogger($category);
        Log::add($message, Log::ERROR, $category);
    }

    public static function reset(): void
    {
        self::$logsEnabled = null;
    }
}
