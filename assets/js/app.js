/* Examora — interaksi umum */
(function () {
  'use strict';

  // Sidebar
  var sidebar = document.querySelector('.sidebar');
  var backdrop = document.querySelector('.sidebar-backdrop');
  document.querySelectorAll('[data-sidebar-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (window.innerWidth < 992) {
        sidebar.classList.toggle('show');
        backdrop && backdrop.classList.toggle('show');
      } else {
        sidebar.classList.toggle('collapsed');
        try { localStorage.setItem('cbt_sidebar', sidebar.classList.contains('collapsed') ? '1' : '0'); } catch (e) {}
      }
    });
  });
  try {
    if (localStorage.getItem('cbt_sidebar') === '1' && window.innerWidth >= 992) sidebar.classList.add('collapsed');
  } catch (e) {}
  backdrop && backdrop.addEventListener('click', function () {
    sidebar.classList.remove('show');
    backdrop.classList.remove('show');
  });

  // Konfirmasi form & notifikasi ditangani oleh ui.js (modal/toast aplikasi).

  // Token uppercase
  document.querySelectorAll('input[data-upper]').forEach(function (el) {
    el.addEventListener('input', function () { el.value = el.value.toUpperCase(); });
  });

  // Tampilkan nama file
  document.querySelectorAll('input[type=file][data-label]').forEach(function (el) {
    el.addEventListener('change', function () {
      var target = document.querySelector(el.getAttribute('data-label'));
      if (target) target.textContent = el.files.length ? el.files[0].name : 'Belum ada file dipilih';
    });
  });

  // Modal bootstrap otomatis
  document.querySelectorAll('[data-bs-toggle="modal"]').forEach(function () {});

  // Ikon mata untuk tampilkan / sembunyikan password
  var eyeOn = '<svg class="pw-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
  var eyeOff = '<svg class="pw-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><path d="M3 3l18 18"/></svg>';
  document.querySelectorAll('input[type=password]').forEach(function (input) {
    if (input.hasAttribute('data-no-toggle') || input.closest('.pw-field')) return;
    var wrap = document.createElement('div');
    wrap.className = 'pw-field';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pw-toggle';
    btn.setAttribute('aria-label', 'Tampilkan password');
    btn.setAttribute('aria-pressed', 'false');
    btn.innerHTML = eyeOn + eyeOff;
    wrap.appendChild(btn);

    btn.addEventListener('click', function () {
      var show = input.getAttribute('type') === 'password';
      input.setAttribute('type', show ? 'text' : 'password');
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
      try { input.focus({ preventScroll: true }); } catch (e) { input.focus(); }
    });
  });
})();
