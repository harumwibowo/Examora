<?php $u = $authUser ?? null; ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Masuk') ?> — <?= e($sekolah['nama']) ?></title>
<?= favicon_tags() ?>
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/ui.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-card">
  <?php foreach (($flashes ?? []) as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?> mb-0 rounded-0 text-center py-2" data-auto><?= e($f['message']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</div>
<script src="<?= e(asset('vendor/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/ui.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
