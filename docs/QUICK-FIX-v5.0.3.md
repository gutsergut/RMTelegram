# Быстрое решение проблемы v5.0.3

## Проблема
Константа `PLG_TASK_RADICALMART_TELEGRAM_FETCH_TITLE` вместо текста = старые файлы на сервере

## Проверка (выполните на сервере)

```bash
# Проверить routine ID в файле
grep "TASKS_MAP" /var/www/kakao/data/www/cacao.land/plugins/task/radicalmart_telegram_fetch/src/Extension/RadicalMartTelegramFetch.php -A 3
```

**Если увидите** `plg_task_radicalmart_telegram_fetch_apiship` = файлы старые!

## Решение: Полная переустановка через SSH

```bash
cd /var/www/kakao/data/www/cacao.land

# 1. Удалить старые файлы
rm -rf plugins/task/radicalmart_telegram_fetch

# 2. Удалить задачи (чтобы создать заново)
mysql -u kakao_shop -p'kakao_shop_MZpW' cacao <<EOF
DELETE FROM tw9cs_scheduler_tasks WHERE type LIKE '%radicalmart%fetch%';
DELETE FROM tw9cs_extensions WHERE element='radicalmart_telegram_fetch' AND folder='task';
EOF

# 3. Очистить кэш
rm -rf administrator/cache/* cache/*
sudo systemctl reload php8.1-fpm

echo "Готово! Теперь установите плагин через Joomla интерфейс"
```

## После удаления - установите через Joomla

1. **System → Extensions → Install**
2. Upload файл: `plg_task_radicalmart_telegram_fetch-5.0.3-FIXED.zip`
3. Install
4. **System → Manage → Plugins** → включить плагин
5. **System → Manage → Scheduled Tasks → New**
   - Task Type: выбрать из списка "RadicalMart Telegram..."
   - Title: "Обновление ПВЗ ApiShip"
   - Frequency: Weekly, Sunday, 03:00
   - Save
6. Run Now → проверить логи

## Альтернатива: Загрузить правильные файлы через SFTP

1. Скачать с GitHub правильную версию
2. Удалить на сервере: `/var/www/kakao/data/www/cacao.land/plugins/task/radicalmart_telegram_fetch/`
3. Загрузить через SFTP из: `c:\Users\serge\PhpstormProjects\cacao.land\plugins\task\radicalmart_telegram_fetch\`
4. Установить права:
   ```bash
   chown -R www-data:www-data plugins/task/radicalmart_telegram_fetch
   chmod -R 755 plugins/task/radicalmart_telegram_fetch
   ```
5. Очистить кэш
6. Проверить

## Проверка после установки

```bash
# Должно показать: radicalmart_telegram.fetch
grep "radicalmart_telegram.fetch" /var/www/kakao/data/www/cacao.land/plugins/task/radicalmart_telegram_fetch/src/Extension/RadicalMartTelegramFetch.php

# Должно показать: RadicalmartTelegramFetch (CamelCase)
grep "RadicalmartTelegramFetch" /var/www/kakao/data/www/cacao.land/plugins/task/radicalmart_telegram_fetch/services/provider.php
```

---

**Выполните команду проверки и пришлите результат!**
