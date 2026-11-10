<?php $u = $authUser ?? null; $logo = $sekolah['logo'] ?? ''; ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
<title><?= e($title ?? 'Area Siswa') ?> — <?= e($sekolah['nama']) ?></title>
<?= favicon_tags() ?>
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/ui.css')) ?>">
</head>
<body>
<header class="topbar">
  <div class="sidebar-brand p-0 border-0" style="background:transparent">
    <div class="logo" style="background:var(--teal-soft);color:var(--teal-dark)"><?php if ($logo): ?><img src="<?= e(asset('uploads/' . $logo)) ?>" alt="Logo"><?php else: ?>EX<?php endif; ?></div>
    <div class="brand-text" style="color:var(--ink)"><?= e($sekolah['nama']) ?><small style="color:var(--muted)"><?= e($sekolah['tahun_ajaran']) ?></small></div>
  </div>
  <div class="userbox">
    <div class="avatar"><?= e(inisial_nama($u['nama'] ?? '?')) ?></div>
    <div class="user-meta d-none d-sm-block">
      <div class="n"><?= e($u['nama'] ?? '') ?></div>
      <div class="r"><?= e($u['username'] ?? '') ?></div>
    </div>
    <form method="post" action="<?= e(url('logout')) ?>" class="m-0">
      <?= Csrf::field() ?>
      <button class="btn btn-ghost btn-sm" type="submit">Keluar</button>
    </form>
  </div>
</header>
<main class="content" style="max-width:1120px;margin:0 auto;width:100%">
  <?php foreach (($flashes ?? []) as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" data-auto>
      <?= e($f['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
<script src="<?= e(asset('vendor/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/ui.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
