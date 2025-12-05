// RadicalMart Telegram WebApp core JS (externalized)
(function(){
  const L = window.RMT_LANG || {};
  const api = window.RMT_API; // ожидается глобально из шаблона
  const UIkit = window.UIkit;
  function makeNonce(){ return (Date.now().toString(36)+Math.random().toString(36).substring(2,8)); }

  async function loadProfile(){
    try {
      const data = await api('profile');
      const box = document.getElementById('profile-box');
      if (!box) return;
      const u = data.user || null;
      const isLinked = !!u;
      let html = '';

      // Блок пользователя
      if (!u) {
        html += '<p class="uk-text-meta">'+(L.COM_RADICALMART_TELEGRAM_PROFILE_NO_USER||'Нет данных пользователя')+'</p>';
      } else {
        html += '<div class="uk-margin-small"><strong>'+(u.name||u.username||'')+'</strong></div>';
        html += '<div class="uk-text-meta">ID: '+u.id+'</div>';
        if (u.email) html += '<div class="uk-text-meta">'+(L.COM_RADICALMART_TELEGRAM_EMAIL||'Email')+': '+u.email+'</div>';
        if (u.phone) html += '<div class="uk-text-meta">'+(L.COM_RADICALMART_TELEGRAM_PHONE||'Телефон')+': '+u.phone+'</div>';
      }

      // Блок баллов и реферальной программы
      html += '<div class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-small">';
      html += '<h4 class="uk-card-title uk-margin-remove-top">💰 '+(L.COM_RADICALMART_TELEGRAM_POINTS_AND_REFERRALS||'Баллы и рефералы')+'</h4>';

      // Баланс баллов
      const points = data.points || 0;
      if (isLinked) {
        html += '<div class="uk-margin-small">';
        html += '<span class="uk-label uk-label-success" style="font-size:1.1em;">'+points+' '+(L.COM_RADICALMART_TELEGRAM_POINTS_UNIT||'баллов')+'</span>';
        html += ' <a href="#" onclick="openPointsHistory();return false;" class="uk-link uk-text-small">'+(L.COM_RADICALMART_TELEGRAM_VIEW_HISTORY||'История')+'</a>';
        html += '</div>';
      } else {
        html += '<div class="uk-margin-small">';
        html += '<span class="uk-label" style="background:#ccc;">0 '+(L.COM_RADICALMART_TELEGRAM_POINTS_UNIT||'баллов')+'</span>';
        html += '<div class="uk-text-warning uk-text-small uk-margin-xsmall-top">'+(L.COM_RADICALMART_TELEGRAM_POINTS_LOGIN_HINT||'Авторизуйтесь, чтобы копить баллы')+'</div>';
        html += '</div>';
      }

      // Реферальная информация
      if (data.referrals_info && data.referrals_info.in_chain){
        const ri = data.referrals_info;
        html += '<div class="uk-text-meta uk-margin-small">'+(L.COM_RADICALMART_TELEGRAM_PROFILE_REFERRALS||'Рефералы')+': '+(ri.referrals_count||0);
        if (ri.parent) {
          html += ' · '+(L.COM_RADICALMART_TELEGRAM_PROFILE_PARENT||'Пригласил')+': '+(ri.parent.name||ri.parent.username||ri.parent.id);
        }
        html += '</div>';
      }

      // Реферальные коды
      const codes = Array.isArray(data.referral_codes)?data.referral_codes:[];
      if (codes.length){
        html += '<div class="uk-margin-small"><strong>'+(L.COM_RADICALMART_TELEGRAM_PROFILE_CODES||'Ваши коды')+':</strong></div>';
        html += '<ul class="uk-list uk-list-divider uk-margin-remove">'+codes.map(function(c){
          let item = '<li class="uk-padding-small-left">';
          item += '<div class="uk-flex uk-flex-between uk-flex-middle">';
          item += '<div>';
          item += '<span class="uk-text-bold">'+(c.code||'')+'</span>';
          item += ' — '+(c.discount||'');
          if (c.expires && c.expires!=='0000-00-00 00:00:00') {
            item += ' <span class="uk-text-meta">'+(L.COM_RADICALMART_TELEGRAM_PROFILE_EXPIRES_UNTIL||'до')+' '+c.expires+'</span>';
          }
          item += '</div>';
          if (c.link) {
            item += '<button type="button" class="uk-button uk-button-small uk-button-primary" onclick="shareReferralLink(\''+c.link+'\', \''+c.code+'\');return false;" title="'+(L.COM_RADICALMART_TELEGRAM_SHARE||'Поделиться')+'">';
            item += '📤 '+(L.COM_RADICALMART_TELEGRAM_SHARE||'Поделиться');
            item += '</button>';
          }
          item += '</div>';
          item += '</li>';
          return item;
        }).join('') + '</ul>';
      } else if (isLinked) {
        html += '<p class="uk-text-meta uk-margin-small">'+(L.COM_RADICALMART_TELEGRAM_PROFILE_CODES_EMPTY||'Реферальных кодов нет')+'</p>';
      }

      // Форма создания кода
      if (data.can_create_code){
        html += '<div class="uk-margin-small"><form onsubmit="createReferralCode(event)">\n'
          +'<div class="uk-grid-small" uk-grid>'
          +(data.create_mode==='custom' ? '<div class="uk-width-1-2"><input class="uk-input uk-form-small" type="text" name="ref_code" placeholder="'+(L.COM_RADICALMART_TELEGRAM_PROFILE_CODE_PLACEHOLDER||'Код')+'"></div>' : '')
          +'<div class="uk-width-1-2"><input class="uk-input uk-form-small" type="text" name="ref_currency" placeholder="'+(L.COM_RADICALMART_TELEGRAM_PROFILE_CURRENCY_PLACEHOLDER||'Валюта')+'"></div>'
          +'<div class="uk-width-1-1"><button class="uk-button uk-button-primary uk-button-small" type="submit">'+(L.COM_RADICALMART_TELEGRAM_PROFILE_CREATE_CODE||'Создать код')+'</button></div>'
          +'</div></form></div>';
      }

      html += '</div>'; // закрываем карточку

      box.innerHTML = html;
    } catch(e) { console.error('loadProfile error:', e); }
  }

  // Открыть страницу истории баллов
  function openPointsHistory(){
    const root = window.RMT_ROOT || '';
    const chat = new URLSearchParams(location.search).get('chat') || '';
    let url = root + '/index.php?option=com_radicalmart_telegram&view=points&tmpl=component';
    if (chat) url += '&chat=' + encodeURIComponent(chat);
    // Попробуем открыть в том же окне или через Telegram
    if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.openLink) {
      window.Telegram.WebApp.openLink(url);
    } else {
      window.location.href = url;
    }
  }

  // Поделиться реферальной ссылкой через Telegram
  function shareReferralLink(link, code){
    const L = window.RMT_LANG || {};
    // Текст для шаринга
    const shareText = (L.COM_RADICALMART_TELEGRAM_SHARE_TEXT || 'Используй мой промокод {code} и получи скидку!').replace('{code}', code);

    // Формируем URL для Telegram share
    const shareUrl = 'https://t.me/share/url?url=' + encodeURIComponent(link) + '&text=' + encodeURIComponent(shareText);

    // Пробуем открыть через Telegram WebApp API
    if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.openTelegramLink) {
      window.Telegram.WebApp.openTelegramLink(shareUrl);
    } else if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.openLink) {
      window.Telegram.WebApp.openLink(shareUrl);
    } else {
      // Fallback — открыть в новом окне
      window.open(shareUrl, '_blank');
    }
  }
  window.shareReferralLink = shareReferralLink;
  window.openPointsHistory = openPointsHistory;

  let SEARCH_TIMER=null; let LAST_SEARCH_Q='';
  window.openSearch = function(){ UIkit.modal('#search-modal').show(); document.getElementById('search-input')?.focus(); };
  window.onSearchInput = function(ev){ const q=(ev.target.value||'').trim(); if(q===LAST_SEARCH_Q) return; LAST_SEARCH_Q=q; clearTimeout(SEARCH_TIMER); SEARCH_TIMER=setTimeout(()=>runSearch(q), 250); };

  // Полноценные карточки для поиска (как в каталоге)
  async function runSearch(q) {
    const box = document.getElementById('search-results');
    if (!box) return;
    if (!q) {
      box.innerHTML = '<p class="uk-text-meta">' + (L.COM_RADICALMART_TELEGRAM_SEARCH_ENTER_QUERY || 'Введите запрос') + '</p>';
      return;
    }
    box.innerHTML = '<div class="uk-text-center uk-padding-small"><span uk-spinner></span></div>';

    try {
      const res = await api('search', { q, limit: 12 });
      const items = res.items || [];

      if (!items.length) {
        box.innerHTML = '<p class="uk-text-meta">' + (L.COM_RADICALMART_TELEGRAM_SEARCH_NO_RESULTS || 'Ничего не найдено') + '</p>';
        return;
      }

      // Отображаем мета-карточки с вариантами
      const root = window.RMT_ROOT || '';
      const chatParam = new URLSearchParams(location.search).get('chat');
      const chatSuffix = chatParam ? '&chat=' + encodeURIComponent(chatParam) : '';

      let html = '<div class="uk-grid-small uk-child-width-1-2@s" uk-grid>';

      items.forEach(p => {
        if (!p.is_meta) return;

        const children = Array.isArray(p.children) ? p.children : [];
        const hasVariants = children.length > 0;
        const allOutOfStock = hasVariants && children.every(ch => !ch.in_stock);
        const first = hasVariants ? children[0] : null;

        // Карточка без вариантов
        if (!hasVariants) {
          html += '<div><div class="uk-card uk-card-default uk-card-small">';
          html += '<div class="uk-card-media-top">' + (p.image ? '<img src="' + p.image + '" alt="" class="uk-width-1-1 uk-object-cover" style="height:160px">' : '<div class="uk-height-small uk-background-muted"></div>') + '</div>';
          html += '<div class="uk-card-body">';
          html += '<div class="uk-text-small uk-text-muted">' + (p.category || '') + '</div>';
          html += '<h5 class="uk-margin-remove">' + (p.title || '') + '</h5>';
          html += '<div class="uk-text-small uk-text-warning">' + (L.COM_RADICALMART_TELEGRAM_NO_VARIANTS || 'Нет вариантов') + '</div>';
          html += '</div></div></div>';
          return;
        }

        // Карточка "Нет в наличии"
        if (allOutOfStock) {
          html += '<div><div class="uk-card uk-card-default uk-card-small">';
          html += '<div class="uk-card-media-top">' + (p.image ? '<img src="' + p.image + '" alt="" class="uk-width-1-1 uk-object-cover" style="height:160px">' : '<div class="uk-height-small uk-background-muted"></div>') + '</div>';
          html += '<div class="uk-card-body">';
          html += '<div class="uk-text-small uk-text-muted">' + (p.category || '') + '</div>';
          html += '<h5 class="uk-margin-remove">' + (p.title || '') + '</h5>';
          html += '<div class="uk-margin-small uk-text-danger uk-text-center"><strong>' + (L.COM_RADICALMART_NOT_IN_STOCK || 'Нет в наличии') + '</strong></div>';
          html += '</div></div></div>';
          return;
        }

        // Полная карточка с вариантами
        const variantBtns = children.map(ch => {
          let label = '';
          if (ch.field_weight) {
            label = String(ch.field_weight).trim();
          }
          if (!label) {
            const t = ch.title || '';
            const m = /([0-9]+(?:[.,][0-9]+)?\s?(?:кг|г))$/i.exec(t);
            if (m) label = m[1].replace(',', '.');
          }
          if (!label) label = (ch.title || '').replace(/\(ID\s+\d+\)/i, '').trim();
          if (!label) label = String(ch.id);
          return '<button class="uk-button uk-button-default uk-button-small rmt-variant" data-vid="' + ch.id + '" title="' + (ch.title || '').replace(/"/g, '&quot;') + '">' + label + '</button>';
        }).join(' ');

        html += '<div><div class="uk-card uk-card-default uk-card-small">';
        html += '<div class="uk-card-media-top" style="position:relative;">' + (p.image ? '<img src="' + p.image + '" alt="" class="uk-width-1-1 uk-object-cover" style="height:160px">' : '<div class="uk-height-small uk-background-muted"></div>') + '</div>';
        html += '<div class="uk-card-body" data-search-card="' + p.id + '" data-children=\'' + JSON.stringify(children).replace(/'/g, '&#39;') + '\'>';
        html += '<div class="uk-text-small uk-text-muted">' + (p.category || '') + '</div>';
        html += '<h5 class="uk-margin-remove">' + (p.title || '') + '</h5>';
        html += '<div class="uk-margin-small js-variants">' + variantBtns + '</div>';
        html += '<div class="uk-margin-small tg-safe-text js-price-block">';
        html += '<div class="js-price-base uk-text-muted uk-text-small" style="text-decoration:line-through;display:none;"></div>';
        html += '<div class="js-price-final" style="font-size:1.1em;"><strong>' + (first.price_final || '') + '</strong></div>';
        html += '<div class="js-price-discount uk-text-danger uk-text-small" style="display:none;"></div>';
        html += '</div>';
        html += '<div class="uk-flex uk-flex-between uk-margin-small-top">';
        html += '<button class="uk-button uk-button-primary js-add" data-vid="' + first.id + '">' + (L.COM_RADICALMART_TELEGRAM_ADD_TO_CART || 'В корзину') + '</button>';
        html += '<a class="uk-button uk-button-default js-link" href="' + root + '/index.php?option=com_radicalmart_telegram&view=product&id=' + first.id + chatSuffix + '">' + (L.COM_RADICALMART_TELEGRAM_MORE || 'Подробнее') + '</a>';
        html += '</div>';
        html += '</div></div></div>';
      });

      html += '</div>';
      box.innerHTML = html;

      // Инициализируем обработчики для кнопок вариантов
      box.querySelectorAll('[data-search-card]').forEach(cardBody => {
        const children = JSON.parse(cardBody.dataset.children || '[]');
        const variantBtns = cardBody.querySelectorAll('.rmt-variant');
        const priceBaseEl = cardBody.querySelector('.js-price-base');
        const priceFinalEl = cardBody.querySelector('.js-price-final');
        const priceDiscEl = cardBody.querySelector('.js-price-discount');
        const addBtn = cardBody.querySelector('.js-add');
        const linkEl = cardBody.querySelector('.js-link');

        function applyVariant(ch) {
          // Обновляем цены
          const finalPrice = ch.price_final || '';
          const basePrice = ch.base_string || ch.price_base || '';
          const hasDiscount = !!(ch.discount_enable && basePrice && finalPrice !== basePrice);

          if (hasDiscount && priceBaseEl) {
            priceBaseEl.textContent = basePrice;
            priceBaseEl.style.display = '';
          } else if (priceBaseEl) {
            priceBaseEl.style.display = 'none';
          }

          if (priceFinalEl) {
            priceFinalEl.innerHTML = '<strong>' + finalPrice + '</strong>';
          }

          if (hasDiscount && ch.discount_percent && priceDiscEl) {
            priceDiscEl.textContent = '-' + ch.discount_percent + '%';
            priceDiscEl.style.display = '';
          } else if (priceDiscEl) {
            priceDiscEl.style.display = 'none';
          }

          // Обновляем кнопку добавления
          if (addBtn) {
            addBtn.dataset.vid = ch.id;
            addBtn.disabled = !ch.in_stock;
          }

          // Обновляем ссылку
          if (linkEl) {
            const chatParam = new URLSearchParams(location.search).get('chat');
            const chatSuffix = chatParam ? '&chat=' + encodeURIComponent(chatParam) : '';
            linkEl.href = (window.RMT_ROOT || '') + '/index.php?option=com_radicalmart_telegram&view=product&id=' + ch.id + chatSuffix;
          }

          // Выделяем активную кнопку варианта
          variantBtns.forEach(btn => {
            btn.classList.toggle('uk-button-primary', parseInt(btn.dataset.vid) === ch.id);
            btn.classList.toggle('uk-button-default', parseInt(btn.dataset.vid) !== ch.id);
          });
        }

        // Применяем первый вариант по умолчанию
        if (children.length > 0) {
          applyVariant(children[0]);
        }

        // Обработчики кликов по вариантам
        variantBtns.forEach(btn => {
          btn.addEventListener('click', function() {
            const vid = parseInt(this.dataset.vid);
            const variant = children.find(c => c.id === vid);
            if (variant) applyVariant(variant);
          });
        });
      });

    } catch (e) {
      console.error('Search error:', e);
      box.innerHTML = '<p class="uk-text-danger">' + (L.COM_RADICALMART_TELEGRAM_SEARCH_ERROR || 'Ошибка поиска') + '</p>';
    }
  }
  window.runSearch = runSearch;

  // Не трогаем summary/PVZ здесь — логика в шаблоне

  async function createReferralCode(ev){
    ev.preventDefault();
    try{
      const form = ev.target;
      const code = (form.ref_code && form.ref_code.value || '').trim();
      const currency = (form.ref_currency && form.ref_currency.value || '').trim();
      await api('profile', { action: 'create_code', code, currency });
      UIkit.notification({ message: (L.COM_RADICALMART_TELEGRAM_PROFILE_CODE_CREATED||'Code created'), status: 'success' });
      loadProfile();
    }catch(e){
      UIkit.notification({ message: (L.COM_RADICALMART_TELEGRAM_PROFILE_CODE_ERROR||'Create code failed'), status: 'danger' });
    }
  }


  // Expose to window
  window.loadProfile = loadProfile;
  window.createReferralCode = createReferralCode;
})();
