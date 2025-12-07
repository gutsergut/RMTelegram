# Email Integration TODO — RadicalMart Telegram Bot

> Дата создания: 2025-12-06
> Статус: В разработке (Фаза 3 завершена)
> Версия: 0.1.89

---

## 📋 Общий план

### Фаза 1: База данных и структура ✅ ЗАВЕРШЕНО
- [x] **1.1** Добавить колонки в `#__radicalmart_telegram_users`:
  - `email` VARCHAR(255) DEFAULT NULL
  - `email_verified` TINYINT(1) DEFAULT 0
  - `email_verification_code` VARCHAR(10) DEFAULT NULL
  - `email_verification_expires` DATETIME DEFAULT NULL
  - `email_verification_attempts` INT DEFAULT 0 (защита от брутфорса)
  - `email_code_sent_at` DATETIME DEFAULT NULL (rate limiting)
  - `acymailing_subscribed` TINYINT(1) DEFAULT 0
- [x] **1.2** Создать SQL миграцию `sql/updates/mysql/0.1.89.sql`
- [x] **1.3** Добавить индекс `INDEX idx_email (email)` для быстрого поиска

---

### Фаза 2: Сбор email в Telegram боте (после телефона) ✅ ЗАВЕРШЕНО

#### 2.1 Состояния сессии ✅
- [x] Добавить состояния в `SessionStore`:
  - `awaiting_email` — ожидаем ввод email
  - `awaiting_email_code` — ожидаем код подтверждения

#### 2.2 Поток в UpdateHandler ✅
- [x] **2.2.1** После успешного `requestContact`:
  - Спросить email с кнопкой "Пропустить"
  - Перейти в состояние `awaiting_email`
  - Управляется настройкой `email_ask_after_phone` в config.xml

- [x] **2.2.2** Обработка ввода email (`state=awaiting_email`):
  - Валидация формата (FILTER_VALIDATE_EMAIL)
  - Проверка уникальности (telegram_users + Joomla users)
  - Генерация OTP (6 цифр) + токен для ссылки (64 символа)
  - Отправка письма с кодом и ссылкой
  - Переход в `awaiting_email_code`

- [x] **2.2.3** Обработка кода (`state=awaiting_email_code`):
  - Проверка количества попыток
  - Проверка срока действия кода
  - Проверка совпадения кода
  - Установка `email_verified=1`
  - Переход в `idle`

- [x] **2.2.4** Кнопка "Пропустить":
  - Сохранить `email=NULL, email_verified=0`
  - Перейти в `idle`

- [x] **2.2.5** Callback обработчики:
  - `email_skip` — пропуск email
  - `email_resend` — повторная отправка кода
  - `email_cancel` — отмена ввода email

---

### Фаза 3: Email в UI профиля (WebApp) ✅ ЗАВЕРШЕНО

#### 3.1 Helper класс ✅
- [x] **3.1.0** Создан `EmailVerificationHelper.php`:
  - `generateCode()` — генерация 6-значного OTP
  - `generateToken()` — генерация 64-char токена для link-based верификации
  - `validateFormat()` — валидация формата email
  - `checkUniqueness()` — проверка уникальности в telegram_users и Joomla users
  - `canRequestCode()` — rate limiting (60 сек cooldown, 5 попыток max)
  - `saveCode()` — сохранение кода + токена с expires (15 мин)
  - `verifyCode()` — проверка кода с защитой от брутфорса
  - `verifyToken()` — проверка токена из email-ссылки
  - `markVerified()` — установка email_verified=1
  - `sendVerificationEmail()` — отправка письма с кодом И ссылкой через Joomla mailer
  - `getEmailData()` — получение данных email пользователя
  - `updateEmail()` — обновление email с валидацией

#### 3.2 View/Settings ✅
- [x] **3.1.1** Загрузка email из `telegram_users` в `ProfileService::getProfile()`
- [x] **3.1.2** Добавить в шаблон `tmpl/settings/default.php`:
  - Поле ввода email
  - Статус верификации (бейдж "✓ Подтверждён" или "⚠ Не подтверждён")
  - Кнопка "Отправить код" для неподтверждённых
  - Поле ввода 6-значного кода
  - Кнопка "Подтвердить"
  - JavaScript логика для API вызовов

#### 3.3 API endpoints ✅
- [x] **3.2.1** Модификация `ApiController::updateprofile()`:
  - Принимать `email` из формы
  - Если email изменился — сбросить `email_verified=0`
  - Сохранить в `telegram_users`

