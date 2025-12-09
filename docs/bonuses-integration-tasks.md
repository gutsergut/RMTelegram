# Интеграция RadicalMart Bonuses с Telegram WebApp

> **Статус**: ✅ Завершено
> **Последнее обновление**: 2025-11-29

---

## 📋 Порядок работы

Перед каждой задачей:
1. Уточняющие вопросы по UX/дизайну
2. Согласование подхода
3. Реализация
4. Тестирование
5. Переход к следующей задаче

---

## Обзор системы бонусов

### Таблицы БД
- `#__radicalmart_bonuses_codes` — Промокоды (включая реферальные)
- `#__radicalmart_bonuses_points` — История баллов (начисления/списания)
- `#__radicalmart_bonuses_referrals` — Связи рефералов (user_id → parent_id)
- `#__radicalmart_bonuses_referrals_logs` — Логи реферальной системы

### Ключевые хелперы (Administrator)
- `PointsHelper` — работа с баллами (баланс, начисление, конвертация)
- `CodesHelper` — работа с промокодами (поиск, валидация, применение)
- `ReferralHelper` — реферальная система (связи, логи, статистика)
- `DiscountHelper` — расчёт скидок на товары

### Существующие Site Views
- `Points` — История баллов пользователя
- `Codes` — Промокоды пользователя
- `Referrals` — Реферальная программа (коды, статистика)

---

## Настройки RadicalMart Bonuses (из com_radicalmart.config.xml)

### Баллы (bonuses_points)
| Параметр | Описание | Default |
|----------|----------|---------|
| `bonuses_points_enabled` | Включены ли баллы | `1` |
| `bonuses_points_non_discount_products` | Баллы для товаров без скидки | `0` |
| `bonuses_points_statuses` | Статусы заказов для работы с баллами | `[]` |
| `bonuses_provider` | Провайдер расчёта баллов | `standard` |
| `bonuses_points_instant` | Мгновенное начисление | `1` |
| `bonuses_points_precision` | Точность (знаки после запятой) | `0` |
| `rounding` | Округление: round/ceil/floor | `round` |
| `bonuses_points_accrual_statuses` | Статусы для начисления | `[]` |
| `bonuses_points_accrual_timeout` | Таймаут начисления | `0 days` |
| `bonuses_points_accrual_formula_from` | Расчёт от: base/final | `base` |
| `bonuses_points_accrual_formula_{currency}` | Формула начисления по валюте | — |
| `bonuses_points_withdraw_statuses` | Статусы для списания | `[]` |
| `bonuses_points_withdraw_formula_{currency}` | Формула списания (конвертация баллов в деньги) | `1=1` |
| `bonuses_points_refund_statuses` | Статусы для возврата баллов | `[]` |

### Промокоды (bonuses_codes)
| Параметр | Описание | Default |
|----------|----------|---------|
| `bonuses_codes_enabled` | Включены ли промокоды | `1` |
| `bonuses_codes_guest` | Промокоды для гостей | `1` |
| `bonuses_codes_non_discount_products` | Для товаров без скидки | `0` |
| `bonuses_codes_statuses` | Статусы заказов | `[]` |
| `bonuses_codes_generation_length` | Длина генерируемого кода | `8` |
| `bonuses_codes_cookies_enabled` | Сохранять в cookies | `1` |
| `bonuses_codes_cookies_selector` | Имя cookie | `rbc` |
| `bonuses_codes_buy_enabled` | Покупка промокодов | `1` |
| `bonuses_codes_buy_statuses` | Статусы для покупки | `[]` |

