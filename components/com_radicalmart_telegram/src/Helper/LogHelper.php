<?php
/**
 * @package     com_radicalmart_telegram
 * @subpackage  Helper
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Log\Log;

/**
 * Log helper for RadicalMart Telegram component.
 * Provides centralized logging with component settings check.
 *
 * @since  1.0.0
 */
class LogHelper
{
    /**
     * Cached logs_enabled setting
     *
     * @var bool|null
     */
    private static ?bool $logsEnabled = null;

    /**
     * Logger initialized flag
     *
     * @var bool
     */
    private static bool $loggerReady = false;

    /**
     * Check if logging is enabled in component settings
     *
     * @return bool
     */
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

    /**
     * Initialize logger if not already done
     *
     * @param   string  $category  Log category
     *
     * @return void
     */
    private static function initLogger(string $category = 'com_radicalmart.telegram'): void
    {
        if (self::$loggerReady) {
            return;
        }

        try {
            Log::addLogger(
                ['text_file' => 'everything.php'],
                Log::ALL,
                [$category, 'radicalmart_telegram_catalog', 'com_radicalmart.telegram.tariff']
            );
            self::$loggerReady = true;
        } catch (\Throwable $e) {
            // Ignore logger initialization errors
        }
    }

    /**
     * Log a debug message (only if logging is enabled)
     *
     * @param   string  $message   Log message
     * @param   string  $category  Log category
     *
     * @return void
     */
    public static function debug(string $message, string $category = 'com_radicalmart.telegram'): void
    {
        if (!self::isEnabled()) {
            return;
        }

        self::initLogger($category);
        Log::add($message, Log::DEBUG, $category);
    }

    /**
     * Log an info message (only if logging is enabled)
     *
     * @param   string  $message   Log message
     * @param   string  $category  Log category
     *
     * @return void
     */
    public static function info(string $message, string $category = 'com_radicalmart.telegram'): void
    {
        if (!self::isEnabled()) {
            return;
        }

        self::initLogger($category);
        Log::add($message, Log::INFO, $category);
    }

    /**
     * Log a warning message (always logged, regardless of setting)
     *
     * @param   string  $message   Log message
     * @param   string  $category  Log category
     *
     * @return void
     */
    public static function warning(string $message, string $category = 'com_radicalmart.telegram'): void
    {
        self::initLogger($category);
        Log::add($message, Log::WARNING, $category);
    }

    /**
     * Log an error message (always logged, regardless of setting)
     *
     * @param   string  $message   Log message
     * @param   string  $category  Log category
     *
     * @return void
     */
    public static function error(string $message, string $category = 'com_radicalmart.telegram'): void
    {
        self::initLogger($category);
        Log::add($message, Log::ERROR, $category);
    }

    /**
     * Reset the cached setting (useful for testing)
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$logsEnabled = null;
    }
}