- [x] **3.2.2** Новый endpoint `ApiController::sendEmailCode()`:
  - Валидация email формата
  - Rate limiting проверка
  - Генерация OTP
  - Отправка письма
  - Сохранение кода и expires

- [x] **3.2.3** Новый endpoint `ApiController::verifyEmailCode()`:
  - Проверка brute-force защиты
  - Проверка кода
  - Установка `email_verified=1`

- [x] **3.2.4** Новый endpoint `ApiController::verifyEmailLink()`:
  - Верификация по токену из email-ссылки (64-char token)
  - Редирект на страницу результата `view=emailverified`
  - Поддержка уже верифицированных (`alreadyVerified`)

#### 3.5 View для верификации по ссылке ✅
- [x] **3.5.1** Создан `View/Emailverified/HtmlView.php`
- [x] **3.5.2** Создан `tmpl/emailverified/default.php` со стилизованной страницей результата

#### 3.4 Языковые строки ✅
- [x] Добавлены 25+ строк для email верификации в `language/ru-RU/com_radicalmart_telegram.ini`

---

### Фаза 4: Валидация и проверки на дурака ✅ ЗАВЕРШЕНО (в EmailVerificationHelper)

#### 4.1 Валидация формата email ✅
- [x] **4.1.1** Базовая валидация `filter_var($email, FILTER_VALIDATE_EMAIL)`
- [x] **4.1.2** Дополнительные проверки:
  - Длина не более 255 символов
  - Не содержит кириллицу в локальной части

#### 4.2 Проверка уникальности email ✅
- [x] **4.2.1** Проверка в `#__radicalmart_telegram_users`:
  - Если найден — ошибка "Этот email уже используется другим пользователем"

- [x] **4.2.2** Проверка в `#__users` (Joomla users):
  - Если найден и не привязан к текущему chat — ошибка с предложением входа через сайт

#### 4.3 Защита от брутфорса OTP ✅
- [x] **4.3.1** Лимит попыток ввода кода:
  - Максимум 5 попыток (MAX_ATTEMPTS = 5)
  - После 5 неудачных — блокировка на 30 минут (LOCKOUT_MINUTES = 30)
  - Счётчик `email_verification_attempts`

- [x] **4.3.2** Лимит запросов нового кода:
  - Не чаще 1 раза в 60 секунд (RESEND_COOLDOWN_SECONDS = 60)

- [x] **4.3.3** Срок действия кода:
  - 15 минут (CODE_EXPIRES_MINUTES = 15)

#### 4.4 Обработка edge cases ✅
- [x] **4.5.1** Email уже подтверждён:
  - При изменении email сбрасывается email_verified=0

- [x] **4.5.2** Пользователь пытается верифицировать чужой email:
  - Код привязан к `chat_id` через БД

- [x] **4.5.3** Смена email после верификации:
  - Сбросить `email_verified=0`, `acymailing_subscribed=0`
  - Очистить код верификации

- [x] **4.5.4** Удаление email:
  - Разрешить очистку поля (email=NULL)

---

### Фаза 5: Интеграция AcyMailing ✅ ЗАВЕРШЕНО

#### 5.1 Хелпер AcyMailingHelper ✅
- [x] **5.1.1** Создан `AcyMailingHelper.php`:
  - `isAvailable()` — проверка установки AcyMailing
  - `isEnabled()` — проверка включения в настройках
  - `subscribe($email, $name, $listId)` — подписка на список
  - `unsubscribe($email, $listId)` — отписка от списка
  - `isSubscribed($email, $listId)` — проверка подписки
  - `getLists()` — получение списков AcyMailing
  - `subscribeAndUpdateFlag($chatId, $email, $name)` — подписка + флаг в БД
  - `unsubscribeAndUpdateFlag($chatId, $email)` — отписка + флаг в БД

- [x] **5.1.2** Интеграция в `EmailVerificationHelper::markVerified()`:
  - При успешной верификации автоматически подписывает на AcyMailing
  - Обновляет флаг `acymailing_subscribed` в БД

#### 5.2 Настройки компонента ✅
- [x] **5.2.1** Добавлено в `config.xml`:
  - `acymailing_enabled` (да/нет, по умолчанию нет)
  - `acymailing_list_id` (ID списка для подписки)
  - `email_ask_after_phone` (спрашивать email после телефона)

---

### Фаза 6: Использование email при создании пользователя ✅ ЗАВЕРШЕНО

