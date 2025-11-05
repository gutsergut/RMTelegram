<?php
/** @var Joomla\Component\RadicalMartTelegram\Administrator\View\Status\HtmlView $this */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

?>
<div class="container-fluid">
    <h2><?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_STATUS_TITLE'); ?></h2>
    <p class="small text-muted"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_STATUS_DESC'); ?></p>

    <div class="mb-3">
        <a class="btn btn-success" target="_blank"
           href="<?php echo Route::_('index.php?option=com_radicalmart_telegram&task=api.apishipfetch'); ?>">
            <?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_STATUS_FETCH'); ?>
        </a>
    </div>

    <table class="table table-striped table-sm">
        <thead>
        <tr>
            <th><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROVIDER'); ?></th>
            <th><?php echo Text::_('COM_RADICALMART_TELEGRAM_LAST_FETCH'); ?></th>
            <th class="text-end"><?php echo Text::_('COM_RADICALMART_TELEGRAM_LAST_TOTAL'); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($this->items)) : ?>
            <tr><td colspan="3" class="text-muted"><?php echo Text::_('COM_RADICALMART_TELEGRAM_NO_DATA'); ?></td></tr>
        <?php else : foreach ($this->items as $row) : ?>
            <tr>
                <td><?php echo htmlspecialchars($row['provider'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($row['last_fetch'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="text-end"><?php echo (int)($row['last_total'] ?? 0); ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

