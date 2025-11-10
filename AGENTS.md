# Контекст для агента: Telegram‑бот для RadicalMart

Область действия: весь репозиторий. Этот файл содержит краткий контекст и ориентиры интеграции магазина RadicalMart (Joomla 5) с Telegram‑ботом, чтобы ускорить разработку и обсуждения.

## Структура нашего проекта (ВАЖНО!)

### Основной репозиторий
- **Рабочая копия**: `c:\Users\serge\PhpstormProjects\cacao.land\` — работающий сайт
- **Git репозиторий**: `c:\Users\serge\PhpstormProjects\cacao.land\github\RMTelegram\` — для коммитов
- **⚠️ ВАЖНО**: После изменений в рабочей копии ВСЕГДА копировать в репозиторий перед коммитом!

### Ключевые компоненты
- **Компонент бота**: `components/com_radicalmart_telegram/` (site + admin)
- **Системный плагин**: `plugins/system/radicalmart_telegram/` — создаёт альтернативное меню админки
- **Task плагин**: `plugins/task/radicalmart_telegram_sync/` — автообновление ПВЗ раз в неделю

### Альтернативное меню (плагин system)
**Файл**: `plugins/system/radicalmart_telegram/src/Extension/RadicalMartTelegram.php`

Плагин создаёт меню через событие `onRadicalMartPreprocessSubmenu`:
- Settings (view=settings) — управление вебхуком, информация о боте ← ДОБАВЛЕНО
- Status (view=status) — мониторинг загрузки ПВЗ
- Links (view=links) — одноразовые коды привязки
- Payments (view=payments) — история платежей
- Configuration — глобальные настройки компонента

**⚠️ Меню НЕ в манифесте компонента**, а создаётся динамически плагином!

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

## Пагинация и импорт ПВЗ (ApiShip) — реализовано в 0.1.59

Цель блока: надежная загрузка точек выдачи (ПВЗ) всех провайдеров ApiShip, особенно x5 с проблемной offset‑пагинацией.

### Новые endpoints (admin, task=api.*)
- `api.apishipfetchInit` — инициализация списка провайдеров (берёт totals через ApiShip; для x5 при наличии файла `apiship_x5.json` подменяет total на `rows_count`).
- `api.apishipfetchStep` — пошаговая загрузка чанков (500 по умолчанию), возвращает расширенный JSON.
- `api.apishipfetchJson` — полный префетч одного провайдера в JSON (строго по offset до достижения total или короткой страницы). Сохраняет `administrator/components/com_radicalmart_telegram/cache/apiship_<provider>.json`.
- `api.apishipjsonAnalyze` — анализ ранее сохранённого JSON: distinct IDs, диапазон, количество координат.
- `api.apishipdbCheck` — диагностирует состояние БД (distinct, дубли, пустые координаты, наличие индекса).
- `api.apishipimportFile` — массовый импорт из файла `apiship_<provider>.ndjson` или `apiship_<provider>.json` (bulk upsert).

### Импорт из файла
NDJSON файл для x5 (24 776 строк) можно положить в `administrator/components/com_radicalmart_telegram/cache/apiship_x5.ndjson`. Endpoint `apishipimportFile` выполнит потоковый батчевый upsert (1000/батч) с подсчётом inserted/updated. После импорта обновляется мета‑таблица.

### Улучшения пагинации (fetchPointsStep)
1. Гибрид offset → cursor: при больших offset и/или аномалиях (повторы страниц) включается keyset‑пагинация по `id`.
2. Повтор‑детектор страниц: хэш первых до 50 id — если для x5 повторилось ≥3 подряд в offset‑режиме, фиксируем аномалию `sweep-repeating-page` и переключаемся в cursor.
3. Asc‑probe: первый шаг курсора после повтора стартует с направлением `asc` (id > anchor). Поля:
   - `ascProbeAttempted`: true если проба была.
   - `ascProbeResult`: `ok` (получены строки), `flip-to-desc` (переключились на убывание из-за пустоты), `empty` (нет данных и направление ещё не сменилось).
4. Адаптивная обработка пустых чанков в cursor‑desc: динамическое уменьшение anchor (−1 / −10 / −100 / −1000) + «jump» для x5 (процентный). При истощении — переход в sweep или завершение.
5. Sweep‑фаза для x5: fallback на чистый offset со своим прогрессом (`sweepPhase='offset'`, `sweepOffsetCurrent`). Ограничение по числу подряд пустых чанков.
6. Dynamic total adjust: если при повтор‑аномалии доля distinct (по БД) < 0.2 — корректируем `remaining` (ограничиваем сверху оценкой по distinct). Возвращаем поля `adjustedTotal`, `dbDistinct`, `ratioDistinct`.

### Расширенный ответ `apishipfetchStep`
Возвращаемые ключи (новые выделены):
- `mode` (`offset`|`cursor`), `dir` (asc|desc), `cursorLastId`.
- `sweepPhase`, `sweepOffsetCurrent`.
- `sweepRepeatCount` (количество подряд повторов страницы).
- `ascProbeAttempted`, `ascProbeResult`.
- `adjustedTotal`, `dbDistinct`, `ratioDistinct`.
- `anomaly`, `anomalyReason`, `completed`, `completedReason`, `remaining`.

### UI (admin view Status)
- Добавлены кнопки на каждую строку провайдера:
  - Prefetch JSON
  - Analyze JSON
  - Import From File
  - Fetch Only This (однопровайдерский прогресс)
- Глобальные: Start, Cancel, DB Check, Prefetch x5.
- В Debug‑логе видны все диагностические поля.

### Рекомендации по применению для x5
1. Предпочтительно сначала `Prefetch JSON` → проверка структуры → `Import From File` (быстро и без рисков повторов).
2. Если нужен живой fetch: следить за `sweepRepeatCount`, `ascProbeResult` и `remaining`. При подозрительно высоком remaining после завершения — свериться через `DB Check`.

### Возможные дальнейшие шаги (после 0.1.59)
- Визуализировать ascProbe/повторы в прогресс‑карточках (бейджи).
- Хранить `adjusted_total` в meta для повторных запусков.
- Добавить авто‑рестарт cursor на «дырявых» диапазонах по эвристике пропусков id.

### Быстрые команды (пример)
Prefetch x5 JSON:
`POST index.php?option=com_radicalmart_telegram&task=api.apishipfetchJson&format=raw provider=x5`

Импорт x5 из файла после загрузки NDJSON:
`POST index.php?option=com_radicalmart_telegram&task=api.apishipimportFile&format=raw provider=x5`

Запуск пошаговой загрузки (глобально через UI): кнопка "Начать" или через JS вызовы `apishipfetchInit` + сериал шагов к `apishipfetchStep`.

### Диагностика типичных случаев
- Повторяющиеся чанки: см. `anomalyReason='sweep-repeating-page'`, растёт `sweepRepeatCount`, последующий переход в cursor.
- Пустой cursor asc: `ascProbeResult='flip-to-desc'` — нормальное поведение; последующие чанки пойдут в убывании.
- Недобор строк: сравнить `dbDistinct` и meta.total через `DB Check`; если ratio <0.2 и была корректировка — возможно нужен файл‑импорт.

Версия реализации: пакет `pkg_radicalmart_telegram-0.1.65.zip`.

### x5 курсорная пагинация НЕ РАБОТАЕТ (0.1.65): force offset-only

**Критическая находка**: ApiShip API для провайдера x5 **не поддерживает курсорную пагинацию** через фильтры `id<N` или `id>N`.

**Тест (PowerShell)**:
- Первый чанк `offset=0` вернул 500 точек с ID: `141169-143045`
- Курсор `id < 143045` → **0 записей** (50 попыток с шагами −10/−100/−1000 до ID 102315)
- Курсор `id > 141169` → **0 записей** (3 попытки с шагом +1000)
- **Вывод**: API возвращает данные ТОЛЬКО через offset-пагинацию

**Проблема в коде (до 0.1.65)**:
- При обнаружении повторов offset (hash `e105f5c7`) код устанавливал `$forceCursor=1` для x5
- Это включало курсор `id<N`, который **не работает** → зацикливание на пустых чанках
- `emptyCount` рос до 17+, anchor падал от 141090 до ~100000, но ничего не находилось

**Решение (0.1.65)**:
- **Полностью отключен курсор для x5**: `$useCursor = ... && ($provider !== 'x5')`
- При обнаружении повторов для x5 **не переключаемся на курсор**, сбрасываем `$forceCursor` в 0
- Debug: `"Provider x5: cursor disabled (API incompatible), using offset with cache buster"`
- Остаётся offset-пагинация с кэшбастером `&_cb={time}{rand}` (из 0.1.64)
- **0.1.66**: добавлены anti-cache заголовки (`Cache-Control`, `Pragma`, `Expires`, `X-Cache-Bypass`)

**Файлы изменены**:
- `ApiShipFetchHelper.php` line ~407-420: условие `$useCursor` исключает x5 полностью
- `ApiShipFetchHelper.php` line ~520-530: anti-cache заголовки для прямого HTTP x5
- При `$forceCursor=1` для x5 сбрасываем флаг и логируем "cursor disabled (API incompatible)"

**⚠ КРИТИЧЕСКОЕ ОГРАНИЧЕНИЕ (подтверждено 0.1.66)**:
- **Live fetch x5 НЕ РАБОТАЕТ на production сервере** из-за CDN кэша
- Кэшбастеры (`&_cb=`, `&_t=`) и anti-cache заголовки **НЕ ПОМОГАЮТ**
- CDN/proxy между сервером и ApiShip API игнорирует query params и cache headers
- Все offset возвращают hash `e105f5c7` (одни и те же 500 ID) независимо от параметров
- В БД загружается только 1003 уникальных точки вместо 24776

**Рекомендации после установки 0.1.65/0.1.66**:
1. **Для полной загрузки x5**: использовать **Prefetch JSON** → **Import From File** (NDJSON приоритет)
   - Prefetch использует offset с де-дупликацией (0.1.63)
   - Import батчами 1000 с upsert
   - **ОБЯЗАТЕЛЬНО для production**: live fetch на сервере зациклен из-за CDN кэша
2. **Live fetch x5**: работает ЛОКАЛЬНО, но **зациклен на сервере** (CDN проблема)
   - Кэшбастер `&_cb=` + anti-cache headers не помогают
   - Hash всегда `e105f5c7` (одни и те же 500 ID независимо от offset)
   - В БД только 1003 точки вместо 24776 из-за повторов
   - **Не рекомендуется** до решения проблемы CDN на хостинге
3. **Мониторинг**: debug logs должны показывать `chunkIdsHash` меняющийся в каждом шаге
   - Если hash повторяется — CDN/proxy кэширует ответы API

**CDN диагностика (локальный тест показал)**:
- Локально: все offset возвращают уникальные hash (c9adeff0, 1f6c43cc, 948044f0...)
- На сервере: все offset возвращают один hash (e105f5c7) независимо от `&_t=` параметра
- **Вывод**: между сервером и ApiShip API есть кэширующий слой (CloudFlare/CDN)
- **Решение**: bypass CDN для `/v1/lists/points` или использовать NDJSON импорт

**Тест скрипт**: `tools/test-x5-cursor.ps1` — воспроизводит проблему курсора локально

### Prefetch object→array bug (0.1.67): фикс де-дупликации

**Проблема**: Prefetch возвращал `distinct=0`, analyze показывал `distinctIds=0` даже при 25000 загруженных строк.

**Причина**: API возвращает строки как **объекты** (`stdClass`), а не массивы. В коде де-дупликации (0.1.63+) была проверка `if (is_array($r))`, которая пропускала все объекты → де-дупликация не выполнялась → в файл сохранялся пустой `dedupRows=[]`.

**Решение (0.1.67)**:
- `ApiController::apishipfetchJson` line ~245: добавлена конвертация `if (is_object($r)) { $r = get_object_vars($r); }`
- `ApiController::apishipjsonAnalyze` line ~428: добавлена такая же конвертация
- Теперь де-дупликация работает корректно даже с объектами API

**Файлы изменены**:
- `administrator/components/com_radicalmart_telegram/src/Controller/ApiController.php` (2 метода)

**После установки 0.1.67**:
- Prefetch x5 должен показать `rows=25000 distinct=500` (если CDN повторяет страницы) или `rows=24776 distinct=24776` (если CDN обойден локально)
- Analyze покажет корректное `distinctIds`
- Import сможет загрузить unique записи в БД

Версия реализации: пакет `pkg_radicalmart_telegram-0.1.65.zip`.

### x5 API зацикливание (0.1.64): кэшбастер и прямой HTTP

**Проблема**: на сервере live fetch x5 возвращал одни и те же 500 ID при любом offset (hash `e105f5c7`, repeatCount 30+). Локальный тест (PowerShell) показал 24776 уникальных записей — значит, проблема в состоянии API на сервере или кэшировании (CDN/proxy).

**Гипотезы**:
1. CDN/proxy кэширует ответ ApiShip API по URL без учета offset.
2. Старая версия плагина ApiShip без метода `getPointsRegistry()`.
3. Временная аномалия состояния API (server-side bug).

**Решение (0.1.64)**:
- **Кэшбастер**: для x5 добавляется параметр `&_cb={timestamp}{random}` в URL запроса, чтобы обойти CDN/proxy кэш.
- **Прямой HTTP для x5**: вместо вызова `ApiShipHelper::getPointsRegistry()` выполняется прямой `Http::get()` с кэшбастером. Fallback на стандартный метод при ошибке.
- Логика в `ApiShipFetchHelper::fetchPointsStep()` (lines ~515-540):
  ```php
  if ($provider === 'x5') {
      $cacheBuster = '&_cb=' . time() . mt_rand(1000,9999);
      $urlWithCache = $requestUrl . $cacheBuster;
      $response = $http->get($urlWithCache, $headers);
      $registry = new Registry($response->body);
      // fallback to ApiShipHelper if error
  }
  ```

**Дополнительные метрики**:
- Debug лог показывает `x5: direct HTTP GET with cache buster`, status, body length.
- Сохранена обратная совместимость для других провайдеров (используют стандартный `getPointsRegistry()`).

**Рекомендации**:
1. Установить 0.1.64 и протестировать live fetch x5.
2. Проверить debug logs: если hash чанков меняется и distinct ~500 в каждом — кэшбастер работает.
3. Если проблема сохраняется — проверить версию плагина ApiShip на сервере (должен иметь `getPointsRegistry()`).
4. Альтернатива: использовать NDJSON import (prefetch x5 + import from file) вместо live fetch.

Версия реализации: пакет `pkg_radicalmart_telegram-0.1.63.zip`.

### Диагностика префетча и импорта (>=0.1.61, обновлено 0.1.63)
Добавлены расширенные метрики и поля для надёжной форензики проблемных провайдеров (x5):

1. Prefetch (`api.apishipfetchJson`):
  - **De-duplication (0.1.63)**: сохраняет только уникальные записи (первое вхождение каждого `id`) в поле `rows`. Защита от временных аномалий API.
  - `distinct_ids` / `distinct_ratio` — фактическое количество уникальных id и отношение к общему числу строк (`rows_count`).
  - `top_repeat_ids` — первые повторяющиеся id (для оценки зацикливания).
  - `page_repeat_chain` — длина текущей последовательности повторяющихся страниц (по md5 первых 50 id). При `>=3` фиксируем повторный паттерн.
  - Ранний `break` при чрезмерных повторениях (`repeatChain>=10`).
  - Fallback `getPoints()` если отсутствует `getPointsRegistry()`.

2. Analyze (`api.apishipjsonAnalyze`):
  - Расширенный парс координат (`lat|lng|lon|location.{lat,lng,latitude,longitude}`).
  - Извлечение id из разных ключей (`id|extId|externalId|code`).
  - Возвращает `coords_total`, `ids_total`, `distinct_ids`.

3. Import (`api.apishipimportFile`):
  - **NDJSON приоритет (0.1.63)**: при автовыборе файла сначала проверяется `.ndjson`, затем `.json`.
  - Поддержка JSON и NDJSON.
  - Де-дупликация на уровне батча (пропуск повторов внутри файла) — поле `skippedFileDuplicates`.
  - Отчёт `inserted` vs `updated` с учётом уникального индекса `(provider, ext_id)`.
  - **Диагностика (0.1.63)**: `Absolute file path`, `File size`, `Detected file extension`, `Auto-selected file`.
  - Улучшенная диагностика ошибок путей (показывает все проверенные варианты).
  - Геометрия: `ST_GeomFromText('POINT(lon lat)',4326)`.

4. Fetch Step (`api.apishipfetchStep`):
  - **Chunk diagnostics (0.1.63)**: `distinctInChunk`, `chunkIdsHash` (первые 8 символов MD5 хэша первых 50 id).
  - Поля: `sweepRepeatCount`, `ascProbeAttempted`, `ascProbeResult`, `sweepPhase`, `sweepOffsetCurrent`, `ratioDistinct`, `dbDistinct`, `adjustedTotal`, `pageRepeatChain`.
  - Защита от преждевременного завершения sweep для x5 (`x5 safeguard`).
  - Адаптивные прыжки в `cursor-desc` при множестве пустых чанков и автопереход в sweep.

5. RepeatChain (0.1.62):
  - Интеграция `page_repeat_chain` в живой fetch (не только префетч): при `repeatChain>=3` для x5 выполняется ранняя активация sweep offset.
  - Доп. метрики в UI (планы): бейджи повторов и статус направления курсора.

6. **x5 API Stability (0.1.63 verification)**:
  - Тест загрузки 2025-11-09: **24776 строк, 24776 уникальных ID, 0 дубликатов**.
  - Offset-пагинация работает корректно при стабильном состоянии API.
  - Ранее наблюдаемые повторы (503 уникальных из 25000) были временной аномалией.
  - Де-дубликация в prefetch — страховка от edge cases.

Рекомендация для x5: сначала префетч JSON → анализ → импорт из файла; живой fetch запускать лишь для обновлений или верификации.

## Task плагин для автообновления ПВЗ (добавлен 2025-11-10)

**Файл**: `plugins/task/radicalmart_telegram_sync/`

**Назначение**: Автоматическая синхронизация пунктов выдачи заказов (ПВЗ) из ApiShip для всех активных провайдеров.

**Как работает**:
1. Читает список провайдеров из таблицы `#__radicalmart_telegram_pvz_meta` (где `enabled = 1`)
2. Для каждого провайдера вызывает `ApiShipFetchHelper::fetchPointsStep()` в цикле
3. Обновляет таблицу `#__radicalmart_telegram_pvz` через batch upsert
4. Логирует результаты в `administrator/logs/com_radicalmart.telegram.php`

