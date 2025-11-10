# Диагностика плагина Task v5.0.3

## Проверка 1: Задача существует?

**SSH**:
```bash
mysql -u kakao_shop -p'kakao_shop_MZpW' kakao_shop -e "SELECT id, title, type, state, last_exit_code FROM jos_scheduler_tasks WHERE type LIKE '%radicalmart%';"
```

**Ожидаемый результат**:
```
+----+-------+------------------------------+-------+----------------+
| id | title | type                         | state | last_exit_code |
+----+-------+------------------------------+-------+----------------+
|  X | ...   | radicalmart_telegram.fetch   |   1   |      0         |
+----+-------+------------------------------+-------+----------------+
```

**Если задачи НЕТ**: создайте её вручную через `System → Manage → Scheduled Tasks → New`

## Проверка 2: Плагин включён?

**SSH**:
```bash
mysql -u kakao_shop -p'kakao_shop_MZpW' kakao_shop -e "SELECT extension_id, name, element, enabled FROM jos_extensions WHERE element='radicalmart_telegram_fetch' AND folder='task';"
```

**Должно быть**: `enabled = 1`

**Если enabled = 0**:
```sql
UPDATE jos_extensions SET enabled=1 WHERE element='radicalmart_telegram_fetch' AND folder='task';
```

## Проверка 3: Файлы на месте?

**SSH**:
```bash
ls -la /var/www/kakao/data/www/cacao.land/plugins/task/radicalmart_telegram_fetch/
```

**Должны быть**:
- `radicalmart_telegram_fetch.xml`
- `services/provider.php`
- `src/Extension/RadicalMartTelegramFetch.php`
- `language/ru-RU/*.ini`

## Проверка 4: Routine ID правильный?

**SSH**:
```bash
grep -n "TASKS_MAP" /var/www/kakao/data/www/cacao.land/plugins/task/radicalmart_telegram_fetch/src/Extension/RadicalMartTelegramFetch.php
```

**Должно быть**:
```php
protected const TASKS_MAP = [
    'radicalmart_telegram.fetch' => [
```

**НЕ должно быть**: `plg_task_radicalmart_telegram_fetch_apiship`

## Проверка 5: Namespace правильный?

**SSH**:
```bash
grep "namespace\|use.*RadicalMart" /var/www/kakao/data/www/cacao.land/plugins/task/radicalmart_telegram_fetch/services/provider.php
```

**Должно быть**:
```php
use Joomla\Plugin\Task\RadicalmartTelegramFetch\Extension\RadicalMartTelegramFetch;
```

**НЕ должно быть**: `Radicalmart_telegram_fetch` (со snake_case)

## Проверка 6: Запуск задачи вручную

**Через UI**:
1. `System → Manage → Scheduled Tasks`
2. Найти задачу (если есть)
3. Нажать ▶️ `Run Now`
4. Смотреть на статус

**Или через CLI**:
```bash
cd /var/www/kakao/data/www/cacao.land
php cli/joomla.php scheduler:run
```

**Проверить логи**:
```bash
tail -n 50 /var/www/kakao/data/www/cacao.land/administrator/logs/com_radicalmart.telegram.php
```

## Проверка 7: Кэш очищен?

**SSH**:
```bash
rm -rf /var/www/kakao/data/www/cacao.land/administrator/cache/*
rm -rf /var/www/kakao/data/www/cacao.land/cache/*
sudo systemctl reload php8.1-fpm
```

## Если задача НЕ создалась автоматически

### Создать вручную:

1. **System → Manage → Scheduled Tasks → New**
2. **Task Type**: выбрать "RadicalMart Telegram: обновление ПВЗ ApiShip" из выпадающего списка
   - Если НЕТ в списке = плагин не загружается!
3. **Title**: "Обновление ПВЗ ApiShip"
4. **Frequency**: Weekly
5. **Weekday**: Sunday
6. **Time**: 03:00
7. **Save**

### Если тип задачи НЕТ в выпадающем списке:

**ПРОБЛЕМА**: Плагин не регистрируется в системе!

**Решение**:
1. Удалить плагин полностью
2. Очистить весь кэш
3. Перезапустить PHP-FPM
4. Переустановить плагин
5. Проверить логи ошибок: `administrator/logs/error.php`

## Диагностика &ndash; вместо названия

`&ndash;` появляется, когда:
1. ✅ Плагин установлен
2. ✅ Языковые файлы загружаются
3. ❌ Но routine ID не найден в advertiseRoutines

**Причины**:
- Плагин не включён (enabled=0)
- Кэш не очищен
- Файлы на сервере старые (не обновились)

## Быстрая проверка всех параметров (одной командой)

**SSH**:
```bash
cd /var/www/kakao/data/www/cacao.land

echo "=== 1. Плагин в БД ==="
mysql -u kakao_shop -p'kakao_shop_MZpW' kakao_shop -e "SELECT extension_id, name, enabled FROM jos_extensions WHERE element='radicalmart_telegram_fetch';"

echo ""
echo "=== 2. Задачи в БД ==="
mysql -u kakao_shop -p'kakao_shop_MZpW' kakao_shop -e "SELECT id, title, type, state FROM jos_scheduler_tasks WHERE type LIKE '%radical%';"

echo ""
echo "=== 3. Файлы плагина ==="
ls -1 plugins/task/radicalmart_telegram_fetch/

echo ""
echo "=== 4. Routine ID ==="
grep "radicalmart_telegram.fetch" plugins/task/radicalmart_telegram_fetch/src/Extension/RadicalMartTelegramFetch.php

echo ""
echo "=== 5. Namespace в provider.php ==="
grep "RadicalmartTelegramFetch" plugins/task/radicalmart_telegram_fetch/services/provider.php

echo ""
echo "=== 6. Последние логи ==="
tail -n 10 administrator/logs/com_radicalmart.telegram.php 2>/dev/null || echo "Лог файл не найден"
```

## Что делать дальше?

1. Запустите быструю проверку (команда выше)
2. Скопируйте вывод
3. Я помогу определить проблему

---

**Дата**: 11.11.2025  
**Версия**: 5.0.3
