<?php
/** @var Joomla\Component\RadicalMartTelegram\Administrator\View\Links\HtmlView $this */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;

?>
<div class="container-fluid">
    <h2><?php echo Text::_('COM_RADICALMART_TELEGRAM_USER_LINKS_TITLE'); ?></h2>
    <p class="small text-muted"><?php echo Text::_('COM_RADICALMART_TELEGRAM_USER_LINKS_DESC'); ?></p>

    <form method="get" action="index.php" class="mb-3">
        <input type="hidden" name="option" value="com_radicalmart_telegram">
        <input type="hidden" name="view" value="links">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CHAT_ID'); ?></label>
                <input type="text" class="form-control form-control-sm" name="filter_chat" value="<?php echo htmlspecialchars($this->filters['chat'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-auto">
                <label class="form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_TG_USER_ID'); ?></label>
                <input type="text" class="form-control form-control-sm" name="filter_tg" value="<?php echo htmlspecialchars($this->filters['tg'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-auto">
                <label class="form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_TG_USERNAME'); ?></label>
                <input type="text" class="form-control form-control-sm" name="filter_username" value="<?php echo htmlspecialchars($this->filters['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-auto">
                <label class="form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PHONE'); ?></label>
                <input type="text" class="form-control form-control-sm" name="filter_phone" value="<?php echo htmlspecialchars($this->filters['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-auto">
                <label class="form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_USER_ID'); ?></label>
                <input type="number" class="form-control form-control-sm" name="filter_user" value="<?php echo (int) ($this->filters['user'] ?? 0); ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><?php echo Text::_('JAPPLY'); ?></button>
                <a href="index.php?option=com_radicalmart_telegram&view=links" class="btn btn-sm btn-secondary"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></a>
            </div>
        </div>
    </form>

    <table class="table table-striped table-sm">
        <thead>
        <tr>
            <th><?php echo Text::_('COM_RADICALMART_TELEGRAM_CHAT_ID'); ?></th>
            <th><?php echo Text::_('COM_RADICALMART_TELEGRAM_TG_USER_ID'); ?></th>
            <th><?php echo Text::_('COM_RADICALMART_TELEGRAM_TG_USERNAME'); ?></th>
            <th><?php echo Text::_('COM_RADICALMART_TELEGRAM_USER_ID'); ?></th>
            <th><?php echo Text::_('COM_RADICALMART_TELEGRAM_USER_NAME'); ?></th>
            <th><?php echo Text::_('COM_RADICALMART_TELEGRAM_PHONE'); ?></th>
            <th><?php echo Text::_('COM_RADICALMART_TELEGRAM_CREATED'); ?></th>
            <th class="text-end">&nbsp;</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($this->items)) : ?>
            <tr><td colspan="7" class="text-muted"><?php echo Text::_('COM_RADICALMART_TELEGRAM_NO_DATA'); ?></td></tr>
        <?php else : foreach ($this->items as $row) : ?>
            <tr>
                <td><?php echo (int) ($row['chat_id'] ?? 0); ?></td>
                <td><?php echo (int) ($row['tg_user_id'] ?? 0); ?></td>
                <td><?php echo htmlspecialchars($row['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo (int) ($row['user_id'] ?? 0); ?></td>
                <td><?php echo htmlspecialchars(($row['jname'] ?? '') ?: ($row['jlogin'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($row['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($row['created'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="text-end">
                    <form method="post" action="<?php echo Route::_('index.php?option=com_radicalmart_telegram&task=links.unlink'); ?>" style="display:inline-block">
                        <input type="hidden" name="chat_id" value="<?php echo (int) ($row['chat_id'] ?? 0); ?>">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <button type="submit" class="btn btn-sm btn-warning"><?php echo Text::_('COM_RADICALMART_TELEGRAM_UNLINK'); ?></button>
                    </form>
                    <form method="post" action="<?php echo Route::_('index.php?option=com_radicalmart_telegram&task=links.attach'); ?>" style="display:inline-block">
                        <input type="hidden" name="chat_id" value="<?php echo (int) ($row['chat_id'] ?? 0); ?>">
                        <input type="number" class="form-control form-control-sm" name="user_id" placeholder="User ID" style="width:110px; display:inline-block">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <button type="submit" class="btn btn-sm btn-primary"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ATTACH'); ?></button>
                    </form>
                    <form method="post" action="<?php echo Route::_('index.php?option=com_radicalmart_telegram&task=links.attachByPhone'); ?>" style="display:inline-block">
                        <input type="hidden" name="chat_id" value="<?php echo (int) ($row['chat_id'] ?? 0); ?>">
                        <input type="text" class="form-control form-control-sm" name="phone" placeholder="+7..." style="width:140px; display:inline-block">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <button type="submit" class="btn btn-sm btn-success"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ATTACH_BY_PHONE'); ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
