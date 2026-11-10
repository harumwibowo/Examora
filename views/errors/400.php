<?php $pesan = $pesan ?? 'Permintaan tidak dapat diproses.'; ?>
<div class="card">
  <div class="card-body text-center py-5">
    <div style="font-size:3rem">⚠️</div>
    <h1 class="h4 mt-2">Permintaan tidak valid</h1>
    <p class="text-muted mb-3"><?= e($pesan) ?></p>
    <a class="btn btn-teal" href="<?= e(url('dashboard')) ?>">Kembali</a>
  </div>
</div>
