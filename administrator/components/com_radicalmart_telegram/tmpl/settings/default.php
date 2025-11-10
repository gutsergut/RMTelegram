<?php
/*
 * @package     com_radicalmart_telegram (admin)
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

?>
<div class="container-fluid">
    <h1><?php echo Text::_('COM_RADICALMART_TELEGRAM_SETTINGS_TITLE'); ?></h1>

    <?php if ($this->botInfo): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0"><?php echo Text::_('COM_RADICALMART_TELEGRAM_BOT_INFO'); ?></h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th style="width: 200px;">Bot ID:</th>
                        <td><?php echo htmlspecialchars($this->botInfo['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th>Username:</th>
                        <td>@<?php echo htmlspecialchars($this->botInfo['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th>Name:</th>
                        <td><?php echo htmlspecialchars($this->botInfo['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th>Can join groups:</th>
                        <td><?php echo !empty($this->botInfo['can_join_groups']) ? Text::_('JYES') : Text::_('JNO'); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <?php echo Text::_('COM_RADICALMART_TELEGRAM_BOT_TOKEN_NOT_SET'); ?>
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0"><?php echo Text::_('COM_RADICALMART_TELEGRAM_WEBHOOK_SETTINGS'); ?></h5>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label"><strong><?php echo Text::_('COM_RADICALMART_TELEGRAM_WEBHOOK_URL'); ?>:</strong></label>
                <div class="input-group">
                    <input type="text" class="form-control" readonly value="<?php echo htmlspecialchars($this->webhookUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($this->webhookUrl, ENT_QUOTES, 'UTF-8'); ?>')">
                        <span class="icon-copy"></span> <?php echo Text::_('JLIB_HTML_BEHAVIOR_COPY'); ?>
                    </button>
                </div>
            </div>

            <?php if (isset($this->webhookInfo['error'])): ?>
                <div class="alert alert-danger">
                    <strong><?php echo Text::_('COM_RADICALMART_TELEGRAM_WEBHOOK_ERROR'); ?>:</strong>
                    <?php echo htmlspecialchars($this->webhookInfo['error'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php else: ?>
                <table class="table table-sm">
                    <tr>
                        <th style="width: 200px;"><?php echo Text::_('COM_RADICALMART_TELEGRAM_WEBHOOK_CURRENT_URL'); ?>:</th>
                        <td>
                            <?php if (!empty($this->webhookInfo['url'])): ?>
                                <code><?php echo htmlspecialchars($this->webhookInfo['url'], ENT_QUOTES, 'UTF-8'); ?></code>
                            <?php else: ?>
                                <span class="text-muted"><?php echo Text::_('COM_RADICALMART_TELEGRAM_WEBHOOK_NOT_SET'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo Text::_('COM_RADICALMART_TELEGRAM_WEBHOOK_STATUS'); ?>:</th>
                        <td>
                            <?php if (!empty($this->webhookInfo['url'])): ?>
                                <?php if ($this->webhookInfo['url'] === $this->webhookUrl): ?>
                                    <span class="badge bg-success"><?php echo Text::_('COM_RADICALMART_TELEGRAM_WEBHOOK_OK'); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-warning"><?php echo Text::_('COM_RADICALMART_TELEGRAM_WEBHOOK_MISMATCH'); ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?php echo Text::_('COM_RADICALMART_TELEGRAM_WEBHOOK_NOT_CONFIGURED'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (isset($this->webhookInfo['pending_update_count'])): ?>
                    <tr>
                        <th><?php echo Text::_('COM_RADICALMART_TELEGRAM_WEBHOOK_PENDING'); ?>:</th>
                        <td><?php echo (int) $this->webhookInfo['pending_update_count']; ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($this->webhookInfo['last_error_date'])): ?>
                    <tr>
                        <th><?php echo Text::_('COM_RADICALMART_TELEGRAM_WEBHOOK_LAST_ERROR'); ?>:</th>
                        <td class="text-danger">
                            <?php echo date('Y-m-d H:i:s', $this->webhookInfo['last_error_date']); ?>
                            <?php if (!empty($this->webhookInfo['last_error_message'])): ?>
                                <br><small><?php echo htmlspecialchars($this->webhookInfo['last_error_message'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            <?php endif; ?>

            <div class="mt-3">
                <button type="button" class="btn btn-primary" id="btnSetWebhook">
                    <span class="icon-upload"></span>
                    <?php echo Text::_('COM_RADICALMART_TELEGRAM_WEBHOOK_SET'); ?>
                </button>
                <button type="button" class="btn btn-secondary" id="btnRefresh">
                    <span class="icon-refresh"></span>
                    <?php echo Text::_('COM_RADICALMART_TELEGRAM_WEBHOOK_REFRESH'); ?>
                </button>
                <button type="button" class="btn btn-danger" id="btnDeleteWebhook">
                    <span class="icon-delete"></span>
                    <?php echo Text::_('COM_RADICALMART_TELEGRAM_WEBHOOK_DELETE'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const token = '<?php echo Session::getFormToken(); ?>';

    document.getElementById('btnSetWebhook').addEventListener('click', function() {
        if (confirm('<?php echo Text::_('COM_RADICALMART_TELEGRAM_WEBHOOK_SET_CONFIRM'); ?>')) {
            window.location.href = 'index.php?option=com_radicalmart_telegram&task=settings.setWebhook&' + token + '=1';
        }
    });

    document.getElementById('btnRefresh').addEventListener('click', function() {
        window.location.reload();
    });

    document.getElementById('btnDeleteWebhook').addEventListener('click', function() {
        if (confirm('<?php echo Text::_('COM_RADICALMART_TELEGRAM_WEBHOOK_DELETE_CONFIRM'); ?>')) {
            window.location.href = 'index.php?option=com_radicalmart_telegram&task=settings.deleteWebhook&' + token + '=1';
        }
    });
});
</script>
