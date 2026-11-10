<?php
/** @var array $r */
$tampil = ($r['tampil_nilai'] ?? 'Tidak') === 'Ya';
$selesai = $r['waktu_selesai'] !== null;
?>
<div class="page-head">
  <div>
    <h1>Hasil Ujian</h1>
    <p><?= e($r['nama_soal']) ?></p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="<?= e(url('siswa_home')) ?>">Daftar Ujian</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">Status Pengerjaan</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tbody>
            <tr><th style="width:180px">Status</th><td><?= status_badge($r['status']) ?></td></tr>
            <tr><th>Status koreksi</th><td><?= status_badge($r['status_koreksi']) ?></td></tr>
            <tr><th>Waktu mulai</th><td><?= e(fmt_tanggal($r['waktu_mulai'])) ?></td></tr>
            <tr><th>Batas waktu</th><td><?= e(fmt_tanggal($r['batas_waktu'])) ?></td></tr>
            <tr><th>Waktu selesai</th><td><?= e(fmt_tanggal($r['waktu_selesai'])) ?></td></tr>
            <tr><th>Pelanggaran</th><td><?= (int) $r['pelanggaran'] ?> kali</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($r['status'] === 'Menunggu Koreksi'): ?>
      <div class="alert alert-info mt-3 mb-0">
        Jawaban esai Anda sedang dikoreksi oleh guru. Nilai akhir akan tersedia setelah proses koreksi selesai.
      </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-5">
    <?php if ($tampil): ?>
      <div class="card">
        <div class="card-header">Nilai</div>
        <div class="card-body text-center">
          <div style="font-size:.78rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted)">Nilai Akhir</div>
          <div style="font-size:3.2rem;font-weight:800;line-height:1.1;color:var(--teal-dark)">
            <?= $r['nilai_akhir'] !== null ? e(fmt_nilai($r['nilai_akhir'])) : '—' ?>
          </div>
          <?php if ($r['nilai_akhir'] !== null && (float) $r['nilai_akhir'] >= (float) $sekolah['kkm']): ?>
            <span class="badge text-bg-success">Tuntas</span>
          <?php elseif ($r['nilai_akhir'] !== null): ?>
            <span class="badge text-bg-danger">Belum tuntas</span>
          <?php endif; ?>
          <hr>
          <div class="d-flex justify-content-between" style="font-size:.88rem">
            <span class="text-muted">Nilai objektif</span>
            <strong><?= $r['nilai_pg'] !== null ? e(fmt_nilai($r['nilai_pg'])) : '—' ?></strong>
          </div>
          <div class="d-flex justify-content-between mt-1" style="font-size:.88rem">
            <span class="text-muted">Nilai esai</span>
            <strong><?= $r['nilai_uraian'] !== null ? e(fmt_nilai($r['nilai_uraian'])) : '—' ?></strong>
          </div>
          <div class="d-flex justify-content-between mt-1" style="font-size:.8rem">
            <span class="text-muted">KKM</span>
            <strong><?= e(fmt_nilai($sekolah['kkm'], 0)) ?></strong>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="card">
        <div class="card-body text-center py-4">
          <div style="font-size:2.2rem">🔒</div>
          <h2 class="h6 mt-2">Nilai disembunyikan</h2>
          <p class="text-muted mb-0" style="font-size:.86rem">Guru tidak menampilkan nilai ujian ini kepada siswa.</p>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($selesai): ?>
      <div class="card mt-3">
        <div class="card-body" style="font-size:.84rem">
          <div class="d-flex justify-content-between"><span class="text-muted">Jenis soal</span><span>PDF Google Drive</span></div>
          <div class="d-flex justify-content-between mt-1"><span class="text-muted">Bobot objektif</span><span><?= e(fmt_nilai($r['bobot_pg'], 0)) ?>%</span></div>
          <div class="d-flex justify-content-between mt-1"><span class="text-muted">Bobot esai</span><span><?= e(fmt_nilai($r['bobot_uraian'], 0)) ?>%</span></div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
