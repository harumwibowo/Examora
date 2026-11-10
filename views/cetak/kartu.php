<?php
/** @var array $rows */
$logo = $sekolah['logo'] ?? '';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kartu Peserta Ujian — <?= e($sekolah['nama']) ?></title>
<?= favicon_tags() ?>
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<style>
  body { background: #EDF2F7; }
  .toolbar { position: sticky; top: 0; z-index: 20; background: #fff; border-bottom: 1px solid var(--line); padding: .7rem 1rem; display: flex; gap: .5rem; align-items: center; }
  .kartu-grid { max-width: 210mm; margin: 0 auto; }
  @media print { body { background:#fff } .toolbar { display:none !important } .kartu-grid { max-width:none; margin:0 } }
</style>
</head>
<body>
<div class="toolbar no-print">
  <strong style="font-size:.95rem">Kartu Peserta Ujian</strong>
  <span class="badge text-bg-secondary"><?= count($rows) ?> kartu</span>
  <div class="ms-auto d-flex gap-2">
    <button class="btn btn-teal btn-sm" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    <button class="btn btn-ghost btn-sm" type="button" onclick="window.close()">Tutup</button>
  </div>
</div>

<div class="kartu-grid">
  <?php if (!$rows): ?>
    <div class="text-center text-muted py-5">Belum ada siswa untuk dicetak.</div>
  <?php endif; ?>
  <?php foreach ($rows as $s): ?>
    <div class="kartu">
      <div class="kop">
        <?php if ($logo): ?>
          <img class="lg" src="<?= e(asset('uploads/' . $logo)) ?>" alt="Logo">
        <?php endif; ?>
        <div>
          <div class="nm"><?= e($sekolah['nama']) ?></div>
          <div class="al">
            <?= e($sekolah['alamat'] ?: '') ?>
            <?php if ($sekolah['npsn']): ?><?= $sekolah['alamat'] ? ' · ' : '' ?>NPSN <?= e($sekolah['npsn']) ?><?php endif; ?>
          </div>
        </div>
      </div>

      <div class="judul">KARTU PESERTA UJIAN</div>

      <table>
        <tbody>
          <tr><td style="width:28%">Nama</td><td style="width:4%">:</td><td><strong><?= e($s['nama_siswa']) ?></strong></td></tr>
          <tr><td>Kelas</td><td>:</td><td><?= e($s['nama_kelas']) ?></td></tr>
          <tr><td>Username</td><td>:</td><td><span class="pass" style="font-size:10pt;letter-spacing:.04em"><?= e($s['username']) ?></span></td></tr>
          <tr><td>Password</td><td>:</td><td><span class="pass"><?= e($s['password_teks'] ?: '—') ?></span></td></tr>
          <?php if (!empty($s['agama'])): ?><tr><td>Agama</td><td>:</td><td><?= e($s['agama']) ?></td></tr><?php endif; ?>
        </tbody>
      </table>

      <div class="note">
        Simpan kartu ini dengan baik. Gunakan username dan password di atas untuk masuk melalui
        <strong>halaman ujian sekolah</strong> di browser. Jangan berikan kepada orang lain.
        <?php if ($sekolah['tahun_ajaran']): ?><br>Tahun ajaran <?= e($sekolah['tahun_ajaran']) ?>.<?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
</body>
</html>
