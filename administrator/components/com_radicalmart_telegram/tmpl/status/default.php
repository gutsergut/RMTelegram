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
            </div>
        </div>
    </div>

    <table class="table table-striped table-sm">
        <thead>
        <tr>
            <th><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROVIDER'); ?></th>
            <th><?php echo Text::_('COM_RADICALMART_TELEGRAM_LAST_FETCH'); ?></th>
            <th class="text-end"><?php echo Text::_('COM_RADICALMART_TELEGRAM_LAST_TOTAL'); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($this->items)) : ?>
            <tr><td colspan="3" class="text-muted"><?php echo Text::_('COM_RADICALMART_TELEGRAM_NO_DATA'); ?></td></tr>
        <?php else : foreach ($this->items as $row) : ?>
            <tr>
                <td><?php echo htmlspecialchars($row['provider'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($row['last_fetch'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="text-end"><?php echo (int)($row['last_total'] ?? 0); ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<script>
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

    btnStart.addEventListener('click', startFetch);
    btnCancel.addEventListener('click', cancelFetch);

    function startFetch() {
        cancelled = false;
        currentProviderIndex = 0;
        currentOffset = 0;
        totalPoints = 0;
        processedPoints = 0;

        btnStart.classList.add('d-none');
        btnCancel.classList.remove('d-none');
        progressContainer.classList.remove('d-none');

        updateProgress(0, '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_PROGRESS_INITIALIZING'); ?>');

        // Инициализация - получаем список провайдеров
        fetch('index.php?option=com_radicalmart_telegram&task=api.apishipfetchInit&format=raw', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: '<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>=1'
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Initialization failed');
            }

            providers = data.providers;
            totalPoints = providers.reduce((sum, p) => sum + p.total, 0);

            updateProgressDetails();
            processNextBatch();
        })
        .catch(error => {
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
            // Все провайдеры обработаны
            updateProgress(100, '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_PROGRESS_COMPLETED'); ?>: ' + processedPoints + ' <?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_PROGRESS_POINTS'); ?>');
            progressBar.classList.remove('progress-bar-animated');
            progressBar.classList.add('bg-success');
            btnCancel.classList.add('d-none');

            setTimeout(() => {
                location.reload();
            }, 2000);
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

        fetch('index.php?option=com_radicalmart_telegram&task=api.apishipfetchStep&format=raw', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Fetch step failed');
            }

            processedPoints += data.inserted;
            currentOffset += data.fetched;

            const percent = Math.round((processedPoints / totalPoints) * 100);
            updateProgress(percent, provider.code + ': ' + currentOffset + ' / ' + provider.total);
            updateProgressDetails();

            // Следующий batch
            setTimeout(() => processNextBatch(), 100);
        })
        .catch(error => {
            showError('Ошибка загрузки: ' + error.message);
            resetButtons();
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
})();
</script>