### Рефералы (bonuses_referral)
| Параметр | Описание | Default |
|----------|----------|---------|
| `bonuses_referral_enabled` | Включены ли рефералы | `1` |
| `bonuses_referral_access` | Группы пользователей | `[]` |
| `bonuses_referral_codes_enabled` | Реферальные коды | `1` |
| `bonuses_referral_codes_custom_code` | Пользователь может задать код | `1` |
| `bonuses_referral_codes_limit` | Лимит кодов на пользователя | `1` |
| `bonuses_referral_codes_expires_timeout` | Срок действия кода | — |
| `bonuses_referral_orders_accrual_from` | Кому начислять: referral/customer | `customer` |
| `bonuses_referral_orders_accrual_statuses` | Статусы для начисления | `[]` |
| `bonuses_referral_orders_accrual_timeout` | Таймаут начисления | `0 days` |
| `bonuses_referral_orders_accrual_formula_from` | Расчёт от: base/final | `base` |
| `bonuses_referral_orders_refund_statuses` | Статусы для возврата | `[]` |

### Ключевые методы PointsHelper
```php
// Получить баланс баллов клиента
PointsHelper::getCustomerPoints(int $customerId): float

// Конвертировать баллы в деньги (по формуле bonuses_points_withdraw_formula_{currency})
PointsHelper::convertToMoney(float $points, string $currencyCode): float

// Конвертировать деньги в баллы
PointsHelper::convertToPoints(float $money, string $currencyCode): float

// Очистить/округлить значение баллов (учитывает precision и rounding)
PointsHelper::clean(mixed $value): float

// Создать запись о баллах (начисление/списание)
PointsHelper::createRecord(int $customer, float $points, ?string $context, array $data): int|false
```

---

## Задачи на интеграцию

### Этап 1: Базовая интеграция баллов

#### 1.1 Отображение баланса баллов в профиле
**Статус**: ✅ Завершено (2025-11-28)

**Решения по вопросам**:
- Баланс в **отдельном блоке** (uk-card)
- Формат: вариант A "X баллов (= Y ₽)"
- Ссылка на историю — только если баллов > 0
- При 0 баллов показываем "0 баллов" без ссылки

**Что сделано**:
- [x] `ProfileView::loadPointsBalance()` — загрузка баланса через PointsHelper
- [x] Получение customer_id по user_id из `#__radicalmart_customers`
- [x] Блок баллов в `tmpl/profile/default.php` с форматированием
- [x] Эквивалент в рублях через `PointsHelper::convertToMoney()`
- [x] Языковые строки: POINTS_UNIT, VIEW_HISTORY, POINTS_HISTORY, REFERRALS, GUEST

#### 1.2 Страница истории баллов
**Статус**: ✅ Завершено (2025-11-28)

**Решения по вопросам**:
- Пагинация: кнопка "Загрузить ещё" (10 записей)
- Группировка: простой хронологический список
- Сгорающие баллы: отдельный блок вверху (если есть баллы с датой окончания)
- Фильтр: не нужен, общий список

**Что сделано**:
- [x] `View/Points/HtmlView.php` — загрузка истории + сгорающих баллов
- [x] `View/Points/JsonView.php` — AJAX endpoint для "Загрузить ещё"
- [x] `tmpl/points/default.php` — список операций
- [x] Карточка текущего баланса вверху страницы
- [x] Блок "Сгорающие баллы" (если есть записи с end > NOW())
- [x] Кнопка "Назад" к профилю
- [x] Языковые строки: POINTS_BALANCE, POINTS_EXPIRING, POINTS_EXPIRES_DATE, POINTS_NO_HISTORY, контексты операций

#### 1.3 Использование баллов при оформлении
**Статус**: ✅ Завершено (2025-11-28)

**Решения по вопросам**:
- UI: поле ввода с кнопками +/- И чекбокс "Использовать все баллы"
- Ограничения: используем настройки из RadicalMart Bonuses (без доп. лимитов)
- Обновление итога: по кнопке "Применить"
- Расположение: отдельная секция "Скидки и баллы" перед блоком "Итого" (рядом с промокодом)

**Что сделано**:
- [x] Секция "Скидки и баллы" в `tmpl/checkout/default.php`
- [x] Поле ввода баллов с кнопками +/− и чекбокс "Использовать все"
- [x] Показ доступного баланса и эквивалента в рублях
- [x] Кнопка "Применить" → AJAX сохранение в сессию RadicalMart
- [x] `ApiController::applyPoints()` — сохранение в `com_radicalmart.checkout.data['plugins']['bonuses']['points']`
- [x] Плагин `radicalmart/bonuses` автоматически обрабатывает списание при createOrder
- [x] Сохранение и восстановление применённых баллов между страницами
- [x] Языковые строки: DISCOUNTS_POINTS, USE_ALL_POINTS, POINTS_APPLIED, POINTS_CLEARED

