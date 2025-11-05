# Контекст для агента: Telegram‑бот для RadicalMart

Область действия: весь репозиторий. Этот файл содержит краткий контекст и ориентиры интеграции магазина RadicalMart (Joomla 5) с Telegram‑ботом, чтобы ускорить разработку и обсуждения.

## Что уже есть в проекте

- Магазин: `components/com_radicalmart` (каталог, корзина, оформление, оплата, роутер).
  - Контроллеры: `components/com_radicalmart/src/Controller` (Cart, Checkout, Payment и т. д.).
  - Модели: `components/com_radicalmart/src/Model` (CartModel, ProductsModel, CheckoutModel, PaymentModel и др.).
- Бонусы/промокоды/рефералы: `components/com_radicalmart_bonuses` + плагин `plugins/radicalmart/bonuses` (расширяет формы/суммы заказа и профиль).
- Доставка ApiShip: `plugins/radicalmart_shipping/apiship` (богатая интеграция, события `onRadicalMartGetOrderShipping*`, AJAX `onAjaxApiship`).
- Оплаты: `plugins/radicalmart_payment/*` (Tinkoff, CloudPayments, YooKassa и др.), маршрутизация оплаты через `PaymentController::pay`.
- Telegram‑сообщения: `plugins/radicalmart_message/telegram` — отправка уведомлений в чаты/каналы по событиям `radicalmart.order.*`.
- Авторизация по телефону: `components/com_j_sms_registration` — SMS‑верификация и регистрация по номеру.

Ключевые файлы для ориентирования:
- Checkout: `components/com_radicalmart/src/Controller/CheckoutController.php:1`, `components/com_radicalmart/src/Model/CheckoutModel.php:1`.
- Оплата: `components/com_radicalmart/src/Controller/PaymentController.php:1`.
- Бонусы: `plugins/radicalmart/bonuses/src/Extension/Bonuses.php:1`.
- ApiShip: `plugins/radicalmart_shipping/apiship/src/Extension/ApiShip.php:1`.
- Telegram сообщения: `plugins/radicalmart_message/telegram/src/Extension/Telegram.php:1`.

## Куда идём: компонент бота `com_radicalmart_telegram`

Цель: чат‑бот/мини‑приложение Telegram для просмотра каталога, управления корзиной, оформления заказа с доставкой и оплатой, с поддержкой промокодов/баллов и персональных уведомлений.

Основные элементы реализации:
- Site‑часть компонента: endpoint вебхука (`WebhookController`), обработчик апдейтов, построитель клавиатур/сообщений, сценарии диалога (browse → cart → checkout → payment).
- Admin‑часть: настройки (`bot_token`, `webhook_secret`, флаги фич), страницы привязки пользователей, мониторинг сессий/логов.
- База: `#__radicalmart_telegram_users` (привязка chat_id↔user_id), `#__radicalmart_telegram_sessions` (состояния), `#__radicalmart_telegram_links` (одноразовые коды).
- Интеграция с RadicalMart:
  - Получение каталога/товаров: `ProductsModel`.
  - Корзина и заказ: `CartModel` + `CheckoutModel::createOrder($data)`.
  - Оплата: ссылка на `PaymentController::pay` по `order_number` (универсально для всех платёжных плагинов).
  - Доставка ApiShip: расчет тарифов через события/хелперы плагина (адрес в формате `ApiShip::$defaultAddressFieldsParams`).
  - Бонусы/промокоды: плагин `radicalmart/bonuses` автоматически расширяет форму/суммы заказа — подтверждаем применимость через модель.
- Уведомления: можно переиспользовать `plg_radicalmart_message_telegram` (или отправлять напрямую при наличии `chat_id`).

Маршруты
- Webhook: `index.php?option=com_radicalmart_telegram:1&task=webhook.receive&secret=...`
- WebApp: `index.php?option=com_radicalmart_telegram:1&view=app` (временно `tmpl=component`; далее добавим отдельный `tmpl=tgwebapp`).

## Фиксация решений (из общения с заказчиком)
- Используем гибрид: Mini App (WebApp) + чат‑диалоги.
- Поддерживаем оплату:
  - Ссылка (через `PaymentController::pay`) для Yandex Pay и Tinkoff.
  - Внутри Telegram: Telegram Payments (карты) через YooKassa/Robokassa и Telegram Stars (курс из RUB + % конвертации в настройках).
- Полный функционал магазина в боте: каталог → карточки → корзина → выбор оплаты и доставки → выбор ПВЗ на карте → создание заказа в RadicalMart.
- Базовая авторизация по Telegram ID; при оформлении запрашиваем телефон (requestContact) и связываем с пользователем сайта (через `com_j_sms_registration`). При отсутствии телефона — SMS/звонок через sms.ru.
- Напоминания о брошенных корзинах через сообщения бота; добавляем настройки таймингов/повторов.
- «Онлайн‑режим» как опция для отдельных функций (уточняется в M0).

