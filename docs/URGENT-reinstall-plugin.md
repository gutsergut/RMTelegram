# СРОЧНАЯ ИНСТРУКЦИЯ: Переустановка плагина Task

**Дата**: 11 ноября 2025
**Проблема**: Константа `PLG_TASK_RADICALMART_TELEGRAM_FETCH_TITLE` вместо текста
**Причина**: Старый файл `services/provider.php` не обновился

## ⚠️ ВАЖНО: Нужна именно УДАЛЕНИЕ + УСТАНОВКА!

Простая установка поверх **НЕ РАБОТАЕТ** из-за кэширования DI контейнера!

## Пошаговая инструкция

### ШАГ 1: Удалить старый плагин

1. Открыть **System → Manage → Plugins**
2. В поиске ввести: `radicalmart telegram fetch`
3. Найти плагин: **Task - RadicalMart Telegram Fetch** или **PLG_TASK_RADICALMART_TELEGRAM_FETCH**
4. **НЕ ОТКЛЮЧАТЬ!** Сразу отметить чекбокс слева от названия
5. В верхнем меню нажать кнопку **Actions → Uninstall** (иконка корзины)
6. Подтвердить удаление в диалоге

**Результат**: Плагин должен исчезнуть из списка

### ШАГ 2: Очистить кэш Joomla

1. Открыть **System → Clear Cache**
2. Нажать кнопку **Check All** (отметить все)
3. Нажать **Delete** (удалить)
4. Дождаться сообщения об успешной очистке

### ШАГ 3: Установить исправленный плагин

**Вариант A: Установка только плагина (быстрее)**

1. Открыть **System → Extensions → Install**
2. Вкладка **Upload Package File**
3. Нажать **Browse** (Обзор)
4. Выбрать файл: `plg_task_radicalmart_telegram_fetch-5.0.2-HOTFIX.zip`
   - Локальный путь: `c:\Users\serge\PhpstormProjects\cacao.land\dist\plg_task_radicalmart_telegram_fetch-5.0.2-HOTFIX.zip`
5. Нажать **Upload & Install**
6. Дождаться сообщения: **"Installation of the plugin was successful"**

**Вариант B: Установка полного пакета**

1. Открыть **System → Extensions → Install**
2. Вкладка **Upload Package File**
3. Нажать **Browse** (Обзор)
4. Выбрать файл: `pkg_radicalmart_telegram-5.0.zip`
   - Локальный путь: `c:\Users\serge\PhpstormProjects\cacao.land\dist\pkg_radicalmart_telegram-5.0.zip`
5. Нажать **Upload & Install**
6. Дождаться сообщения об успешной установке пакета

### ШАГ 4: Проверить плагин

1. Открыть **System → Manage → Plugins**
2. В поиске ввести: `radicalmart telegram`
3. Найти плагин: должно быть **"Task - RadicalMart Telegram Fetch"** (БЕЗ константы!)
4. Проверить статус: должна быть **зелёная галочка** (Enabled)
5. Если красный крестик - нажать на него, чтобы включить

### ШАГ 5: Проверить задачу

1. Открыть **System → Manage → Scheduled Tasks**
2. Найти задачу
3. В колонке **Title** должно быть: **"Обновление ПВЗ ApiShip"** (НЕ константа!)
4. Нажать кнопку ▶️ **Run Now** (в строке задачи справа)
5. Дождаться выполнения (статус должен стать **Last Exit Code: 0**)

### ШАГ 6: Проверить логи

**Через SSH**:
```bash
tail -n 30 /var/www/kakao/data/www/cacao.land/administrator/logs/com_radicalmart.telegram.php
```

**Должны появиться новые записи**:
```
2025-11-11T20:XX:XX+00:00	INFO	...	ApiShip fetch started for providers: yataxi,cdek,x5
2025-11-11T20:XX:XX+00:00	INFO	...	Fetching provider: yataxi
2025-11-11T20:XX:XX+00:00	INFO	...	Provider yataxi: total points = 150
2025-11-11T20:XX:XX+00:00	INFO	...	Provider yataxi: fetched chunk offset=0, size=150
2025-11-11T20:XX:XX+00:00	INFO	...	Provider yataxi: meta updated (last_fetch, last_total=150)
```

**Если логов НЕТ** - значит плагин всё ещё старый!

## Альтернатива: Ручная замена через SSH/SFTP

Если установка через интерфейс не помогает (кэш очень упорный):

### Вариант 1: SSH (самый надёжный)

```bash
# Подключиться к серверу
ssh user@cacao.land

# Перейти в директорию сайта
cd /var/www/kakao/data/www/cacao.land

# Удалить старый плагин полностью
rm -rf plugins/task/radicalmart_telegram_fetch

# Очистить кэш Joomla
rm -rf administrator/cache/*
rm -rf cache/*

# Очистить кэш OPcache PHP
sudo systemctl reload php8.1-fpm
# или
sudo service php-fpm reload
```

