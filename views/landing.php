<?php $logo = $sekolah['logo'] ?? ''; ?>
<div class="auth-head">
  <div class="logo"><?php if ($logo): ?><img src="<?= e(asset('uploads/' . $logo)) ?>" alt="Logo"><?php else: ?>EX<?php endif; ?></div>
  <h1><?= e($sekolah['nama']) ?></h1>
  <p>Sistem Ujian Berbasis Komputer</p>
</div>
<div class="auth-choice">
  <a class="choice-card" href="<?= e(url('login')) ?>">
    <div class="ic">♟</div>
    <div><strong>Masuk sebagai Guru / Admin</strong><span>Kelola kelas, siswa, ujian, dan nilai</span></div>
  </a>
  <a class="choice-card" href="<?= e(url('masuk_siswa')) ?>">
    <div class="ic">☰</div>
    <div><strong>Masuk sebagai Siswa</strong><span>Kerjakan ujian langsung dari browser</span></div>
  </a>
</div>
<div class="auth-foot">
  <?= e($sekolah['tahun_ajaran']) ?> · <?= e(APP_NAME) ?> v<?= e(DB_UJIAN_VERSION) ?>
</div>
