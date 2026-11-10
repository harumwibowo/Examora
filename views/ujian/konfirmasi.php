<?php
/** @var array $soal @var array $riwayat @var array|null $aktif */
$total = komposisi_total($soal);
$menit = max(1, (int) $soal['durasi']);
$minPengerjaan = max(0, min($menit, (int) ($soal['min_pengerjaan'] ?? 0)));
?>
<div class="page-head">
  <div>
    <h1>Konfirmasi Ujian</h1>
    <p>Baca informasi berikut dengan saksama sebelum memulai.</p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="<?= e(url('siswa_home')) ?>">Kembali</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header"><?= e($soal['nama_soal']) ?></div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tbody>
            <tr><th style="width:190px">Mata pelajaran</th><td><?= e($soal['mapel'] ?: 'Umum') ?></td></tr>
            <tr><th>Target peserta</th><td><?= e(agama_label($soal['agama_target'] ?? '')) ?><?php if (!empty($soal['agama_target'])): ?> <span class="text-muted">· hanya siswa beragama ini</span><?php endif; ?></td></tr>
            <tr><th>Guru</th><td><?= e($soal['guru_nama']) ?></td></tr>
            <tr><th>Jenis ujian</th><td><?= e($soal['jenis_ujian']) ?> · soal PDF Google Drive</td></tr>
            <tr><th>Jumlah soal</th><td><?= e(komposisi_label($soal)) ?> (total <?= $total ?>)</td></tr>
            <tr><th>Durasi</th><td><?= $menit ?> menit</td></tr>
            <?php if ($minPengerjaan > 0): ?>
              <tr><th>Min. pengerjaan</th><td><?= $minPengerjaan ?> menit <span class="text-muted">· tombol kumpulkan terkunci sampai batas ini</span></td></tr>
            <?php endif; ?>
            <tr><th>Kesempatan</th><td><?= e($soal['kesempatan']) ?></td></tr>
            <?php if ($soal['mulai_at']): ?><tr><th>Mulai</th><td><?= e(fmt_tanggal($soal['mulai_at'])) ?></td></tr><?php endif; ?>
            <?php if ($soal['selesai_at']): ?><tr><th>Berakhir</th><td><?= e(fmt_tanggal($soal['selesai_at'])) ?></td></tr><?php endif; ?>
            <tr><th>Tampilkan nilai</th><td><?= e($soal['tampil_nilai']) ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header">Aturan Pengerjaan</div>
      <div class="card-body">
        <ol class="mb-0 ps-3" style="font-size:.9rem;line-height:1.8">
          <li>Masukkan token ujian yang diberikan pengawas. Token bersifat rahasia.</li>
          <li>Jawaban tersimpan otomatis. Jika koneksi terputus, kembali ke halaman ini dan lanjutkan.</li>
          <li>Waktu berjalan sejak tombol <strong>Mulai</strong> ditekan dan tidak dapat dijeda.</li>
          <?php if ($minPengerjaan > 0): ?>
            <li>Tombol <strong>Selesai &amp; Kumpulkan</strong> baru aktif setelah <?= $minPengerjaan ?> menit pengerjaan.</li>
          <?php endif; ?>
          <li>Jangan berpindah tab atau aplikasi. Pelanggaran akan dicatat dan dapat mengakhiri ujian.</li>
          <li>Ujian otomatis masuk mode <strong>fullscreen</strong>. Keluar dari fullscreen juga dihitung sebagai pelanggaran.</li>
          <li>Setelah menekan <strong>Selesai</strong>, jawaban tidak dapat diubah lagi.</li>
        </ol>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <?php if ($aktif): ?>
      <div class="card">
        <div class="card-header">Ujian Sedang Berlangsung</div>
        <div class="card-body text-center">
          <p class="mb-1">Anda memiliki sesi ujian yang belum diselesaikan.</p>
          <p class="text-muted" style="font-size:.84rem">Batas waktu: <?= e(fmt_tanggal($aktif['batas_waktu'])) ?></p>
          <a class="btn btn-teal w-100" href="<?= e(url('ujian_kerjakan', ['id' => $aktif['id']])) ?>">Lanjutkan Ujian</a>
        </div>
      </div>
    <?php elseif ($riwayat !== []): ?>
      <div class="card">
        <div class="card-header">Riwayat Pengerjaan</div>
        <div class="card-body">
          <?php foreach (array_slice($riwayat, 0, 5) as $ri): ?>
            <div class="d-flex align-items-center gap-2 py-2 border-bottom">
              <div class="flex-grow-1">
                <div class="fw-semibold" style="font-size:.88rem">Percobaan <?= (int) $ri['percobaan_ke'] ?></div>
                <div class="text-muted" style="font-size:.76rem"><?= e(fmt_tanggal($ri['waktu_selesai'] ?: $ri['waktu_mulai'])) ?></div>
              </div>
              <div class="text-end">
                <?= status_badge($ri['status']) ?>
                <?php if ($ri['nilai_akhir'] !== null): ?>
                  <div class="fw-bold" style="color:var(--teal-dark)"><?= e(fmt_nilai($ri['nilai_akhir'])) ?></div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <p class="text-muted mb-0 mt-2" style="font-size:.8rem">Ujian ini tidak dapat dikerjakan ulang.</p>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!$aktif && ($riwayat === [] || $soal['kesempatan'] === 'Dapat Diulang')): ?>
      <div class="card">
        <div class="card-header">Mulai Ujian</div>
        <div class="card-body">
          <form method="post" action="<?= e(url('ujian_mulai')) ?>" class="vstack gap-3">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int) $soal['id'] ?>">
            <div>
              <label class="form-label" for="token">Token Ujian</label>
              <input class="form-control text-center fw-bold" id="token" name="token" maxlength="6" autocomplete="off"
                     data-upper placeholder="XXXXXX" style="letter-spacing:.35em;font-size:1.15rem" required>
              <div class="form-text">Minta token kepada guru atau pengawas.</div>
            </div>
            <button class="btn btn-teal py-2" type="submit">Mulai Sekarang</button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
