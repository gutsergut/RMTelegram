# Changelog - com_radicalmart_telegram

Все значимые изменения в этом проекте документируются в этом файле.

Формат основан на [Keep a Changelog](https://keepachangelog.com/ru/1.0.0/).

## [5.0.4] - 2025-11-11

### Fixed / Improved

#### Task плагин radicalmart_telegram_fetch
- Добавлены недостающие языковые ключи в sys.ini: `PLG_TASK_RADICALMART_TELEGRAM_FETCH_TITLE`, `PLG_TASK_RADICALMART_TELEGRAM_FETCH_DESC` (ru/en) — теперь заголовок рутины корректно отображается в планировщике.
- Расширен `TASKS_MAP` для обратной совместимости со старым `routineId` (`plg_task_radicalmart_telegram_fetch_apiship`) — предотвращает «молчаливый пропуск» задачи у инсталляций, где в БД осталось старое значение.
- Добавлено раннее логирование запуска рутины: `Routine start: {routineId}` для форензики.
- Усилен блок upsert: try/catch и логирование ошибок БД (`Upsert failed provider=...`).
- Принудительная загрузка языков (`$this->loadLanguage()`) перед логированием — защита при нестандартных последовательностях инициализации.

### Рекомендации по деплою
1. Переустановить только плагин или пакет ≥5.0.4.
2. Проверить в `administrator/logs/com_radicalmart.telegram.php` наличие строки `Routine start:` после ручного запуска.
3. Если в БД у задач всё ещё старый `type`, выполнить: `UPDATE #__scheduler_tasks SET type='radicalmart_telegram.fetch' WHERE type='plg_task_radicalmart_telegram_fetch_apiship';`

### Files
- `plugins/task/radicalmart_telegram_fetch/src/Extension/RadicalMartTelegramFetch.php`
- `plugins/task/radicalmart_telegram_fetch/language/ru-RU/plg_task_radicalmart_telegram_fetch.sys.ini`
- `plugins/task/radicalmart_telegram_fetch/language/en-GB/plg_task_radicalmart_telegram_fetch.sys.ini`

## [5.0.3] - 2025-11-11

### Fixed (Исправлено)

#### Task плагин radicalmart_telegram_fetch - КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ
- **Routine ID исправлен**: изменён с `plg_task_radicalmart_telegram_fetch_apiship` на `radicalmart_telegram.fetch`
  - **ПРИЧИНА ПРОБЛЕМЫ**: неправильный format routine ID приводил к тому, что TaskPluginTrait не мог найти языковые константы
  - **СИМПТОМЫ**: константа `PLG_TASK_RADICALMART_TELEGRAM_FETCH_TITLE` отображалась вместо текста, задача не выполнялась
  - **РЕШЕНИЕ**: используем короткий формат как в плагине `radicalmart_telegram_sync`: `{component}.{action}`
- **Файлы**:
  - `plugins/task/radicalmart_telegram_fetch/src/Extension/RadicalMartTelegramFetch.php` - исправлен TASKS_MAP

### Technical Details
- Правильный формат routine ID: `{namespace}.{action}` (например: `radicalmart_telegram.fetch`)
- НЕправильный формат: `plg_task_{plugin}_{action}` (слишком длинный, не распознаётся)
- TaskPluginTrait ищет константы по паттерну: `{langConstPrefix}_TITLE`

## [5.0.2] - 2025-11-11

### Fixed (Исправлено)

#### Task плагин radicalmart_telegram_fetch
- **Namespace исправлен**: изменён с `Radicalmart_telegram_fetch` на `RadicalmartTelegramFetch` (правильный CamelCase для Joomla 5)
  - ⚠️ **КРИТИЧНО**: исправлен в 3 файлах (XML, Extension.php, services/provider.php)
  - Без исправления services/provider.php возникает ошибка "Class not found"
- **Логирование добавлено**:
  - Инициализация Log для категории `com_radicalmart.telegram`
  - Логи старта задачи с указанием провайдеров
  - Логи для каждого провайдера (total, chunk size, offset)
  - Логи обновления meta-таблицы
  - Логи ошибок при отсутствии токена/провайдеров
- **Диагностика**: добавлены детальные логи на каждом этапе загрузки ПВЗ для отладки
- **Файлы**:
  - `plugins/task/radicalmart_telegram_fetch/radicalmart_telegram_fetch.xml` - исправлен namespace
  - `plugins/task/radicalmart_telegram_fetch/src/Extension/RadicalMartTelegramFetch.php` - namespace + логирование
  - `plugins/task/radicalmart_telegram_fetch/services/provider.php` - исправлен use statement

### Technical Details
- Логи пишутся в `administrator/logs/com_radicalmart.telegram.php`
- Уровни логирования: INFO (процесс), ERROR (ошибки)
- Формат сообщений: `Provider {name}: {action} {details}`

## [5.0.1] - 2025-11-10

### Added (Добавлено)

#### Юридические документы
- **Настройки компонента**: новая секция "Legal Documents" с полями для выбора статей Joomla
  - Политика конфиденциальности (`article_privacy_policy`)
  - Согласие на обработку ПД (`article_consent_personal_data`)
  - Пользовательское соглашение (`article_terms_of_service`)
  - Согласие на рассылки (`article_consent_marketing`)
- **Тип поля**: `modal_article` (как в плагине privacyconsent)
  - Кнопка "Select" для открытия модального окна
  - Кнопка "New" для создания новой статьи
  - Кнопка "Edit" для редактирования выбранной статьи
  - Кнопка "Clear" для очистки выбора
- **ConsentHelper**: методы `getDocumentUrl($type)` и `getAllDocumentUrls()` для получения URL статей
- **Языковые константы**: переводы полей настроек (ru-RU, en-GB)
- **Документация**: `/docs/legal-documents-setup.md` - инструкция по настройке

#### Юридическое соответствие (ФЗ-152, ФЗ-38)
- Созданы 4 шаблона документов с актуальными реквизитами ИП Гутников С.В.:
  - `/docs/privacy-policy.md` - Политика конфиденциальности
  - `/docs/consent-personal-data.md` - Согласие на обработку ПД
  - `/docs/terms-of-service.md` - Пользовательское соглашение
  - `/docs/consent-marketing.md` - Согласие на рассылки

### Changed (Изменено)

#### Логика запроса согласия
- **UpdateHandler**: URL согласия берётся из настроек компонента (через `ConsentHelper::getDocumentUrl('consent')`)
- Fallback на языковую константу `COM_RADICALMART_TELEGRAM_CONSENT_URL` если статья не настроена
- Добавлен namespace для `ConsentHelper` в `UpdateHandler`

#### Документы
- Обновлены все плейсхолдеры `[ИМЯ ИП]`, `[НОМЕР]`, `[АДРЕС]`, `[EMAIL]`, `[ТЕЛЕФОН]`:
  - ИП: Гутников Сергей Викторович
  - ОГРН: 313643914700015
  - ИНН: 643905793610
  - Адрес: 413859, Саратовская область, г. Балаково, ул. Каховская, д 9, кв 29
  - Email: support@cacao.land
  - Telegram: @CacaoLandBot (телефон не указывается)

### Technical Details (Технические детали)

#### Config.xml
- Добавлен fieldset `legal` с 4 полями типа `contentarticle`
- Поля позволяют выбрать статью Joomla через стандартный selector

#### ConsentHelper.php
- Импорт: `ComponentHelper`, `Route`
- Метод `getDocumentUrl(string $type): string`
  - Маппинг типов: `privacy`, `consent`, `terms`, `marketing`
  - Возвращает полный URL через `Route::link()`
  - Возвращает пустую строку если статья не настроена
- Метод `getAllDocumentUrls(): array`
  - Возвращает ассоциативный массив всех URL

#### Обратная совместимость
- Сохранён fallback на языковые константы для старых установок
- Миграция не требуется (добавление новых полей в params)

### Migration Notes (Заметки по миграции)

Для существующих установок:
1. Создайте 4 статьи в Joomla с содержимым из `/docs/`
2. Настройте пункты меню с нужными URL alias
3. Выберите статьи в **Components → Telegram Магазин → Options → Legal Documents**
4. Проверьте работу через команду `/start` в боте

Подробная инструкция: `/docs/legal-documents-setup.md`

---

## [5.0.0] - 2025-11-09

### Added (Добавлено)

#### Система согласий (GDPR/ФЗ-152)
- **База данных**: поля `consent_personal_data`, `consent_marketing`, `consent_terms` + timestamps
- **Миграция**: SQL update `5.0.1.sql` для переименования старых полей
- **Логика бота**: проверка согласия на ПД перед запросом телефона в `/start`
- **Callback handler**: `consent_accept` для сохранения согласия с timestamp
- **ConsentHelper**: класс для управления согласиями (`hasPersonalDataConsent`, `hasMarketingConsent`, `saveConsent`, `getConsents`)
- **Языковые константы**: тексты для запроса согласия и подтверждения

#### Webhook и базовая функциональность
- **WebhookController**: обработка входящих обновлений от Telegram
- **SessionStore**: хранение состояний пользователей
- **UpdateHandler**: обработка команд и callback-запросов
- **TelegramClient**: обёртка для Telegram Bot API

### Fixed (Исправлено)

#### Webhook authentication
- **Проблема**: "Expected secret: EMPTY" несмотря на сохранённый webhook_secret
- **Решение**: приоритет `ComponentHelper::getParams()` в `WebhookController::getParams()`
- **Файл**: `components/com_radicalmart_telegram/src/Controller/WebhookController.php:24`

#### Database schema
- **Проблема**: "Unknown column 'updated' in 'field list'"
- **Решение**: переименование поля `updated` → `updated_at` в 5 местах `SessionStore.php`
- **Файлы**: `components/com_radicalmart_telegram/src/Service/SessionStore.php` (SELECT, 2×UPDATE, 2×INSERT)

### Changed (Изменено)

#### UX улучшения
- **Запрос телефона**: добавлено объяснение зачем нужен телефон
- **Текст кнопки**: "Отправить телефон" → "📱 Поделиться номером телефона"
- **Подтверждение**: добавлены эмодзи и упоминание бонусов

---

## [Unreleased] - Планируемые функции

### WebApp consent modal
- Проверка согласий при первом открытии WebApp
- Модальное окно с чекбоксами и ссылками на политики
- Блокировка функционала до получения согласия

### AcyMailing интеграция
- Синхронизация `consent_marketing` с подпиской в AcyMailing
- Двусторонняя синхронизация (webhook AcyMailing → обновление БД)
- Настройка ID листа AcyMailing в конфиге

### Функционал рассылок
- Админка: создание рассылки (текст, кнопки, медиа)
- Фильтры получателей (все/согласившиеся/с покупками/регион)
- Планировщик отправки (сразу/по расписанию)
- Статистика: доставлено/прочитано/кликнуто
- Персонализация: {name}, {bonus_balance}
- Rate limiting для массовых рассылок

### Команды управления
- `/settings` - просмотр и изменение согласий
- `/revoke_consent` - отзыв согласия на ПД
- `/revoke_marketing` - отписка от рассылок
- `/my_data` - запрос копии персональных данных (GDPR)

---

**Формат версий**: MAJOR.MINOR.PATCH
- MAJOR: несовместимые изменения API
- MINOR: новая функциональность с обратной совместимостью
- PATCH: исправления ошибок