---

### Этап 2: Промокоды

#### 2.1 Применение промокода в checkout
**Статус**: ✅ Завершено (2025-11-28)

**Решения по вопросам**:
- Один промокод за раз (как в основном сайте)
- Показываем детали скидки после применения
- Кнопка "Применить" перезаписывает предыдущий код

**Что сделано**:
- [x] Поле ввода промокода в секции "Скидки и баллы"
- [x] `ApiController::applyPromo()` — валидация через `CodesHelper::checkCode()`
- [x] Сохранение в `com_radicalmart.checkout.data['plugins']['bonuses']['codes']`
- [x] Показ результата: успех/ошибка, размер скидки
- [x] Сохранение и восстановление применённого кода между страницами
- [x] Языковые строки: PROMO, ENTER_PROMO, APPLY, PROMO_APPLIED, ERR_PROMO_*

#### 2.2 Страница промокодов пользователя
**Статус**: ✅ Завершено (2025-11-28)

**Решения по вопросам**:
- Показываем истёкшие промокоды (серым, с пометкой "Истёк")
- Кнопка "Скопировать код" с анимацией успеха и Telegram haptic feedback
- Показываем ограничения по товарам/категориям (apply = "действует на", ignore = "не действует на")

**Что сделано**:
- [x] `View/Codes/HtmlView.php` — загрузка промокодов пользователя из `#__radicalmart_bonuses_codes`
- [x] `tmpl/codes/default.php` — адаптивный UI с карточками промокодов
- [x] Отображение: код, скидка (%-я или фикс), срок действия, использования
- [x] Статусы: "Активен" (зелёный), "Истёк" (красный), "Лимит исчерпан" (жёлтый)
- [x] Блок ограничений по товарам/категориям из plugins JSON
- [x] Кнопка копирования с clipboard API и fallback
- [x] Telegram WebApp интеграция (BackButton, haptic feedback)
- [x] Ссылка на страницу из профиля (иконка tag)
- [x] Языковые строки: PROMO_CODES, CODES_EMPTY, CODE_*, COPY_CODE, COPIED

---

### Этап 3: Реферальная программа

#### 3.1 Страница реферальной программы
**Статус**: ✅ Завершено (2025-11-28)

**Решения по вопросам**:
- Показываем список рефералов с **маскированными** контактами (email: `j***n@g***l.com`)
- Показываем сумму заработанных баллов от рефералов (из логов)
- Показываем полную цепочку рефералов (многоуровневая, до 5 уровней вниз)
- Показываем цепочку родителей (кто пригласил, вверх)

**Что сделано**:
- [x] `View/Referrals/HtmlView.php` — загрузка данных реферальной программы
- [x] `tmpl/referrals/default.php` — адаптивный UI
- [x] Статистика: количество рефералов + заработанные баллы (градиентный блок)
- [x] Блок "Мои реферальные коды" с кнопкой копирования ссылки
- [x] Блок "Кто меня пригласил" — цепочка родителей
- [x] Блок "Мои рефералы" — многоуровневое дерево с цветовыми индикаторами
- [x] Маскирование email для защиты приватности
- [x] Пагинация "Загрузить ещё"
- [x] Ссылка на страницу уже была в профиле
- [x] Языковые строки: REFERRALS_*, CODE_*, COPY, COPIED

#### 3.2 Создание реферального кода
**Статус**: ✅ Завершено (2025-11-28)

**Решения по вопросам**:
- Форма создания кода на странице реферальной программы (не отдельная страница)
- Показываем код, ссылку И размер скидки от шаблона
- При достижении лимита: скрываем кнопку и показываем сообщение о лимите

