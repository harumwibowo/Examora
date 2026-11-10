<?php $pesan = $pesan ?? 'Anda tidak memiliki akses ke halaman ini.'; ?>
<div class="card">
  <div class="card-body text-center py-5">
    <div style="font-size:3rem">🔒</div>
    <h1 class="h4 mt-2">Akses ditolak</h1>
    <p class="text-muted mb-3"><?= e($pesan) ?></p>
    <a class="btn btn-teal" href="<?= e(url('dashboard')) ?>">Kembali ke dashboard</a>
  </div>
</div>
