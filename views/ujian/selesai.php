<?php /** @var array $r */ ?>
<div class="card">
  <div class="card-body text-center py-5">
    <div style="font-size:3rem">✅</div>
    <h1 class="h4 mt-3">Ujian telah dikumpulkan</h1>
    <p class="text-muted mb-1">Terima kasih, jawaban Anda sudah tersimpan.</p>
    <p class="text-muted" style="font-size:.86rem">Waktu selesai: <?= e(fmt_tanggal($r['waktu_selesai'])) ?></p>
    <div class="d-flex justify-content-center gap-2 mt-3">
      <a class="btn btn-teal" href="<?= e(url('ujian_hasil', ['id' => $r['id']])) ?>">Lihat Status</a>
      <a class="btn btn-ghost" href="<?= e(url('siswa_home')) ?>">Kembali ke Daftar Ujian</a>
    </div>
  </div>
</div>
