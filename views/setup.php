<?php /** @var array $errors @var bool $done @var array $requirements @var bool $siap */ ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalasi <?= e(APP_NAME) ?></title>
<?= favicon_tags() ?>
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/ui.css')) ?>">
</head>
<body style="background:linear-gradient(140deg,#E6FFFA,#F4F7F7)">
<div class="container py-5" style="max-width:760px">
  <div class="text-center mb-4">
    <div class="d-inline-grid mb-3" style="width:64px;height:64px;border-radius:16px;background:#319795;color:#fff;place-items:center;font-size:1.6rem;font-weight:800">EX</div>
    <h1 class="h3 mb-1">Instalasi <?= e(APP_NAME) ?></h1>
    <p class="text-muted mb-0">PHP <?= e(PHP_VERSION) ?> · tanpa framework · tanpa biaya lisensi</p>
  </div>

  <?php if ($done): ?>
    <div class="card">
      <div class="card-body p-4 text-center">
        <div style="font-size:2.6rem">✅</div>
        <h2 class="h4 mt-2">Instalasi berhasil</h2>
        <p class="text-muted">Database, akun superadmin, dan pengaturan dasar sudah dibuat.</p>
        <a class="btn btn-teal px-4" href="<?= e(url('login')) ?>">Masuk sekarang</a>
      </div>
    </div>
  <?php else: ?>
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span>Persyaratan Server</span>
        <span class="badge <?= $siap ? 'text-bg-success' : 'text-bg-danger' ?>">
          <?= $siap ? 'Siap dipasang' : 'Perlu diperbaiki' ?>
        </span>
      </div>
      <div class="card-body py-2">
        <ul class="list-unstyled mb-0" style="font-size:.84rem">
          <?php foreach ($requirements as $req): ?>
            <li class="d-flex gap-2 align-items-start py-1">
              <span aria-hidden="true"><?= $req['ok'] ? '✅' : ($req['wajib'] ? '❌' : '⚠️') ?></span>
              <span>
                <?= e($req['label']) ?>
                <?php if ($req['hint'] !== ''): ?>
                  <span class="text-muted">— <?= e($req['hint']) ?></span>
                <?php endif; ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if (!$siap): ?>
          <div class="form-text mt-2">
            Item bertanda ❌ wajib diperbaiki sebelum aplikasi dapat dipasang.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <strong>Periksa kembali:</strong>
        <ul class="mb-0 mt-1">
          <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" class="card">
      <div class="card-header">1. Koneksi Database</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Host</label>
            <input class="form-control" name="db_host" value="<?= e($_POST['db_host'] ?? '127.0.0.1') ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Port</label>
            <input class="form-control" type="number" name="db_port" value="<?= e($_POST['db_port'] ?? '3306') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Nama Database</label>
            <input class="form-control" name="db_name" value="<?= e($_POST['db_name'] ?? 'cbt_bebas') ?>" required>
            <div class="form-text">Dibuat otomatis bila belum ada. Jika akun database tidak berhak membuat database, buat dulu lewat panel hosting lalu isi nama yang sama.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">User Database</label>
            <input class="form-control" name="db_user" value="<?= e($_POST['db_user'] ?? 'root') ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label">Password Database</label>
            <input class="form-control" type="password" name="db_pass" value="<?= e($_POST['db_pass'] ?? '') ?>">
          </div>
        </div>
      </div>

      <div class="card-header border-top">2. Identitas Sekolah</div>
      <div class="card-body">
        <label class="form-label">Nama Sekolah / Lembaga</label>
        <input class="form-control" name="nama_sekolah" value="<?= e($_POST['nama_sekolah'] ?? 'SMA Negeri 1 Contoh') ?>" required>
      </div>

      <div class="card-header border-top">3. Akun Superadmin</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nama</label>
            <input class="form-control" name="admin_nama" value="<?= e($_POST['admin_nama'] ?? 'Administrator') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" name="admin_email" value="<?= e($_POST['admin_email'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Password</label>
            <input class="form-control" type="password" name="admin_pass" minlength="8" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Ulangi Password</label>
            <input class="form-control" type="password" name="admin_pass2" minlength="8" required>
          </div>
        </div>
      </div>

      <div class="card-body border-top d-flex align-items-center gap-3">
        <?= Csrf::field() ?>
        <button class="btn btn-teal px-4" type="submit" <?= $siap ? '' : 'disabled' ?>>Pasang Sekarang</button>
        <span class="form-text mb-0">Pastikan folder <code>config/</code> dapat ditulis.</span>
      </div>
    </form>
  <?php endif; ?>
</div>
<script src="<?= e(asset('vendor/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/ui.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
