/* Examora — mesin ujian sisi klien */
(function () {
  'use strict';
  var holder = document.getElementById('exam-data');
  if (!holder) return;

  var data;
  try { data = JSON.parse(holder.textContent); } catch (e) { return; }

  var state = {
    answers: data.jawaban || {},
    current: 1,
    timers: {},
    selesai: false,
    violations: data.pelanggaran || 0,
    lastViolation: 0
  };

  // ui.js menyediakan dialog aplikasi; fallback ke browser bila tidak termuat.
  var UI = window.UI || {
    confirm: function (opts) { return Promise.resolve(window.confirm(opts.message || String(opts))); },
    alert: function (opts) { window.alert(opts.message || String(opts)); return Promise.resolve(); }
  };

  var esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };

  var $ljk = document.getElementById('ljk-body');
  var $nav = document.getElementById('ljk-nav');
  var $save = document.getElementById('save-state');
  var $timer = document.getElementById('timer');
  var $hint = document.getElementById('ljk-hint');

  function localKey() { return 'cbt_ans_' + data.id_riwayat; }

  function loadLocal() {
    try {
      var raw = localStorage.getItem(localKey());
      if (!raw) return;
      var obj = JSON.parse(raw);
      Object.keys(obj).forEach(function (n) {
        if (state.answers[n] === undefined || state.answers[n] === null || state.answers[n] === '' ||
            (Array.isArray(state.answers[n]) && state.answers[n].length === 0)) {
          state.answers[n] = obj[n];
        }
      });
    } catch (e) {}
  }

  function saveLocal() {
    try { localStorage.setItem(localKey(), JSON.stringify(state.answers)); } catch (e) {}
  }

  function block(n) { return document.querySelector('.q-block[data-n="' + n + '"]'); }

  /** Teks opsi untuk ditampilkan. Buang awalan huruf ("A. jawaban") agar huruf tidak dobel. */
  function teksOpsi(h, teks) {
    var t = teks == null ? '' : String(teks).replace(/\s+/g, ' ').trim();
    if (/^Pilihan\s+[A-E]$/i.test(t)) return '';
    var re = new RegExp('^' + h + '\\s*[.)\\-]\\s*', 'i');
    return t.replace(re, '').replace(/\s+/g, ' ').trim();
  }

  function renderQuestion(q) {
    var html = '<div class="q-block" data-n="' + q.nomor + '" data-tipe="' + esc(q.tipe) + '">';
    html += '<div class="head"><span class="num">' + q.nomor + '</span><span class="type">' + labelTipe(q.tipe) + '</span></div>';
    if (q.pertanyaan) html += '<div class="mb-2" style="font-size:.88rem;white-space:pre-wrap">' + esc(q.pertanyaan) + '</div>';

    if (q.tipe === 'PG') {
      html += '<div class="opt-row" role="radiogroup" aria-label="Pilihan jawaban nomor ' + q.nomor + '">';
      (q.huruf || ['A', 'B', 'C', 'D']).forEach(function (h, i) {
        var teks = teksOpsi(h, q.opsi && q.opsi[i] ? q.opsi[i] : '');
        html += '<label class="opt' + (state.answers[q.nomor] === h ? ' sel' : '') + '">' +
          '<input type="radio" name="q' + q.nomor + '" value="' + h + '"' + (state.answers[q.nomor] === h ? ' checked' : '') + '>' +
          '<span class="dot" aria-hidden="true"></span>' +
          '<span class="huruf">' + h + '</span>' +
          (teks !== '' ? '<span class="opt-teks">' + esc(teks) + '</span>' : '') +
          '</label>';
      });
      html += '</div>';
    } else if (q.tipe === 'PG_Kompleks') {
      var dipilih = Array.isArray(state.answers[q.nomor]) ? state.answers[q.nomor] : [];
      html += '<div class="opt-row" role="group" aria-label="Pilihan jawaban nomor ' + q.nomor + '">';
      (q.huruf || ['A', 'B', 'C', 'D']).forEach(function (h, i) {
        var teks = teksOpsi(h, q.opsi && q.opsi[i] ? q.opsi[i] : '');
        html += '<label class="opt' + (dipilih.indexOf(h) >= 0 ? ' sel' : '') + '">' +
          '<input type="checkbox" name="q' + q.nomor + '" value="' + h + '"' + (dipilih.indexOf(h) >= 0 ? ' checked' : '') + '>' +
          '<span class="dot" aria-hidden="true"></span>' +
          '<span class="huruf">' + h + '</span>' +
          (teks !== '' ? '<span class="opt-teks">' + esc(teks) + '</span>' : '') +
          '</label>';
      });
      html += '</div>';
      html += '<div class="form-text mt-1">Pilih semua jawaban yang benar.</div>';
    } else if (q.tipe === 'Benar_Salah') {
      var jw = Array.isArray(state.answers[q.nomor]) ? state.answers[q.nomor] : [];
      (q.pernyataan || []).forEach(function (p, i) {
        var v = jw[i];
        html += '<div class="mb-2" style="font-size:.88rem">' + (i + 1) + '. ' + esc(p) + '</div>' +
          '<div class="d-flex gap-3 mb-2 ps-3">' +
          '<label class="q-label' + (v === true || v === '1' || v === 1 ? ' sel' : '') + '"><input type="radio" name="bs' + q.nomor + '_' + i + '" value="1"' + (v === true || v === '1' || v === 1 ? ' checked' : '') + '> Benar</label>' +
          '<label class="q-label' + (v === false || v === '0' || v === 0 ? ' sel' : '') + '"><input type="radio" name="bs' + q.nomor + '_' + i + '" value="0"' + (v === false || v === '0' || v === 0 ? ' checked' : '') + '> Salah</label>' +
          '</div>';
      });
    } else if (q.tipe === 'Menjodohkan') {
      var jm = Array.isArray(state.answers[q.nomor]) ? state.answers[q.nomor] : [];
      var pilihan = q.pilihan_kanan || [];
      (q.kiri || []).forEach(function (k, i) {
        html += '<div class="match-row"><div>' + esc(k) + '</div><select class="form-select form-select-sm" name="mj' + q.nomor + '_' + i + '">' +
          '<option value="">— pilih —</option>';
        pilihan.forEach(function (p) {
          html += '<option value="' + esc(p) + '"' + (jm[i] === p ? ' selected' : '') + '>' + esc(p) + '</option>';
        });
        html += '</select></div>';
      });
    } else if (q.tipe === 'Isian') {
      html += '<input type="text" class="form-control" name="isian' + q.nomor + '" autocomplete="off" value="' +
        esc(typeof state.answers[q.nomor] === 'string' ? state.answers[q.nomor] : '') + '" placeholder="Tulis jawaban singkat">';
    } else {
      html += '<textarea class="q-textarea" name="uraian' + q.nomor + '" placeholder="Tulis jawaban uraian Anda di sini...">' +
        esc(typeof state.answers[q.nomor] === 'string' ? state.answers[q.nomor] : '') + '</textarea>';
    }
    html += '</div>';
    return html;
  }

  function labelTipe(t) {
    return {
      'PG': 'Pilihan Ganda', 'PG_Kompleks': 'Pilihan Ganda Kompleks',
      'Benar_Salah': 'Benar / Salah', 'Menjodohkan': 'Menjodohkan',
      'Isian': 'Isian Singkat', 'Uraian': 'Uraian'
    }[t] || t;
  }

  function renderAll() {
    $ljk.innerHTML = data.soal.map(renderQuestion).join('');
    renderNav();
  }

  function renderNav() {
    $nav.innerHTML = data.soal.map(function (q) {
      var n = q.nomor;
      var cls = 'nav-btn';
      if (terisi(q)) cls += ' answered';
      if (n === state.current) cls += ' active';
      return '<button type="button" class="' + cls + '" data-nav="' + n + '">' + n + '</button>';
    }).join('');
    var terjawab = data.soal.filter(function (q) { return terisi(q); }).length;
    var el = document.getElementById('ljk-count');
    if (el) el.textContent = terjawab + '/' + data.soal.length + ' terjawab';
    syncFinishGate();
  }

  function collect(n) {
    var b = block(n);
    if (!b) return state.answers[n];
    var tipe = b.getAttribute('data-tipe');
    if (tipe === 'PG') {
      var r = b.querySelector('input[type=radio]:checked');
      return r ? r.value : null;
    }
    if (tipe === 'PG_Kompleks') {
      return Array.prototype.slice.call(b.querySelectorAll('input[type=checkbox]:checked')).map(function (x) { return x.value; });
    }
    if (tipe === 'Benar_Salah') {
      var radios = {};
      b.querySelectorAll('input[type=radio]:checked').forEach(function (x) {
        var m = /^bs\d+_(\d+)$/.exec(x.name);
        if (m) radios[m[1]] = x.value === '1';
      });
      var keys = Object.keys(radios);
      if (!keys.length) return null;
      var max = -1;
      keys.forEach(function (k) { max = Math.max(max, parseInt(k, 10)); });
      var arr = [];
      for (var i = 0; i <= max; i++) {
        arr[i] = radios.hasOwnProperty(i) ? radios[i] : null;
      }
      return arr;
    }
    if (tipe === 'Menjodohkan') {
      return Array.prototype.slice.call(b.querySelectorAll('select')).map(function (s) { return s.value || ''; });
    }
    if (tipe === 'Isian') {
      var inp = b.querySelector('input[type=text]');
      return inp && inp.value.trim() !== '' ? inp.value.trim() : null;
    }
    var ta = b.querySelector('textarea');
    return ta && ta.value.trim() !== '' ? ta.value.trim() : null;
  }

  function answered(v) {
    if (v === undefined || v === null || v === '') return false;
    if (Array.isArray(v)) {
      // array berisi semua nilai kosong (mis. menjodohkan belum dipilih)
      // dianggap belum ada jawaban.
      return v.some(function (x) { return x !== undefined && x !== null && x !== ''; });
    }
    return typeof v === 'string' ? v.trim() !== '' : true;
  }

  /** Soal dianggap terisi penuh — dasar gerbang "wajib isi semua soal". */
  function terisi(q) {
    var v = state.answers[q.nomor];
    if (q.tipe === 'Benar_Salah') {
      var n = (q.pernyataan || []).length;
      if (n === 0) return true;
      return Array.isArray(v) && v.length >= n && v.every(function (x) {
        return x !== undefined && x !== null && x !== '';
      });
    }
    if (q.tipe === 'Menjodohkan') {
      var m = (q.kiri || []).length;
      if (m === 0) return true;
      return Array.isArray(v) && v.length >= m && v.every(function (x) {
        return x !== undefined && x !== null && String(x).trim() !== '';
      });
    }
    if (q.tipe === 'PG_Kompleks') return Array.isArray(v) && v.length > 0;
    return answered(v);
  }

  function soalKosong() {
    return data.soal.filter(function (q) { return !terisi(q); });
  }

  function scheduleSave(n) {
    clearTimeout(state.timers[n]);
    state.timers[n] = setTimeout(function () { saveAnswer(n); }, 700);
  }

  function saveAnswer(n) {
    var value = state.answers[n];
    saveLocal();
    if (!answered(value)) {
      $save.className = 'save-state';
      $save.textContent = 'Menunggu jawaban';
      return;
    }
    var fd = new FormData();
    fd.append('_token', data.csrf);
    fd.append('id_riwayat', data.id_riwayat);
    fd.append('nomor', n);
    if (Array.isArray(value)) {
      value.forEach(function (v) { fd.append('jawaban[]', typeof v === 'boolean' ? (v ? '1' : '0') : v); });
    } else {
      fd.append('jawaban', value);
    }
    $save.className = 'save-state';
    $save.textContent = 'Menyimpan…';

    fetch(data.api.jawab, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': data.csrf }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j.terkunci) {
          $save.className = 'save-state err';
          $save.textContent = 'Terkunci';
          showOverlay('Ujian dikunci', j.pesan || 'Hubungi guru/pengawas untuk membuka kunci.', false, true);
          return;
        }
        if (!j.ok) throw new Error(j.pesan || 'Gagal menyimpan');
        $save.className = 'save-state ok';
        $save.textContent = 'Tersimpan ' + j.tersimpan;
        renderNav();
      })
      .catch(function () {
        $save.className = 'save-state err';
        $save.textContent = 'Gagal menyimpan';
        setTimeout(function () { saveAnswer(n); }, 3000);
      });
  }

  // event delegation
  $ljk.addEventListener('change', function (ev) {
    var b = ev.target.closest('.q-block');
    if (!b) return;
    var n = parseInt(b.getAttribute('data-n'), 10);
    state.answers[n] = collect(n);
    // Sorot opsi terpilih: label .opt (PG / PG kompleks) dan .q-label (benar-salah)
    // sama-sama memakai kelas .sel, jadi perbarui keduanya di dalam blok ini.
    b.querySelectorAll('label.opt, label.q-label').forEach(function (l) {
      var inp = l.querySelector('input');
      if (!inp) return;
      l.classList.toggle('sel', inp.checked);
    });
    renderNav();
    scheduleSave(n);
  });

  $ljk.addEventListener('input', function (ev) {
    var b = ev.target.closest('.q-block');
    if (!b) return;
    var n = parseInt(b.getAttribute('data-n'), 10);
    state.answers[n] = collect(n);
    renderNav();
    scheduleSave(n);
  });

  $ljk.addEventListener('focusin', function (ev) {
    var b = ev.target.closest('.q-block');
    if (!b) return;
    state.current = parseInt(b.getAttribute('data-n'), 10);
    renderNav();
  });

  $nav.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-nav]');
    if (!btn) return;
    var n = parseInt(btn.getAttribute('data-nav'), 10);
    state.current = n;
    var b = block(n);
    if (b) {
      // Gulir hanya di dalam lembar jawaban. scrollIntoView() bisa meneruskan
      // gulirannya ke dokumen bila ruang sudah habis, sehingga seluruh tampilan
      // ikut naik ke atas dan tidak bisa digeser kembali oleh pengguna.
      var top = b.getBoundingClientRect().top - $ljk.getBoundingClientRect().top + $ljk.scrollTop - 8;
      top = Math.max(0, top);
      if (typeof $ljk.scrollTo === 'function') $ljk.scrollTo({ top: top, behavior: 'smooth' });
      else $ljk.scrollTop = top;
    }
    renderNav();
  });

  // ---------- tampil/sembunyi daftar nomor soal ----------
  // Menyembunyikan daftar soal membuat area input jawaban lebih luas, terutama
  // saat layar pendek atau soal bertipe uraian. Pilihan diingat per ujian.
  var $navToggle = document.getElementById('ljk-nav-toggle');
  var navKey = 'cbt_nav_hidden_' + data.id_riwayat;

  function setNavHidden(hidden, persist) {
    if (!$nav || !$navToggle) return;
    $nav.hidden = hidden;
    $navToggle.setAttribute('aria-expanded', hidden ? 'false' : 'true');
    $navToggle.title = hidden ? 'Tampilkan daftar soal' : 'Sembunyikan daftar soal';
    if (persist) {
      try { localStorage.setItem(navKey, hidden ? '1' : '0'); } catch (e) {}
    }
  }

  if ($navToggle) {
    $navToggle.addEventListener('click', function () { setNavHidden(!$nav.hidden, true); });
    try { setNavHidden(localStorage.getItem(navKey) === '1', false); } catch (e) {}
  }

  // ---------- timer ----------
  var serverTime = new Date(String(data.server_time).replace(' ', 'T')).getTime();
  var clientTime = Date.now();
  var offset = clientTime - serverTime;
  var batas = new Date(String(data.batas_waktu).replace(' ', 'T')).getTime();
  var minBatas = data.min_selesai ? new Date(String(data.min_selesai).replace(' ', 'T')).getTime() : 0;

  function fmtSisa(sec) {
    sec = Math.max(0, Math.floor(sec));
    var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), d = sec % 60;
    return (h > 0 ? String(h).padStart(2, '0') + ':' : '') +
      String(m).padStart(2, '0') + ':' + String(d).padStart(2, '0');
  }

  function sisa() {
    return Math.floor((batas - (Date.now() - offset)) / 1000);
  }

  function tick() {
    var s = sisa();
    if (s <= 0) {
      $timer.textContent = '00:00';
      finish(true);
      return;
    }
    var m = Math.floor(s / 60), d = s % 60;
    $timer.textContent = String(m).padStart(2, '0') + ':' + String(d).padStart(2, '0');
    $timer.classList.toggle('warn', s <= 300);
    syncFinishGate();
  }

  function cekStatus() {
    fetch(data.api.status, {
      method: 'POST',
      body: (function () { var f = new FormData(); f.append('_token', data.csrf); f.append('id_riwayat', data.id_riwayat); return f; })(),
      headers: { 'X-CSRF-Token': data.csrf },
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (j.terkunci) {
        showOverlay('Ujian dikunci', j.pesan || 'Hubungi guru/pengawas untuk membuka kunci.', false, true);
        return;
      }
      if (j.ok && j.selesai && !state.selesai) {
        finish(true);
      }
    }).catch(function () {});
  }

  // ---------- anti-curang ----------
  var lastViolation = 0;
  function violation(jenis, force) {
    if ((!parseInt(data.deteksi_tab, 10) && !force) || state.selesai) return;
    var now = Date.now();
    if (now - lastViolation < 2500) return;
    lastViolation = now;
    var fd = new FormData();
    fd.append('_token', data.csrf);
    fd.append('id_riwayat', data.id_riwayat);
    fd.append('jenis', jenis);
    fetch(data.api.pelanggaran, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': data.csrf }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) return;
        state.violations = j.pelanggaran;
        if (j.aksi === 'akhiri') {
          showOverlay('Ujian dihentikan', 'Batas pelanggaran tercapai. Jawaban Anda telah dikumpulkan.', true);
          finish(true);
        } else if (j.aksi === 'kunci') {
          showOverlay('Ujian dikunci', 'Anda berpindah tab/aplikasi. Panggil pengawas untuk membuka kunci, lalu muat ulang halaman.', false, true);
        } else {
          showOverlay('Peringatan', 'Anda terdeteksi berpindah tab atau aplikasi (' + j.pelanggaran + '/' + data.maks_pelanggaran + '). Fokus kembali pada ujian.', false);
        }
      }).catch(function () {});
  }

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) violation('pindah_tab');
  });
  window.addEventListener('blur', function () { violation('kehilangan_fokus'); });

  // ---------- wajib fullscreen ----------
  var fsOverlay = document.getElementById('fs-overlay');
  var fsBtn = document.getElementById('fs-btn');
  var fsText = document.getElementById('fs-text');
  var fsAktif = false;
  var fsRoot = document.documentElement;
  var fsMendukung = !!(fsRoot.requestFullscreen || fsRoot.webkitRequestFullscreen);

  function fsElement() {
    return document.fullscreenElement || document.webkitFullscreenElement || null;
  }

  function tampilFs(pesan) {
    if (!fsOverlay) return;
    if (fsText && pesan) fsText.textContent = pesan;
    fsOverlay.classList.add('show');
  }

  function sembunyiFs() {
    if (fsOverlay) fsOverlay.classList.remove('show');
  }

  function masukFullscreen() {
    if (!fsMendukung) return;
    var hasil;
    try {
      hasil = fsRoot.requestFullscreen
        ? fsRoot.requestFullscreen()
        : fsRoot.webkitRequestFullscreen();
    } catch (e) {
      tampilFs('Browser menolak mode fullscreen. Izinkan fullscreen untuk situs ini, lalu coba lagi.');
      return;
    }

    function berhasil() {
      fsAktif = true;
      sembunyiFs();
    }
    function gagal() {
      // Jangan hitung sebagai pelanggaran bila siswa belum pernah masuk fullscreen.
      tampilFs('Aktifkan mode fullscreen untuk mengerjakan ujian. Klik tombol di bawah.');
    }

    if (hasil && typeof hasil.then === 'function') {
      hasil.then(berhasil).catch(gagal);
    } else {
      window.setTimeout(function () {
        if (fsElement()) berhasil(); else gagal();
      }, 300);
    }
  }

  function syncFullscreen() {
    if (fsElement()) {
      fsAktif = true;
      sembunyiFs();
      return;
    }
    if (state.selesai) return;
    if (fsAktif) {
      tampilFs('Anda keluar dari mode fullscreen. Klik tombol di bawah untuk kembali.');
      violation('keluar_fullscreen', true);
    }
  }

  if (fsBtn) {
    fsBtn.addEventListener('click', function () { masukFullscreen(); });
  }
  document.addEventListener('fullscreenchange', syncFullscreen);
  document.addEventListener('webkitfullscreenchange', syncFullscreen);

  if (fsMendukung && !state.selesai) {
    if (fsElement()) {
      fsAktif = true;
    } else {
      // Browser biasanya menolak requestFullscreen tanpa gestur pengguna.
      // Overlay menyediakan tombol yang berfungsi sebagai gestur tersebut.
      masukFullscreen();
    }
  }

  var overlay = document.getElementById('exam-overlay');
  var overlayTitle = document.getElementById('overlay-title');
  var overlayText = document.getElementById('overlay-text');
  var overlayBtn = document.getElementById('overlay-btn');

  function showOverlay(title, text, final, locked) {
    if (!overlay) return;
    overlayTitle.textContent = title;
    overlayText.textContent = text;
    overlay.classList.add('show');
    overlayBtn.textContent = final ? 'Lihat hasil' : (locked ? 'Muat ulang' : 'Lanjutkan');
    overlayBtn.onclick = function () {
      if (final) { location.href = data.api.hasil; }
      else if (locked) { location.reload(); }
      else { overlay.classList.remove('show'); }
    };
  }

  // ---------- selesai ----------
  // autoFailed mencegah finish() otomatis (timer habis / polling) mengulang
  // dan memunculkan alert berulang tiap detik bila pengumpulan gagal.
  // Penyintas tetap bisa mencoba manual lewat tombol Selesai.
  var autoFailed = false;

  function finish(auto) {
    if (state.selesai) return;
    if (auto && autoFailed) return;
    if (!auto && minBatas) {
      var sisaMin = Math.ceil((minBatas - (Date.now() - offset)) / 1000);
      if (sisaMin > 0) {
        UI.alert({
          title: 'Belum bisa dikumpulkan',
          message: 'Durasi minimal pengerjaan belum terpenuhi. Tombol kumpulkan terbuka dalam ' +
            fmtSisa(sisaMin) + '.',
          tone: 'warning',
          confirmText: 'Mengerti'
        });
        return;
      }
    }
    var terjawab = data.soal.filter(function (q) { return terisi(q); }).length;
    var kosong = data.soal.length - terjawab;

    // Gerbang wajib isi semua: kumpulkan manual ditolak selama masih ada
    // jawaban kosong. Pengumpulan otomatis (waktu habis) tetap berjalan.
    if (!auto && kosong > 0) {
      syncFinishGate();
      UI.alert({
        title: 'Masih ada soal kosong',
        message: kosong + ' dari ' + data.soal.length + ' soal belum dijawab.\n\n'
          + 'Lengkapi seluruh jawaban terlebih dahulu. Tombol "Selesai & Kumpulkan" '
          + 'tetap terkunci sampai tidak ada lagi jawaban yang kosong.',
        tone: 'warning',
        confirmText: 'Mengerti'
      });
      return;
    }

    function kumpulkan() {
      state.selesai = true;
      if (btnFinish) btnFinish.disabled = true;
      $save.textContent = 'Mengumpulkan…';

      function gagal(pesan) {
        state.selesai = false;
        syncFinishGate();
        if (auto) {
          autoFailed = true;
          $save.textContent = 'Pengumpulan gagal';
          UI.alert({
            title: 'Pengumpulan gagal',
            message: pesan + '\n\nPeriksa koneksi internet Anda, lalu tekan tombol "Selesai & Kumpulkan" untuk mencoba lagi.',
            tone: 'danger',
            confirmText: 'Mengerti'
          });
        } else {
          UI.alert({
            title: 'Pengumpulan gagal',
            message: pesan,
            tone: 'danger',
            confirmText: 'Mengerti'
          });
        }
      }

      var fd = new FormData();
      fd.append('_token', data.csrf);
      fd.append('id_riwayat', data.id_riwayat);
      fetch(data.api.selesai, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': data.csrf }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j.terkunci) {
            state.selesai = false;
            showOverlay('Ujian dikunci', j.pesan || 'Hubungi guru/pengawas untuk membuka kunci.', false, true);
            return;
          }
          if (j.ok) { location.href = j.redirect; }
          else { gagal(j.pesan || 'Gagal menyelesaikan ujian.'); }
        })
        .catch(function () {
          gagal('Koneksi bermasalah. Jawaban Anda tersimpan.');
        });
    }

    if (auto) {
      kumpulkan();
      return;
    }

    var pesan = 'Terjawab ' + terjawab + ' dari ' + data.soal.length + ' soal.\n\nJawaban tidak dapat diubah setelah dikumpulkan.';
    UI.confirm({
      title: 'Selesaikan ujian?',
      message: pesan,
      confirmText: 'Ya, kumpulkan',
      cancelText: 'Periksa lagi',
      tone: 'question'
    }).then(function (ok) { if (ok) kumpulkan(); });
  }

  var btnFinish = document.getElementById('btn-finish');
  var btnFinishLabel = btnFinish ? btnFinish.textContent : '';

  /**
   * Gerbang tombol kumpulkan. Dua syarat: durasi minimal pengerjaan terpenuhi
   * DAN semua soal sudah terjawab. Selama salah satu belum terpenuhi, tombol
   * dikunci dan alasan ditampilkan pada petunjuk di atasnya.
   */
  function syncFinishGate() {
    if (!btnFinish || state.selesai) return;
    var kosong = soalKosong().length;
    var sisaMin = minBatas ? Math.ceil((minBatas - (Date.now() - offset)) / 1000) : 0;
    var alasan = '';

    if (sisaMin > 0) {
      alasan = 'Tombol kumpulkan terbuka dalam ' + fmtSisa(sisaMin)
        + ' (durasi minimal pengerjaan belum terpenuhi).';
    } else if (kosong > 0) {
      alasan = 'Masih ada ' + kosong + ' dari ' + data.soal.length
        + ' soal yang kosong. Lengkapi semua jawaban untuk membuka tombol kumpulkan.';
    }

    if (alasan) {
      btnFinish.disabled = true;
      btnFinish.textContent = sisaMin > 0
        ? 'Terkunci · ' + fmtSisa(sisaMin)
        : 'Kurang ' + kosong + ' soal';
      btnFinish.title = alasan;
      if ($hint) {
        $hint.className = 'ljk-hint warn';
        $hint.textContent = alasan;
      }
      return;
    }

    if (btnFinish.disabled) {
      btnFinish.disabled = false;
      btnFinish.textContent = btnFinishLabel;
      btnFinish.removeAttribute('title');
    }
    if ($hint) {
      $hint.className = 'ljk-hint ok';
      $hint.textContent = 'Semua ' + data.soal.length + ' jawaban sudah terisi. Ujian siap dikumpulkan.';
    }
  }

  if (btnFinish) btnFinish.addEventListener('click', function () { finish(false); });

  // mobile tabs
  document.querySelectorAll('[data-exam-tab]').forEach(function (b) {
    b.addEventListener('click', function () {
      var wrap = document.querySelector('.exam-body-wrap');
      wrap.classList.toggle('mobile-soal', b.getAttribute('data-exam-tab') === 'soal');
      document.querySelectorAll('[data-exam-tab]').forEach(function (x) { x.classList.toggle('active', x === b); });
      // Lembar soal baru tampil setelah tab berganti, jadi beri tahu penampil
      // PDF agar halaman dihitung ulang mengikuti lebar panel yang baru.
      window.dispatchEvent(new Event('resize'));
    });
  });

  // ---------- init ----------
  // Dokumen ujian tidak bisa digulir pengguna (overflow:hidden), tetapi browser
  // masih bisa menggesernya saat memfokuskan input yang berada di luar layar.
  // Kembalikan ke atas agar lembar jawaban tidak pernah terdorong keluar layar.
  window.addEventListener('scroll', function () {
    if (window.scrollY > 0 || document.documentElement.scrollTop > 0) window.scrollTo(0, 0);
  }, { passive: true });

  loadLocal();
  renderAll();
  tick();
  setInterval(tick, 1000);
  setInterval(cekStatus, 30000);
})();
