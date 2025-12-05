<?php
/**
 * @package     com_radicalmart_telegram
 * @subpackage  Helper
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Log\Log;

/**
 * Log helper for RadicalMart Telegram component (Administrator).
 * Provides centralized logging with component settings check.
 *
 * @since  1.0.0








































































































































}    }        self::$logsEnabled = null;    {    public static function reset(): void     */     * @return void     *     * Reset the cached setting (useful for testing)    /**    }        Log::add($message, Log::ERROR, $category);        self::initLogger($category);    {    public static function error(string $message, string $category = 'com_radicalmart_telegram'): void     */     * @return void     *     * @param   string  $category  Log category     * @param   string  $message   Log message     *     * Log an error message (always logged, regardless of setting)    /**    }        Log::add($message, Log::WARNING, $category);        self::initLogger($category);    {    public static function warning(string $message, string $category = 'com_radicalmart_telegram'): void     */     * @return void     *     * @param   string  $category  Log category     * @param   string  $message   Log message     *     * Log a warning message (always logged, regardless of setting)    /**    }        Log::add($message, Log::INFO, $category);        self::initLogger($category);        }            return;        if (!self::isEnabled()) {    {    public static function info(string $message, string $category = 'com_radicalmart_telegram'): void     */     * @return void     *     * @param   string  $category  Log category     * @param   string  $message   Log message     *     * Log an info message (only if logging is enabled)    /**    }        Log::add($message, Log::DEBUG, $category);        self::initLogger($category);        }            return;        if (!self::isEnabled()) {    {    public static function debug(string $message, string $category = 'com_radicalmart_telegram'): void     */     * @return void     *     * @param   string  $category  Log category     * @param   string  $message   Log message     *     * Log a debug message (only if logging is enabled)    /**    }        }            // Ignore logger initialization errors        } catch (\Throwable $e) {            self::$loggerCategories[$category] = true;            );                [$category]                Log::ALL,                ['text_file' => 'com_radicalmart.telegram.php'],            Log::addLogger(        try {        }            return;        if (isset(self::$loggerCategories[$category])) {    {    private static function initLogger(string $category = 'com_radicalmart_telegram'): void     */     * @return void     *     * @param   string  $category  Log category     *     * Initialize logger for a specific category if not already done    /**    }        return self::$logsEnabled;        }            }                self::$logsEnabled = false;            } catch (\Throwable $e) {                self::$logsEnabled = ((int) $params->get('logs_enabled', 0) === 1);                $params = ComponentHelper::getParams('com_radicalmart_telegram');            try {        if (self::$logsEnabled === null) {    {    public static function isEnabled(): bool     */     * @return bool     *     * Check if logging is enabled in component settings    /**    private static array $loggerCategories = [];     */     * @var array     *     * Logger initialized categories    /**    private static ?bool $logsEnabled = null;     */     * @var bool|null     *     * Cached logs_enabled setting    /**{class LogHelper */
