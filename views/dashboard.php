<?php
$cards = [
    ['Total Kelas', $totalKelas, '▤', 'kelas'],
    ['Total Siswa', $totalSiswa, '☰', 'siswa'],
    ['Ujian Aktif', $ujianAktif, '✎', 'soal'],
    ['Sedang Berlangsung', $berlangsung, '⏱', 'nilai'],
];
if (empty($isSuper)) {
    // Guru tidak mengelola data kelas/siswa.
    $cards = array_slice($cards, 2);
}
?>
<div class="page-head">
  <div>
    <h1>Selamat datang, <?= e($authUser['nama'] ?? '') ?>!</h1>
    <p>Ringkasan penyelenggaraan ujian sekolah Anda.</p>
  </div>
  <div class="actions">
    <a class="btn btn-teal" href="<?= e(url('soal_form')) ?>">+ Ujian Baru</a>
    <?php if (!empty($isSuper)): ?>
      <a class="btn btn-ghost" href="<?= e(url('siswa')) ?>">Kelola Siswa</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($isSuper) && ($resetRequest ?? 0) > 0): ?>
  <div class="alert alert-warning d-flex align-items-center gap-2">
    <span>🔑</span>
    <div><strong><?= (int) $resetRequest ?> siswa</strong> meminta reset password.
      <a href="<?= e(url('siswa')) ?>" class="alert-link">Buka Data Siswa</a></div>
  </div>
<?php endif; ?>
<?php if (($menunggu ?? 0) > 0): ?>
  <div class="alert alert-info d-flex align-items-center gap-2">
    <span>✍</span>
    <div><strong><?= (int) $menunggu ?> hasil ujian</strong> menunggu koreksi esai.
      <a href="<?= e(url('koreksi_massal')) ?>" class="alert-link">Mulai koreksi</a></div>
  </div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <?php foreach ($cards as [$label, $value, $icon, $link]): ?>
    <div class="col-6 col-md-4 col-xl-2">
      <a class="stat-card card h-100 text-reset" href="<?= e(url($link)) ?>">
        <div class="card-body py-3">
          <div class="stat-icon"><?= $icon ?></div>
          <div class="stat-value"><?= (int) $value ?></div>
          <div class="stat-label"><?= e($label) ?></div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center">
        Ujian Terbaru
        <a class="ms-auto btn btn-sm btn-ghost" href="<?= e(url('soal')) ?>">Lihat semua</a>
      </div>
      <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Ujian</th><th>Jenis</th><th>Token</th><th>Status</th><th class="text-end">Peserta</th></tr></thead>
          <tbody>
          <?php if (!$recent): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada ujian. Mulai dengan membuat ujian baru.</td></tr>
          <?php endif; ?>
          <?php foreach ($recent as $s): ?>
            <tr>
              <td>
                <a class="fw-semibold" href="<?= e(url('kunci', ['id' => $s['id']])) ?>"><?= e($s['nama_soal']) ?></a>
                <div class="text-muted" style="font-size:.76rem"><?= e($s['mapel'] ?: 'Tanpa mata pelajaran') ?> · <?= e($s['guru_nama']) ?></div>
              </td>
              <td><span class="badge badge-soft"><?= e($s['jenis_ujian']) ?></span></td>
              <td><code><?= e($s['kode_soal']) ?></code></td>
              <td><?= status_badge($s['status']) ?></td>
              <td class="text-end"><?= (int) $s['peserta'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">Akses Cepat</div>
      <div class="card-body vstack gap-2">
        <a class="btn btn-ghost text-start" href="<?= e(url('nilai')) ?>">✓ &nbsp; Hasil Ujian</a>
        <a class="btn btn-ghost text-start" href="<?= e(url('nilai_kelas')) ?>">▥ &nbsp; Rekap Nilai per Kelas</a>
        <?php if ($isSuper): ?>
          <a class="btn btn-ghost text-start" href="<?= e(url('guru')) ?>">♟ &nbsp; Kelola Akun Guru</a>
          <a class="btn btn-ghost text-start" href="<?= e(url('pengaturan')) ?>">⚙ &nbsp; Pengaturan Sekolah</a>
          <a class="btn btn-ghost text-start" href="<?= e(url('update')) ?>">⟳ &nbsp; Update Aplikasi</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