**Настройка**:
- System → Manage → Scheduled Tasks → + New
- Тип задачи: "Синхронизация ПВЗ ApiShip"
- Рекомендуемое расписание: `0 0 * * 0` (каждое воскресенье в полночь)
- Или: Frequency = Custom, Interval = 7 days

**Файлы**:
- `src/Extension/RadicalMartTelegramSync.php` — основной класс
- `services/provider.php` — DI контейнер
- `language/ru-RU/*.ini` — языковые строки

**Метод**: `syncPvz()` — подписан на событие Task Scheduler.

**⚠️ Важно**: Плагин должен быть включён в Extensions → Plugins → Task - RadicalMart Telegram Sync.

4. Fetch Step (`api.apishipfetchStep`):
  - **Chunk diagnostics (0.1.63)**: `distinctInChunk`, `chunkIdsHash` (первые 8 символов MD5 хэша первых 50 id).
  - Поля: `sweepRepeatCount`, `ascProbeAttempted`, `ascProbeResult`, `sweepPhase`, `sweepOffsetCurrent`, `ratioDistinct`, `dbDistinct`, `adjustedTotal`, `pageRepeatChain`.
  - Защита от преждевременного завершения sweep для x5 (`x5 safeguard`).
  - Адаптивные прыжки в `cursor-desc` при множестве пустых чанков и автопереход в sweep.

5. RepeatChain (0.1.62):
  - Интеграция `page_repeat_chain` в живой fetch (не только префетч): при `repeatChain>=3` для x5 выполняется ранняя активация sweep offset.
  - Доп. метрики в UI (планы): бейджи повторов и статус направления курсора.

6. **x5 API Stability (0.1.63 verification)**:
  - Тест загрузки 2025-11-09: **24776 строк, 24776 уникальных ID, 0 дубликатов**.
  - Offset-пагинация работает корректно при стабильном состоянии API.
  - Ранее наблюдаемые повторы (503 уникальных из 25000) были временной аномалией.
  - Де-дубликация в prefetch — страховка от edge cases.

Рекомендация для x5: сначала префетч JSON → анализ → импорт из файла; живой fetch запускать лишь для обновлений или верификации.
