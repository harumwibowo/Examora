<?php
/** @var array $ujian @var int $totalSelesai */
$totalUjian = count($ujian);
$siap = 0;
foreach ($ujian as $x) {
    if (!empty($x['boleh'])) {
        $siap++;
    }
}
?>
<div class="siswa-hero">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="flex-grow-1">
      <h1>Halo, <?= e($authUser['nama'] ?? 'Siswa') ?>!</h1>
      <p>Pilih ujian yang tersedia, lalu masukkan token dari pengawas untuk mulai mengerjakan.</p>
    </div>
    <div class="text-end">
      <div style="font-size:1.6rem;font-weight:800;line-height:1"><?= (int) $siap ?></div>
      <div style="font-size:.76rem;opacity:.8;text-transform:uppercase;letter-spacing:.08em">Siap dikerjakan</div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-4">
    <div class="card h-100"><div class="card-body py-3">
      <div class="stat-value" style="font-size:1.5rem;font-weight:800;color:var(--teal-dark)"><?= $totalUjian ?></div>
      <div class="stat-label">Ujian tersedia</div>
    </div></div>
  </div>
  <div class="col-6 col-md-4">
    <div class="card h-100"><div class="card-body py-3">
      <div class="stat-value" style="font-size:1.5rem;font-weight:800;color:var(--teal-dark)"><?= (int) $totalSelesai ?></div>
      <div class="stat-label">Ujian selesai</div>
    </div></div>
  </div>
  <div class="col-12 col-md-4">
    <div class="card h-100"><div class="card-body py-3">
      <div class="stat-value" style="font-size:1.5rem;font-weight:800;color:var(--teal-dark)"><?= e($sekolah['tahun_ajaran'] ?: '—') ?></div>
      <div class="stat-label">Tahun ajaran</div>
    </div></div>
  </div>
</div>

<?php if ($ujian === []): ?>
  <div class="card">
    <div class="card-body kosong">
      <div class="ic">📭</div>
      <h2 class="h5 mt-2">Belum ada ujian untuk kelas Anda</h2>
      <p class="mb-0">Ujian akan muncul di sini setelah guru mengaktifkannya.</p>
    </div>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($ujian as $s): ?>
      <?php
      $aktif = $s['aktif'] ?? null;
      $terbaik = $s['terbaik'] ?? null;
      $sudah = (int) ($s['percobaan'] ?? 0) > 0;
      $boleh = !empty($s['boleh']);
      $tampilNilai = ($s['tampil_nilai'] ?? 'Tidak') === 'Ya';
      ?>
      <div class="col-md-6 col-xl-4">
        <div class="exam-tile">
          <div class="d-flex align-items-start gap-2">
            <div class="flex-grow-1">
              <h3><?= e($s['nama_soal']) ?></h3>
              <div class="meta">
                <span>📚 <?= e($s['mapel'] ?: 'Umum') ?></span>
                <span>⏱ <?= (int) $s['durasi'] ?> menit</span>
                <span>✎ <?= komposisi_total($s) ?> soal</span>
                <?php if (!empty($s['agama_target'])): ?>
                  <span>🎯 Khusus <?= e($s['agama_target']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <?php if ($aktif): ?>
              <?php if ((int) ($aktif['terkunci'] ?? 0) === 1): ?>
                <span class="badge text-bg-danger">Terkunci</span>
              <?php else: ?>
                <span class="badge text-bg-warning">Berlangsung</span>
              <?php endif; ?>
            <?php elseif ($terbaik && $terbaik['nilai_akhir'] !== null): ?>
              <span class="badge text-bg-success">Selesai</span>
            <?php elseif ($terbaik): ?>
              <span class="badge text-bg-info">Dikoreksi</span>
            <?php endif; ?>
          </div>

          <div class="text-muted mb-3" style="font-size:.82rem">
            Guru: <?= e($s['guru_nama']) ?><br>
            Kesempatan: <?= e($s['kesempatan']) ?>
            <?php if ($s['selesai_at']): ?> · Berakhir <?= e(fmt_tanggal($s['selesai_at'])) ?><?php endif; ?>
          </div>

          <?php if ($tampilNilai && $terbaik && $terbaik['nilai_akhir'] !== null): ?>
            <div class="mb-2" style="font-size:.84rem">
              Nilai terbaik: <strong style="color:var(--teal-dark)"><?= e(fmt_nilai($terbaik['nilai_akhir'])) ?></strong>
            </div>
          <?php endif; ?>

          <div class="foot">
            <?php if ($aktif): ?>
              <a class="btn btn-teal btn-sm flex-grow-1" href="<?= e(url('ujian_kerjakan', ['id' => $aktif['id']])) ?>">
                <?= (int) ($aktif['terkunci'] ?? 0) === 1 ? 'Menunggu Pengawas' : 'Lanjutkan Ujian' ?>
              </a>
            <?php elseif ($boleh): ?>
              <a class="btn btn-teal btn-sm flex-grow-1" href="<?= e(url('ujian', ['id' => $s['id']])) ?>">Kerjakan Ujian</a>
            <?php elseif ($terbaik): ?>
              <a class="btn btn-ghost btn-sm flex-grow-1" href="<?= e(url('ujian_hasil', ['id' => $terbaik['id']])) ?>">Lihat Hasil</a>
            <?php else: ?>
              <button class="btn btn-ghost btn-sm flex-grow-1" type="button" disabled>Sudah dikerjakan</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="alert alert-light border mt-4" style="font-size:.84rem">
  <strong>Catatan:</strong> jawaban tersimpan otomatis setiap kali Anda menjawab. Jangan menutup browser sebelum menekan tombol <em>Selesai</em> atau waktu habis.
</div>
