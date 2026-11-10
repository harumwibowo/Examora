/**
 * Penampil PDF ujian (PDF.js).
 * iframe PDF bawaan tidak bisa di-scroll di Chrome Android, jadi halaman
 * dirender ke <canvas> di dalam wadah yang bisa digulir.
 */
(function () {
  'use strict';

  var host = document.getElementById('pdf-view');
  var pagesEl = document.getElementById('pdf-pages');
  var loading = document.getElementById('pdf-loading');
  var errorBox = document.getElementById('pdf-error');
  if (!host || !pagesEl) return;

  var src = host.getAttribute('data-src') || '';
  var worker = host.getAttribute('data-worker') || '';
  var lib = window.pdfjsLib;
  if (src === '') return;

  function fallback(judul, isi) {
    if (loading) loading.hidden = true;
    if (errorBox) {
      errorBox.hidden = false;
      errorBox.innerHTML = '<strong></strong><span></span>';
      errorBox.querySelector('strong').textContent = judul;
      errorBox.querySelector('span').textContent = isi;
      var a = document.createElement('a');
      a.className = 'btn btn-sm btn-light mt-2';
      a.href = src;
      a.target = '_blank';
      a.rel = 'noopener';
      a.textContent = 'Buka dokumen di tab baru ↗';
      errorBox.appendChild(a);
      return;
    }
    var f = document.createElement('iframe');
    f.src = src;
    f.title = 'Teks soal ujian (PDF)';
    f.className = 'pdf-fallback-frame';
    host.appendChild(f);
  }

  if (!lib) { fallback('Penampil PDF tidak tersedia', 'Muat ulang halaman, atau buka dokumen di tab baru.'); return; }
  lib.GlobalWorkerOptions.workerSrc = worker;

  var dpr = Math.min(2, window.devicePixelRatio || 1);
  var doc = null;
  var daftar = [];            // {el, nomor, rendered, sibuk, rasio}
  var skala = 1;
  var pengamat = window.IntersectionObserver
    ? new IntersectionObserver(onLihat, { root: host, rootMargin: '900px 0px' })
    : null;

  function lebarPakai() {
    var w = pagesEl.clientWidth || host.clientWidth || 320;
    return Math.max(240, w - 8);
  }

  function onLihat(entries) {
    entries.forEach(function (e) {
      if (e.isIntersecting) {
        var i = parseInt(e.target.getAttribute('data-page'), 10) - 1;
        gambar(daftar[i]);
      }
    });
  }

  function gambar(p) {
    if (!p || p.sibuk || !doc) return;
    p.sibuk = true;
    doc.getPage(p.nomor).then(function (page) {
      var asli = page.getViewport({ scale: 1 });
      var skalaBaru = lebarPakai() / asli.width;
      var vp = page.getViewport({ scale: skalaBaru * dpr });
      var c = document.createElement('canvas');
      c.width = Math.max(1, Math.floor(vp.width));
      c.height = Math.max(1, Math.floor(vp.height));
      c.style.width = Math.floor(vp.width / dpr) + 'px';
      c.style.height = Math.floor(vp.height / dpr) + 'px';
      p.el.innerHTML = '';
      p.el.style.height = 'auto';
      p.el.appendChild(c);
      p.rasio = asli.height / asli.width;
      p.rendered = true;
      p.sibuk = false;
      return page.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise;
    }).catch(function () {
      p.sibuk = false;
    });
  }

  function aturTinggi() {
    daftar.forEach(function (p) {
      if (!p.rendered) {
        p.el.style.height = Math.round(lebarPakai() * (p.rasio || 1.414)) + 'px';
      }
    });
  }

  function siapkan() {
    var halamanPertama = doc.getPage(1);
    return halamanPertama.then(function (page) {
      var asli = page.getViewport({ scale: 1 });
      skala = lebarPakai() / asli.width;
      var rasio = asli.height / asli.width;
      for (var i = 1; i <= doc.numPages; i++) {
        var el = document.createElement('div');
        el.className = 'pdf-page';
        el.setAttribute('data-page', i);
        el.style.height = Math.round(lebarPakai() * rasio) + 'px';
        pagesEl.appendChild(el);
        var item = { el: el, nomor: i, rendered: false, sibuk: false, rasio: rasio };
        daftar.push(item);
        if (pengamat) pengamat.observe(el);
      }
      if (!pengamat) daftar.forEach(gambar);
      else if (daftar.length) gambar(daftar[0]);
      if (loading) loading.hidden = true;
    });
  }

  function muat() {
    if (loading) loading.hidden = false;
    fetch(src, { credentials: 'same-origin' })
      .then(function (r) {
        var ct = (r.headers.get('content-type') || '').toLowerCase();
        if (!r.ok || ct.indexOf('pdf') < 0) throw new Error('http-' + r.status);
        return r.arrayBuffer();
      })
      .then(function (buf) { return lib.getDocument({ data: buf }).promise; })
      .then(function (d) { doc = d; return siapkan(); })
      .catch(function (e) {
        var pesan = String(e && e.message || e);
        var judul = 'Dokumen soal belum bisa dibuka';
        var isi = 'Periksa koneksi Anda, lalu muat ulang halaman ini.';
        if (pesan === 'http-401' || pesan === 'http-403') {
          judul = 'Google Drive menolak akses';
          isi = 'Pastikan berkas dibagikan "Siapa saja yang memiliki link" (Viewer).';
        } else if (pesan === 'http-404') {
          judul = 'Berkas tidak ditemukan';
          isi = 'Periksa kembali ID file Google Drive pada pengaturan ujian.';
        } else if (pesan === 'http-502') {
          judul = 'Google Drive tidak dapat dihubungi';
          isi = 'Server gagal mengunduh berkas. Coba lagi beberapa saat lagi.';
        }
        fallback(judul, isi);
      });
  }

  var jeda = null;
  window.addEventListener('resize', function () {
    clearTimeout(jeda);
    jeda = setTimeout(function () {
      if (!doc) return;
      aturTinggi();
      daftar.forEach(function (p) { if (p.rendered) p.rendered = false; });
      var tampak = daftar.filter(function (p) {
        var r = p.el.getBoundingClientRect();
        return r.bottom > 0 && r.top < window.innerHeight;
      });
      (tampak.length ? tampak : daftar.slice(0, 1)).forEach(gambar);
    }, 250);
  });

  muat();
})();