Затем установить плагин через интерфейс Joomla (ШАГ 3).

### Вариант 2: SFTP (если нет SSH)

1. **Подключиться по SFTP** к `cacao.land`
2. **Удалить папку**: `/var/www/kakao/data/www/cacao.land/plugins/task/radicalmart_telegram_fetch/`
3. **Загрузить новую папку** из локального репозитория:
   - Локальный путь: `c:\Users\serge\PhpstormProjects\cacao.land\plugins\task\radicalmart_telegram_fetch\`
   - Серверный путь: `/var/www/kakao/data/www/cacao.land/plugins/task/`
4. **Установить права** (через SSH или SFTP):
   ```bash
   chown -R www-data:www-data plugins/task/radicalmart_telegram_fetch
   chmod -R 755 plugins/task/radicalmart_telegram_fetch
   ```
5. Очистить кэш через интерфейс Joomla (ШАГ 2)
6. Проверить (ШАГ 4-6)

## Проверка, что файл правильный

**SSH**:
```bash
cat /var/www/kakao/data/www/cacao.land/plugins/task/radicalmart_telegram_fetch/services/provider.php | grep "use Joomla"
```

**Должно быть**:
```php
use Joomla\Plugin\Task\RadicalmartTelegramFetch\Extension\RadicalMartTelegramFetch;
```

**НЕ должно быть** (с подчёркиванием):
```php
use Joomla\Plugin\Task\Radicalmart_telegram_fetch\Extension\RadicalMartTelegramFetch;
```

## Диагностика: Почему константа всё ещё видна?

### 1. Старый файл services/provider.php
- **Решение**: Полное удаление папки плагина + переустановка

### 2. Кэш Joomla
- **Решение**: System → Clear Cache → Delete All

### 3. Кэш OPcache PHP
- **Решение**: Перезапустить PHP-FPM:
  ```bash
  sudo systemctl reload php8.1-fpm
  ```

### 4. Плагин не включён
- **Решение**: System → Manage → Plugins → Enable плагин

### 5. Языковые файлы не загружаются
- **Проверка**: Если после всех действий константа видна - проверить:
  ```bash
  ls -la /var/www/kakao/data/www/cacao.land/plugins/task/radicalmart_telegram_fetch/language/ru-RU/
  ```
  Должны быть файлы:
  - `plg_task_radicalmart_telegram_fetch.ini`
  - `plg_task_radicalmart_telegram_fetch.sys.ini`

## Файлы для установки

### Только плагин (рекомендуется для переустановки)
**Файл**: `plg_task_radicalmart_telegram_fetch-5.0.2-HOTFIX.zip`
**Путь**: `c:\Users\serge\PhpstormProjects\cacao.land\dist\plg_task_radicalmart_telegram_fetch-5.0.2-HOTFIX.zip`
**Размер**: ~30 KB

### Полный пакет (все компоненты)
**Файл**: `pkg_radicalmart_telegram-5.0.zip`
**Путь**: `c:\Users\serge\PhpstormProjects\cacao.land\dist\pkg_radicalmart_telegram-5.0.zip`
**Размер**: 0.12 MB

## Контрольный чек-лист

После всех действий проверьте:

- [ ] Плагин удалён из списка (System → Manage → Plugins)
- [ ] Кэш очищен (System → Clear Cache)
- [ ] Плагин установлен заново
- [ ] Плагин включён (зелёная галочка в списке)
- [ ] Название плагина **БЕЗ** константы PLG_TASK_...
- [ ] Задача запускается без ошибки "Class not found"
- [ ] В логах `com_radicalmart.telegram.php` появились новые записи
- [ ] В записях видно: "ApiShip fetch started for providers: ..."

## Если ничего не помогло

**Последний вариант**: Полная переустановка через SSH

```bash
# 1. Удалить всё связанное с плагином
cd /var/www/kakao/data/www/cacao.land
rm -rf plugins/task/radicalmart_telegram_fetch
rm -rf administrator/cache/*
rm -rf cache/*

# 2. Удалить из базы (если нужно)
mysql -u username -p database_name -e "DELETE FROM jos_extensions WHERE element='radicalmart_telegram_fetch' AND folder='task';"

# 3. Очистить OPcache
sudo systemctl reload php8.1-fpm

# 4. Перезапустить nginx/apache
sudo systemctl reload nginx
```

Затем установить плагин через интерфейс Joomla заново.

---

**ВАЖНО**: Обязательно выполните **ШАГ 1 (Удаление)** перед установкой! Без удаления файл `services/provider.php` не обновится!

---

**Дата создания**: 11.11.2025
**Автор**: @gutsergut
**Версия**: 2.0
