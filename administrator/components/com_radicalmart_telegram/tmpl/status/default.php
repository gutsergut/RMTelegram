<?php
/** @var Joomla\Component\RadicalMartTelegram\Administrator\View\Status\HtmlView $this */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

?>
<div class="container-fluid">
    <h2><?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_STATUS_TITLE'); ?></h2>
    <p class="small text-muted"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_STATUS_DESC'); ?></p>

    <div class="mb-3">
        <button id="btnStartFetch" type="button" class="btn btn-success">
            <span class="icon-refresh" aria-hidden="true"></span>
            <?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_STATUS_FETCH'); ?>
        </button>
        <button id="btnCancelFetch" type="button" class="btn btn-danger d-none">
            <span class="icon-cancel" aria-hidden="true"></span>
            <?php echo Text::_('JCANCEL'); ?>
        </button>
        <button id="btnDbCheck" type="button" class="btn btn-secondary">
            <span class="icon-database" aria-hidden="true"></span>
            Проверить базу
        </button>
        <a href="<?php echo Route::_('index.php?option=com_radicalmart_telegram&task=api.resetInactivePvz&' . \Joomla\CMS\Session\Session::getFormToken() . '=1'); ?>"
           class="btn btn-warning"
           onclick="return confirm('<?php echo Text::_('COM_RADICALMART_TELEGRAM_RESET_INACTIVE_PVZ_DESC'); ?>');">
            <span class="icon-unpublish" aria-hidden="true"></span>
            <?php echo Text::_('COM_RADICALMART_TELEGRAM_RESET_INACTIVE_PVZ'); ?>
        </a>
        <button id="btnInactiveStats" type="button" class="btn btn-info">
            <span class="icon-chart" aria-hidden="true"></span>
            <?php echo Text::_('COM_RADICALMART_TELEGRAM_INACTIVE_PVZ_COUNT'); ?>
        </button>
    </div>

    <!-- Progress Bar -->
    <div id="fetchProgress" class="d-none mb-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_PROGRESS_TITLE'); ?></h5>
                <div class="progress mb-2" style="height: 25px;">
                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                         role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                        0%
                    </div>
                </div>
                <div id="progressInfo" class="small text-muted">
                    <?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_PROGRESS_INITIALIZING'); ?>
                </div>
                <div id="progressDetails" class="mt-2">
                    <!-- Provider progress details -->
                </div>
                <hr/>
                <details open>
                    <summary class="small text-muted">Debug</summary>
                    <pre id="pvzDebug" style="max-height:200px;overflow:auto;background:#111;color:#0f0;padding:8px;font-size:12px;margin:0;"></pre>
                </details>
            </div>
        </div>
    </div>

    <table class="table table-striped table-sm">
        <thead>
        <tr>
            <th><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROVIDER'); ?></th>
            <th><?php echo Text::_('COM_RADICALMART_TELEGRAM_LAST_FETCH'); ?></th>
            <th class="text-end">Всего точек</th>
            <th class="text-end">ПВЗ</th>
            <th class="text-end">Постоматы</th>
            <th class="text-center">Действия</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($this->items)) : ?>
            <tr><td colspan="6" class="text-muted"><?php echo Text::_('COM_RADICALMART_TELEGRAM_NO_DATA'); ?></td></tr>
        <?php else : foreach ($this->items as $row) : ?>
            <tr>
                <td><?php echo htmlspecialchars($row['provider'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($row['last_fetch'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="text-end"><?php echo (int)($this->dbCounts[$row['provider']] ?? 0); ?></td>
                <td class="text-end"><?php echo (int)($this->pvzCounts[$row['provider']] ?? 0); ?></td>
                <td class="text-end"><?php echo (int)($this->postomatCounts[$row['provider']] ?? 0); ?></td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary" data-action="prefetch" data-provider="<?php echo htmlspecialchars($row['provider']); ?>" data-bs-toggle="tooltip" title="Скачать JSON для провайдера (префетч полного списка)">
                            <span class="icon-download" aria-hidden="true"></span>
                            <span class="visually-hidden">Скачать JSON</span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-action="analyze" data-provider="<?php echo htmlspecialchars($row['provider']); ?>" data-bs-toggle="tooltip" title="Анализ JSON: distinct ID, ключи, координаты">
                            <span class="icon-search" aria-hidden="true"></span>
                            <span class="visually-hidden">Анализ JSON</span>
                        </button>
                        <button type="button" class="btn btn-outline-success" data-action="import" data-provider="<?php echo htmlspecialchars($row['provider']); ?>" data-bs-toggle="tooltip" title="Импорт из файла (NDJSON/JSON) на сервере">
                            <span class="icon-database" aria-hidden="true"></span>
                            <span class="visually-hidden">Импорт из файла</span>
                        </button>
                        <button type="button" class="btn btn-outline-warning" data-action="fetch-single" data-provider="<?php echo htmlspecialchars($row['provider']); ?>" data-bs-toggle="tooltip" title="Загрузить только этого провайдера (offset/cursor)">
                            <span class="icon-refresh" aria-hidden="true"></span>
                            <span class="visually-hidden">Загрузить только этого</span>
                        </button>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php $nonceAttr = method_exists($this->document, 'getCspNonce') ? ' nonce="' . htmlspecialchars($this->document->getCspNonce(), ENT_QUOTES, 'UTF-8') . '"' : ''; ?>
<script<?php echo $nonceAttr; ?>>
(function() {
    'use strict';

    const btnStart = document.getElementById('btnStartFetch');
    const btnCancel = document.getElementById('btnCancelFetch');
    const progressContainer = document.getElementById('fetchProgress');
    const progressBar = document.getElementById('progressBar');
    const progressInfo = document.getElementById('progressInfo');
    const progressDetails = document.getElementById('progressDetails');

    let cancelled = false;
    let providers = [];
    let currentProviderIndex = 0;
    let currentOffset = 0;
    let totalPoints = 0;
    let processedPoints = 0;

    // Helper: visible debug
    function logDebug() {
        try { console.log.apply(console, arguments); } catch (e) {}
        const el = document.getElementById('pvzDebug');
        if (!el) return;
        const msg = Array.from(arguments).map(v => {
            try { return typeof v === 'string' ? v : JSON.stringify(v); } catch (_) { return String(v); }
        }).join(' ');
        const line = `[${new Date().toISOString()}] ${msg}`;
        el.textContent += line + '\n';
        el.scrollTop = el.scrollHeight;
    }

    logDebug('[PVZ] Script loaded');

    btnStart.addEventListener('click', function() {
        logDebug('[PVZ] Start button clicked');
        startFetch();
    });
    // Delegate action buttons in table
    document.querySelectorAll('table [data-action]')?.forEach(btn => {
        btn.addEventListener('click', evt => {
            const action = btn.getAttribute('data-action');
            const provider = btn.getAttribute('data-provider');
            if (!provider) return;
            if (action === 'prefetch') return prefetchProvider(provider);
            if (action === 'analyze') return analyzeProvider(provider);
            if (action === 'import') return importProvider(provider);
            if (action === 'fetch-single') return startFetchSingle(provider);
        });
    });

    function startFetchSingle(providerCode) {
        logDebug('[PVZ] Single-provider fetch init for', providerCode);
        cancelled = false;
        currentProviderIndex = 0;
        currentOffset = 0;
        processedPoints = 0;
        providers = [];
        // Получаем init и отфильтровываем только нужный провайдер
        const initUrl = 'index.php?option=com_radicalmart_telegram&task=api.apishipfetchInit&format=raw';
        fetch(initUrl, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>=1' })
            .then(r=>r.text())
            .then(t=>{ const j = JSON.parse(t); if(!j.success) throw new Error(j.error||'Init failed'); return j; })
            .then(data => {
                const found = (data.providers||[]).filter(p => p.code === providerCode);
                if (!found.length) throw new Error('Provider not found in init list');
                providers = found;
                totalPoints = providers.reduce((s,p)=>s+p.total,0);
                btnStart.classList.add('d-none');
                btnCancel.classList.remove('d-none');
                progressContainer.classList.remove('d-none');
                updateProgress(0, 'Init single ' + providerCode);
                updateProgressDetails();
                processNextBatch();
            })
            .catch(err => { showError('Single fetch init error: '+err.message); });
    }

    function prefetchProvider(provider) {
        const url = 'index.php?option=com_radicalmart_telegram&task=api.apishipfetchJson&format=raw';
        logDebug('[PVZ] Prefetch request →', provider, url);
        const form = new URLSearchParams();
        form.append('<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>', '1');
        form.append('provider', provider);
        fetch(url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: form })
            .then(r => r.text())
            .then(t => { const j = JSON.parse(t); if(!j.success) throw new Error(j.error||'Prefetch failed'); logDebug('[PVZ] Prefetch '+provider+' rows='+j.rowsCount+' distinct='+j.distinctExtIds); showInfo('Prefetch '+provider+': '+j.rowsCount+' rows'); try { applyPrefetchMetrics(provider, j); } catch(e){ logDebug('[PVZ] applyPrefetchMetrics error', e); } })
            .catch(e => { showError('Prefetch '+provider+' error: '+e.message); });
    }
    function analyzeProvider(provider) {
        const url = 'index.php?option=com_radicalmart_telegram&task=api.apishipjsonAnalyze&format=raw';
        logDebug('[PVZ] Analyze request →', provider, url);
        const form = new URLSearchParams();
        form.append('<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>', '1');
        form.append('provider', provider);
        fetch(url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: form })
            .then(r => r.text())
            .then(t => { const j = JSON.parse(t); if(!j.success) throw new Error(j.error||'Analyze failed'); logDebug('[PVZ] Analyze '+provider, j); showInfo('Analyze '+provider+': distinct='+j.distinctIds+' / rows='+j.rowsCount); })
            .catch(e => { showError('Analyze '+provider+' error: '+e.message); });
    }
    function importProvider(provider) {
        const url = 'index.php?option=com_radicalmart_telegram&task=api.apishipimportFile&format=raw';
        logDebug('[PVZ] Import request →', provider, url);
        const form = new URLSearchParams();
        form.append('<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>', '1');
        form.append('provider', provider);
        fetch(url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: form })
            .then(r => r.text())
            .then(t => { const j = JSON.parse(t); if(!j.success) throw new Error(j.error||'Import failed'); logDebug('[PVZ] Import '+provider+' processed='+j.processed+' inserted='+j.inserted+' updated='+j.updated); showInfo('Import '+provider+': '+j.inserted+' new, '+j.updated+' updated'); })
            .catch(e => { showError('Import '+provider+' error: '+e.message); });
    }
    btnCancel.addEventListener('click', cancelFetch);
    const btnDbCheck = document.getElementById('btnDbCheck');
    btnDbCheck.addEventListener('click', dbCheck);
    const btnPrefetchX5 = document.getElementById('btnPrefetchX5');
    btnPrefetchX5.addEventListener('click', prefetchX5);

    function startFetch() {
        cancelled = false;
        currentProviderIndex = 0;
        currentOffset = 0;
        totalPoints = 0;
        processedPoints = 0;

    logDebug('[PVZ] Starting fetch workflow');

        btnStart.classList.add('d-none');
        btnCancel.classList.remove('d-none');
        progressContainer.classList.remove('d-none');

        updateProgress(0, '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_PROGRESS_INITIALIZING'); ?>');

        // Инициализация - получаем список провайдеров
        const initUrl = 'index.php?option=com_radicalmart_telegram&task=api.apishipfetchInit&format=raw';
    logDebug('[PVZ] Init request →', initUrl);
        fetch(initUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: '<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>=1'
        })
        .then(async (response) => {
            logDebug('[PVZ] Init HTTP status:', response.status);
            const text = await response.text();
            logDebug('[PVZ] Init raw response (first 1000 chars):', text.slice(0, 1000));
            try {
                const data = JSON.parse(text);
                return data;
            } catch (e) {
                throw new Error('Invalid JSON from apishipfetchInit: ' + e.message);
            }
        })
        .then(data => {
            logDebug('[PVZ] Init response parsed:', data);
            if (data.debug) logDebug('[PVZ] Init debug:', data.debug);

            if (!data.success) {
                throw new Error(data.error || 'Initialization failed');
            }

            providers = data.providers;
            totalPoints = providers.reduce((sum, p) => sum + p.total, 0);

            updateProgressDetails();
            processNextBatch();
        })
        .catch(error => {
            logDebug('[PVZ] Init error:', error && (error.stack || error.message || String(error)));
            showError('Ошибка инициализации: ' + error.message);
            resetButtons();
        });
    }

    function processNextBatch() {
        if (cancelled) {
            showInfo('<?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_PROGRESS_CANCELLED'); ?>');
            resetButtons();
            return;
        }

        if (currentProviderIndex >= providers.length) {
            // Все провайдеры обработаны — не перезагружаем страницу, оставляем лог
            updateProgress(100, '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_PROGRESS_COMPLETED'); ?>: ' + processedPoints + ' <?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_PROGRESS_POINTS'); ?>');
            progressBar.classList.remove('progress-bar-animated');
            progressBar.classList.add('bg-success');
            btnCancel.classList.add('d-none');
            resetButtons();
            logDebug('[PVZ] Fetch workflow finished — page will not reload to allow copying debug.');
            return;
        }

        const provider = providers[currentProviderIndex];

        // Проверяем, закончили ли мы с текущим провайдером
        if (currentOffset >= provider.total) {
            currentProviderIndex++;
            currentOffset = 0;
            processNextBatch();
            return;
        }

        // Загружаем следующий batch
        const formData = new URLSearchParams();
        formData.append('<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>', '1');
        formData.append('provider', provider.code);
        formData.append('offset', currentOffset.toString());
        formData.append('batchSize', '500');

        const stepUrl = 'index.php?option=com_radicalmart_telegram&task=api.apishipfetchStep&format=raw';
    logDebug('[PVZ] Step request →', stepUrl, JSON.stringify({ provider: provider.code, offset: currentOffset }));
        fetch(stepUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        })
        .then(async (response) => {
            logDebug('[PVZ] Step HTTP status:', response.status);
            const text = await response.text();
            logDebug('[PVZ] Step raw response (first 1000 chars):', text.slice(0, 1000));
            try {
                const data = JSON.parse(text);
                return data;
            } catch (e) {
                throw new Error('Invalid JSON from apishipfetchStep: ' + e.message);
            }
        })
        .then(data => {
            logDebug('[PVZ] Step response parsed:', data);
            if (data.debug) logDebug('[PVZ] Step debug:', data.debug);

            if (!data.success) {
                logDebug('[PVZ] Step failed:', JSON.stringify(data));
                if (data.trace) logDebug('[PVZ] Trace:', JSON.stringify(data.trace));
                throw new Error(data.error || 'Fetch step failed');
            }

            processedPoints += (data.fetched || 0);

            // Управляем переходом к следующему провайдеру аккуратнее:
            // - завершаем только при completed=true
            // - при пустом чанке (fetched=0) продолжаем, если сервер сигнализирует, что есть ещё данные (remaining>0)
            //   или это служебный шаг курсора (flip/desc-step) с аномалией до достижения total
            if (data.completed === true) {
                logDebug('[PVZ] Provider completed by server (reason=' + (data.completedReason || '') + '). Moving to next provider.');
                currentProviderIndex++;
                currentOffset = 0;
            } else if (data.fetched === 0) {
                const shouldContinueSameProvider = (
                    (data.mode === 'cursor' && (typeof data.remaining !== 'number' || data.remaining > 0)) ||
                    (data.completedReason && String(data.completedReason).startsWith('cursor-')) ||
                    (data.anomaly === true && !!data.anomalyReason)
                );
                if (shouldContinueSameProvider) {
                    logDebug('[PVZ] Empty chunk but continuing same provider (mode=' + data.mode + ', remaining=' + data.remaining + ', reason=' + (data.completedReason || data.anomalyReason || '') + ').');
                    // offset не меняем — в cursor-режиме он не влияет, состояние хранится на сервере
                    if (typeof data.sweepOffsetCurrent === 'number' && data.sweepOffsetCurrent > 0) {
                        currentOffset = data.sweepOffsetCurrent; // синхронизируем прогресс в sweep
                    }
                } else {
                    logDebug('[PVZ] Empty chunk with no remaining → moving to next provider.');
                    currentProviderIndex++;
                    currentOffset = 0;
                }
            } else {
                // По умолчанию считаем по fetched, но если сервер дал sweepOffsetCurrent — он источник истины
                currentOffset = (typeof data.sweepOffsetCurrent === 'number' && data.sweepOffsetCurrent > 0)
                    ? data.sweepOffsetCurrent
                    : (currentOffset + (data.fetched || 0));
            }

            const percent = Math.round((processedPoints / totalPoints) * 100);
            updateProgress(percent, provider.code + ': ' + currentOffset + ' / ' + provider.total);
            updateProgressDetails();

            // Обновление RepeatChain бейджа (live)
            try {
                if (typeof data.pageRepeatChain !== 'undefined') {
                    const rcCell = document.querySelector('td.repeat-chain[data-provider-rc="' + provider.code + '"]');
                    if (rcCell) {
                        const badge = rcCell.querySelector('.badge');
                        if (badge) {
                            const v = data.pageRepeatChain;
                            badge.textContent = v;
                            badge.classList.remove('bg-secondary','bg-warning','bg-danger');
                            if (v >= 6) badge.classList.add('bg-danger');
                            else if (v >= 3) badge.classList.add('bg-warning');
                            else badge.classList.add('bg-secondary');
                            badge.title = 'Повторяющаяся последовательность страниц: ' + v;
                        }
                    }
                }
            } catch (e) { logDebug('[PVZ] RepeatChain live update error', e); }

            // Debug info диагностики чанков (distinctInChunk, chunkIdsHash)
            if (typeof data.distinctInChunk !== 'undefined') {
                logDebug('[PVZ] Step chunk distinct: ' + data.distinctInChunk + '/' + data.fetched + ' hash=' + (data.chunkIdsHash || 'n/a'));
            }

            // Следующий шаг или следующий провайдер
            setTimeout(() => processNextBatch(), 150);
        })
        .catch(error => {
            showError('Ошибка загрузки: ' + error.message);
            resetButtons();
        });
    }

    function dbCheck() {
        const url = 'index.php?option=com_radicalmart_telegram&task=api.apishipdbCheck&format=raw';
        logDebug('[PVZ] DB Check request →', url);
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: '<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>=1'
        })
        .then(async r => {
            logDebug('[PVZ] DB Check HTTP status:', r.status);
            const text = await r.text();
            logDebug('[PVZ] DB Check raw (first 1000):', text.slice(0,1000));
            try { return JSON.parse(text); } catch(e){ throw new Error('Invalid JSON from apishipdbCheck: ' + e.message); }
        })
        .then(data => {
            logDebug('[PVZ] DB Check parsed:', data);
            if (!data.success) { throw new Error(data.error || 'DB check failed'); }
            Object.entries(data.providers || {}).forEach(([prov, info]) => {
                logDebug('[PVZ] Provider stats:', prov, JSON.stringify(info));
                if ((info.duplicatesByExt || []).length) {
                    logDebug('[PVZ] Duplicates ext_id for', prov, 'count groups=', info.duplicatesByExt.length);
                }
            });
            if (data.hasUniqueIndex === false) {
                logDebug('[PVZ] WARNING: Unique index (provider, ext_id) missing');
            }
            showInfo('Проверка завершена: провайдеров ' + Object.keys(data.providers || {}).length);
        })
        .catch(err => {
            showError('Ошибка проверки базы: ' + err.message);
        });
    }

    function prefetchX5() {
        const url = 'index.php?option=com_radicalmart_telegram&task=api.apishipfetchJson&format=raw';
        logDebug('[PVZ] Prefetch JSON request →', url);
        const form = new URLSearchParams();
        form.append('<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>', '1');
        form.append('provider', 'x5');
        fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form })
            .then(async r => { const t = await r.text(); logDebug('[PVZ] Prefetch raw (first 1000):', t.slice(0,1000)); try { return JSON.parse(t); } catch(e){ throw new Error('Invalid JSON from apishipfetchJson: ' + e.message); } })
            .then(data => {
                logDebug('[PVZ] Prefetch parsed:', data);
                if (!data.success) throw new Error(data.error || 'Prefetch failed');
                showInfo('Prefetch x5: rows=' + data.rowsCount + ', distinct=' + data.distinctExtIds + ', metaTotal=' + data.metaTotal + (data.file ? (', file=' + data.file) : ''));
            })
            .catch(err => {
                showError('Prefetch error: ' + err.message);
            });
    }

    function cancelFetch() {
        cancelled = true;
        btnCancel.disabled = true;
    }

    function updateProgress(percent, message) {
        progressBar.style.width = percent + '%';
        progressBar.setAttribute('aria-valuenow', percent);
        progressBar.textContent = percent + '%';
        progressInfo.textContent = message;
    }

    function updateProgressDetails() {
        if (providers.length === 0) return;

        let html = '<div class="row g-2 mt-2">';
        providers.forEach((p, index) => {
            const current = (index === currentProviderIndex) ? currentOffset : (index < currentProviderIndex ? p.total : 0);
            const percent = p.total > 0 ? Math.round((current / p.total) * 100) : 0;
            const status = index < currentProviderIndex ? 'success' : (index === currentProviderIndex ? 'primary' : 'secondary');

            html += `
                <div class="col-md-4">
                    <div class="card border-${status}">
                        <div class="card-body p-2">
                            <h6 class="card-title mb-1">${p.name}</h6>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-${status}" role="progressbar" style="width: ${percent}%">
                                    ${current} / ${p.total}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        progressDetails.innerHTML = html;
    }

    function showError(message) {
        progressInfo.innerHTML = '<span class="text-danger">' + message + '</span>';
        progressBar.classList.remove('progress-bar-animated');
        progressBar.classList.add('bg-danger');
    }

    function showInfo(message) {
        progressInfo.textContent = message;
        progressBar.classList.remove('progress-bar-animated');
    }

    function resetButtons() {
        btnStart.classList.remove('d-none');
        btnCancel.classList.add('d-none');
        btnCancel.disabled = false;
    }

    // Кнопка копирования лога
    const dbgEl = document.getElementById('pvzDebug');
    const copyBtn = document.createElement('button');
    copyBtn.type = 'button';
    copyBtn.className = 'btn btn-sm btn-outline-secondary mt-2';
    copyBtn.textContent = 'Скопировать лог';
    copyBtn.addEventListener('click', () => {
        if (!dbgEl) return;
        try {
            const txt = dbgEl.textContent;
            navigator.clipboard.writeText(txt).then(() => {
                logDebug('[PVZ] Debug copied to clipboard, length=' + txt.length);
                showInfo('Лог скопирован в буфер');
            }).catch(err => {
                showError('Не удалось скопировать лог: ' + err.message);
            });
        } catch (e) {
            showError('Clipboard error: ' + e.message);
        }
    });
    dbgEl.parentElement.appendChild(copyBtn);

    // Инициализация Bootstrap tooltips (для кнопок действий)
    try {
        if (window.bootstrap && typeof window.bootstrap.Tooltip === 'function') {
            const tEls = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tEls.forEach(el => { try { new window.bootstrap.Tooltip(el); } catch (_) {} });
        }
    } catch (_) {}
    // Обновление distinctRatio/topRepeatIds после префетча JSON
    function applyPrefetchMetrics(provider, payload) {
        if (!payload) return;
        const drCell = document.querySelector('td.distinct-ratio[data-provider-dr="' + provider + '"]');
        const trCell = document.querySelector('td.top-repeat[data-provider-tr="' + provider + '"]');
        const rcCell = document.querySelector('td.repeat-chain[data-provider-rc="' + provider + '"]');
        if (drCell && typeof payload.distinct_ratio !== 'undefined') {
            const ratio = payload.distinct_ratio;
            drCell.textContent = (ratio > 0 ? (ratio*100).toFixed(2) + '%' : '0%');
            drCell.classList.remove('text-muted');
            if (ratio < 0.05) { drCell.classList.add('text-danger'); drCell.title = 'Аномально низкий коэффициент уникальности'; }
            else if (ratio < 0.20) { drCell.classList.add('text-warning'); }
        }
        if (trCell && payload.top_repeat_ids) {
            const entries = Object.entries(payload.top_repeat_ids).map(([id,c]) => id + '×' + c);
            trCell.textContent = entries.slice(0,3).join(', ');
            if (entries.length > 3) { trCell.title = entries.join(', '); }
        }
        if (rcCell) {
            const vRaw = (typeof payload.page_repeat_chain !== 'undefined') ? payload.page_repeat_chain : (typeof payload.pageRepeatChain !== 'undefined' ? payload.pageRepeatChain : null);
            if (vRaw !== null) {
                const v = parseInt(vRaw, 10) || 0;
                const badge = rcCell.querySelector('.badge');
                if (badge) {
                    badge.textContent = v;
                    badge.classList.remove('bg-secondary','bg-warning','bg-danger');
                    if (v >= 6) badge.classList.add('bg-danger');
                    else if (v >= 3) badge.classList.add('bg-warning');
                    else badge.classList.add('bg-secondary');
                    badge.title = 'Повторяющаяся последовательность страниц (prefetch): ' + v;
                }
            }
        }
    }

    // Hook префетча JSON — парсим payload и применяем метрики
    const originalPrefetch = window.prefetchProvider;
    if (typeof originalPrefetch === 'function') {
        window.prefetchProvider = async function(btn){
            const provider = btn.getAttribute('data-provider');
            const res = await originalPrefetch(btn);
            try { if (res && res.success) { applyPrefetchMetrics(provider, res); } } catch(e){ logDebug('[PVZ] applyPrefetchMetrics error', e); }
            return res;
        }
    }

    // Inactive PVZ stats button
    document.getElementById('btnInactiveStats')?.addEventListener('click', async function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Загрузка...';

        try {
            const url = 'index.php?option=com_radicalmart_telegram&task=api.inactivePvzStats&format=raw';
            const resp = await fetch(url);
            const data = await resp.json();

            if (data.success && data.data) {
                const d = data.data;
                alert(
                    'Статистика неактивных ПВЗ:\n\n' +
                    'Всего ПВЗ: ' + d.total + '\n' +
                    'Активных: ' + d.active + '\n' +
                    'Помечено как неактивные (временно): ' + d.temporarily_flagged + '\n' +
                    'Скрыто навсегда (>=10 отказов): ' + d.permanently_inactive
                );
            } else {
                alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
            }
        } catch(e) {
            alert('Ошибка запроса: ' + e.message);
        }

        btn.disabled = false;
        btn.innerHTML = '<span class="icon-chart" aria-hidden="true"></span> <?php echo Text::_('COM_RADICALMART_TELEGRAM_INACTIVE_PVZ_COUNT'); ?>';
    });
})();
</script>
