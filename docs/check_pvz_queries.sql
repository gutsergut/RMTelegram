-- SQL запросы для проверки загруженных точек ПВЗ

-- 1. Количество точек по провайдерам
SELECT
    provider,
    COUNT(*) as total,
    MIN(updated_at) as first_update,
    MAX(updated_at) as last_update
FROM tw9cs_radicalmart_apiship_points
GROUP BY provider
ORDER BY total DESC;

-- 2. Общее количество точек
SELECT
    COUNT(*) as total_points,
    COUNT(DISTINCT provider) as total_providers
FROM tw9cs_radicalmart_apiship_points;

-- 3. Примеры точек из каждого провайдера (первые 3)
SELECT provider, ext_id, title, address, lat, lon, updated_at
FROM tw9cs_radicalmart_apiship_points
WHERE provider = 'yataxi'
ORDER BY updated_at DESC
LIMIT 3;

SELECT provider, ext_id, title, address, lat, lon, updated_at
FROM tw9cs_radicalmart_apiship_points
WHERE provider = 'cdek'
ORDER BY updated_at DESC
LIMIT 3;

SELECT provider, ext_id, title, address, lat, lon, updated_at
FROM tw9cs_radicalmart_apiship_points
WHERE provider = 'x5'
ORDER BY updated_at DESC
LIMIT 3;

-- 4. Проверка на дубликаты
SELECT provider, ext_id, COUNT(*) as duplicates
FROM tw9cs_radicalmart_apiship_points
GROUP BY provider, ext_id
HAVING COUNT(*) > 1;

-- 5. Точки с пустыми координатами (должно быть 0)
SELECT COUNT(*) as invalid_coords
FROM tw9cs_radicalmart_apiship_points
WHERE lat = 0 OR lon = 0 OR lat IS NULL OR lon IS NULL;

-- Ожидаемые результаты:
-- yataxi: ~17,576 точек
-- cdek:   ~7,532 точки
-- x5:     ~24,791 точка
-- TOTAL:  ~49,899 точек
