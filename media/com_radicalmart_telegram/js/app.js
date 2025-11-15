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
      let html = '';
      if (!u) { html += '<p class="uk-text-meta">'+(L.COM_RADICALMART_TELEGRAM_PROFILE_NO_USER||'')+'</p>'; }
      else {
        html += '<div class="uk-margin-small"><strong>'+(u.name||u.username||'')+'</strong></div>';
        html += '<div class="uk-text-meta">ID: '+u.id+'</div>';
        if (u.email) html += '<div class="uk-text-meta">'+(L.COM_RADICALMART_TELEGRAM_EMAIL||'Email')+': '+u.email+'</div>';
        if (u.phone) html += '<div class="uk-text-meta">'+(L.COM_RADICALMART_TELEGRAM_PHONE||'Phone')+': '+u.phone+'</div>';
      }
      html += '<div class="uk-margin-small"><span class="uk-label uk-label-success">'+(L.COM_RADICALMART_TELEGRAM_PROFILE_POINTS||'')+': '+(data.points||0)+'</span></div>';
      if (data.referrals_info && data.referrals_info.in_chain){
        const ri = data.referrals_info;
        html += '<div class="uk-text-meta">'+(L.COM_RADICALMART_TELEGRAM_PROFILE_REFERRALS||'Referrals')+': '+(ri.referrals_count||0)+(ri.parent?(' · '+(L.COM_RADICALMART_TELEGRAM_PROFILE_PARENT||'Parent')+': '+(ri.parent.name||ri.parent.username||ri.parent.id)):'')+'</div>';
      }
      const codes = Array.isArray(data.referral_codes)?data.referral_codes:[];
      if (codes.length){
        html += '<ul class="uk-list uk-list-divider">'+codes.map(function(c){
          return '<li>'+(c.code||'')+' — '+(c.discount||'')
            +(c.link ? ' <a href="'+c.link+'" target="_blank" class="uk-link">'+(L.JLINK||'Link')+'</a>' : '')
            +(c.expires && c.expires!=='0000-00-00 00:00:00' ? ' <span class="uk-text-meta">'+(L.COM_RADICALMART_TELEGRAM_PROFILE_EXPIRES_UNTIL||'Until')+' '+c.expires+'</span>' : '')
            +'</li>';
        }).join('') + '</ul>';
      } else {
        html += '<p class="uk-text-meta">'+(L.COM_RADICALMART_TELEGRAM_PROFILE_CODES_EMPTY||'No codes')+'</p>';
      }
      if (data.can_create_code){
        html += '<div class="uk-margin"><form onsubmit="createReferralCode(event)">\n'
          +'<div class="uk-grid-small" uk-grid>'
          +(data.create_mode==='custom' ? '<div class="uk-width-1-2"><input class="uk-input" type="text" name="ref_code" placeholder="'+(L.COM_RADICALMART_TELEGRAM_PROFILE_CODE_PLACEHOLDER||'Code')+'"></div>' : '')
          +'<div class="uk-width-1-2"><input class="uk-input" type="text" name="ref_currency" placeholder="'+(L.COM_RADICALMART_TELEGRAM_PROFILE_CURRENCY_PLACEHOLDER||'Currency')+'"></div>'
          +'<div class="uk-width-1-1"><button class="uk-button uk-button-primary uk-button-small" type="submit">'+(L.COM_RADICALMART_TELEGRAM_PROFILE_CREATE_CODE||'Create')+'</button></div>'
          +'</div></form></div>';
      }
      box.innerHTML = html;
    } catch(e) { /* ignore */ }
  }

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
