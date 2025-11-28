<?php
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\HTML\HTMLHelper;

$root = Uri::root();
$app = \Joomla\CMS\Factory::getApplication();
$chat = $app->input->getInt('chat', 0);
$chatId = $this->tgUser['chat_id'] ?? $chat;
$baseQuery = $chatId > 0 ? '&chat=' . $chatId : '';

$doc = \Joomla\CMS\Factory::getDocument();
$doc->addStyleDeclaration('
    #app-bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; z-index: 1000; background: var(--tg-theme-bg-color, #fff); border-top: 1px solid var(--tg-theme-hint-color, #e5e5e5); padding-bottom: env(safe-area-inset-bottom); }
    #app-bottom-nav .uk-navbar-nav > li > a { min-height: 60px; padding: 0 10px; font-size: 10px; line-height: 1.2; text-transform: none; color: var(--tg-theme-hint-color, #999); }
    #app-bottom-nav .uk-navbar-nav > li.uk-active > a { color: var(--tg-theme-link-color, #1e87f0); }
    #app-bottom-nav .bottom-tab { display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; }
    #app-bottom-nav .uk-icon { margin-bottom: 4px; }
    body { padding-bottom: 80px; }
    .referral-code-card { background: var(--tg-theme-secondary-bg-color, #f5f5f5); border-radius: 12px; padding: 16px; margin-bottom: 12px; }
    .referral-code-value { font-family: monospace; font-size: 18px; font-weight: 600; color: var(--tg-theme-text-color, #333); letter-spacing: 1px; }
    .referral-code-discount { font-size: 14px; color: var(--tg-theme-link-color, #1e87f0); }
    .referral-code-meta { font-size: 12px; color: var(--tg-theme-hint-color, #999); }
    .referral-code-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 500; }
    .referral-code-badge.active { background: #e8f5e9; color: #2e7d32; }
    .referral-code-badge.expired { background: #ffebee; color: #c62828; }
    .referral-code-badge.used { background: #fff3e0; color: #ef6c00; }
    .referral-item { position: relative; padding: 12px 0; border-bottom: 1px solid var(--tg-theme-hint-color, #eee); }
    .referral-item:last-child { border-bottom: none; }
    .referral-item .level-indicator { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: var(--tg-theme-link-color, #1e87f0); color: #fff; font-size: 12px; font-weight: 600; margin-right: 8px; flex-shrink: 0; }
    .referral-item .level-indicator.level-2 { background: #7c4dff; }
    .referral-item .level-indicator.level-3 { background: #ff4081; }
    .referral-item .level-indicator.level-4 { background: #ff9800; }
    .referral-item .level-indicator.level-5 { background: #607d8b; }
    .sub-referrals { margin-left: 32px; margin-top: 8px; border-left: 2px solid var(--tg-theme-hint-color, #e0e0e0); padding-left: 12px; }
    .parent-chain-item { display: flex; align-items: center; padding: 8px 0; }
    .parent-chain-item .level-badge { background: var(--tg-theme-secondary-bg-color, #f0f0f0); padding: 2px 8px; border-radius: 8px; font-size: 11px; margin-right: 8px; }
    .stats-card { background: linear-gradient(135deg, var(--tg-theme-link-color, #1e87f0) 0%, #7c4dff 100%); border-radius: 12px; padding: 20px; color: #fff; margin-bottom: 16px; }
    .stats-card .stats-value { font-size: 28px; font-weight: 700; }
    .stats-card .stats-label { font-size: 13px; opacity: 0.9; }
    .copy-btn { background: var(--tg-theme-button-color, #1e87f0); color: var(--tg-theme-button-text-color, #fff); border: none; border-radius: 8px; padding: 8px 16px; font-size: 13px; cursor: pointer; transition: opacity 0.2s; }
    .copy-btn:hover { opacity: 0.9; }
    .copy-btn.copied { background: #4caf50; }
');
?>
<div id="referrals-app" class="uk-container uk-container-small uk-padding-small">
    <div class="uk-flex uk-flex-middle uk-margin-small-bottom">
        <a href="<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=profile<?php echo $baseQuery; ?>" class="uk-margin-small-right" uk-icon="icon: arrow-left"></a>
        <h1 class="uk-h3 uk-margin-remove"><?php echo Text::_('COM_RADICALMART_TELEGRAM_REFERRALS'); ?></h1>
    </div>

    <?php if (!$this->userId): ?>
    <div class="uk-card uk-card-default uk-card-body uk-text-center">
        <span uk-icon="icon: lock; ratio: 2" class="uk-text-muted"></span>
        <p class="uk-margin-small-top"><?php echo Text::_('COM_RADICALMART_TELEGRAM_REFERRALS_LOGIN_REQUIRED'); ?></p>
    </div>
    
    <?php elseif (!$this->inChain): ?>
    <div class="uk-card uk-card-default uk-card-body uk-text-center">
        <span uk-icon="icon: users; ratio: 2" class="uk-text-muted"></span>
        <h3 class="uk-margin-small-top"><?php echo Text::_('COM_RADICALMART_TELEGRAM_REFERRALS_NOT_IN_PROGRAM'); ?></h3>
        <p class="uk-text-muted"><?php echo Text::_('COM_RADICALMART_TELEGRAM_REFERRALS_NOT_IN_PROGRAM_DESC'); ?></p>
    </div>
    
    <?php else: ?>
    
    <div class="stats-card">
        <div class="uk-grid-small uk-child-width-1-2" uk-grid>
            <div>
                <div class="stats-value"><?php echo $this->referralsCount; ?></div>
                <div class="stats-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_REFERRALS_COUNT'); ?></div>
            </div>
            <div>
                <div class="stats-value"><?php echo number_format($this->totalEarnedPoints, 0, ',', ' '); ?></div>
                <div class="stats-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_REFERRALS_EARNED_POINTS'); ?></div>
            </div>
        </div>
    </div>

    <div class="uk-card uk-card-default uk-card-body uk-margin-bottom">
        <h3 class="uk-card-title uk-margin-small-bottom">
            <span uk-icon="icon: link" class="uk-margin-small-right"></span>
            <?php echo Text::_('COM_RADICALMART_TELEGRAM_REFERRALS_MY_CODES'); ?>
        </h3>
        
        <?php if (empty($this->referralCodes)): ?>
        <p class="uk-text-muted uk-text-center uk-margin-small"><?php echo Text::_('COM_RADICALMART_TELEGRAM_REFERRALS_NO_CODES'); ?></p>
        <?php else: ?>
            <?php foreach ($this->referralCodes as $code): ?>
            <div class="referral-code-card">
                <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-bottom">
                    <span class="referral-code-value"><?php echo htmlspecialchars($code->code); ?></span>
                    <span class="referral-code-badge <?php echo $code->enabled ? 'active' : ($code->expires && strtotime($code->expires) < time() ? 'expired' : 'used'); ?>">
                        <?php 
                        if (!$code->enabled) {
                            if ($code->expires && strtotime($code->expires) < time()) {
                                echo Text::_('COM_RADICALMART_TELEGRAM_CODE_EXPIRED');
                            } else {
                                echo Text::_('COM_RADICALMART_TELEGRAM_CODE_LIMIT_REACHED');
                            }
                        } else {
                            echo Text::_('COM_RADICALMART_TELEGRAM_CODE_ACTIVE');
                        }
                        ?>
                    </span>
                </div>
                
                <div class="referral-code-discount uk-margin-small-bottom">
                    <?php echo Text::sprintf('COM_RADICALMART_TELEGRAM_REFERRALS_CODE_DISCOUNT', $code->discount_string); ?>
                </div>
                
                <div class="referral-code-meta uk-margin-small-bottom">
                    <?php if ($code->customers_limit > 0): ?>
                    <?php echo Text::sprintf('COM_RADICALMART_TELEGRAM_REFERRALS_CODE_USAGE', $code->used_count, $code->customers_limit); ?>
                    <?php else: ?>
                    <?php echo Text::sprintf('COM_RADICALMART_TELEGRAM_REFERRALS_CODE_USED_TIMES', $code->used_count); ?>
                    <?php endif; ?>
                    
                    <?php if ($code->expires): ?>
                    <span class="uk-margin-small-left">
                        <?php echo Text::sprintf('COM_RADICALMART_TELEGRAM_CODE_EXPIRES_AT', HTMLHelper::_('date', $code->expires, 'd.m.Y')); ?>
                    </span>
                    <?php endif; ?>
                </div>
                
                <?php if ($code->link && $code->enabled): ?>
                <div class="uk-flex uk-flex-between uk-flex-middle">
                    <input type="text" value="<?php echo htmlspecialchars($code->link); ?>" 
                           class="uk-input uk-form-small uk-width-expand uk-margin-small-right" 
                           readonly id="code-link-<?php echo $code->id; ?>"
                           style="font-size: 12px; background: var(--tg-theme-bg-color, #fff);">
                    <button type="button" class="copy-btn" onclick="copyCodeLink(<?php echo $code->id; ?>, this)">
                        <span uk-icon="icon: copy; ratio: 0.8"></span>
                        <?php echo Text::_('COM_RADICALMART_TELEGRAM_COPY'); ?>
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if ($this->canCreateCode): ?>
        <p class="uk-text-muted uk-text-small uk-margin-top">
            <?php echo Text::_('COM_RADICALMART_TELEGRAM_REFERRALS_CREATE_CODE_HINT'); ?>
        </p>
        <?php endif; ?>
    </div>

    <?php if (!empty($this->parentChain)): ?>
    <div class="uk-card uk-card-default uk-card-body uk-margin-bottom">
        <h3 class="uk-card-title uk-margin-small-bottom">
            <span uk-icon="icon: chevron-double-left" class="uk-margin-small-right"></span>
            <?php echo Text::_('COM_RADICALMART_TELEGRAM_REFERRALS_INVITED_BY'); ?>
        </h3>
        
        <div class="parent-chain">
            <?php foreach ($this->parentChain as $parent): ?>
            <div class="parent-chain-item">
                <span class="level-badge"><?php echo Text::sprintf('COM_RADICALMART_TELEGRAM_REFERRALS_LEVEL', $parent->level); ?></span>
                <span class="uk-text-emphasis"><?php echo htmlspecialchars($parent->name ?: $parent->masked_email); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="uk-card uk-card-default uk-card-body uk-margin-bottom">
        <h3 class="uk-card-title uk-margin-small-bottom">
            <span uk-icon="icon: users" class="uk-margin-small-right"></span>
            <?php echo Text::_('COM_RADICALMART_TELEGRAM_REFERRALS_MY_REFERRALS'); ?>
            <?php if ($this->referralsCount > 0): ?>
            <span class="uk-badge"><?php echo $this->referralsCount; ?></span>
            <?php endif; ?>
        </h3>
        
        <?php if (empty($this->myReferrals)): ?>
        <p class="uk-text-muted uk-text-center uk-margin-small"><?php echo Text::_('COM_RADICALMART_TELEGRAM_REFERRALS_NO_REFERRALS'); ?></p>
        <p class="uk-text-muted uk-text-small uk-text-center"><?php echo Text::_('COM_RADICALMART_TELEGRAM_REFERRALS_SHARE_CODE_HINT'); ?></p>
        <?php else: ?>
        
        <div class="referral-tree">
            <?php foreach ($this->myReferrals as $ref): ?>
            <div class="referral-item">
                <div class="uk-flex uk-flex-middle">
                    <span class="level-indicator level-<?php echo min($ref->level, 5); ?>"><?php echo $ref->level; ?></span>
                    <div class="uk-width-expand">
                        <div class="uk-text-emphasis"><?php echo htmlspecialchars($ref->name ?: $ref->masked_email); ?></div>
                        <div class="uk-text-meta uk-text-small">
                            <?php echo HTMLHelper::_('date', $ref->created, 'd.m.Y'); ?>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($ref->sub_referrals)): ?>
                <div class="sub-referrals">
                    <?php foreach ($ref->sub_referrals as $sub): ?>
                    <div class="referral-item">
                        <div class="uk-flex uk-flex-middle">
                            <span class="level-indicator level-<?php echo min($sub->level, 5); ?>"><?php echo $sub->level; ?></span>
                            <div class="uk-width-expand">
                                <div class="uk-text-emphasis"><?php echo htmlspecialchars($sub->name ?: $sub->masked_email); ?></div>
                                <div class="uk-text-meta uk-text-small">
                                    <?php echo HTMLHelper::_('date', $sub->created, 'd.m.Y'); ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($sub->sub_referrals)): ?>
                        <div class="sub-referrals">
                            <?php foreach ($sub->sub_referrals as $sub2): ?>
                            <div class="referral-item">
                                <div class="uk-flex uk-flex-middle">
                                    <span class="level-indicator level-<?php echo min($sub2->level, 5); ?>"><?php echo $sub2->level; ?></span>
                                    <div class="uk-width-expand">
                                        <div class="uk-text-emphasis"><?php echo htmlspecialchars($sub2->name ?: $sub2->masked_email); ?></div>
                                        <div class="uk-text-meta uk-text-small"><?php echo HTMLHelper::_('date', $sub2->created, 'd.m.Y'); ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($this->hasMore): ?>
        <div class="uk-text-center uk-margin-top">
            <a href="<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=referrals<?php echo $baseQuery; ?>&start=<?php echo $this->start + $this->limit; ?>" 
               class="uk-button uk-button-default uk-button-small">
                <?php echo Text::_('COM_RADICALMART_TELEGRAM_LOAD_MORE'); ?>
            </a>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
    
    <?php endif; ?>
</div>

<div id="app-bottom-nav" class="uk-navbar-container" uk-navbar>
    <div class="uk-navbar-center uk-width-1-1 uk-flex uk-flex-center">
        <ul class="uk-navbar-nav">
            <li>
                <a href="<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=app<?php echo $baseQuery; ?>" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: thumbnails"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CATALOG'); ?></span></span>
                </a>
            </li>
            <li>
                <a href="<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=cart<?php echo $baseQuery; ?>" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: cart"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CART'); ?></span></span>
                </a>
            </li>
            <li>
                <a href="<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=orders<?php echo $baseQuery; ?>" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: list"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS'); ?></span></span>
                </a>
            </li>
            <li>
                <a href="<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=profile<?php echo $baseQuery; ?>" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: user"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE'); ?></span></span>
                </a>
            </li>
        </ul>
    </div>
</div>

<script>
function copyCodeLink(codeId, btn) {
    var input = document.getElementById('code-link-' + codeId);
    if (!input) return;
    
    navigator.clipboard.writeText(input.value).then(function() {
        if (window.Telegram && window.Telegram.WebApp) {
            window.Telegram.WebApp.HapticFeedback.notificationOccurred('success');
        }
        
        btn.classList.add('copied');
        var originalHtml = btn.innerHTML;
        btn.innerHTML = '<span uk-icon="icon: check; ratio: 0.8"></span> Скопировано';
        
        setTimeout(function() {
            btn.classList.remove('copied');
            btn.innerHTML = originalHtml;
        }, 2000);
    }).catch(function() {
        input.select();
        document.execCommand('copy');
        btn.classList.add('copied');
        setTimeout(function() { btn.classList.remove('copied'); }, 2000);
    });
}
</script>
