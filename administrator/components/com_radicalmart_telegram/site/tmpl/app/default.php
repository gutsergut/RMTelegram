<?php
/**
 * Minimal WebApp container for Telegram
 */
\defined('_JEXEC') or die;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars((isset($this->params)?$this->params->get('store_title','магазин Cacao.Land'):'магазин Cacao.Land'), ENT_QUOTES, 'UTF-8'); ?></title>
    <script>
        (function(){ try{ if(window.Telegram && window.Telegram.WebApp){ Telegram.WebApp.ready(); Telegram.WebApp.expand(); } }catch(e){} })();
    </script>
    <style>
        html, body { height: 100%; margin: 0; padding: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, Arial, sans-serif; }
        .app { min-height: 100%; display: flex; align-items: center; justify-content: center; }
        .box { text-align: center; padding: 24px; }
        .muted { color: #666; font-size: 14px; }
    </style>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
</head>
<body>
<div class="app">
    <div class="box">
        <h1><?php echo htmlspecialchars((isset($this->params)?$this->params->get('store_title','магазин Cacao.Land'):'магазин Cacao.Land'), ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="muted">WebApp установлен. Каталог/корзина будут подключены через API.</p>
    </div>
</div>
</body>

