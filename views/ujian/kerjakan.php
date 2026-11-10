<?php
/** @var array $r @var array $payload @var int $total */
// Dokumen disajikan lewat aplikasi sendiri (same-origin) agar tidak
// bergantung pada cookie pihak ketiga Google Drive di browser siswa.
$pdfDrive = (string) ($r['link_pdf'] ?? '');
$pdfUrl = $pdfDrive !== '' ? url('pdf_soal', ['id' => (int) ($r['id_soal'] ?? 0)]) : '';
$mapel = (string) ($r['mapel'] ?? '');
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<div class="exam-top">
  <div class="title">
    <span class="nm"><?= e($r['nama_soal']) ?></span>
    <small><?= e($mapel) ?><?= $mapel !== '' ? ' · ' : '' ?>Token <?= e($r['kode_soal']) ?></small>
  </div>
  <div class="save-state" id="save-state">Siap</div>
  <div class="timer" id="timer">--:--</div>
</div>

<div class="exam-body-wrap">
  <div class="exam-left">
    <?php if ($pdfUrl !== ''): ?>
      <div class="pdf-view" id="pdf-view"
           data-src="<?= e($pdfUrl) ?>"
           data-worker="<?= e(asset('vendor/pdfjs/pdf.worker.min.js')) ?>">
        <div class="pdf-loading" id="pdf-loading">
          <span class="pdf-spin" aria-hidden="true"></span>
          <span>Memuat dokumen soal…</span>
        </div>
        <div class="pdf-pages" id="pdf-pages"></div>
        <div class="pdf-error" id="pdf-error" hidden></div>
      </div>
      <button type="button" class="mobile-ljk-back" data-exam-tab="ljk">
        <span aria-hidden="true">✎</span> Lembar Jawaban
      </button>
    <?php else: ?>
      <div class="exam-left-empty d-flex flex-column align-items-center justify-content-center"
           style="min-height:60vh;text-align:center;padding:2rem;gap:.4rem">
        <strong>Dokumen soal belum diatur</strong>
        <p class="text-muted mb-0" style="font-size:.86rem;max-width:34ch">
          Hubungi guru atau pengawas.
        </p>
        <button type="button" class="btn btn-sm btn-ghost mt-2" data-exam-tab="ljk">Lembar Jawaban</button>
      </div>
    <?php endif; ?>
  </div>

  <div class="exam-right">
    <div class="ljk-head d-flex align-items-center">
      <div>
        <div class="t">Lembar Jawaban</div>
        <div class="s" id="ljk-count">0/<?= (int) $total ?> terjawab</div>
      </div>
      <div class="ms-auto d-flex flex-wrap align-items-center justify-content-end gap-2">
        <button type="button" class="ljk-nav-toggle" id="ljk-nav-toggle"
                aria-expanded="true" aria-controls="ljk-nav"
                title="Sembunyikan daftar soal">
          <span class="chev" aria-hidden="true"></span>
          <span class="lbl">Daftar soal</span>
        </button>
        <div class="mobile-tabs">
          <button type="button" class="btn btn-sm btn-ghost" data-exam-tab="soal">Soal</button>
          <button type="button" class="btn btn-sm btn-ghost active" data-exam-tab="ljk">Jawaban</button>
        </div>
      </div>
    </div>
    <div class="ljk-nav" id="ljk-nav"></div>
    <div class="ljk-body" id="ljk-body"></div>
    <div class="ljk-foot">
      <div class="ljk-hint" id="ljk-hint">Semua jawaban wajib diisi sebelum ujian dikumpulkan.</div>
      <button type="button" class="btn-finish" id="btn-finish">Selesai &amp; Kumpulkan</button>
    </div>
  </div>
</div>

<div class="overlay" id="exam-overlay">
  <div class="box">
    <h2 id="overlay-title">Peringatan</h2>
    <p id="overlay-text"></p>
    <button class="btn btn-light px-4 mt-2" type="button" id="overlay-btn">Lanjutkan</button>
  </div>
</div>

<div class="overlay fs-overlay" id="fs-overlay">
  <div class="box">
    <h2>Mode Fullscreen</h2>
    <p id="fs-text">Ujian harus dikerjakan dalam mode fullscreen. Klik tombol di bawah untuk kembali.</p>
    <button class="btn btn-light px-4 mt-2" type="button" id="fs-btn">Kembali ke Fullscreen</button>
  </div>
</div>

<noscript>
  <div style="position:fixed;inset:0;background:#fff;z-index:999;padding:2rem;text-align:center">
    JavaScript wajib diaktifkan untuk mengerjakan ujian ini.
  </div>
</noscript>

<script type="application/json" id="exam-data"><?= json_encode($payload, $jsonFlags) ?></script>
<?php if ($pdfUrl !== ''): ?>
<script src="<?= e(asset('vendor/pdfjs/pdf.min.js')) ?>"></script>
<script src="<?= e(asset('js/pdf_viewer.js')) ?>"></script>
<?php endif; ?>
<script src="<?= e(asset('js/ujian.js')) ?>"></script>
