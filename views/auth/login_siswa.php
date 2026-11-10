<?php $logo = $sekolah['logo'] ?? ''; ?>
<div class="auth-head">
  <div class="logo"><?php if ($logo): ?><img src="<?= e(asset('uploads/' . $logo)) ?>" alt="Logo"><?php else: ?>EX<?php endif; ?></div>
  <h1>Masuk Siswa</h1>
  <p>Gunakan username dan password dari kartu ujian</p>
</div>
<div class="auth-body-inner">
  <?php if (!empty($browserDitolak)): ?>
    <div class="alert alert-warning" style="font-size:.84rem">
      Browser yang Anda pakai tidak diizinkan untuk login siswa.
      Gunakan salah satu browser berikut:
      <strong>
        <?= e(implode(', ', array_map('browser_label', (array) ($browserDiizinkan ?? [])))) ?>
      </strong>.
    </div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('masuk_siswa_proses')) ?>" class="vstack gap-3">
    <?= Csrf::field() ?>
    <div>
      <label class="form-label" for="username">Username</label>
      <input class="form-control" type="text" id="username" name="username" value="<?= e(old('username')) ?>" required autofocus autocomplete="username">
    </div>
    <div>
      <label class="form-label" for="password">Password</label>
      <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <button class="btn btn-teal w-100 py-2" type="submit">Masuk &amp; Lihat Ujian</button>
  </form>
  <div class="form-text text-center mt-3">
    Tidak bisa masuk? Hubungi guru atau admin untuk reset password.
  </div>
</div>
<div class="auth-foot">
  <a href="<?= e(url('login')) ?>">Masuk sebagai guru/admin</a>
  <span class="mx-2">·</span>
  <a href="<?= e(url('home')) ?>">Halaman depan</a>
</div>
