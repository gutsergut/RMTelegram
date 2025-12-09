# RadicalMart Telegram Bot - Smoke Tests
# Тестирование основных API эндпоинтов
# Запуск: .\tools\smoke-test.ps1 -BaseUrl "https://cacao.land"

param(
    [string]$BaseUrl = "https://cacao.land",
    [string]$ChatId = "test_smoke_123",
    [switch]$Verbose
)

$ErrorActionPreference = "Stop"

# Цвета для вывода
function Write-Success { param($msg) Write-Host "  ✓ $msg" -ForegroundColor Green }
function Write-Fail { param($msg) Write-Host "  ✗ $msg" -ForegroundColor Red }
function Write-Info { param($msg) Write-Host "  ℹ $msg" -ForegroundColor Cyan }
function Write-Header { param($msg) Write-Host "`n=== $msg ===" -ForegroundColor Yellow }

# Счётчики
$script:passed = 0
$script:failed = 0
$script:skipped = 0

# Хелпер для API запросов
function Invoke-ApiTest {
    param(
        [string]$Name,
        [string]$Url,
        [string]$Method = "GET",
        [hashtable]$Body = @{},
        [scriptblock]$Validate
    )

    try {
        $fullUrl = "$BaseUrl/$Url"

        if ($Method -eq "GET") {
            $response = Invoke-RestMethod -Uri $fullUrl -Method GET -TimeoutSec 30
        } else {
            $response = Invoke-RestMethod -Uri $fullUrl -Method POST -Body $Body -TimeoutSec 30
        }

        if ($Validate) {
            $result = & $Validate $response
            if ($result -eq $true) {
                Write-Success "$Name"
                $script:passed++
            } else {
                Write-Fail "$Name - validation failed: $result"
                $script:failed++
            }
        } else {
            Write-Success "$Name (response received)"
            $script:passed++
        }

        if ($Verbose) {
            Write-Info "Response: $($response | ConvertTo-Json -Depth 3 -Compress)"
        }

        return $response
    }
    catch {
        $statusCode = $_.Exception.Response.StatusCode.value__
        if ($statusCode -eq 401 -or $statusCode -eq 403) {
            Write-Info "$Name - auth required (expected)"
            $script:skipped++
        } else {
            Write-Fail "$Name - Error: $($_.Exception.Message)"
            $script:failed++
        }
        return $null
    }
}

# ========================================
# ТЕСТЫ
# ========================================

Write-Host "`n╔════════════════════════════════════════════╗" -ForegroundColor Magenta
Write-Host "║  RadicalMart Telegram Bot - Smoke Tests    ║" -ForegroundColor Magenta
Write-Host "╚════════════════════════════════════════════╝" -ForegroundColor Magenta
Write-Host "Base URL: $BaseUrl"
Write-Host "Chat ID: $ChatId"

# --- 1. Каталог ---
Write-Header "1. КАТАЛОГ (api.list)"

Invoke-ApiTest -Name "Получение списка товаров" `
    -Url "index.php?option=com_radicalmart_telegram&task=api.list&chat=$ChatId&format=raw" `
    -Validate {
        param($r)
        if ($r.success -eq $true -and $r.data.items) { $true }
        else { "success=$($r.success), items count=$($r.data.items.Count)" }
    }

Invoke-ApiTest -Name "Каталог с лимитом" `
    -Url "index.php?option=com_radicalmart_telegram&task=api.list&chat=$ChatId&limit=5&format=raw" `
    -Validate {
        param($r)
        if ($r.success -eq $true) { $true } else { "success=$($r.success)" }
    }

# --- 2. Поиск ---
Write-Header "2. ПОИСК (api.search)"

Invoke-ApiTest -Name "Поиск товаров" `
    -Url "index.php?option=com_radicalmart_telegram&task=api.search&chat=$ChatId&q=шоколад&format=raw" `
    -Validate {
        param($r)
        if ($r.success -eq $true) { $true } else { "success=$($r.success)" }
    }

# --- 3. Товар ---
Write-Header "3. ТОВАР (api.product)"