#### 6.1 Модификация checkout ✅
- [x] **6.1.1** В `ApiController::checkout()`:
  - Получен `email` из `telegram_users` (если email_verified=1)
  - Передаётся в `RMUserHelper::saveData()`
  - Приоритет: verified telegram email > form email > fake email

#### 6.2 Синхронизация с Joomla User ✅
- [x] **6.2.1** При создании пользователя:
  - Используется реальный verified email из telegram_users
  - Обновляется `telegram_users.user_id`

- [x] **6.2.2** При существующем пользователе:
  - Если email в Joomla фейковый (@fake., .fake, @telegram.) — обновляется на реальный
  - Логирование: `[checkout] Updated Joomla user X email from fake to verified`

---

### Фаза 7: Языковые строки ✅ ЗАВЕРШЕНО

- [ ] **7.1** Добавить константы в `language/ru-RU/com_radicalmart_telegram.ini`:
  ```ini
  ; Сбор email в боте
  COM_RADICALMART_TELEGRAM_ASK_EMAIL="📧 Укажите ваш email для получения уведомлений о заказах:"
  COM_RADICALMART_TELEGRAM_SKIP_EMAIL="Пропустить"
  COM_RADICALMART_TELEGRAM_INVALID_EMAIL="❌ Неверный формат email. Попробуйте снова."
  COM_RADICALMART_TELEGRAM_EMAIL_ALREADY_USED="❌ Этот email уже используется другим пользователем."
  COM_RADICALMART_TELEGRAM_EMAIL_EXISTS_ON_SITE="⚠️ Этот email зарегистрирован на сайте. Войдите через сайт для привязки аккаунта."
  COM_RADICALMART_TELEGRAM_EMAIL_CODE_SENT="✉️ Код подтверждения отправлен на %s.\n\nВведите 6-значный код:"
  COM_RADICALMART_TELEGRAM_EMAIL_VERIFIED="✅ Email подтверждён!"
  COM_RADICALMART_TELEGRAM_EMAIL_CODE_INVALID="❌ Неверный код. Осталось попыток: %d"
  COM_RADICALMART_TELEGRAM_EMAIL_CODE_EXPIRED="❌ Код истёк. Запросите новый."
  COM_RADICALMART_TELEGRAM_EMAIL_TOO_MANY_ATTEMPTS="❌ Слишком много попыток. Попробуйте через 30 минут."
  COM_RADICALMART_TELEGRAM_EMAIL_RATE_LIMIT="⏳ Подождите минуту перед повторной отправкой кода."

  ; UI профиля
  COM_RADICALMART_TELEGRAM_EMAIL="Email"
  COM_RADICALMART_TELEGRAM_EMAIL_VERIFIED_BADGE="Подтверждён"
  COM_RADICALMART_TELEGRAM_EMAIL_NOT_VERIFIED="Не подтверждён"
  COM_RADICALMART_TELEGRAM_VERIFY_EMAIL="Подтвердить email"
  COM_RADICALMART_TELEGRAM_SEND_CODE="Отправить код"
  COM_RADICALMART_TELEGRAM_ENTER_CODE="Введите код"
  COM_RADICALMART_TELEGRAM_SUBSCRIBE_NEWSLETTER="Подписаться на рассылку о новинках и акциях"
  COM_RADICALMART_TELEGRAM_EMAIL_CHANGE_WARNING="При изменении email потребуется повторное подтверждение"
  ```

---

## 📊 Диаграмма потока

