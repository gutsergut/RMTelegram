# Dev Notes — com_radicalmart_telegram

Контекст и ключевые решения (для команды разработки)

- DB/Геоданные
  - MariaDB: 10.5.29 — используем InnoDB SPATIAL с POINT SRID 4326
  - Таблица ПВЗ: `#__radicalmart_apiship_points` (SPATIAL index `point`), мета: `#__radicalmart_apiship_meta`
  - BBox отбор: MBRWithin(point, ST_GeomFromText(Polygon, 4326)) — задействует R-tree

- ApiShip
  - Ключ: 4446add5de10615e33d5834f0c931925 (в настройках компонента — для dev)
  - Провайдеры (минимум): Яндекс.Доставка(yataxi), СДЭК(cdek), 5Post(x5)
  - Инкремента нет — делаем полный фетч еженедельно (ночью)
  - Ручной фетч: кнопка в настройках компонента; CLI команда: `com_radicalmart_telegram:apiship:fetch`

- Бот/WebApp
  - Отдача ПВЗ — только по текущему bbox (api.pvz), лимит ~1000
  - Промокоды/Баллы — API (`promocode`, `points`, `summary`, `bonuses`), UI в tgwebapp
  - Локальные ассеты: UIkit (YOOtheme), IMask (WebAsset), Telegram SDK внешне
- Платежи: Telegram Cards/Stars вынесены в плагины RadicalMart (telegramcards/telegramstars). Cards шлёт инвойс (currency RUB), Stars — XTR (звёзды) по `rub_per_star` + наценка.
  - API WebApp: `api.invoice` — повторная отправка инвойса в Telegram по номеру заказа (для методов оплаты Telegram). Используется кнопка «Отправить счёт ещё раз» в tgwebapp.
  - Telegram Stars (plugin params): `rub_per_star` (RUB за 1 XTR), `conversion_percent` (наценка %), ограничения `allowed_categories/products`, `excluded_categories/products`.
- Возвраты: админ-инструмент (view=payments) помечает возврат/частичный возврат (смена статуса + лог). Вызов провайдерского API для Refund будет добавлен в плагин Cards позже (с параметрами auth).
- Refund API (MVP): плагин telegramcards имеет хук onRadicalMartPaymentRefund(order, amount) и пытается выполнить:
  - YooKassa: POST /v3/refunds (Basic Auth shopId:secretKey), опираясь на provider_payment_charge_id из лога successful_payment
  - Robokassa: базовая реализация по Refund API: POST/GET на rk_refund_url с полями MerchantLogin/InvoiceID/Amount/SignatureValue (MD5 upper of MerchantLogin:InvoiceID:Amount:Password2). Документация: https://docs.robokassa.ru/refund-api/
  - В любом случае пишет лог в заказе с результатом
 - Stars: ограничения по категориям/товарам (allowed/excluded), конверсия из RUB по параметрам плагина. Возвраты (refund) не поддерживаются — плагин возвращает отрицательный результат на onRadicalMartPaymentRefund.

- Логи
  - Канал: `com_radicalmart.telegram`; настройка `logs_enabled` включена по умолчанию

- TODO кратко
  - Внедрить Yandex.Maps в tgwebapp, выбор ПВЗ
  - Scheduler: задача «ApiShip Fetch» (плагин task) раз в неделю
  - Подключить RU site‑языки в манифест при упаковке package

Служебные пути
- Выгрузка ПВЗ: `index.php?option=com_radicalmart_telegram&task=api.apishipfetch&providers=yataxi,cdek,x5`
- bbox выдача: `index.php?option=com_radicalmart_telegram&task=api.pvz&bbox=lon1,lat1,lon2,lat2&providers=yataxi,cdek,x5&limit=1000`
- повторная отправка счёта: `index.php?option=com_radicalmart_telegram&task=api.invoice&chat=...&number=...`
- CLI: `php cli/joomla.php com_radicalmart_telegram:apiship:fetch --providers=yataxi,cdek,x5`

Примечание по SQL миграциям
- Файл `install.mysql.utf8.sql` содержит все нужные таблицы для dev. Не удаляем до развёртывания на сайте.

- Rate-limit (DB):
  - Таблица: #__radicalmart_telegram_ratelimits (scope, rkey, window_start, count)
  - Окно: 60 секунд (округление до минуты), insert on duplicate update
  - Ключ: tg_user.id из initData (если проверен) → chat_id → IP
- Nonce (идемпотентность)
  - Таблица: #__radicalmart_telegram_nonces (chat_id, scope, nonce, created, uniq(chat_id,scope,nonce))
  - Клиент: WebApp добавляет nonce ко всем мутациионным вызовам
  - Сервер: guardNonce(scope) — требование можно включить флагом strict_nonce