# Сначала получаем ID товара из каталога
$catalogResponse = Invoke-ApiTest -Name "Получение ID товара для теста" `
    -Url "index.php?option=com_radicalmart_telegram&task=api.list&chat=$ChatId&limit=1&format=raw"

if ($catalogResponse -and $catalogResponse.data.items -and $catalogResponse.data.items.Count -gt 0) {
    $testProductId = $catalogResponse.data.items[0].id
    Write-Info "Тестовый товар ID: $testProductId"

    Invoke-ApiTest -Name "Получение карточки товара" `
        -Url "index.php?option=com_radicalmart_telegram&task=api.product&chat=$ChatId&id=$testProductId&format=raw" `
        -Validate {
            param($r)
            if ($r.success -eq $true -and $r.data.id) { $true }
            else { "success=$($r.success), id=$($r.data.id)" }
        }
}

# --- 4. Корзина ---
Write-Header "4. КОРЗИНА (api.cart, api.add)"

Invoke-ApiTest -Name "Получение корзины" `
    -Url "index.php?option=com_radicalmart_telegram&task=api.cart&chat=$ChatId&format=raw" `
    -Validate {
        param($r)
        if ($r.PSObject.Properties.Name -contains 'success' -or $r.PSObject.Properties.Name -contains 'items') { $true }
        else { "unexpected response structure" }
    }

# --- 5. Методы доставки/оплаты ---
Write-Header "5. МЕТОДЫ (api.methods)"

Invoke-ApiTest -Name "Получение методов доставки и оплаты" `
    -Url "index.php?option=com_radicalmart_telegram&task=api.methods&chat=$ChatId&format=raw" `
    -Validate {
        param($r)
        if ($r.success -eq $true) { $true } else { "success=$($r.success)" }
    }

# --- 6. ПВЗ ---
Write-Header "6. ПВЗ (api.pvz)"

# Тестовый bbox для Москвы
$bbox = "37.5,55.7,37.7,55.8"

Invoke-ApiTest -Name "Получение ПВЗ по bbox" `
    -Url "index.php?option=com_radicalmart_telegram&task=api.pvz&chat=$ChatId&bbox=$bbox&limit=10&format=raw" `
    -Validate {
        param($r)
        if ($r.success -eq $true) { $true } else { "success=$($r.success)" }
    }

# --- 7. Бонусы ---
Write-Header "7. БОНУСЫ (api.bonuses)"

Invoke-ApiTest -Name "Получение информации о бонусах" `
    -Url "index.php?option=com_radicalmart_telegram&task=api.bonuses&chat=$ChatId&format=raw" `
    -Validate {
        param($r)
        # Может вернуть ошибку если пользователь не авторизован - это ОК
        $true
    }

# --- 8. Юридические документы ---
Write-Header "8. LEGAL (api.legal)"

Invoke-ApiTest -Name "Получение оферты (terms)" `
    -Url "index.php?option=com_radicalmart_telegram&task=api.legal&chat=$ChatId&type=terms&format=raw" `
    -Validate {
        param($r)
        if ($r.success -eq $true -and $r.data.html) { $true } else { "success=$($r.success)" }
    }

Invoke-ApiTest -Name "Получение политики конфиденциальности (privacy)" `
    -Url "index.php?option=com_radicalmart_telegram&task=api.legal&chat=$ChatId&type=privacy&format=raw" `
    -Validate {
        param($r)
        if ($r.success -eq $true) { $true } else { "success=$($r.success) (может быть не настроено)" }
    }

# --- 9. Согласия ---
Write-Header "9. СОГЛАСИЯ (api.consents)"

Invoke-ApiTest -Name "Получение статуса согласий" `
    -Url "index.php?option=com_radicalmart_telegram&task=api.consents&chat=$ChatId&format=raw" `
    -Validate {
        param($r)
        $true  # Любой ответ OK
    }

# --- 10. Summary ---
Write-Header "10. SUMMARY (api.summary)"

Invoke-ApiTest -Name "Получение итогов заказа" `
    -Url "index.php?option=com_radicalmart_telegram&task=api.summary&chat=$ChatId&format=raw" `
    -Validate {
        param($r)
        $true  # Любой ответ OK - может требовать корзину
    }

# --- 11. Профиль ---
Write-Header "11. ПРОФИЛЬ (api.profile)"

Invoke-ApiTest -Name "Получение профиля пользователя" `
    -Url "index.php?option=com_radicalmart_telegram&task=api.profile&chat=$ChatId&format=raw" `
    -Validate {
        param($r)
        $true  # Может требовать авторизацию
    }

# --- 12. Заказы ---
Write-Header "12. ЗАКАЗЫ (api.orders)"

Invoke-ApiTest -Name "Получение списка заказов" `
    -Url "index.php?option=com_radicalmart_telegram&task=api.orders&chat=$ChatId&format=raw" `
    -Validate {
        param($r)
        $true  # Может требовать авторизацию
    }

# --- 13. Facets (фильтры) ---
Write-Header "13. ФИЛЬТРЫ (api.facets)"

Invoke-ApiTest -Name "Получение доступных фильтров" `
    -Url "index.php?option=com_radicalmart_telegram&task=api.facets&chat=$ChatId&format=raw" `
    -Validate {
        param($r)
        if ($r.success -eq $true) { $true } else { "success=$($r.success)" }
    }

# ========================================
# ИТОГИ
# ========================================

Write-Host "`n╔════════════════════════════════════════════╗" -ForegroundColor Magenta
Write-Host "║               РЕЗУЛЬТАТЫ                   ║" -ForegroundColor Magenta
Write-Host "╚════════════════════════════════════════════╝" -ForegroundColor Magenta

$total = $script:passed + $script:failed + $script:skipped

Write-Host ""
Write-Host "  Всего тестов:  $total" -ForegroundColor White
Write-Host "  ✓ Пройдено:    $($script:passed)" -ForegroundColor Green
Write-Host "  ✗ Провалено:   $($script:failed)" -ForegroundColor Red
Write-Host "  ⊘ Пропущено:   $($script:skipped)" -ForegroundColor Yellow
Write-Host ""

if ($script:failed -eq 0) {
    Write-Host "  ★ Все тесты пройдены успешно! ★" -ForegroundColor Green
    exit 0
} else {
    Write-Host "  ⚠ Есть проваленные тесты!" -ForegroundColor Red
    exit 1
}
