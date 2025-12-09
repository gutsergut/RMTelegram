# RadicalMart Telegram Bot — Документация

## Оглавление

1. [Обзор](#обзор)
2. [Требования](#требования)
3. [Установка](#установка)
4. [Настройка](#настройка)
5. [Структура компонента](#структура-компонента)
6. [API Reference](#api-reference)
7. [WebApp](#webapp)
8. [Платежи](#платежи)
9. [ПВЗ и доставка](#пвз-и-доставка)
10. [Администрирование](#администрирование)
11. [CLI команды](#cli-команды)
12. [Troubleshooting](#troubleshooting)

---

## Обзор

`com_radicalmart_telegram` — компонент Joomla 5 для интеграции магазина RadicalMart с Telegram. Поддерживает:

- **Чат-бот**: команды, навигация по каталогу, корзина
- **WebApp (Mini App)**: полноценный интерфейс магазина в Telegram
- **Telegram Payments**: оплата картой через YooKassa, Telegram Stars
- **ПВЗ**: карта Yandex.Maps с пунктами выдачи (ApiShip)
- **Бонусы**: промокоды, баллы, рефералы

---

## Требования

### Сервер
- **PHP**: 8.1+
- **MariaDB**: 10.5+ (с поддержкой SPATIAL indexes, SRID 4326)
- **Joomla**: 5.x
- **SSL**: обязателен для Webhook и WebApp

### Компоненты Joomla
- `com_radicalmart` — основной магазин
- `com_radicalmart_bonuses` — бонусная система (опционально)
- `plg_radicalmart_shipping_apiship` — доставка ApiShip

### Telegram
- Бот создан через [@BotFather](https://t.me/BotFather)
- Домен добавлен в `Web App` → `Edit Web App URL`
- Webhook настроен на HTTPS

---

## Установка

### 1. Установка пакета

```bash
# Скачать последнюю версию
wget https://github.com/gutsergut/RMTelegram/releases/latest/download/pkg_radicalmart_telegram.zip

# Установить через Extensions → Install
```

Пакет включает:
- `com_radicalmart_telegram` — основной компонент
- `plg_system_radicalmart_telegram` — системный плагин (меню)
- `plg_task_radicalmart_telegram_fetch` — задача синхронизации ПВЗ
- `plg_task_radicalmart_telegram_cart_reminders` — напоминания о брошенных корзинах
- `plg_task_radicalmart_telegram_restock` — уведомления о поступлении товара
- `plg_task_radicalmart_telegram_expiring` — уведомления об истекающих баллах
- `plg_radicalmart_payment_telegramcards` — оплата картой
- `plg_radicalmart_payment_telegramstars` — оплата звёздами
- `plg_radicalmart_telegram_notifications` — уведомления о заказах

### 2. Включение плагинов

После установки включите плагины:
- Extensions → Plugins → `System - RadicalMart Telegram` → Enable
- Extensions → Plugins → `Task - RadicalMart Telegram Fetch` → Enable
- Extensions → Plugins → `Task - RadicalMart Telegram Cart Reminders` → Enable
- Extensions → Plugins → `Task - RadicalMart Telegram Restock` → Enable
- Extensions → Plugins → `RadicalMart Payment - Telegram Cards` → Enable (если нужно)
- Extensions → Plugins → `RadicalMart Payment - Telegram Stars` → Enable (если нужно)

### 3. Настройка Webhook

```bash
# Через CLI
php cli/joomla.php com_radicalmart_telegram:webhook set

# Проверка статуса
php cli/joomla.php com_radicalmart_telegram:webhook info
```

---

## Настройка

### Основные настройки компонента

**Components → RadicalMart Telegram → Options**

#### Секция "Bot"
| Параметр | Описание |
|----------|----------|
| `bot_token` | Токен бота от BotFather |
| `webhook_secret` | Секретный ключ для Webhook (генерируется автоматически) |
| `store_title` | Название магазина в сообщениях |

#### Секция "WebApp"
| Параметр | Описание |
|----------|----------|
| `webapp_allowed_domains` | Разрешённые домены (например, `cacao.land`) |
| `strict_initdata` | Строгая проверка initData Telegram |

#### Секция "ApiShip"
| Параметр | Описание |
|----------|----------|
| `apiship_api_key` | API ключ ApiShip |
| `apiship_providers` | Провайдеры (например, `yataxi,cdek,x5`) |
| `pvz_cache_enabled` | Включить кеширование ПВЗ |
| `pvz_cache_ttl` | TTL кеша в секундах |

#### Секция "Payments"
| Параметр | Описание |
|----------|----------|
| `payment_success_status` | Статус заказа после успешной оплаты |

#### Секция "Legal Documents"
| Параметр | Описание |
|----------|----------|
| `article_privacy_policy` | Статья с политикой конфиденциальности |
| `article_consent_personal_data` | Статья с согласием на обработку ПД |
| `article_terms_of_service` | Пользовательское соглашение |
| `article_consent_marketing` | Согласие на рассылки |

---

## Структура компонента

```
components/com_radicalmart_telegram/          # Site-часть
├── src/
│   ├── Controller/
│   │   ├── ApiController.php                 # Все API эндпоинты
│   │   └── WebhookController.php             # Обработка Webhook
│   ├── Service/
│   │   ├── TelegramClient.php                # Клиент Telegram API
│   │   ├── UpdateHandler.php                 # Обработчик команд бота
│   │   ├── SessionStore.php                  # Хранение состояний
│   │   ├── CartService.php                   # Работа с корзиной
│   │   ├── CheckoutService.php               # Создание заказов
│   │   └── ...
│   ├── Helper/
│   │   ├── ConsentHelper.php                 # Управление согласиями
│   │   ├── TelegramUserHelper.php            # Связь chat_id ↔ user_id
│   │   └── ...
│   └── View/                                 # Views для WebApp
└── tmpl/                                     # Шаблоны
    ├── app/
    │   ├── tgwebapp.php                      # Основной layout WebApp
    │   └── webapp.php                        # Альтернативный layout
    ├── checkout/default.php                  # Оформление заказа
    ├── cart/default.php                      # Корзина
    └── ...

administrator/components/com_radicalmart_telegram/  # Admin-часть
├── config.xml                                # Настройки
├── sql/                                      # Миграции БД
├── src/
│   ├── Controller/ApiController.php          # Admin API (ПВЗ fetch)
│   ├── View/                                 # Admin views
│   │   ├── Settings/                         # Настройки бота
│   │   ├── Status/                           # Статус ПВЗ
│   │   ├── Links/                            # Привязки пользователей
│   │   └── Payments/                         # История платежей
│   └── Helper/ApiShipFetchHelper.php         # Загрузка ПВЗ
└── tmpl/                                     # Admin шаблоны
```

---

## API Reference

### Каталог

| Endpoint | Метод | Описание |
|----------|-------|----------|
| `api.list` | GET | Список товаров |
| `api.product` | GET | Карточка товара |
| `api.search` | GET | Поиск товаров |
| `api.facets` | GET | Доступные фильтры |

**Пример:**
```
GET /index.php?option=com_radicalmart_telegram&task=api.list&chat=123&limit=10&format=raw
```

### Корзина

| Endpoint | Метод | Описание |
|----------|-------|----------|
| `api.cart` | GET | Содержимое корзины |
| `api.add` | POST | Добавить товар |
| `api.qty` | POST | Изменить количество |
| `api.remove` | POST | Удалить товар |
| `api.summary` | GET | Итоги с учётом скидок |

### Оформление

| Endpoint | Метод | Описание |
|----------|-------|----------|
| `api.methods` | GET | Методы доставки/оплаты |
| `api.setpvz` | POST | Выбрать ПВЗ |
| `api.setpayment` | POST | Выбрать способ оплаты |
| `api.checkout` | POST | Создать заказ |
| `api.tariffs` | GET | Тарифы доставки |

### Бонусы

| Endpoint | Метод | Описание |
|----------|-------|----------|
| `api.bonuses` | GET | Баланс баллов |
| `api.applyPoints` | POST | Применить баллы |
| `api.applyPromo` | POST | Применить промокод |
| `api.removePromo` | POST | Удалить промокод |

### Пользователь

| Endpoint | Метод | Описание |
|----------|-------|----------|
| `api.profile` | GET | Профиль пользователя |
| `api.updateprofile` | POST | Обновить профиль |
| `api.orders` | GET | Список заказов |
| `api.consents` | GET | Статус согласий |
| `api.setconsent` | POST | Установить согласие |

### ПВЗ

| Endpoint | Метод | Описание |
|----------|-------|----------|
| `api.pvz` | GET | ПВЗ по bbox |

**Параметры `api.pvz`:**
- `bbox` — границы карты (lon1,lat1,lon2,lat2)
- `providers` — фильтр провайдеров (опционально)
- `limit` — максимум точек (по умолчанию 1000)

---

## WebApp

### URL WebApp

```
https://cacao.land/index.php?option=com_radicalmart_telegram&view=app&tmpl=tgwebapp&chat={chat_id}
```

### Структура WebApp

WebApp использует:
- **UIkit 3** — UI framework (из YOOtheme)
- **Yandex.Maps** — карта ПВЗ
- **Telegram WebApp SDK** — интеграция с Telegram

### Навигация

- `/` — Каталог
- `/cart` — Корзина
- `/checkout` — Оформление
- `/orders` — Заказы
- `/profile` — Профиль
- `/settings` — Настройки

---

## Платежи

### Telegram Cards (YooKassa)

**Настройка плагина:**
1. Extensions → Plugins → `RadicalMart Payment - Telegram Cards`
2. Указать `provider_token` от YooKassa
3. Настроить `allowed_categories` / `excluded_categories` (опционально)

**Возвраты:**
- Админка: Components → RadicalMart Telegram → Payments
- Выбрать платёж → Refund (полный или частичный)
- Вызывается YooKassa API `/v3/refunds`

### Telegram Stars

**Настройка плагина:**
1. Extensions → Plugins → `RadicalMart Payment - Telegram Stars`
2. Указать `rub_per_star` (курс рубль/звезда)
3. Указать `conversion_percent` (наценка %)
4. Настроить ограничения по категориям/товарам

**Важно:** Возвраты для Stars не поддерживаются Telegram API.

---

## ПВЗ и доставка

### Полная выгрузка ПВЗ

**Через админку:**
- Components → RadicalMart Telegram → Status
- Кнопка "Начать загрузку"

**Через CLI:**
```bash
php cli/joomla.php com_radicalmart_telegram:apiship:fetch --providers=yataxi,cdek,x5
```

**Через Task Scheduler:**
- System → Manage → Scheduled Tasks → New
- Тип: "Синхронизация ПВЗ ApiShip"
- Расписание: еженедельно (рекомендуется)

### Импорт из файла (для x5)

Для провайдера x5 с проблемной пагинацией API:

1. Скачать NDJSON файл
2. Положить в `administrator/components/com_radicalmart_telegram/cache/apiship_x5.ndjson`
3. Components → RadicalMart Telegram → Status → Import From File (x5)

---

## Администрирование

### Меню админки

Меню создаётся системным плагином `plg_system_radicalmart_telegram`:

- **Settings** — управление Webhook, информация о боте
- **Status** — мониторинг загрузки ПВЗ по провайдерам
- **Links** — привязки Telegram пользователей к аккаунтам сайта
- **Payments** — история платежей через Telegram, возвраты
- **Configuration** — глобальные настройки компонента

### Привязка пользователей

Процесс привязки:
1. Пользователь отправляет `/start` боту
2. Бот запрашивает согласие на обработку ПД
3. После согласия — запрос телефона через `requestContact`
4. При совпадении телефона с пользователем сайта — автоматическая привязка
5. Если телефон новый — создаётся новый пользователь

---

## Scheduled Tasks

Компонент включает несколько Task-плагинов для автоматизации. Настройка через **System → Manage → Scheduled Tasks**.

### Синхронизация ПВЗ (ApiShip Fetch)
- **Плагин**: `Task - RadicalMart Telegram Fetch`
- **Рекомендация**: раз в неделю (воскресенье ночью)
- **Cron**: `0 3 * * 0`

Загружает пункты выдачи заказов от всех активных провайдеров (yataxi, cdek, x5).

### Напоминания о брошенных корзинах
- **Плагин**: `Task - RadicalMart Telegram Cart Reminders`
- **Рекомендация**: каждые 15-30 минут
- **Cron**: `*/15 * * * *`

Отправляет напоминания по графику:
1. Через 1 час после добавления
2. Через 24 часа
3. Через 3 дня

Настройки в компоненте: тихие часы (22:00-9:00), max повторов, тексты сообщений.

### Уведомления о поступлении товаров (Restock)
- **Плагин**: `Task - RadicalMart Telegram Restock`
- **Рекомендация**: раз в день утром
- **Cron**: `0 10 * * *`

Проверяет товары, на которые подписаны пользователи (кнопка "🔔 Сообщить о поступлении"), и отправляет уведомления при появлении в наличии.

### Уведомления об истекающих баллах
- **Плагин**: `Task - RadicalMart Telegram Expiring`
- **Рекомендация**: раз в день
- **Cron**: `0 11 * * *`

Отправляет предупреждения пользователям о скором сгорании бонусных баллов.

---

## Команды бота

### Основные команды

| Команда | Описание |
|---------|----------|
| `/start` | Приветствие, согласия, запрос телефона |
| `/help` | То же, что /start |
| `/catalog` | Открыть каталог |
| `/cart` | Просмотр корзины |
| `/checkout` | Оформление заказа |
| `/orders` | Мои заказы |
| `/promo` | Промокоды |
| `/points` | Баллы |
| `/settings` | Управление подписками |

### Команда /settings

Позволяет пользователю управлять своими согласиями:
- Включить/отключить рассылку (маркетинг)
- Включить/отключить напоминания о корзине
- Отозвать все согласия (с подтверждением)

---

## CLI команды

### Webhook

```bash
# Установить webhook
php cli/joomla.php com_radicalmart_telegram:webhook set

# Удалить webhook
php cli/joomla.php com_radicalmart_telegram:webhook delete

# Информация о webhook
php cli/joomla.php com_radicalmart_telegram:webhook info
```

### Housekeeping

```bash
# Очистка старых nonces и rate-limits
php cli/joomla.php com_radicalmart_telegram:housekeep
```

### ApiShip Fetch

```bash
# Полная выгрузка ПВЗ
php cli/joomla.php com_radicalmart_telegram:apiship:fetch --providers=yataxi,cdek,x5
```

---

## Troubleshooting

### Логи

Логи записываются в `administrator/logs/com_radicalmart.telegram.php`

Включение debug-режима:
- Components → RadicalMart Telegram → Options → Debug Mode = Yes

### Частые проблемы

#### Webhook не работает
1. Проверьте SSL сертификат
2. Убедитесь, что `webhook_secret` совпадает
3. Проверьте логи на наличие ошибок

```bash
php cli/joomla.php com_radicalmart_telegram:webhook info
```

#### ПВЗ не загружаются
1. Проверьте `apiship_api_key` в настройках
2. Посмотрите логи на ошибки API
3. Для x5 используйте импорт из файла

#### Платежи не проходят
1. Проверьте `provider_token` в настройках плагина
2. Убедитесь, что плагин включён
3. Проверьте логи `pre_checkout_query` / `successful_payment`

#### WebApp не открывается
1. Проверьте, что домен добавлен в BotFather
2. Убедитесь, что `webapp_allowed_domains` настроен
3. Проверьте SSL сертификат

### Диагностические эндпоинты

```bash
# Проверка корзин (debug)
GET /index.php?option=com_radicalmart_telegram&task=api.debugCarts&chat=123&format=raw

# Состояние БД ПВЗ
GET /administrator/index.php?option=com_radicalmart_telegram&task=api.apishipdbCheck&format=raw
```

---

## Контакты

- **GitHub**: https://github.com/gutsergut/RMTelegram
- **Joomla**: RadicalMart Extensions
- **Telegram**: @CacaoLandBot

---

*Версия документации: 5.0.5*
*Дата: 2025-12-09*