**Что сделано**:
- [x] `HtmlView.php` — добавлены свойства: `$canCustomCode`, `$codesLimit`, `$codesLimitReached`, `$templateDiscount`
- [x] `HtmlView::checkCanCreateCode()` — улучшен для получения всех параметров
- [x] `HtmlView::loadTemplateDiscount()` — загрузка скидки из шаблона кода
- [x] `ApiController::createReferralCode()` — создание кода через `ReferralsModel::createCode()`
- [x] Форма в `tmpl/referrals/default.php`:
  - Информация о скидке нового кода
  - Поле для пользовательского кода (если `canCustomCode`)
  - Кнопка "Создать код" → AJAX
  - Показ результата: код, ссылка, скидка + кнопка копирования
  - Сообщение о достигнутом лимите кодов
- [x] JavaScript функции: `createReferralCode()`, `copyNewCodeLink()`
- [x] Языковые строки: CREATE_NEW_CODE, CODE_WILL_GIVE, ENTER_CUSTOM_CODE и др.

#### 3.3 Интеграция с Telegram реферальной системой
**Статус**: ✅ Завершено (2025-11-28)

**Решения по вопросам**:
- Формат ссылки: `https://t.me/BOT_USERNAME?start=ref_CODE`
- При переходе: сохраняем код в БД (`referral_code` в `#__radicalmart_telegram_users`)
- Связь создаётся при привязке телефона (когда есть user_id и referral_code)

**Что сделано**:
- [x] SQL миграция 0.1.71: добавлено поле `referral_code` в `#__radicalmart_telegram_users`
- [x] `UpdateHandler::onMessage()` — обработка `/start ref_CODE`:
  - Извлечение кода из параметра start
  - Сохранение в БД через `saveReferralCode()`
  - Приветственное сообщение с информацией о скидке
- [x] `UpdateHandler::applyReferralCodeOnLink()` — применение кода при связывании:
  - Вызывается после успешной привязки телефона
  - Находит код через `CodesHelper::find()`
  - Создаёт связь через `ReferralsHelper::createReferralRelationship()`
  - Очищает `referral_code` в БД после применения
- [x] Telegram ссылка на странице реферальной программы:
  - `HtmlView::$botUsername` — имя бота из настроек
  - Каждый код имеет `telegram_link` (`t.me/bot?start=ref_CODE`)
  - UI: две ссылки (сайт + Telegram) с кнопками копирования
- [x] Языковые строки: TELEGRAM_LINK, COPY_TELEGRAM_LINK, REFERRAL_WELCOME

---

### Этап 4: Уведомления в Telegram

#### 4.1 Уведомление о начислении баллов
**Статус**: ✅ Завершено (2025-11-28)

**Что сделано**:
- [x] Создан плагин `plg_radicalmart_telegram_notifications`
- [x] Слушает событие `onRadicalMartAfterChangeOrderStatus`
- [x] При начислении баллов (reason='accrual') отправляет сообщение клиенту:
  - "🎁 Начислены бонусные баллы!"
  - "За заказ №X вам начислено Y баллов"
  - "💰 Ваш баланс: Z баллов (≈ N ₽)"
- [x] Получает chat_id из `#__radicalmart_telegram_users`
- [x] Использует bot_token из настроек компонента

#### 4.2 Уведомление о реферальном бонусе
**Статус**: ✅ Завершено (2025-11-28)

**Что сделано**:
- [x] В том же плагине `plg_radicalmart_telegram_notifications`
- [x] При начислении реферальных баллов (reason='referral_accrual') отправляет сообщение родителю:
  - "👥 Реферальный бонус!"
  - "Ваш реферал совершил заказ. Вам начислено X баллов"
  - "💰 Ваш баланс: Y баллов (≈ Z ₽)"
- [x] Отправка каждому родителю в цепочке рефералов

#### 4.3 Уведомление об истечении баллов
**Статус**: ✅ Завершено (2025-11-28)

