/* a2hs.js — Popup/Modal bonito para instalar o app (Android + iOS) em /farmafixed */
(function () {
  const CONFIG = {
    appName: 'Farma Fácil',
    // >>> caminhos ABSOLUTOS para a subpasta
    icon192: '/assets/icons/farma-192.png',
    deferDaysAfterDismiss: 7,
    swPaths: ['/sw.js'] // registra sempre o SW da subpasta
  };

  const ls  = window.localStorage;
  const DISMISS_KEY = 'a2hs_dismissed_at';
  const now  = () => Math.floor(Date.now()/1000);
  const days = d => d*24*60*60;
  const dismissedRecently = () => {
    const t = parseInt(ls.getItem(DISMISS_KEY)||'0',10);
    return t && (now()-t) < days(CONFIG.deferDaysAfterDismiss);
  };
  const markDismissed = () => ls.setItem(DISMISS_KEY, String(now()));

  const isIOS = (() => {
    const ua = navigator.userAgent;
    const iOS = /iPad|iPhone|iPod/.test(ua);
    const isSafari = /^((?!chrome|android).)*safari/i.test(ua);
    return iOS && isSafari;
  })();
  const isInStandalone =
    matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;

  async function ensureSW() {
    if (!('serviceWorker' in navigator)) return false;
    const existing = await navigator.serviceWorker.getRegistration();
    if (existing) return true;
    try {
      const reg = await navigator.serviceWorker.register(CONFIG.swPaths[0]);
      await navigator.serviceWorker.ready;
      return !!reg;
    } catch { return false; }
  }

  function injectStyles() {
    if (document.getElementById('a2hs-styles')) return;
    const css = `
      .a2hs-overlay{position:fixed;inset:0;background:rgba(2,6,23,.6);backdrop-filter:blur(6px);z-index:9998;opacity:0;animation:a2hs-fade .18s ease-out forwards}
      .a2hs-modal{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;z-index:9999;opacity:0;animation:a2hs-pop .18s ease-out forwards}
      .a2hs-card{width:min(92vw,420px);background:#0f172a;color:#e5e7eb;border:1px solid rgba(148,163,184,.18);
        border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.45);overflow:hidden;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
      .a2hs-hero{display:flex;align-items:center;gap:12px;padding:16px;background:linear-gradient(135deg,#0ea5e9 0%,#6366f1 100%)}
      .a2hs-hero img{width:48px;height:48px;border-radius:12px;object-fit:cover;background:#fff}
      .a2hs-hero h3{margin:0;font-size:18px;line-height:1.2;color:#0b1220;font-weight:800}
      .a2hs-body{padding:16px}
      .a2hs-sub{opacity:.9;font-size:13px;margin:0 0 12px}
      .a2hs-actions{display:flex;gap:10px;margin-top:6px}
      .a2hs-btn{flex:1;appearance:none;border:0;border-radius:12px;padding:10px 14px;font-weight:800;cursor:pointer}
      .a2hs-primary{background:#7dd3fc;color:#0b1220}
      .a2hs-secondary{background:#111827;color:#cbd5e1;border:1px solid rgba(148,163,184,.18)}
      .a2hs-close{position:absolute;top:10px;right:12px;background:transparent;border:0;color:#0b1220;font-size:18px;cursor:pointer;opacity:.8}
      .a2hs-ios-tip{display:flex;align-items:center;gap:8px;padding:10px 12px;border-radius:10px;background:#0b1220;color:#cbd5e1;font-size:12px}
      .a2hs-row{display:flex;align-items:center;gap:6px}
      .a2hs-small{opacity:.8;font-size:12px;margin-top:8px}
      @keyframes a2hs-pop{from{opacity:0;transform:translateY(12px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
      @keyframes a2hs-fade{from{opacity:0}to{opacity:1}}
    `.trim();
    const s = document.createElement('style');
    s.id = 'a2hs-styles';
    s.textContent = css;
    document.head.appendChild(s);
  }

  function makeModal({ title, subtitle, primaryLabel, onPrimary, ios = false }) {
    injectStyles();
    const overlay = document.createElement('div'); overlay.className = 'a2hs-overlay';
    const modal   = document.createElement('div'); modal.className   = 'a2hs-modal';
    const card    = document.createElement('div'); card.className    = 'a2hs-card';
    const hero = document.createElement('div'); hero.className = 'a2hs-hero';
    hero.innerHTML = `
      <img src="${CONFIG.icon192}" alt="${CONFIG.appName}">
      <h3>${title}</h3>
      <button class="a2hs-close" aria-label="Fechar">✕</button>
    `;
    const body = document.createElement('div'); body.className = 'a2hs-body';

    if (!ios) {
      body.innerHTML = `
        <p class="a2hs-sub">${subtitle}</p>
        <div class="a2hs-actions">
          <button class="a2hs-btn a2hs-primary" type="button">${primaryLabel || 'Instalar App'}</button>
          <button class="a2hs-btn a2hs-secondary" type="button">Agora não</button>
        </div>
        <div class="a2hs-small">Você pode instalar depois pelo menu do navegador.</div>
      `;
    } else {
      const shareSVG = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-2px;"><path d="M12 16a1 1 0 0 1-1-1V6.414L8.707 8.707a1 1 0 1 1-1.414-1.414l4-4a1 1 0 0 1 1.414 0l4 4A1 1 0 0 1 15.293 8.707L13 6.414V15a1 1 0 0 1-1 1Z"></path><path d="M5 10a1 1 0 0 1 1 1v7h12v-7a1 1 0 1 1 2 0v7a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3v-7a1 1 0 0 1 1-1Z"></path></svg>`;
      body.innerHTML = `
        <p class="a2hs-sub">Para instalar no iPhone/iPad:</p>
        <div class="a2hs-ios-tip">
          <div class="a2hs-row">${shareSVG} Abra <b>Compartilhar</b></div>
          <div class="a2hs-row">→ <b>Adicionar à Tela de Início</b></div>
        </div>
        <div class="a2hs-actions" style="margin-top:12px">
          <button class="a2hs-btn a2hs-secondary" type="button">Entendi</button>
        </div>
      `;
    }

    card.appendChild(hero); card.appendChild(body);
    modal.appendChild(card);
    document.body.appendChild(overlay); document.body.appendChild(modal);

    function closeAll(){ try{markDismissed();}catch(_){ } overlay.remove(); modal.remove(); }
    card.querySelector('.a2hs-close')?.addEventListener('click', closeAll);
    card.querySelector('.a2hs-secondary')?.addEventListener('click', closeAll);
    overlay.addEventListener('click', closeAll);

    if (!ios) {
      const primary = card.querySelector('.a2hs-primary');
      if (primary) primary.addEventListener('click', async () => {
        try { deferredEvt?.prompt(); await deferredEvt?.userChoice; markDismissed(); } catch {}
        closeAll();
      });
    }
  }

  // ANDROID
  let deferredEvt = null;
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredEvt = e;
    if (dismissedRecently() || isInStandalone) return;
    makeModal({
      title: 'Instalar App Farma Fácil',
      subtitle: location.hostname + '/farmafixed',
      primaryLabel: 'Instalar App Farma Fácil'
    });
  });

  // iOS
  function ensureIOSMeta() {
    if (!isIOS) return;
    [['apple-mobile-web-app-capable','yes'],
     ['apple-mobile-web-app-status-bar-style','black-translucent'],
     ['apple-mobile-web-app-title', 'Farma Fácil']
    ].forEach(([n,c])=>{
      if (!document.querySelector(`meta[name="${n}"]`)) {
        const m=document.createElement('meta'); m.name=n; m.content=c; document.head.appendChild(m);
      }
    });
    if (!document.querySelector('link[rel="apple-touch-icon"]')) {
      const l=document.createElement('link'); l.rel='apple-touch-icon'; l.href=CONFIG.icon192; document.head.appendChild(l);
    }
  }
  function showIOSModal() {
    if (dismissedRecently() || isInStandalone) return;
    makeModal({ title: 'Instalar App Farma Fácil', subtitle: '', ios: true });
  }

  document.addEventListener('DOMContentLoaded', async () => {
    const swOk = await ensureSW();
    if (isIOS && !isInStandalone) {
      ensureIOSMeta();
      setTimeout(showIOSModal, 600);
    }
    if (swOk && !navigator.serviceWorker.controller) {
      setTimeout(()=>location.reload(), 300);
    }
  });
})();
