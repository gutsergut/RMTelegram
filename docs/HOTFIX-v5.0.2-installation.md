# HOTFIX v5.0.2: Инструкция по установке

**Дата**: 11 ноября 2025  
**Проблема**: `Class "Joomla\Plugin\Task\Radicalmart_telegram_fetch\Extension\RadicalMartTelegramFetch" not found`

## Причина ошибки

На сервере остался старый файл `services/provider.php` со старым namespace. Joomla кэширует этот файл и не обновляет его при простой установке поверх.

## Решение: Переустановка плагина

### Вариант 1: Через интерфейс Joomla (рекомендуется)

#### Шаг 1: Отключить плагин
1. **System → Manage → Plugins**
2. Найти: **Task - RadicalMart Telegram: обновление ПВЗ ApiShip**
3. Нажать на кнопку **Disable** (Status должен стать красным крестиком)

#### Шаг 2: Удалить плагин
1. В списке плагинов отметить чекбокс у плагина
2. Нажать кнопку **Uninstall** (мусорная корзина в toolbar)
3. Подтвердить удаление

⚠️ **Важно**: Удаление плагина **НЕ удаляет** настроенные задачи в Scheduled Tasks!

#### Шаг 3: Установить исправленный пакет
1. **System → Extensions → Install**
2. **Upload Package File**
3. Выбрать: `pkg_radicalmart_telegram-5.0.zip`
4. Нажать **Upload & Install**
5. Дождаться сообщения "Installation of the package was successful"

#### Шаг 4: Включить плагин
1. **System → Manage → Plugins**
2. Найти: **Task - RadicalMart Telegram: обновление ПВЗ ApiShip**
3. Нажать на кнопку **Enable** (Status должен стать зелёной галочкой)

#### Шаг 5: Проверить работу
1. **System → Manage → Scheduled Tasks**
2. Должна отображаться задача с нормальным названием (не константа!)
3. Нажать ▶️ **Run Now**
4. **Не должно быть ошибки** "Class not found"

### Вариант 2: Через SSH (быстрее)

```bash
# Перейти в директорию сайта
cd /var/www/kakao/data/www/cacao.land

# Удалить старый плагин
rm -rf plugins/task/radicalmart_telegram_fetch

# Загрузить новый пакет (если уже на сервере)
cd administrator/components/com_installer

# Или загрузить через SFTP пакет pkg_radicalmart_telegram-5.0.zip
# и установить через интерфейс Joomla

# Очистить кэш Joomla
rm -rf administrator/cache/*
rm -rf cache/*

# Перезапустить PHP-FPM (если нужно)
sudo systemctl reload php8.1-fpm
# или
sudo service php-fpm reload
```

### Вариант 3: Через FTP/SFTP (ручная замена)

#### Шаг 1: Скачать правильные файлы
Из GitHub: https://github.com/gutsergut/RMTelegram/tree/main/plugins/task/radicalmart_telegram_fetch

Или из локального репозитория:
```
c:\Users\serge\PhpstormProjects\cacao.land\github\RMTelegram\plugins\task\radicalmart_telegram_fetch\
```

#### Шаг 2: Заменить файлы на сервере

Подключиться по SFTP к серверу и заменить файлы в:
```
/var/www/kakao/data/www/cacao.land/plugins/task/radicalmart_telegram_fetch/
```

**Обязательно заменить**:
- ✅ `radicalmart_telegram_fetch.xml`
- ✅ `src/Extension/RadicalMartTelegramFetch.php`
- ✅ **`services/provider.php`** ← КРИТИЧНО!

#### Шаг 3: Очистить кэш

**Через Joomla**:
- System → Clear Cache → Check All → Delete

**Или через SSH**:
```bash
rm -rf /var/www/kakao/data/www/cacao.land/administrator/cache/*
rm -rf /var/www/kakao/data/www/cacao.land/cache/*
```

#### Шаг 4: Проверить
- System → Manage → Scheduled Tasks
- Запустить задачу
- Не должно быть ошибки

