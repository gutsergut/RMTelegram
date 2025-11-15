<?php
/** @var Joomla\Component\RadicalMartTelegram\Administrator\View\Links\HtmlView $this */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;

?>
<div class="container-fluid">
    <h2><?php echo Text::_('COM_RADICALMART_TELEGRAM_USER_LINKS_TITLE'); ?></h2>
    <p class="small text-muted"><?php echo Text::_('COM_RADICALMART_TELEGRAM_USER_LINKS_DESC'); ?></p>

    <form action="index.php?option=com_radicalmart_telegram&view=links" method="get" id="adminForm" name="adminForm">
        <div class="card card-body mb-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-3 col-sm-6">
                    <label class="form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_FILTER_SEARCH_LABEL'); ?></label>
                    <input type="text" class="form-control" name="filter[search]" placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_FILTER_SEARCH_HINT'); ?>" value="<?php echo htmlspecialchars($this->filters['search'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_FILTER_CHAT_ID_LABEL'); ?></label>
                    <input type="text" class="form-control" name="filter[chat_id]" placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_FILTER_CHAT_ID_HINT'); ?>" value="<?php echo htmlspecialchars($this->filters['chat'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_FILTER_TG_USER_ID_LABEL'); ?></label>
                    <input type="text" class="form-control" name="filter[tg_user_id]" placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_FILTER_TG_USER_ID_HINT'); ?>" value="<?php echo htmlspecialchars($this->filters['tg'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_FILTER_USERNAME_LABEL'); ?></label>
                    <input type="text" class="form-control" name="filter[username]" placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_FILTER_USERNAME_HINT'); ?>" value="<?php echo htmlspecialchars($this->filters['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_FILTER_PHONE_LABEL'); ?></label>
                    <input type="text" class="form-control" name="filter[phone]" placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_FILTER_PHONE_HINT'); ?>" value="<?php echo htmlspecialchars($this->filters['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-1 col-sm-6">
                    <label class="form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_FILTER_USER_ID_LABEL'); ?></label>
                    <input type="number" class="form-control" name="filter[user_id]" placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_FILTER_USER_ID_HINT'); ?>" value="<?php echo (int)($this->filters['user'] ?? 0); ?>">
                </div>
            </div>
            <div class="row g-3 align-items-end mt-1">
                <div class="col-md-2 col-sm-6">
                    <label class="form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_FILTER_PD_LABEL'); ?></label>
                    <select class="form-select" name="filter[consent_personal_data]">
                        <option value=""><?php echo Text::_('JALL'); ?></option>
                        <option value="1" <?php echo ((string)($this->filters['consent_personal_data'] ?? '') === '1') ? 'selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
                        <option value="0" <?php echo ((string)($this->filters['consent_personal_data'] ?? '') === '0') ? 'selected' : ''; ?>><?php echo Text::_('JNO'); ?></option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_FILTER_TERMS_LABEL'); ?></label>
                    <select class="form-select" name="filter[consent_terms]">
                        <option value=""><?php echo Text::_('JALL'); ?></option>
                        <option value="1" <?php echo ((string)($this->filters['consent_terms'] ?? '') === '1') ? 'selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
                        <option value="0" <?php echo ((string)($this->filters['consent_terms'] ?? '') === '0') ? 'selected' : ''; ?>><?php echo Text::_('JNO'); ?></option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_FILTER_MKT_LABEL'); ?></label>
                    <select class="form-select" name="filter[consent_marketing]">
                        <option value=""><?php echo Text::_('JALL'); ?></option>
                        <option value="1" <?php echo ((string)($this->filters['consent_marketing'] ?? '') === '1') ? 'selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
                        <option value="0" <?php echo ((string)($this->filters['consent_marketing'] ?? '') === '0') ? 'selected' : ''; ?>><?php echo Text::_('JNO'); ?></option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6 ms-auto text-end">
                    <button type="submit" class="btn btn-sm btn-primary"><?php echo Text::_('JAPPLY'); ?></button>
                    <a href="index.php?option=com_radicalmart_telegram&view=links" class="btn btn-sm btn-secondary"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></a>
                </div>
            </div>
        </div>

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
                <th><?php echo Text::_('COM_RADICALMART_TELEGRAM_CONSENTS'); ?></th>
                <th class="text-end">&nbsp;</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($this->items)) : ?>
                <tr><td colspan="9" class="text-muted"><?php echo Text::_('COM_RADICALMART_TELEGRAM_NO_DATA'); ?></td></tr>
            <?php else : foreach ($this->items as $row) : ?>
                <tr>
                    <td><?php echo (int) ($row['chat_id'] ?? 0); ?></td>
                    <td><?php echo (int) ($row['tg_user_id'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars($row['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int) ($row['user_id'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars(($row['jname'] ?? '') ?: ($row['jlogin'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($row['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($row['created'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="small">
                        <?php
                            $pd  = !empty($row['consent_personal_data']) && (int)$row['consent_personal_data'] === 1;
                            $pdt = trim((string)($row['consent_personal_data_at'] ?? ''));
                            $tm  = !empty($row['consent_terms']) && (int)$row['consent_terms'] === 1;
                            $tmt = trim((string)($row['consent_terms_at'] ?? ''));
                            $mk  = !empty($row['consent_marketing']) && (int)$row['consent_marketing'] === 1;
                            $mkt = trim((string)($row['consent_marketing_at'] ?? ''));
                        ?>
                        <div><?php echo Text::_('COM_RADICALMART_TELEGRAM_CONSENT_PD_SHORT'); ?>: <?php echo $pd ? ('<span class="text-success">✓</span> '.htmlspecialchars($pdt, ENT_QUOTES, 'UTF-8')) : '<span class="text-muted">—</span>'; ?></div>
                        <div><?php echo Text::_('COM_RADICALMART_TELEGRAM_CONSENT_TERMS_SHORT'); ?>: <?php echo $tm ? ('<span class="text-success">✓</span> '.htmlspecialchars($tmt, ENT_QUOTES, 'UTF-8')) : '<span class="text-muted">—</span>'; ?></div>
                        <div><?php echo Text::_('COM_RADICALMART_TELEGRAM_CONSENT_MKT_SHORT'); ?>: <?php echo $mk ? ('<span class="text-success">✓</span> '.htmlspecialchars($mkt, ENT_QUOTES, 'UTF-8')) : '<span class="text-muted">—</span>'; ?></div>
                    </td>
                    <td class="text-end">
                        <form method="post" action="<?php echo Route::_('index.php?option=com_radicalmart_telegram&task=links.unlink'); ?>" style="display:inline-block">
                            <input type="hidden" name="chat_id" value="<?php echo (int) ($row['chat_id'] ?? 0); ?>">
                            <?php echo HTMLHelper::_('form.token'); ?>
                            <button type="submit" class="btn btn-sm btn-warning"><?php echo Text::_('COM_RADICALMART_TELEGRAM_UNLINK'); ?></button>
                        </form>
                        <form method="post" action="<?php echo Route::_('index.php?option=com_radicalmart_telegram&task=links.unlinkPhone'); ?>" style="display:inline-block" onsubmit="return confirm('<?php echo Text::_('COM_RADICALMART_TELEGRAM_UNLINK_PHONE_CONFIRM'); ?>');">
                            <input type="hidden" name="chat_id" value="<?php echo (int) ($row['chat_id'] ?? 0); ?>">
                            <?php echo HTMLHelper::_('form.token'); ?>
                            <button type="submit" class="btn btn-sm btn-outline-warning"><?php echo Text::_('COM_RADICALMART_TELEGRAM_UNLINK_PHONE'); ?></button>
                        </form>
                        <?php $hasUser = (int)($row['user_id'] ?? 0) > 0; $hasPhone = trim((string)($row['phone'] ?? '')) !== ''; ?>
                        <?php if (!$hasUser) : ?>
                            <form method="post" action="<?php echo Route::_('index.php?option=com_radicalmart_telegram&task=links.attach'); ?>" style="display:inline-block">
                                <input type="hidden" name="chat_id" value="<?php echo (int) ($row['chat_id'] ?? 0); ?>">
                                <input type="number" class="form-control form-control-sm" name="user_id" placeholder="User ID" style="width:110px; display:inline-block">
                                <?php echo HTMLHelper::_('form.token'); ?>
                                <button type="submit" class="btn btn-sm btn-primary"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ATTACH'); ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if (!$hasPhone) : ?>
                            <form method="post" action="<?php echo Route::_('index.php?option=com_radicalmart_telegram&task=links.attachByPhone'); ?>" style="display:inline-block">
                                <input type="hidden" name="chat_id" value="<?php echo (int) ($row['chat_id'] ?? 0); ?>">
                                <input type="text" class="form-control form-control-sm" name="phone" placeholder="+7..." style="width:140px; display:inline-block">
                                <?php echo HTMLHelper::_('form.token'); ?>
                                <button type="submit" class="btn btn-sm btn-success"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ATTACH_BY_PHONE'); ?></button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?php echo Route::_('index.php?option=com_radicalmart_telegram&task=links.resetConsents'); ?>" style="display:inline-block" onsubmit="return confirm('<?php echo Text::_('COM_RADICALMART_TELEGRAM_RESET_CONSENTS_CONFIRM'); ?>');">
                            <input type="hidden" name="chat_id" value="<?php echo (int) ($row['chat_id'] ?? 0); ?>">
                            <?php echo HTMLHelper::_('form.token'); ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo Text::_('COM_RADICALMART_TELEGRAM_RESET_CONSENTS'); ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <input type="hidden" name="option" value="com_radicalmart_telegram" />
        <input type="hidden" name="view" value="links" />
    </form>

</div>
