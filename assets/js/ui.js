/* Examora — komponen UI: toast & modal (pengganti alert/confirm browser) */
(function (window, document) {
  'use strict';

  var ICONS = {
    success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
    danger: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>',
    warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9L2.4 17a2 2 0 001.7 3h15.8a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>',
    info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/></svg>',
    question: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.1 9a3 3 0 015.8 1c0 2-3 2.5-3 4"/><path d="M12 17h.01"/></svg>'
  };

  var TITLES = {
    success: 'Berhasil',
    danger: 'Gagal',
    warning: 'Perhatian',
    info: 'Informasi',
    question: 'Konfirmasi'
  };

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function normalizeTone(type) {
    return ICONS[type] ? type : 'info';
  }

  /* ------------------------------------------------------------------
   * Toast
   * ------------------------------------------------------------------ */
  var toastHost = null;

  function getToastHost() {
    if (toastHost && document.body.contains(toastHost)) {
      return toastHost;
    }
    toastHost = document.querySelector('.ui-toast-host');
    if (!toastHost) {
      toastHost = document.createElement('div');
      toastHost.className = 'ui-toast-host';
      toastHost.setAttribute('aria-live', 'polite');
      toastHost.setAttribute('aria-atomic', 'false');
      document.body.appendChild(toastHost);
    }
    return toastHost;
  }

  function toast(message, type, options) {
    options = options || {};
    var tone = normalizeTone(type);
    var host = getToastHost();
    var timeout = options.timeout == null ? 5200 : Number(options.timeout);

    var item = document.createElement('div');
    item.className = 'ui-toast ui-toast--' + tone;
    item.setAttribute('role', tone === 'danger' || tone === 'warning' ? 'alert' : 'status');
    item.innerHTML =
      '<div class="ui-toast-ic">' + ICONS[tone] + '</div>' +
      '<div class="ui-toast-body">' +
        '<div class="ui-toast-title">' + escapeHtml(options.title || TITLES[tone]) + '</div>' +
        '<div class="ui-toast-msg">' + escapeHtml(message) + '</div>' +
      '</div>' +
      '<button type="button" class="ui-toast-x" aria-label="Tutup notifikasi">&times;</button>' +
      (timeout > 0 ? '<span class="ui-toast-bar" style="animation-duration:' + timeout + 'ms"></span>' : '');

    host.appendChild(item);

    var timer = null;
    var closed = false;
    function close() {
      if (closed) return;
      closed = true;
      if (timer) window.clearTimeout(timer);
      item.classList.remove('is-in');
      item.classList.add('is-out');
      window.setTimeout(function () { item.remove(); }, 320);
    }

    item.querySelector('.ui-toast-x').addEventListener('click', close);
    if (timeout > 0) {
      timer = window.setTimeout(close, timeout);
      item.addEventListener('mouseenter', function () { if (timer) window.clearTimeout(timer); item.classList.add('is-paused'); });
      item.addEventListener('mouseleave', function () {
        if (closed) return;
        item.classList.remove('is-paused');
        timer = window.setTimeout(close, 1600);
      });
    }

    window.requestAnimationFrame(function () { item.classList.add('is-in'); });
    return { close: close, node: item };
  }

  /* ------------------------------------------------------------------
   * Modal dialog (Promise)
   * ------------------------------------------------------------------ */
  var current = null;

  function dialog(options) {
    options = options || {};
    var tone = normalizeTone(options.tone || options.type || (options.alert ? 'info' : 'question'));
    var isAlert = options.alert === true;

    return new Promise(function (resolve) {
      if (current && current.close) {
        current.close(false);
      }

      var previousFocus = document.activeElement;
      var backdrop = document.createElement('div');
      backdrop.className = 'ui-modal-backdrop';

      var card = document.createElement('div');
      card.className = 'ui-modal ui-modal--' + tone;
      card.setAttribute('role', 'dialog');
      card.setAttribute('aria-modal', 'true');
      card.setAttribute('aria-labelledby', 'ui-modal-title');
      card.setAttribute('aria-describedby', 'ui-modal-msg');

      var title = options.title || TITLES[tone];
      card.innerHTML =
        '<div class="ui-modal-ic">' + ICONS[tone] + '</div>' +
        '<h3 class="ui-modal-title" id="ui-modal-title">' + escapeHtml(title) + '</h3>' +
        '<div class="ui-modal-msg" id="ui-modal-msg"></div>' +
        '<div class="ui-modal-actions">' +
          (isAlert ? '' : '<button type="button" class="btn btn-ghost ui-modal-cancel"></button>') +
          '<button type="button" class="btn ui-modal-ok ui-modal-ok--' + tone + '"></button>' +
        '</div>';

      var msgNode = card.querySelector('.ui-modal-msg');
      msgNode.textContent = String(options.message == null ? '' : options.message);

      var okBtn = card.querySelector('.ui-modal-ok');
      var cancelBtn = card.querySelector('.ui-modal-cancel');
      okBtn.textContent = options.confirmText || (isAlert ? 'Mengerti' : 'Ya, lanjutkan');
      if (cancelBtn) {
        cancelBtn.textContent = options.cancelText || 'Batal';
      }

      backdrop.appendChild(card);
      document.body.appendChild(backdrop);
      document.body.classList.add('ui-modal-open');

      var inst = { backdrop: backdrop, close: null };
      current = inst;

      function close(result) {
        if (current !== inst) return;
        current = null;
        document.removeEventListener('keydown', onKey, true);
        backdrop.classList.remove('is-show');
        document.body.classList.remove('ui-modal-open');
        window.setTimeout(function () {
          backdrop.remove();
          if (previousFocus && typeof previousFocus.focus === 'function') {
            try { previousFocus.focus({ preventScroll: true }); } catch (e) { previousFocus.focus(); }
          }
        }, 220);
        resolve(result);
      }
      inst.close = close;

      function onKey(ev) {
        if (ev.key === 'Escape') {
          ev.preventDefault();
          close(false);
        } else if (ev.key === 'Tab') {
          var focusables = [okBtn, cancelBtn].filter(function (b) { return b && b.offsetParent !== null; });
          if (!focusables.length) return;
          var first = focusables[focusables.length - 1];
          var last = focusables[0];
          if (ev.shiftKey && document.activeElement === last) {
            ev.preventDefault();
            first.focus();
          } else if (!ev.shiftKey && document.activeElement === first) {
            ev.preventDefault();
            last.focus();
          }
        }
      }

      okBtn.addEventListener('click', function () { close(true); });
      if (cancelBtn) cancelBtn.addEventListener('click', function () { close(false); });
      backdrop.addEventListener('mousedown', function (ev) {
        if (ev.target === backdrop && options.backdrop !== false) close(false);
      });
      document.addEventListener('keydown', onKey, true);

      window.requestAnimationFrame(function () { backdrop.classList.add('is-show'); });
      window.setTimeout(function () {
        var target = (!isAlert && (tone === 'danger' || tone === 'warning')) ? (cancelBtn || okBtn) : okBtn;
        try { target.focus({ preventScroll: true }); } catch (e) { target.focus(); }
      }, 60);
    });
  }

  function confirmDialog(options) {
    if (typeof options === 'string') options = { message: options };
    options = options || {};
    return dialog(options).then(function (ok) { return ok === true; });
  }

  function alertDialog(options) {
    if (typeof options === 'string') options = { message: options };
    options = options || {};
    options.alert = true;
    return dialog(options).then(function () { return undefined; });
  }

  /* ------------------------------------------------------------------
   * Form dengan data-confirm → modal aplikasi
   * ------------------------------------------------------------------ */
  function isDestructive(text) {
    return /(hapus|delete|peringatan|reset|regenerate|kunci|bersihkan|kosongkan)/i.test(text || '');
  }

  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!form || form.nodeName !== 'FORM') return;
    var message = form.getAttribute('data-confirm');
    if (!message) return;
    if (form.getAttribute('data-ui-bypass') === '1') return;

    ev.preventDefault();
    if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();

    var destructive = isDestructive(message);
    var hapus = /(hapus|delete)/i.test(message);
    confirmDialog({
      title: form.getAttribute('data-confirm-title') || (hapus ? 'Hapus data?' : (destructive ? 'Konfirmasi tindakan' : 'Konfirmasi')),
      message: message,
      confirmText: form.getAttribute('data-confirm-ok') || (hapus ? 'Ya, hapus' : (destructive ? 'Ya, lanjutkan' : 'Ya')),
      cancelText: form.getAttribute('data-confirm-cancel') || 'Batal',
      tone: destructive ? 'danger' : 'question'
    }).then(function (ok) {
      if (!ok) return;
      form.setAttribute('data-ui-bypass', '1');
      window.setTimeout(function () { form.removeAttribute('data-ui-bypass'); }, 60);
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        form.submit();
      }
    });
  }, true);

  /* ------------------------------------------------------------------
   * Ubah alert notifikasi bawaan (flash message) menjadi toast
   * ------------------------------------------------------------------ */
  function upgradeFlashes() {
    var nodes = document.querySelectorAll('.alert[data-auto]');
    Array.prototype.forEach.call(nodes, function (node, index) {
      var type = 'info';
      ['success', 'danger', 'warning', 'info'].forEach(function (t) {
        if (node.classList.contains('alert-' + t)) type = t;
      });
      var message = (node.textContent || '').trim();
      node.remove();
      if (!message) return;
      window.setTimeout(function () {
        toast(message, type, { timeout: type === 'danger' ? 8000 : 5600 });
      }, index * 120);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', upgradeFlashes);
  } else {
    upgradeFlashes();
  }

  /* ------------------------------------------------------------------
   * Salin ke clipboard: klik elemen ber-atribut data-copy
   * ------------------------------------------------------------------ */
  function salinManual(isi) {
    return new Promise(function (resolve, reject) {
      var ta = document.createElement('textarea');
      ta.value = isi;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.top = '-1000px';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
      document.body.removeChild(ta);
      if (ok) { resolve(isi); } else { reject(new Error('clipboard ditolak')); }
    });
  }

  function salinTeks(nilai) {
    var isi = String(nilai == null ? '' : nilai).trim();
    if (!isi) {
      return Promise.reject(new Error('teks kosong'));
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(isi).then(function () { return isi; }, function () {
        return salinManual(isi);
      });
    }
    return salinManual(isi);
  }

  /**
   * Modal salin manual: dipakai bila browser memblokir clipboard otomatis.
   * Isi token ditampilkan pada input readonly yang langsung terseleksi,
   * sehingga pengguna cukup menekan Ctrl+C / Cmd+C.
   */
  function dialogSalin(isi) {
    var wrap = document.createElement('div');
    wrap.className = 'ui-modal-backdrop';
    var card = document.createElement('div');
    card.className = 'ui-modal ui-modal--info';
    card.setAttribute('role', 'dialog');
    card.setAttribute('aria-modal', 'true');
    card.setAttribute('aria-labelledby', 'ui-salin-title');

    var title = document.createElement('h3');
    title.className = 'ui-modal-title';
    title.id = 'ui-salin-title';
    title.textContent = 'Salin token manual';

    var msg = document.createElement('div');
    msg.className = 'ui-modal-msg';
    msg.style.width = '100%';
    msg.textContent = 'Browser menolak akses clipboard. Teks di bawah sudah dipilih — tekan Ctrl+C (Cmd+C di Mac).';

    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control mt-2';
    input.readOnly = true;
    input.value = isi;
    input.style.fontFamily = 'monospace';
    input.style.fontWeight = '700';
    input.style.letterSpacing = '.06em';

    var actions = document.createElement('div');
    actions.className = 'ui-modal-actions';
    var ok = document.createElement('button');
    ok.type = 'button';
    ok.className = 'btn ui-modal-ok ui-modal-ok--info';
    ok.textContent = 'Selesai';
    actions.appendChild(ok);

    card.appendChild(title);
    card.appendChild(msg);
    card.appendChild(input);
    card.appendChild(actions);
    wrap.appendChild(card);
    document.body.appendChild(wrap);
    document.body.classList.add('ui-modal-open');

    var tutup = function () {
      document.removeEventListener('keydown', onKey, true);
      wrap.classList.remove('is-show');
      document.body.classList.remove('ui-modal-open');
      window.setTimeout(function () { wrap.remove(); }, 220);
    };
    var onKey = function (ev) {
      if (ev.key === 'Escape') {
        ev.preventDefault();
        tutup();
      }
    };
    ok.addEventListener('click', tutup);
    wrap.addEventListener('mousedown', function (ev) { if (ev.target === wrap) tutup(); });
    document.addEventListener('keydown', onKey, true);
    window.requestAnimationFrame(function () { wrap.classList.add('is-show'); });
    window.setTimeout(function () {
      try { input.focus({ preventScroll: true }); } catch (e) { input.focus(); }
      input.select();
    }, 60);
  }

  function salinElemen(el) {
    var nilai = el.getAttribute('data-copy');
    // Pilih dulu teksnya: bila browser memblokir clipboard, pengguna tetap
    // bisa menekan Ctrl+C / Cmd+C untuk menyalin token.
    try {
      var sel = window.getSelection && window.getSelection();
      if (sel) {
        var range = document.createRange();
        range.selectNodeContents(el);
        sel.removeAllRanges();
        sel.addRange(range);
      }
    } catch (e) { /* abaikan */ }
    var berhasil = function (isi) {
      toast(isi, 'success', { title: el.getAttribute('data-copy-title') || 'Disalin ke clipboard', timeout: 2600 });
      el.classList.add('is-copied');
      window.setTimeout(function () { el.classList.remove('is-copied'); }, 1200);
    };
    salinTeks(nilai).then(berhasil).catch(function () {
      // Cadangan: teks sudah terseleksi di atas, coba salin dari seleksi itu.
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
      if (ok) {
        berhasil(String(nilai == null ? '' : nilai).trim());
        return;
      }
      dialogSalin(String(nilai == null ? '' : nilai).trim());
    });
  }

  document.addEventListener('click', function (ev) {
    var el = ev.target && ev.target.closest ? ev.target.closest('[data-copy]') : null;
    if (!el) return;
    ev.preventDefault();
    salinElemen(el);
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter' && ev.key !== ' ' && ev.key !== 'Spacebar') return;
    var el = ev.target && ev.target.closest ? ev.target.closest('[data-copy]') : null;
    if (!el) return;
    ev.preventDefault();
    salinElemen(el);
  });

  // Peringatan tambahan untuk perubahan data yang belum disimpan akan tetap
  // memakai dialog browser (tidak dapat diganti karena bersifat sinkron).
  window.UI = {
    toast: toast,
    copy: salinTeks,
    dialog: dialog,
    confirm: confirmDialog,
    alert: alertDialog,
    icons: ICONS
  };
})(window, document);