```
┌─────────────────────────────────────────────────────────────────┐
│                      СБОР EMAIL В БОТЕ                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────┐    ┌──────────────┐    ┌───────────────────┐     │
│  │ /start   │───►│ Запрос      │───►│ Запрос email      │     │
│  │          │    │ контакта    │    │ + кнопка Пропустить│     │
│  └──────────┘    └──────────────┘    └─────────┬─────────┘     │
│                                                │               │
│                    ┌───────────────────────────┼───────┐       │
│                    ▼                           ▼       │       │
│            ┌──────────────┐            ┌─────────────┐ │       │
│            │ Пропустить   │            │ Ввод email  │ │       │
│            │ email=NULL   │            └──────┬──────┘ │       │
│            └──────┬───────┘                   │        │       │
│                   │                           ▼        │       │
│                   │              ┌────────────────────┐│       │
│                   │              │ Валидация:        ││       │
│                   │              │ • Формат          ││       │
│                   │              │ • Уникальность    ││       │
│                   │              │ • Не в #__users   │├──►ERR │
│                   │              └─────────┬──────────┘│       │
│                   │                        │ OK        │       │
│                   │                        ▼           │       │
│                   │              ┌────────────────────┐│       │
│                   │              │ Отправка OTP      ││       │
│                   │              │ (rate limit!)     ││       │
│                   │              └─────────┬──────────┘│       │
│                   │                        ▼           │       │
│                   │              ┌────────────────────┐│       │
│                   │              │ Ввод кода         ││       │
│                   │              │ (max 5 попыток)   │├──►ERR │
│                   │              └─────────┬──────────┘│       │
│                   │                        │ OK        │       │
│                   │                        ▼           │       │
│                   │              ┌────────────────────┐│       │
│                   │              │ email_verified=1  ││       │
│                   │              │ + AcyMailing      ││       │
│                   │              └─────────┬──────────┘│       │
│                   │                        │           │       │
│                   └────────────────────────┴───────────┘       │
│                                    │                           │
│                                    ▼                           │
│                           ┌──────────────┐                     │
│                           │    idle      │                     │
│                           │  (готово)    │                     │
│                           └──────────────┘                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                   EMAIL В ПРОФИЛЕ (WebApp)                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌────────────────────────────────────────────────────────┐    │
│  │ Настройки профиля                                      │    │
│  │                                                        │    │
│  │  Email: [user@example.com     ] [✓ Подтверждён]       │    │
│  │         └── readonly если verified                     │    │
│  │                                                        │    │
│  │  ИЛИ                                                   │    │
│  │                                                        │    │
│  │  Email: [новый@email.com      ] [Подтвердить]         │    │
│  │                                     │                  │    │
│  │                                     ▼                  │    │
│  │         ┌─────────────────────────────────────────┐   │    │
│  │         │ Модальное окно:                         │   │    │
│  │         │ Код отправлен на новый@email.com       │   │    │
│  │         │                                         │   │    │
│  │         │ Введите код: [______]                   │   │    │
│  │         │                                         │   │    │
│  │         │ [Подтвердить] [Отправить повторно]     │   │    │
│  │         └─────────────────────────────────────────┘   │    │
│  │                                                        │    │
│  │  [✓] Подписаться на рассылку (только если verified)   │    │
│  │                                                        │    │
│  └────────────────────────────────────────────────────────┘    │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## ⚠️ Важные проверки (чеклист перед релизом)

### Безопасность
- [ ] OTP не логируется в открытом виде
- [ ] Rate limiting работает корректно
- [ ] Нет SQL injection в запросах с email
- [ ] Email экранируется при выводе (XSS)
- [ ] Код верификации удаляется после использования

### Логика
- [ ] Нельзя использовать чужой код верификации
- [ ] При смене email сбрасывается verified
- [ ] Нельзя подписаться на рассылку без verified email
- [ ] Корректно обрабатывается timeout кода

### UX
- [ ] Понятные сообщения об ошибках
- [ ] Показывается количество оставшихся попыток
- [ ] Кнопка "Отправить повторно" с таймером
- [ ] Возможность пропустить верификацию email

---

## 📝 Заметки и решения

### Конфликт email с существующим пользователем Joomla
**Решение:** Если email найден в `#__users`, предлагаем привязку:
1. Показать сообщение "Этот email уже зарегистрирован"
2. Предложить войти через сайт и привязать Telegram
3. Или использовать другой email

### Формат фейкового email (если не указан)
Текущий формат RadicalMart: `{phone}_rm_ace@{host}`
После интеграции: использовать реальный email если verified, иначе фейковый.

### AcyMailing vs Joomla Mail
- Для верификации: можно использовать Joomla Mail (проще)
- Для подписки: обязательно AcyMailing API
- Настройка в config.xml какой метод использовать

---

## 🔗 Связанные файлы

- `components/com_radicalmart_telegram/src/Service/UpdateHandler.php` — обработка сообщений бота
- `components/com_radicalmart_telegram/src/Service/SessionStore.php` — хранение состояний
- `components/com_radicalmart_telegram/src/Controller/ApiController.php` — API endpoints
- `components/com_radicalmart_telegram/src/View/Settings/HtmlView.php` — view настроек
- `components/com_radicalmart_telegram/tmpl/settings/default.php` — шаблон настроек
- `administrator/components/com_radicalmart/src/Helper/UserHelper.php` — создание пользователей