## Проверка правильности установки

### 1. Проверить файл на сервере

**SSH**:
```bash
cd /var/www/kakao/data/www/cacao.land/plugins/task/radicalmart_telegram_fetch/services
grep "RadicalmartTelegramFetch" provider.php
```

**Должно вывести**:
```php
use Joomla\Plugin\Task\RadicalmartTelegramFetch\Extension\RadicalMartTelegramFetch;
```

**НЕ должно быть**:
```php
use Joomla\Plugin\Task\Radicalmart_telegram_fetch\Extension\RadicalMartTelegramFetch;
```

### 2. Проверить языковую константу

**System → Manage → Scheduled Tasks**

В колонке **Title** должно быть:
- ✅ **"Обновление ПВЗ ApiShip"**

НЕ должно быть:
- ❌ `PLG_TASK_RADICALMART_TELEGRAM_FETCH_TITLE`

### 3. Запустить задачу

**System → Manage → Scheduled Tasks**
- Найти задачу
- Нажать ▶️ **Run Now**
- Дождаться завершения

**Не должно быть ошибки**:
```
Class "Joomla\Plugin\Task\Radicalmart_telegram_fetch\Extension\RadicalMartTelegramFetch" not found
```

### 4. Проверить логи

**SSH**:
```bash
tail -n 20 /var/www/kakao/data/www/cacao.land/administrator/logs/com_radicalmart.telegram.php
```

**Должны быть записи**:
```
2025-11-11T...	INFO	...	ApiShip fetch started for providers: yataxi, cdek, x5
2025-11-11T...	INFO	...	Fetching provider: yataxi
2025-11-11T...	INFO	...	Provider yataxi: total points = 150
```

## Почему простая установка поверх не помогла?

Joomla 5 использует **Dependency Injection (DI) контейнер**, который загружает файл `services/provider.php` **один раз** и кэширует его.

При установке пакета поверх существующего расширения Joomla:
1. ✅ Обновляет XML файлы
2. ✅ Обновляет PHP классы
3. ✅ Обновляет языковые файлы
4. ❌ **НЕ перезагружает** DI контейнер
5. ❌ **НЕ очищает** кэш автозагрузки

**Решение**: полная переустановка (uninstall → install) или ручная замена + очистка кэша.

## Файлы, которые были исправлены в v5.0.2

1. `radicalmart_telegram_fetch.xml` - namespace в теге `<namespace>`
2. `src/Extension/RadicalMartTelegramFetch.php` - namespace в `namespace ...;` и добавлено логирование
3. **`services/provider.php`** - namespace в `use ...;` ← **КРИТИЧНО для DI!**

## Коммиты с исправлениями

- **5d5d6c7** - исправлены XML + Extension.php
- **547ccb8** - добавлена документация
- **0ba1a73** - HOTFIX: services/provider.php

## Пакет для установки

**Локальный путь**:
```
c:\Users\serge\PhpstormProjects\cacao.land\dist\pkg_radicalmart_telegram-5.0.zip
```

**Размер**: 0.12 MB

**GitHub**: https://github.com/gutsergut/RMTelegram

## Что делать после установки?

1. ✅ Проверить, что плагин включён (Enabled)
2. ✅ Запустить тестовый прогон задачи
3. ✅ Проверить логи на наличие записей
4. ✅ Настроить расписание (если ещё не настроено):
   - **Frequency**: Weekly
   - **Weekday**: Sunday
   - **Time**: 03:00
   - **Cron Expression**: `0 3 * * 0`

## Поддержка

Если проблема сохраняется после переустановки:

1. Проверить содержимое `services/provider.php` на сервере
2. Очистить весь кэш Joomla (System → Clear Cache)
3. Перезапустить PHP-FPM: `sudo systemctl reload php8.1-fpm`
4. Проверить права на файлы: `chown -R www-data:www-data plugins/task/radicalmart_telegram_fetch`

---

**Дата создания**: 11.11.2025  
**Автор**: @gutsergut  
**Версия**: 1.0
