# RadicalMart Telegram Suite

RadicalMart Telegram Suite — набор расширений для Joomla 5, который добавляет мини-приложение и чат-бот Telegram к интернет-магазину RadicalMart. Пакет включает компонент `com_radicalmart_telegram`, системные и платёжные плагины, задачу планировщика и медиаресурсы.

## Возможности
- Telegram WebApp с каталогом, корзиной и оформлением заказа.
- Вебхук и обработка апдейтов бота, интеграция с ApiShip, промокодами и баллами.
- Уведомления о смене статуса заказа и повторная отправка счётов в Telegram.
- Плагины оплаты: Telegram Payments (карты через YooKassa/Robokassa) и Telegram Stars.
- Планировщик `Task - RadicalMart Telegram Fetch` для обновления базы ПВЗ ApiShip.
- Консольные команды Joomla: `com_radicalmart_telegram:apiship:fetch`, `com_radicalmart_telegram:housekeep`.

## Структура репозитория
```
administrator/components/com_radicalmart_telegram/   # Админ‑часть компонента
components/com_radicalmart_telegram/                  # Публичная часть (WebApp)
plugins/system/radicalmart_telegram/                  # Системный плагин уведомлений и меню
plugins/task/radicalmart_telegram_fetch/              # Планировщик ApiShip Fetch
plugins/radicalmart_payment/telegramcards/            # Оплата Telegram Payments (карты)
plugins/radicalmart_payment/telegramstars/            # Оплата Telegram Stars
media/com_radicalmart_telegram/                       # Веб-ассеты WebApp (IMask, asset manifest)
com_radicalmart_telegram-package/                     # Манифест Joomla-пакета и post-install скрипт
language/                                             # Языковые файлы пакета (en-GB, ru-RU)
tools/build.ps1                                       # Скрипт сборки установочного пакета
docs/                                                 # Дополнительная документация (md)
```

## Сборка установочного пакета
Скрипт `tools/build.ps1` собирает архив `pkg_radicalmart_telegram-<version>.zip` в каталоге `dist/`.

```powershell
pwsh -NoProfile -File tools/build.ps1 -Version 0.1.6
```

Требования: PowerShell 5.1+ или PowerShell 7+, установленный `7z.exe` (опционально, для максимального сжатия). Скрипт создаёт staging `build/package`, копирует компоненты, плагины, медиаресурсы и языки, упаковывает дочерние расширения и формирует финальный ZIP пакета.

## Установка
1. Соберите пакет или используйте готовый архив `pkg_radicalmart_telegram-*.zip` из `dist/`.
2. В Joomla: **System → Extensions → Install** → загрузите ZIP.
3. Включите плагины:
   - System — RadicalMart Telegram
   - Task — RadicalMart Telegram Fetch
   - RadicalMart Payment — Telegram Cards / Telegram Stars
4. Настройте компонент: `bot_token`, `webhook_secret`, домены WebApp, ApiShip и параметры оплат.
5. Установите webhook: `index.php?option=com_radicalmart_telegram&task=webhook.receive&secret=...`.

## Полезные материалы
- `docs/installer-package.md` — структура пакета и чек-лист перед релизом.
- `docs/dev-notes.md` — технические решения, БД, платежи, API и TODO.
- `AGENTS.md` — контекст проекта и дорожная карта бота.
- `todo.md` — ближайшие этапы разработки.

## Лицензия
Проект распространяется по лицензии [GNU GPL v3 или новее](LICENSE).
