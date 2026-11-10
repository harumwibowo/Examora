<?php
$u = $authUser ?? null;
$role = $u['role'] ?? '';
$r = $_GET['r'] ?? '';
$nav = static function (string $key, string $label, string $icon) use ($r): string {
    $active = $r === $key ? ' active' : '';
    return '<a class="menu-link' . $active . '" href="' . e(url($key)) . '"><span class="mi">' . $icon . '</span><span class="menu-text">' . e($label) . '</span></a>';
};
$logo = $sekolah['logo'] ?? '';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
<title><?= e($title ?? APP_NAME) ?> — <?= e($sekolah['nama']) ?></title>
<?= favicon_tags() ?>
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/ui.css')) ?>">
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="logo"><?php if ($logo): ?><img src="<?= e(asset('uploads/' . $logo)) ?>" alt="Logo"><?php else: ?>EX<?php endif; ?></div>
      <div class="brand-text"><?= e($sekolah['nama']) ?><small><?= e(APP_NAME) ?> v<?= e(DB_UJIAN_VERSION) ?></small></div>
    </div>
    <nav class="sidebar-menu">
      <?= $nav('dashboard', 'Dashboard', '▦') ?>
      <div class="menu-label">Data Induk</div>
      <?php if ($role === 'superadmin'): ?>
        <?= $nav('guru', 'Kelola Guru', '♟') ?>
      <?php endif; ?>
      <?php if ($role === 'superadmin'): ?>
        <?= $nav('kelas', 'Data Kelas', '▤') ?>
        <?= $nav('siswa', 'Data Siswa', '☰') ?>
      <?php endif; ?>
      <?= $nav('login_siswa', 'Reset Login Siswa', '⟲') ?>
      <div class="menu-label">Ujian</div>
      <?= $nav('soal', 'Data Soal', '✎') ?>
      <?= $nav('nilai', 'Hasil Ujian', '✓') ?>
      <?= $nav('nilai_kelas', 'Hasil Kelas', '▥') ?>
      <?= $nav('koreksi_massal', 'Koreksi Esai', '✐') ?>
      <?= $nav('analisis', 'Analisis Butir', '◔') ?>
      <div class="menu-label">Sistem</div>
      <?php if ($role === 'superadmin'): ?>
        <?= $nav('pengaturan', 'Pengaturan', '⚙') ?>
        <?= $nav('audit', 'Audit Log', '◎') ?>
        <?= $nav('database', 'Database', '▧') ?>
        <?= $nav('update', 'Update', '⟳') ?>
      <?php endif; ?>
      <?= $nav('profil', 'Akun Saya', '◉') ?>
      <a class="menu-link" href="<?= e(url('home')) ?>"><span class="mi">◈</span><span class="menu-text">Halaman Depan</span></a>
    </nav>
  </aside>
  <div class="sidebar-backdrop"></div>

  <div class="main">
    <header class="topbar">
      <button class="toggle" type="button" data-sidebar-toggle aria-label="Buka menu">☰</button>
      <h2 class="page-title"><?= e($title ?? 'Dashboard') ?></h2>
      <div class="userbox">
        <div class="avatar"><?= e(inisial_nama($u['nama'] ?? '?')) ?></div>
        <div class="user-meta d-none d-sm-block">
          <div class="n"><?= e($u['nama'] ?? '') ?></div>
          <div class="r"><?= e($role) ?></div>
        </div>
        <form method="post" action="<?= e(url('logout')) ?>" class="m-0">
          <?= Csrf::field() ?>
          <button class="btn btn-ghost btn-sm" type="submit">Keluar</button>
        </form>
      </div>
    </header>

    <main class="content">
      <?php foreach (($flashes ?? []) as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" data-auto>
          <?= e($f['message']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endforeach; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<script src="<?= e(asset('vendor/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/ui.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
