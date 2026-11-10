<?php $logo = $sekolah['logo'] ?? ''; ?>
<div class="auth-head">
  <div class="logo"><?php if ($logo): ?><img src="<?= e(asset('uploads/' . $logo)) ?>" alt="Logo"><?php else: ?>EX<?php endif; ?></div>
  <h1>Masuk Guru / Admin</h1>
  <p>Gunakan email dan password akun sekolah Anda</p>
</div>
<div class="auth-body-inner">
  <form method="post" action="<?= e(url('login_proses')) ?>" class="vstack gap-3">
    <?= Csrf::field() ?>
    <div>
      <label class="form-label" for="email">Email</label>
      <input class="form-control" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autofocus autocomplete="username">
    </div>
    <div>
      <label class="form-label" for="password">Password</label>
      <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <button class="btn btn-teal w-100 py-2" type="submit">Masuk</button>
  </form>
</div>
<div class="auth-foot">
  <a href="<?= e(url('masuk_siswa')) ?>">Masuk sebagai siswa</a>
  <span class="mx-2">·</span>
  <a href="<?= e(url('home')) ?>">Halaman depan</a>
</div>
