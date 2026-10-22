(function () {
  'use strict';

  // Mobile nav (public)
  var navBtn = document.querySelector('[data-nav-toggle]');
  var nav = document.getElementById('site-nav');
  function toggleNav(force) {
    if (!nav) return;
    var open = typeof force === 'boolean' ? force : !nav.classList.contains('open');
    nav.classList.toggle('open', open);
    if (navBtn) navBtn.setAttribute('aria-expanded', String(open));
  }
  if (navBtn) navBtn.addEventListener('click', function () { toggleNav(); });
  var menuTab = document.querySelector('[data-nav-toggle-tab]');
  if (menuTab) menuTab.addEventListener('click', function (e) { e.preventDefault(); toggleNav(true); nav && nav.scrollIntoView({ behavior: 'smooth' }); });

  // Highlight current tab
  var path = location.pathname;
  document.querySelectorAll('.app-tabs a[href]').forEach(function (a) {
    var href = a.getAttribute('href');
    if (href === path || (href !== '/' && path.indexOf(href) === 0)) a.classList.add('on');
  });
  if (path === '/') { var home = document.querySelector('.app-tabs a[data-tab="home"]'); home && home.classList.add('on'); }

  // Admin sidebar (mobile)
  var adminBtn = document.querySelector('[data-admin-toggle]');
  var side = document.getElementById('admin-side') || document.querySelector('.admin-side');
  if (adminBtn && side) adminBtn.addEventListener('click', function () { side.classList.toggle('open'); });

  // Chatbot
  var chat = document.querySelector('[data-chat]');
  if (chat) {
    var panel = chat.querySelector('.chat-panel');
    var fab = chat.querySelector('[data-chat-open]');
    var log = chat.querySelector('[data-chat-log]');
    var form = chat.querySelector('[data-chat-form]');
    fab.addEventListener('click', function () { panel.hidden = !panel.hidden; if (!panel.hidden) form.elements.message.focus(); });
    chat.querySelector('[data-chat-close]').addEventListener('click', function () { panel.hidden = true; });
    function add(cls, text) {
      var d = document.createElement('div');
      d.className = cls;
      d.textContent = text;
      log.appendChild(d);
      log.scrollTop = log.scrollHeight;
    }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = form.elements.message.value.trim();
      if (!msg) return;
      form.elements.message.value = '';
      add('me', msg);
      var wait = document.createElement('div'); wait.className = 'bot muted'; wait.textContent = 'Thinking…'; log.appendChild(wait);
      var body = new FormData(); body.append('message', msg);
      fetch('/api/chat.php', { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (data) { wait.remove(); add('bot', data.answer || data.error || 'Sorry, something went wrong.'); })
        .catch(function () { wait.remove(); add('bot', 'The assistant is unavailable right now. Please try again shortly.'); });
    });
  }

  // Service worker (offline shell + fast repeat visits)
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
    navigator.serviceWorker.register('/sw.js').catch(function () {});
  }

  // Install-as-app prompt
  var deferred = null;
  var installBtns = document.querySelectorAll('[data-install]');
  var isStandalone = window.matchMedia && window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    if (!isStandalone) showInstall();
  });
  function showInstall() {
    installBtns.forEach(function (b) { b.hidden = false; });
  }
  installBtns.forEach(function (b) {
    b.addEventListener('click', function () {
      if (deferred) { deferred.prompt(); deferred = null; }
      else alert('To install: on Android tap the browser menu (⋮) → "Install app" or "Add to Home screen". On iPhone tap Share → "Add to Home Screen".');
    });
  });
  var ua = navigator.userAgent || '';
  var isiOS = /iPhone|iPad|iPod/.test(ua);
  if (!isStandalone && isiOS && !localStorage.getItem('sba-ios-hint')) {
    var tip = document.createElement('div');
    tip.className = 'install-banner';
    tip.innerHTML = '<span>Install this app: tap <b>Share</b> then <b>Add to Home Screen</b>.</span><button class="btn btn-sm" type="button">OK</button>';
    tip.querySelector('button').addEventListener('click', function () { localStorage.setItem('sba-ios-hint', '1'); tip.remove(); });
    document.body.appendChild(tip);
  }
  if (!isStandalone && !isiOS) installBtns.forEach(function (b) { b.hidden = false; });
})();
