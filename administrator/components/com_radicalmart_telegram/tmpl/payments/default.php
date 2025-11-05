<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
?>
<div class="container-fluid">
    <h2><?php echo Text::_('COM_RADICALMART_TELEGRAM_REFUND_TITLE'); ?></h2>
    <p class="small text-muted"><?php echo Text::_('COM_RADICALMART_TELEGRAM_REFUND_DESC'); ?></p>

    <form method="post" action="<?php echo Route::_('index.php?option=com_radicalmart_telegram&task=payments.refund'); ?>" class="mt-3">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_REFUND_ORDER_NUMBER'); ?></label>
                <input type="text" class="form-control form-control-sm" name="order_number" required>
            </div>
            <div class="col-auto">
                <label class="form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_REFUND_AMOUNT'); ?></label>
                <input type="number" class="form-control form-control-sm" name="amount" min="0" step="0.01">
            </div>
            <div class="col-auto">
                <label class="form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_REFUND_COMMENT'); ?></label>
                <input type="text" class="form-control form-control-sm" name="comment">
            </div>
            <div class="col-auto">
                <?php echo HTMLHelper::_('form.token'); ?>
                <button type="submit" class="btn btn-sm btn-danger"><?php echo Text::_('COM_RADICALMART_TELEGRAM_REFUND_SUBMIT'); ?></button>
            </div>
        </div>
    </form>
</div>

