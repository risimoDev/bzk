// /assets/js/notifications.js
(function () {
  'use strict';

  const TOAST_LIFETIME = 5000; // ms
  const containerId = 'toast-container';

  const TYPE_CLASSES = {
    success: {
      base: 'bg-green-50 border-green-200 text-green-800',
      icon: `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>`
    },
    error: {
      base: 'bg-red-50 border-red-200 text-red-800',
      icon: `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>`
    },
    warning: {
      base: 'bg-yellow-50 border-yellow-200 text-yellow-800',
      icon: `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4m0 4h.01"/></svg>`
    },
    info: {
      base: 'bg-indigo-50 border-indigo-200 text-indigo-800',
      icon: `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 20a8 8 0 100-16 8 8 0 000 16z"/></svg>`
    }
  };

  function ensureContainer() {
    let container = document.getElementById(containerId);
    if (container) return container;

    container = document.createElement('div');
    container.id = containerId;
    // position fixed top-right, responsive padding
    container.className = 'fixed top-4 right-4 z-[9999] flex flex-col gap-3 max-w-sm w-full';
    document.body.appendChild(container);
    return container;
  }

  function createToast({ id = null, title = '', message = '', type = 'info', persistent = false }) {
    const conf = TYPE_CLASSES[type] || TYPE_CLASSES.info;
    const toast = document.createElement('div');

    toast.className = `flex items-start gap-3 p-4 border rounded-xl shadow-sm ${conf.base} animate-slideIn`;
    toast.setAttribute('role', 'alert');
    if (id) toast.dataset.notificationId = id;

    toast.innerHTML = `
      <div class="flex-none">${conf.icon}</div>
      <div class="flex-1 min-w-0">
        ${title ? `<div class="font-semibold text-sm mb-1">${escapeHtml(title)}</div>` : ''}
        <div class="text-sm leading-snug">${escapeHtml(message)}</div>
      </div>
      <button type="button" class="ml-3 flex-none text-xl leading-none close-toast" aria-label="Закрыть">&times;</button>
    `;

    // auto remove if not persistent
    let timeoutHandle = null;
    function startTimer() {
      if (persistent) return;
      timeoutHandle = setTimeout(() => removeToast(toast), TOAST_LIFETIME);
    }
    function stopTimer() {
      if (timeoutHandle) { clearTimeout(timeoutHandle); timeoutHandle = null; }
    }

    toast.addEventListener('mouseenter', stopTimer);
    toast.addEventListener('mouseleave', startTimer);

    toast.querySelector('.close-toast').addEventListener('click', () => removeToast(toast));

    // Add to DOM
    const container = ensureContainer();
    container.appendChild(toast);

    // start timer
    startTimer();

    return toast;
  }

  function removeToast(node) {
    if (!node) return;
    node.style.transition = 'opacity .18s, transform .18s';
    node.style.opacity = 0;
    node.style.transform = 'translateY(-6px) scale(.99)';
    setTimeout(() => {
      if (node && node.parentNode) node.parentNode.removeChild(node);
    }, 200);
    // optional: tell server that site notification was seen
    const id = node.dataset.notificationId;
    if (id) {
      // try to call endpoint to mark seen (implement server-side optionally)
      fetch('/admin/ajax/mark_site_notification_seen.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
      }).catch(() => {/* ignore errors */});
    }
  }

  // Escape HTML simple
  function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  // Public: showToast convenience
  window.showToast = function (opts) {
    createToast(opts);
  };

  // Initialise from server arrays
  function initFromServer() {
    try {
      const server = window.serverNotifications || [];
      server.forEach(n => {
        // session notifications typically: {type:'success'|'error', message: '...'}
        createToast({
          id: n.id || null,
          title: n.title || '',
          message: n.message || n.message || n,
          type: n.type || (n.level || 'info'),
          persistent: !!n.persistent
        });
      });
    } catch (e) {
      console.error('initFromServer: ', e);
    }

    try {
      const site = window.siteNotifications || [];
      site.forEach(n => {
        createToast({
          id: n.id || null,
          title: n.title || '',
          message: n.message || '',
          type: n.type || 'info',
          persistent: !!(n.persistent && Number(n.persistent) === 1)
        });
      });
    } catch (e) {
      console.error('initSiteNotifications: ', e);
    }
  }

  // Cookie banner
  function showCookieBanner() {
    if (localStorage.getItem('cookie_consent') === '1') return;

    // avoid duplicate banner
    if (document.getElementById('cookie-banner')) return;

    const banner = document.createElement('div');
    banner.id = 'cookie-banner';
    banner.className = 'fixed bottom-4 left-4 right-4 z-[9998] md:left-10 md:right-auto md:max-w-2xl mx-auto bg-white border rounded-2xl shadow-lg p-4 flex flex-col md:flex-row items-center gap-4';
    banner.innerHTML = `
      <div class="flex-1 text-sm text-gray-700">
        <div class="font-semibold mb-1">Мы используем cookies</div>
        <div>Этот сайт использует файлы cookie для улучшения работы и аналитики. Продолжая использование, вы соглашаетесь с нашей политикой.</div>
      </div>
      <div class="flex gap-2 items-center">
        <a href="/privacy" class="px-4 py-2 rounded-lg border border-gray-200 text-sm hover:bg-gray-50">Подробнее</a>
        <button id="cookie-accept" class="px-4 py-2 bg-[#118568] text-white rounded-lg text-sm">Принять</button>
      </div>
    `;
    document.body.appendChild(banner);

    document.getElementById('cookie-accept').addEventListener('click', function () {
      localStorage.setItem('cookie_consent', '1');
      banner.style.transition = 'opacity .18s, transform .18s';
      banner.style.opacity = 0;
      banner.style.transform = 'translateY(10px)';
      setTimeout(() => banner.remove(), 200);
    });
  }

  // Init when DOM ready
  document.addEventListener('DOMContentLoaded', function () {
    initFromServer();
    // small delay to not clash with page load
    setTimeout(() => showCookieBanner(), 600);
  });

  // optional small animation CSS injection (slideIn)
  const style = document.createElement('style');
  style.innerHTML = `
    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-6px) scale(.98); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .animate-slideIn { animation: slideIn .14s ease-out; }
  `;
  document.head.appendChild(style);
})();
