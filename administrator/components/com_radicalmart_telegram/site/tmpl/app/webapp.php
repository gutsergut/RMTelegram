<?php
/**
 * Telegram WebApp layout (UIkit + YOOtheme styles)
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;

$root = rtrim(Uri::root(), '/');
$storeTitle = isset($this->params) ? (string) $this->params->get('store_title', 'магазин Cacao.Land') : 'магазин Cacao.Land';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($storeTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo $root; ?>/templates/yootheme/css/theme.css">
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js"></script>
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit-icons.min.js"></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        html, body { height: 100%; }
        body { background: var(--tg-theme-bg-color, #fff); color: var(--tg-theme-text-color, #222); }
        .tg-safe-text { color: var(--tg-theme-text-color, inherit); }
        .uk-card { background: var(--tg-theme-bg-color, #fff); }
        .uk-navbar-container { background: var(--tg-theme-bg-color, #fff); }
        .uk-button-primary { background-color: var(--tg-theme-button-color, #1e87f0); color: var(--tg-theme-button-text-color, #fff); }
        .uk-section { padding-top: 16px; padding-bottom: 16px; }
    </style>
    <script>
        (function(){ try{ if(window.Telegram && window.Telegram.WebApp){ const tg=window.Telegram.WebApp; tg.ready(); tg.expand(); tg.BackButton.hide(); } }catch(e){} })();
        function qs(name){ const p=new URLSearchParams(location.search); return p.get(name); }
        async function api(method, params={}){
            const url = new URL(location.origin + '/index.php');
            url.searchParams.set('option','com_radicalmart_telegram');
            url.searchParams.set('task','api.'+method);
            const chat = qs('chat'); if (chat) url.searchParams.set('chat', chat);
            for (const [k,v] of Object.entries(params)) url.searchParams.set(k, v);
            const res = await fetch(url.toString(), { credentials: 'same-origin' });
            const json = await res.json();
            if (json && json.success === false) throw new Error(json.message||'API error');
            return json.data || {};
        }
        function el(html){ const t=document.createElement('template'); t.innerHTML=html.trim(); return t.content.firstChild; }
        async function loadCatalog(){
            try {
                const { items } = await api('list', { limit: 12 });
                const root = document.getElementById('catalog-list');
                root.innerHTML = '';
                (items||[]).forEach(p => {
                    const card = el(`
                        <div>
                          <div class=\"uk-card uk-card-default uk-card-small\">
                            <div class=\"uk-card-media-top\">
                              <div class=\"uk-height-small uk-flex uk-flex-middle uk-flex-center uk-background-muted\">Изображение</div>
                            </div>
                            <div class=\"uk-card-body\">
                              <div class=\"uk-text-small uk-text-muted\">&nbsp;</div>
                              <h5 class=\"uk-margin-remove\">${p.title || 'Товар'}</h5>
                              <div class=\"uk-margin-small tg-safe-text\"><strong>${p.price_final||''}</strong></div>
                              <div class=\"uk-flex uk-flex-between\">
                                <button class=\"uk-button uk-button-primary\" data-action=\"add\" data-id=\"${p.id}\">В корзину</button>
                                <button class=\"uk-button uk-button-default\" disabled>Подробнее</button>
                              </div>
                            </div>
                          </div>
                        </div>`);
                    root.appendChild(card);
                });
            } catch(e) { UIkit.notification(e.message, {status:'danger'}); }
        }
        async function refreshCart(){
            try { const { cart } = await api('cart'); const box = document.getElementById('cart-box'); if (!cart || !cart.products) { box.innerHTML = '<p class="uk-margin-remove">Корзина пуста.</p>'; return; } let html = '<ul class="uk-list">'; let i=1; for (const key in cart.products) { const p = cart.products[key]; const title = p.title || 'Товар'; const qty = (p.order && (p.order.quantity_string_short||p.order.quantity)) || '1'; const sum = (p.order && p.order.sum_final_string) || ''; html += `<li>${i++}. ${title} × ${qty} ${sum?('— '+sum):''}</li>`; } html += '</ul>'; if (cart.total && cart.total.final_string) html += `<p><strong>Итого: ${cart.total.final_string}</strong></p>`; box.innerHTML = html; } catch(e) {}
        }
        document.addEventListener('click', async (ev) => { const btn = ev.target.closest('[data-action="add"][data-id]'); if (!btn) return; ev.preventDefault(); btn.disabled = true; try { await api('add', { id: btn.dataset.id, qty: 1 }); await refreshCart(); UIkit.notification('Добавлено в корзину'); } catch(e){ UIkit.notification(e.message, {status:'danger'}); } finally { btn.disabled = false; } });
        document.addEventListener('DOMContentLoaded', () => { loadCatalog(); refreshCart(); });
    </script>
</head>
<body>

<nav class="uk-navbar-container" uk-navbar>
    <div class="uk-navbar-left">
        <a class="uk-navbar-item uk-logo tg-safe-text" href="#"><?php echo htmlspecialchars($storeTitle, ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="uk-navbar-right">
        <ul class="uk-navbar-nav">
            <li><a href="#catalog">Каталог</a></li>
            <li><a href="#cart">Корзина</a></li>
            <li><a href="#checkout">Оформление</a></li>
        </ul>
    </div>
</nav>

<div class="uk-section uk-section-default">
    <div class="uk-container">
        <div class="uk-grid-small" uk-grid>
            <div class="uk-width-1-1">
                <h3 id="catalog" class="tg-safe-text">Каталог</h3>
                <div class="uk-child-width-1-2 uk-child-width-1-3@s" uk-grid id="catalog-list"></div>
            </div>
            <div class="uk-width-1-1">
                <h3 id="cart" class="tg-safe-text">Корзина</h3>
                <div id="cart-box" class="uk-card uk-card-default uk-card-body">
                    <p class="uk-margin-remove">Корзина пуста.</p>
                </div>
            </div>
            <div class="uk-width-1-1">
                <h3 id="checkout" class="tg-safe-text">Оформление</h3>
                <div class="uk-card uk-card-default uk-card-body">
                    <p class="uk-margin-remove">Перейдите к оформлению в приложении.</p>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>