**Что сделано**:
- [x] Создан плагин `plg_task_radicalmart_telegram_expiring`
- [x] Scheduled task для Joomla Task Scheduler
- [x] Параметр `days_before` (по умолчанию 7) — за сколько дней предупреждать
- [x] Запрос сгорающих баллов через SQL с группировкой по customer_id
- [x] Защита от повторных уведомлений в тот же день (session storage)
- [x] Отправка сообщения в Telegram:
  - "⏰ Скоро сгорят баллы!"
  - "Через X дней у вас сгорит Y баллов"
  - "💰 Текущий баланс: Z баллов (≈ N ₽)"
  - "Успейте использовать баллы при следующем заказе!"
- [x] Корректное склонение слова "день" (1 день, 2 дня, 5 дней)

---

### Этап 5: Улучшения UX

#### 5.1 Отображение потенциального кэшбэка
**Статус**: ✅ Завершено (2025-11-28)

**Решения по вопросам**:
- Формат: строка под ценой + бейдж [🎁 X%] на карточке
- В корзине: только итог внизу "За этот заказ вы получите X баллов"
- Показывать всем (для гостей — с пометкой об авторизации)
- Кэшбэк от финальной цены (как в настройках `bonuses_points_accrual_formula_from`)
- **Особенность**: если применён реферальный промокод — кэшбэк не показываем (0 баллов)

**Что сделано**:
- [x] `CatalogService::getCashbackConfig()` — получение настроек кэшбэка из RadicalMart
- [x] `CatalogService::calculateCashback()` — расчёт баллов через `PointsHelper::calculatePoints()`
- [x] `mapProductForMeta()` — добавлены поля `cashback` и `cashback_percent` для каждого товара
- [x] `ApiController::cart()` — расширен ответ с информацией о кэшбэке и `is_linked`
- [x] `ApiController::calculateCartCashback()` — расчёт итогового кэшбэка с учётом реферального промокода:
  - Проверка в данных продуктов корзины
  - Проверка применённого промокода из сессии через `CodesHelper::find()`
  - Флаг `referral` в таблице кодов определяет реферальный промокод
- [x] `tgwebapp.php` — переменная `IS_USER_LINKED` для отслеживания авторизации
- [x] `tgwebapp.php` — отображение кэшбэка в карточке товара:
  - Бейдж [🎁 X%] (зелёный фон)
  - Строка под ценой "🎁 +X баллов (≈X ₽)"
  - Для гостей: "🎁 +X баллов → Авторизуйтесь для баллов"
- [x] `tgwebapp.php` — отображение кэшбэка в корзине:
  - "🎁 За этот заказ вы получите: +X баллов (≈X ₽)"
  - При реферальном промокоде: "Кэшбэк не начисляется при использовании реферального промокода"
  - Для гостей дополнительно: "Авторизуйтесь, чтобы получать баллы за покупки"
- [x] Языковые строки: POINTS_SHORT, CASHBACK_ORDER, CASHBACK_DISABLED_REFERRAL, CASHBACK_LOGIN_HINT, CASHBACK_LOGIN_SHORT

#### 5.2 Виджет баланса в профиле
- [x] Новый блок «💰 Баллы и рефералы» в профиле:
  - Баланс: "X баллов" с надписью + ссылка "История" → `view=points`
  - Для гостей: "0 баллов" + "Авторизуйтесь, чтобы копить баллы"
- [x] Реферальная информация (рефералы, коды) — в том же карточном блоке
- [x] Новые языковые строки: POINTS_AND_REFERRALS, POINTS_UNIT, VIEW_HISTORY, POINTS_LOGIN_HINT
- [x] `window.RMT_ROOT` в tgwebapp.php для построения URL
- [x] Функция `openPointsHistory()` в app.js

#### 5.3 Шаринг реферальной ссылки
- [x] Кнопка «📤 Поделиться» для каждого реферального кода
- [x] Telegram native share через `Telegram.WebApp.openTelegramLink()`
- [x] Готовый текст: "Используй мой промокод {code} и получи скидку!"
- [x] Fallback на `window.open()` для браузера
- [x] Улучшенный UI списка кодов (flex-layout, код жирным)
- [x] Языковые строки: SHARE, SHARE_TEXT

---

### Этап 6: Административные страницы

