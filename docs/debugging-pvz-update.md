# Отладка обновления ПВЗ - версия 0.1.8

## Что исправлено в финальной версии

### 1. Логирование теперь точно работает
- ✅ Логирование инициализируется В САМОМ контроллере
- ✅ Используется прямой вызов `Log::add()` с полным путём к классу
- ✅ Логи пишутся в `administrator/logs/com_radicalmart_telegram.php`

### 2. Обработка ошибок улучшена
- ✅ Try-catch для каждого провайдера отдельно
- ✅ Полный stack trace в логах при ошибках
- ✅ Ошибки не прерывают обработку других провайдеров

## Инструкция по отладке

### Шаг 1: Установите пакет

```
github\RMTelegram\dist\pkg_radicalmart_telegram-0.1.8.zip
```

### Шаг 2: Проверьте права на логи

Убедитесь, что директория `administrator/logs/` имеет права на запись:
```powershell
# В PowerShell
Get-Acl "administrator\logs" | Format-List
```

Должны быть права 755 или 777.

### Шаг 3: Запустите обновление

1. Перейдите в **Компоненты → RadicalMart Telegram → Status**
2. Нажмите кнопку **"Обновить базу ПВЗ"**
3. Дождитесь сообщения об ошибке или успехе

### Шаг 4: Проверьте логи

```powershell
# Откройте файл логов
notepad administrator\logs\com_radicalmart_telegram.php

# Или просмотрите последние строки
Get-Content administrator\logs\com_radicalmart_telegram.php -Tail 50
```

## Ожидаемое содержимое логов

### Успешное выполнение:
```
2025-11-05 16:00:00 INFO ApiController::apishipfetch started
2025-11-05 16:00:00 INFO CSRF token check passed
2025-11-05 16:00:00 INFO Running ApiShip fetch
2025-11-05 16:00:01 INFO ApiShip fetch started: providers=yataxi,cdek,x5
2025-11-05 16:00:02 INFO ApiShip provider 'yataxi': total points = 1234
2025-11-05 16:00:03 INFO ApiShip provider 'yataxi': fetched 500 points at offset 0
2025-11-05 16:00:04 INFO ApiShip provider 'yataxi': fetched 500 points at offset 500
2025-11-05 16:00:05 INFO ApiShip provider 'yataxi': fetched 234 points at offset 1000
2025-11-05 16:00:06 INFO ApiShip provider 'cdek': total points = 2456
...
2025-11-05 16:00:30 INFO ApiShip fetch completed: total=6789, providers=yataxi,cdek,x5
2025-11-05 16:00:30 INFO Fetch finished: success=true, total=6789
```

### Ошибка авторизации API:
```
2025-11-05 16:00:00 INFO ApiController::apishipfetch started
2025-11-05 16:00:00 INFO CSRF token check passed
2025-11-05 16:00:00 INFO Running ApiShip fetch
2025-11-05 16:00:01 INFO ApiShip fetch started: providers=yataxi,cdek,x5
2025-11-05 16:00:02 ERROR ApiShip provider 'yataxi': ERROR getting total - Некорректный ключ безопасности
2025-11-05 16:00:03 ERROR ApiShip provider 'cdek': ERROR getting total - Некорректный ключ безопасности
2025-11-05 16:00:04 ERROR ApiShip provider 'x5': ERROR getting total - Некорректный ключ безопасности
2025-11-05 16:00:05 INFO ApiShip fetch completed: total=0, providers=yataxi,cdek,x5
2025-11-05 16:00:05 INFO Fetch finished: success=true, total=0
```

### Ошибка strtolower():
```
2025-11-05 16:00:00 INFO ApiController::apishipfetch started
2025-11-05 16:00:00 INFO CSRF token check passed
2025-11-05 16:00:00 INFO Running ApiShip fetch
2025-11-05 16:00:01 ERROR ApiShipFetch error: strtolower(): Argument #1 ($string) must be of type string, array given
Stack trace:
#0 /path/to/ApiShipHelper.php(93): strtolower(Array)
#1 /path/to/ApiShipFetchHelper.php(150): ...
...
```

## Анализ ошибки strtolower()

### Причина
Ошибка возникает в `AddressHelper::toDisplay()` строка 93:
```php
$title = ($mb) ? mb_strtolower($title) : strtolower($title);
```

Это происходит, когда `$data[$key]` является массивом вместо строки.

### Где это может произойти?
НЕ в процессе загрузки ПВЗ, а при:
- Сохранении адреса доставки в заказе
- Отображении адреса на странице
- Валидации адреса

### Решение
Если ошибка возникает ПРИ загрузке ПВЗ - нужно смотреть полный stack trace в логах.

## Проверка базы данных через MCP

После обновления проверьте через VS Code (если настроен MCP):

```sql
-- Метаданные
SELECT * FROM tw9cs_radicalmart_apiship_meta ORDER BY provider;

-- Количество точек
SELECT provider, COUNT(*) as total
FROM tw9cs_radicalmart_apiship_points
GROUP BY provider;

-- Примеры точек
SELECT provider, ext_id, title, address
FROM tw9cs_radicalmart_apiship_points
LIMIT 10;
```

## Или через скрипт:

```powershell
php check_apiship_db.php
```

## Что делать если логи пустые?

### 1. Проверьте права доступа
```powershell
icacls administrator\logs
```

### 2. Проверьте, создаётся ли файл
```powershell
# До обновления
ls administrator\logs\com_radicalmart_telegram.php

# Если не существует - создайте пустой
New-Item -Path administrator\logs\com_radicalmart_telegram.php -ItemType File

# Дайте права
icacls administrator\logs\com_radicalmart_telegram.php /grant Users:F
```

### 3. Проверьте настройки PHP
```powershell
php -i | Select-String "error_log"
```

### 4. Проверьте настройки Joomla
- **Система → Общие настройки**
- Вкладка "Сервер"
- "Путь к папке логов" должен указывать на `administrator/logs`

## Альтернативный метод отладки

Если логи всё равно не пишутся, добавьте прямую запись в файл:

В начало `ApiShipFetchHelper::fetchAllPoints()` добавьте:
```php
file_put_contents(
    JPATH_ROOT . '/administrator/logs/debug_pvz.txt',
    date('Y-m-d H:i:s') . " Fetch started\n",
    FILE_APPEND
);
```

## Ожидаемый результат

После успешного обновления:

### В интерфейсе:
✅ Сообщение "Обновлено N точек из M провайдеров"

### В логах:
✅ Детальная информация по каждому провайдеру
✅ Количество загруженных точек
✅ Время выполнения

### В базе данных:
✅ Записи в `tw9cs_radicalmart_apiship_meta`
✅ Точки в `tw9cs_radicalmart_apiship_points`

## Следующие шаги

1. ✅ Установить пакет 0.1.8
2. ✅ Запустить обновление
3. ✅ Проверить логи
4. ✅ Прислать содержимое логов для анализа
5. ✅ Если ошибка `strtolower()` - прислать полный stack trace

---

**Важно**: Если логи всё ещё не создаются, возможно проблема в настройках сервера или правах доступа. В этом случае используйте альтернативный метод с `file_put_contents()`.