Особенности домена/инфраструктуры
- Домен `cacao.land`, WebApp должен быть разрешён у BotFather (allowed domains). Логи — средствами Joomla 5.

## Открытые вопросы (M0)
- Провайдер(ы) Telegram Payments и доступность по региону, получение `provider_token`.
- Политика использования Telegram Stars (перечень цифровых товаров, курс/стоимость, возвраты).
- Карта ПВЗ: подтверждение Yandex.Maps и ключи; переиспользование фронта ApiShip vs облегчённый UI.
- Доверие к номеру из Telegram contact vs обязательная SMS‑верификация.
- Сценарии и частота напоминаний о брошенной корзине; согласие пользователя.
- Перечень функций «онлайн‑режима» на первый этап.
- Требования к фильтрации/пагинации каталога и UX выбора вариаций.

## Бизнес‑ограничения и особенности каталога
- Мета‑товары: содержат варианты с разным весом. Для уведомлений «появился в наличии» отправлять ссылку на вариант с минимальным весом внутри одного мета‑товара, чтобы избежать дубликатов.
- Товары «нет в наличии» маркировать и в списках опускать в конец.
- Единственная опция выбора — «вес» (отразить в интерфейсе бота/веб‑приложения).

## ПВЗ и карта
- Используем Yandex.Maps. Подгрузка ПВЗ по текущим границам карты (bounding box), чтобы не грузить все точки сразу.
- При необходимости дорабатываем `plg_radicalmart_shipping_apiship` (endpoint/события) для выборки ПВЗ по bbox.

## Поддержка/чат
- Inline‑режим: режим общения в духе саппорта. Требуется продумать маршрутизацию сообщений (оператор/CRM) и права доступа.

## Доп. фиксация по оплатам (M0 — подтверждено)
- Telegram Payments: провайдеры YooKassa и Robokassa; разные `provider_token` для тест/прод; поддерживаем возвраты и частичные возвраты.
- Telegram Stars: применимо для всех товаров (с возможностью ограничений по категориям/товарам), курс из RUB + % конвертации.
- Приоритет и применимость способов оплаты задаются в настройках (глобально/по категориям/по товарам).

## Авторизация и соответствие
- При совпадении телефона — автоматический логин и связь `chat_id`↔`user_id`.
- Политика 1:1: один Telegram ID на один номер. Нужна обработка конфликтов и инструменты разлинковки в админке.

## Архитектура реализации (рекомендации)
- Компонент `com_radicalmart_telegram` с отдельными view для WebApp (минимальный шаблон `tmpl=tgwebapp`/`tmpl=component`) и endpoint вебхука.
- Плагины (по необходимости):
  - `plg_radicalmart_telegrammap` — вспомогательные endpoint’ы/интеграция карты/ПВЗ.
  - Расширения оплат (адаптеры Telegram Payments/Stars) — как отдельные плагины.
- Плюсы подхода: единый стек Joomla (ACL, логи, модели RadicalMart), отсутствие лишней инфраструктуры.
- Риски: изоляция ассетов WebApp и управление кэшем/CSRF/проверкой `initData` (Telegram WebApp).

## Минимальный пользовательский сценарий (MVP)
1) Пользователь пишет боту `/start` → меню.
2) Просмотр категорий → карточка товара → «В корзину».
3) Корзина → «Оформить» → ввод контактов → адрес/доставка (ApiShip) → промокод/баллы.
4) Создание заказа → получение ссылки на оплату → переход на страницу оплаты.
5) Смена статуса заказа → уведомление в Telegram.

## Важные соображения
- Авторизация: гость → одноразовый код на сайте для привязки (сохранить `chat_id`), дальше — персональные уведомления и автозаполнение.
- Оплаты в Telegram: предпочтительно внешний редирект (`PaymentController::pay`). Telegram Payments — опционально.
- Локация и адрес: запрос контакта/геопозиции, нормализация (DaData при наличии ключа), расчёт тарифов в ApiShip.
- Безопасность: `webhook_secret`, rate‑limit по `chat_id`, идемпотентность обработки апдейтов, логирование.

## Что сделать прямо сейчас
- Подтвердить решения (см. `todo.md: М0`).
- Стартовать `com_radicalmart_telegram` (каркас, вебхук, админ‑настройки).
- Поднять базу для пользователей/сессий и связок.

Дополнительно: подробная дорожная карта — в `todo.md:1`.