#### 6.1 Просмотр баллов клиента (admin)
- [x] Страница истории баллов клиентов в админке (уже существует)
- [x] В деталях заказа показаны использованные баллы (RadicalMart Bonuses)
- [x] Баланс клиента доступен через RadicalMart Bonuses

**Примечание**: Этап 6 полностью покрыт существующим функционалом RadicalMart Bonuses.

---

## Приоритеты реализации

### MVP (Minimum Viable Product)
1. ✅ Баланс баллов в профиле
2. ✅ Использование баллов при оформлении
3. ✅ Применение промокода при оформлении
4. ✅ Базовая страница реферальной программы

### Фаза 2
5. История баллов
6. Создание реферальных кодов
7. Интеграция с Telegram start параметром
8. Telegram-ссылки для шаринга

### Фаза 3
9. Уведомления о баллах
10. Уведомления о рефералах
11. Отображение кэшбэка в каталоге
12. Напоминания о сгорании баллов

---

## Технические заметки

### Получение customer_id
```php
// RadicalMart использует свою таблицу customers
// customer_id != user_id
// Получить customer_id через user_id:
$db = Factory::getContainer()->get('DatabaseDriver');
$query = $db->getQuery(true)
    ->select('id')
    ->from('#__radicalmart_customers')
    ->where('user_id = ' . (int) $userId);
$customerId = $db->setQuery($query)->loadResult();
```

### Формат данных заказа для баллов/кодов
При создании заказа передать в `$data`:
```php
$data['bonuses_points'] = 100; // количество баллов к списанию
$data['bonuses_codes'] = ['PROMOCODE1', 'REFCODE2']; // массив кодов
```

### Реферальный код в куки
```php
// Установка (при переходе по ссылке)
CodesHelper::setCookieCode('REFCODE');

// Удаление
CodesHelper::removeCookieCode();
```

### События для расширения
- `onRadicalMartBonusesBeforeCalculateProductsDiscounts`
- `onRadicalMartBonusesCalculateProductsDiscounts`
- `onRadicalMartBonusesGetPointsProviders`
- `onRadicalMartBonusesGetCodesProviders`

---

## Файлы для создания/изменения

### Новые файлы
- `src/View/Points/HtmlView.php`
- `src/View/Codes/HtmlView.php`
- `src/View/Referrals/HtmlView.php`
- `tmpl/points/default.php`
- `tmpl/codes/default.php`
- `tmpl/referrals/default.php`

### Изменения
- `src/View/Profile/HtmlView.php` — добавить баланс баллов
- `tmpl/profile/default.php` — показать баллы, ссылки на страницы
- `tmpl/checkout/default.php` — поля баллов и промокода
- `src/Helper/TelegramUserHelper.php` — обработка реферального кода из start

### Языковые строки
- `COM_RADICALMART_TELEGRAM_POINTS` = "Баллы"
- `COM_RADICALMART_TELEGRAM_POINTS_BALANCE` = "Ваш баланс"
- `COM_RADICALMART_TELEGRAM_POINTS_EQUIVALENT` = "Эквивалент"
- `COM_RADICALMART_TELEGRAM_PROMOCODE` = "Промокод"
- `COM_RADICALMART_TELEGRAM_PROMOCODE_APPLY` = "Применить"
- `COM_RADICALMART_TELEGRAM_PROMOCODE_INVALID` = "Недействительный промокод"
- `COM_RADICALMART_TELEGRAM_REFERRAL` = "Реферальная программа"
- `COM_RADICALMART_TELEGRAM_REFERRAL_CODE` = "Ваш реферальный код"
- `COM_RADICALMART_TELEGRAM_REFERRAL_LINK` = "Ваша ссылка"
- `COM_RADICALMART_TELEGRAM_REFERRAL_COUNT` = "Приглашено друзей"
- `COM_RADICALMART_TELEGRAM_REFERRAL_EARNED` = "Заработано баллов"
- `COM_RADICALMART_TELEGRAM_PAY_WITH_POINTS` = "Оплатить баллами"
- `COM_RADICALMART_TELEGRAM_POINTS_TO_USE` = "Использовать баллов"
