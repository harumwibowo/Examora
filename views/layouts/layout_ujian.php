<?php $logo = $sekolah['logo'] ?? ''; ?>
<!doctype html>
<html lang="id" class="exam-page">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, interactive-widget=resizes-content">
<title><?= e($title ?? 'Ujian') ?> — <?= e($sekolah['nama']) ?></title>
<?= favicon_tags() ?>
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/ui.css')) ?>">
<script src="<?= e(asset('js/ui.js')) ?>"></script>
</head>
<body class="exam-body">
<?= $content ?>
<script src="<?= e(asset('vendor/bootstrap.bundle.min.js')) ?>"></script>
</body>
</html>
