<?php
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
$isSuccess = $this->status === 'success';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isSuccess ? Text::_('COM_RADICALMART_TELEGRAM_EMAIL_VERIFIED_TITLE') : Text::_('COM_RADICALMART_TELEGRAM_EMAIL_VERIFICATION_ERROR_TITLE'); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; }
        .card { background: white; border-radius: 16px; padding: 40px; max-width: 400px; width: 100%; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .icon { font-size: 64px; margin-bottom: 20px; }
        .icon.success { color: #10b981; }
        .icon.error { color: #ef4444; }
        h1 { font-size: 24px; margin-bottom: 16px; color: #1f2937; }
        p { color: #6b7280; line-height: 1.6; margin-bottom: 24px; }
        .btn { display: inline-block; padding: 12px 32px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: 500; transition: background 0.2s; }
        .btn:hover { background: #5a67d8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon <?php echo $isSuccess ? 'success' : 'error'; ?>">
            <?php echo $isSuccess ? '✓' : '✗'; ?>
        </div>
        <h1><?php echo $isSuccess ? Text::_('COM_RADICALMART_TELEGRAM_EMAIL_VERIFIED_TITLE') : Text::_('COM_RADICALMART_TELEGRAM_EMAIL_VERIFICATION_ERROR_TITLE'); ?></h1>
        <p><?php echo $this->escape($this->message); ?></p>
        <a href="https://t.me/cacaolandbot" class="btn"><?php echo Text::_('COM_RADICALMART_TELEGRAM_BACK_TO_BOT'); ?></a>
    </div>
</body>
</html>
