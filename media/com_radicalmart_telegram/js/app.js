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
  async function runSearch(q){ const box=document.getElementById('search-results'); if(!box) return; if(!q){ box.innerHTML='<p class="uk-text-meta">'+(L.COM_RADICALMART_TELEGRAM_SEARCH_ENTER_QUERY||'Enter query')+'</p>'; return; } box.innerHTML='<div class="uk-text-center uk-padding-small"><span uk-spinner></span></div>'; try{ const res=await api('search',{ q, limit:12 }); const items=res.items||[]; if(!items.length){ box.innerHTML='<p class="uk-text-meta">'+(L.COM_RADICALMART_TELEGRAM_SEARCH_NO_RESULTS||'No results')+'</p>'; return; } box.innerHTML='<div class="uk-grid-small uk-child-width-1-2@s" uk-grid>'+items.map(p=>'<div><div class="uk-card uk-card-default uk-card-small"><div class="uk-card-media-top">'+(p.image?'<img src="'+p.image+'" alt="" style="height:120px" class="uk-width-1-1 uk-object-cover">':'<div class="uk-height-small uk-background-muted"></div>')+'</div><div class="uk-card-body"><div class="uk-text-small uk-text-muted">'+(p.category||'')+'</div><div class="uk-text-bold">'+(p.title||'')+'</div><div class="uk-text-small">'+(p.price_final||'')+'</div><button class="uk-button uk-button-primary uk-button-small" data-action="add" data-id="'+p.id+'">'+(L.COM_RADICALMART_TELEGRAM_ADD_TO_CART||'Add')+'</button></div></div></div>').join('')+'</div>'; }catch(e){ box.innerHTML='<p class="uk-text-danger">'+(L.COM_RADICALMART_TELEGRAM_SEARCH_ERROR||'Search error')+'</p>'; }
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
