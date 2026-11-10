<?php
/** @var string $lokal @var string $mode @var ?array $hasil @var bool $tersedia */
$labelMode = [
    'git' => 'Git — instalasi dari clone repository',
    'zip' => 'Paket ZIP — unduh dari GitHub, lalu salin ke instalasi',
    'manual' => 'Manual — update otomatis tidak tersedia di server ini',
];
$adaCek = is_array($hasil);
$remote = (string) ($hasil['remote'] ?? '');
$gagal = (string) ($hasil['gagal'] ?? '');
?>
<div class="page-head">
  <div>
    <h1>Update Aplikasi</h1>
    <p>
      Perbarui Examora langsung dari repository resmi. Konfigurasi, database,
      log, dan unggahan logo tidak ikut diubah.
    </p>
  </div>
  <div class="actions">
    <form method="post" action="<?= e(url('update_cek')) ?>" class="m-0">
      <?= Csrf::field() ?>
      <button class="btn btn-teal" type="submit">⟳ Cek Update</button>
    </form>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header">Status Pembaruan</div>
      <div class="table-wrap">
        <table class="table align-middle mb-0">
          <tbody>
            <tr>
              <th style="width:45%">Versi terpasang</th>
              <td><span class="badge badge-soft">v<?= e($lokal) ?></span></td>
            </tr>
            <tr>
              <th>Versi terbaru di repository</th>
              <td>
                <?php if ($adaCek && $remote !== ''): ?>
                  <span class="badge <?= version_compare($remote, $lokal, '>') ? 'text-bg-warning' : 'text-bg-success' ?>">
                    v<?= e($remote) ?>
                  </span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th>Cara update</th>
              <td><?= e($labelMode[$mode] ?? $mode) ?></td>
            </tr>
            <tr>
              <th>Dicek</th>
              <td>
                <?php if ($adaCek): ?>
                  <?= e($hasil['waktu'] ?? '') ?>
                <?php else: ?>
                  <span class="text-muted">belum pernah dicek</span>
                <?php endif; ?>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($gagal !== ''): ?>
      <div class="alert alert-danger">
        <strong>Cek update gagal.</strong> <?= e($gagal) ?>
      </div>
    <?php endif; ?>

    <?php if ($tersedia): ?>
      <div class="alert alert-success">
        <strong>Pembaruan tersedia.</strong>
        v<?= e($lokal) ?> dapat diperbarui ke <strong>v<?= e($remote) ?></strong>.
        Kode saat ini otomatis dicadangkan sebelum update berjalan.
      </div>
      <form method="post" action="<?= e(url('update_jalan')) ?>" class="m-0"
            data-confirm="Aplikasi akan diperbarui ke v<?= e($remote) ?>. Kode saat ini dicadangkan lebih dulu, lalu berkas diperbarui dari repository. Lanjutkan?"
            data-confirm-title="Jalankan update sekarang?"
            data-confirm-ok="Ya, perbarui sekarang">
        <?= Csrf::field() ?>
        <button class="btn btn-teal w-100" type="submit">⬆ Update ke v<?= e($remote) ?></button>
      </form>
    <?php elseif ($adaCek && $gagal === ''): ?>
      <div class="alert alert-info mb-0">
        <strong>Sudah versi terbaru.</strong>
        Tidak ada pembaruan untuk v<?= e($lokal) ?>. Tekan
        <em>Cek Update</em> kembali bila repository diperbarui.
      </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header">Aman dari perubahan</div>
      <div class="card-body" style="font-size:.86rem">
        <ul class="mb-0 ps-3 lh-lg">
          <li><code>config/config.php</code> — kredensial database dan <code>app_key</code></li>
          <li><code>install.lock</code> — penanda instalasi</li>
          <li><code>storage/</code> — log, cache, sesi, dan cadangan kode</li>
          <li><code>assets/uploads/</code> — logo sekolah</li>
          <li>Tabel <code>users</code>, <code>siswa</code>, <code>soal</code>, <code>pengaturan</code>, dst. tetap utuh</li>
        </ul>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Cadangan &amp; pemulihan</div>
      <div class="card-body" style="font-size:.86rem">
        <p class="mb-0">
          Sebelum update, seluruh kode disalin ke folder
          <code>storage/backup-kode-&lt;waktu&gt;</code> (maksimal 3 terbaru).
          Bila ada masalah, salin isi folder tersebut kembali ke root aplikasi.
        </p>
      </div>
    </div>

    <?php if ($mode === 'manual'): ?>
      <div class="alert alert-warning mb-0" style="font-size:.86rem">
        <strong>Update otomatis tidak tersedia.</strong> Server ini tidak punya
        perintah <code>git</code> dan tidak mendukung ekstraksi paket
        (ekstensi <code>zip</code> maupun <code>gzinflate</code>). Perbarui manual:
        unduh paket dari <a href="https://github.com/harumwibowo/Examora/releases" target="_blank" rel="noopener">GitHub</a>,
        lalu timpa isi folder aplikasi <strong>kecuali</strong>
        <code>config/config.php</code>, <code>install.lock</code>,
        <code>storage/</code>, dan <code>assets/uploads/</code>.
      </div>
    <?php endif; ?>
  </div>
</div>
