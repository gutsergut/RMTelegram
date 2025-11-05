# Пакет установщика: содержимое и структура

Цель: фиксируем, что должно войти в итоговый пакет (Joomla 5 «package»), чтобы собрать и поставить компонент бота и связанные части одним архивом.

## Состав пакета (package)
- package manifest: `pkg_radicalmart_telegram.xml`
- Компонент (site + admin): `com_radicalmart_telegram`
  - admin: `administrator/components/com_radicalmart_telegram/`
    - `radicalmart_telegram.xml` (манифест компонента)
    - `config.xml`
    - `language/*` (ru-RU, en-GB)
    - `services/provider.php`
    - `src/**`
    - SQL: `sql/install.sql`, `sql/updates/mysql/*.sql`, `sql/uninstall.sql` (создать/проверить)
  - site: `components/com_radicalmart_telegram/`
    - `src/**` (Controllers, Services, Views)
    - `tmpl/**` (layouts: `default.php`, `tgwebapp.php`, `webapp.php`)
    - `language/ru-RU/com_radicalmart_telegram.ini` (site RU)
- Медиа ассеты компонента: `media/com_radicalmart_telegram/`
  - `joomla.asset.json`
  - `vendor/imask/imask.min.js` (локальная IMask)
  - `apiship/*.json` (бэкап выгрузки ПВЗ по провайдерам), `apiship/cache/*.json` (кеш bbox‑ответов)
- Плагин (системный): `plg_system_radicalmart_telegram`
  - `plugins/system/radicalmart_telegram/`
    - `radicalmart_telegram.xml` (манифест)
    - `services/provider.php`
    - `src/Extension/RadicalMartTelegram.php`
    - `language/ru-RU/plg_system_radicalmart_telegram.ini`
- Плагины оплат (новые):
  - `plugins/radicalmart_payment/telegramcards` — Telegram Payments (карты), инвойс через бота
  - `plugins/radicalmart_payment/telegramstars` — Telegram Stars (будет реализовано)
  - Языки RU: `plugins/radicalmart_payment/telegramcards/language/ru-RU/plg_radicalmart_payment_telegramcards.ini`, `plugins/radicalmart_payment/telegramstars/language/ru-RU/plg_radicalmart_payment_telegramstars.ini`

## Установка пакета
- Установить `pkg_radicalmart_telegram.xml` (архив со структурой директорий из репозитория) через Extensions → Install
- Включить плагины: System - RadicalMart Telegram, Task - RadicalMart Telegram Fetch, RadicalMart Payment - Telegram Cards/Stars

## Что проверить/добавить перед сборкой
- [ ] SQL компонента: создать таблицы
  - `#__radicalmart_telegram_users`
  - `#__radicalmart_telegram_sessions`
  - `#__radicalmart_telegram_links`
  - `#__radicalmart_apiship_points`
  - `#__radicalmart_apiship_meta`
- [ ] Языки site‑части компонента (ru-RU, en-GB), если будут строки в интерфейсе.
- [ ] Скрипт установки компонента (опционально): `script.php`
  - валидация `webapp_allowed_domains`, первичная инициализация настроек.
- [ ] Совместимость шаблона WebApp с отсутствием YOOtheme (минимальные стили, fallback).
- [ ] Настройки кеша ПВЗ: включение, TTL и точность bbox (по умолчанию включено, TTL=60 c, точность=2 знака).
- [ ] Документация: README с шагами настройки webhook и параметров компонента.

## Исключить из пакета
- Логи, кэши, резервные копии: `administrator/components/com_akeebabackup/backup/**`, `templates/yootheme/cache/**` и т. п.
- Файлы окружения и локальные конфиги.

## Постустановочные действия
- Включить плагин `System - RadicalMart Telegram`.
- Заполнить настройки компонента:
  - `bot_token`, `webhook_secret`, `store_title`, `webapp_allowed_domains`.
  - `apiship_api_key`, `apiship_providers` (например: `yataxi,cdek,x5`).
- Настроить метод доставки по умолчанию в RadicalMart.
- Установить webhook у бота на URL:
  - `index.php?option=com_radicalmart_telegram&task=webhook.receive&secret=...`

## Служебные эндпоинты (dev)
- Выгрузка ПВЗ ApiShip: `index.php?option=com_radicalmart_telegram&task=api.apishipfetch&providers=yataxi,cdek,x5`
- Выдача ПВЗ по bbox: `index.php?option=com_radicalmart_telegram&task=api.pvz&bbox=lon1,lat1,lon2,lat2&providers=yataxi,cdek,x5&limit=1000`

## Примечания по сборке
- Рекомендуем собрать «package»‑расширение (тип `package`) со включением компонента, плагина и медиа ассетов.
- Структуру архива держать такой же, как пути выше (корнем архива — директории `administrator/`, `components/`, `plugins/`, `media/` + файл `pkg_radicalmart_telegram.xml`).
- Для разработки можно установить отдельно компонент и плагин через «Discover», но для продакшена — единый пакет.

## Сборка пакета (скрипт)
- Скрипт: `tools/build.ps1`
- Требования: Windows PowerShell 5.1+ или PowerShell 7+, желательно установлен `7z.exe` (7‑Zip).
- Пример запуска:
  - PowerShell: `powershell -NoProfile -File tools\build.ps1 -Version 0.1.0`
  - Результат: архив в `dist/pkg_radicalmart_telegram-0.1.0.zip`
- Что делает скрипт:
  - Создаёт staging `build/package` и копирует:
    - `administrator/components/com_radicalmart_telegram` (admin‑часть компонента)
    - Полную site‑часть из `components/com_radicalmart_telegram` в `administrator/components/com_radicalmart_telegram/site`
    - Плагины: system, task, radicalmart_payment/telegramcards, radicalmart_payment/telegramstars
    - Медиа: `media/com_radicalmart_telegram/**`
    - Манифест пакета и языки пакета
  - Архивирует в ZIP (7‑Zip, иначе Compress‑Archive)

## Консольные команды
- `com_radicalmart_telegram:apiship:fetch` — полная выгрузка ПВЗ из ApiShip.
- `com_radicalmart_telegram:housekeep` — очистка просроченных nonces (старше 24ч) и ratelimits (старше 48ч).
