<?php /** @var array $rows */ $isSuper = Auth::isSuperadmin(); ?>
<div class="page-head">
  <div>
    <h1>Data Kelas</h1>
    <p>Kelas menjadi dasar pembagian peserta ujian.</p>
  </div>
  <?php if ($isSuper): ?>
    <div class="actions">
      <button class="btn btn-ghost" data-bs-toggle="modal" data-bs-target="#modalImporKelas">Import CSV</button>
    </div>
  <?php endif; ?>
</div>

<?php if (!empty($imporReport)): ?>
  <div class="card mb-3">
    <div class="card-header d-flex align-items-center">
      <?= e($imporReport['judul'] ?? 'Hasil Impor') ?>
      <span class="badge text-bg-success ms-2"><?= (int) $imporReport['ok'] ?> berhasil</span>
      <?php if (!empty($imporReport['gagal'])): ?><span class="badge text-bg-danger ms-1"><?= count($imporReport['gagal']) ?> gagal</span><?php endif; ?>
    </div>
    <div class="card-body">
      <?php if (!empty($imporReport['gagal'])): ?>
        <div class="alert alert-warning mb-3">
          <strong>Baris yang dilewati:</strong>
          <ul class="mb-0 mt-1" style="max-height:180px;overflow:auto">
            <?php foreach ($imporReport['gagal'] as $g): ?><li style="font-size:.84rem"><?= e($g) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <?php if (!empty($imporReport['baris'])): ?>
        <div class="table-wrap" style="border:1px solid var(--line);border-radius:10px;max-height:320px;overflow:auto">
          <table class="table table-sm mb-0">
            <thead><tr><th>Nama Kelas</th><th>Tingkat</th></tr></thead>
            <tbody>
              <?php foreach ($imporReport['baris'] as $b): ?>
                <tr><td><?= e($b['nama']) ?></td><td><?= e($b['tingkat']) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($isSuper): ?>
<div class="card mb-3">
  <div class="card-body">
    <form method="post" action="<?= e(url('kelas_simpan')) ?>" class="row g-2 align-items-end">
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="0">
      <div class="col-md-6">
        <label class="form-label">Nama Kelas</label>
        <input class="form-control" name="nama_kelas" placeholder="Contoh: X IPA 1" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Tingkat</label>
        <select class="form-select" name="tingkat">
          <option value="-">-</option><?php foreach (tingkat_options() as $t): ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <button class="btn btn-teal w-100" type="submit">Tambah Kelas</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th style="width:56px">No</th><th>Nama Kelas</th><th>Tingkat</th><th class="text-center">Siswa</th><?php if ($isSuper): ?><th class="text-end">Aksi</th><?php endif; ?></tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada kelas.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $i => $k): ?>
        <tr>
          <td class="text-muted"><?= $i + 1 ?></td>
          <td class="fw-semibold"><?= e($k['nama_kelas']) ?></td>
          <td><?= e($k['tingkat']) ?></td>
          <td class="text-center"><a href="<?= e(url('siswa', ['id_kelas' => $k['id']])) ?>"><?= (int) $k['jumlah_siswa'] ?> siswa</a></td>
          <?php if ($isSuper): ?>
          <td class="text-end">
            <div class="d-inline-flex gap-1">
              <form method="post" action="<?= e(url('kelas_simpan')) ?>" class="d-flex gap-1 m-0">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                <input class="form-control form-control-sm" name="nama_kelas" value="<?= e($k['nama_kelas']) ?>" style="width:150px">
                <?php
                // Nilai tersimpan yang tak lagi ada di daftar jenjang aktif tetap ditawarkan
                // agar tidak hilang diam-diam saat baris disimpan ulang.
                $opsiTingkat = array_merge(['-'], tingkat_options());
                if (!in_array($k['tingkat'], $opsiTingkat, true)) {
                    $opsiTingkat[] = $k['tingkat'];
                }
                ?>
                <select class="form-select form-select-sm" name="tingkat" style="width:76px">
                  <?php foreach ($opsiTingkat as $t): ?><option value="<?= e($t) ?>" <?= $k['tingkat'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-ghost" type="submit">Simpan</button>
              </form>
              <form method="post" action="<?= e(url('kelas_hapus')) ?>" class="m-0" data-confirm="Hapus kelas <?= e($k['nama_kelas']) ?>?">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
              </form>
            </div>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($isSuper): ?>
<!-- Modal impor kelas -->
<div class="modal fade" id="modalImporKelas" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= e(url('kelas_impor')) ?>" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <div class="modal-header"><h5 class="modal-title">Import Data Kelas</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p class="text-muted" style="font-size:.86rem">Gunakan file CSV. Baris judul otomatis diabaikan. Kelas yang sudah ada akan dilewati.</p>
        <div class="d-flex gap-2 mb-3">
          <a class="btn btn-sm btn-ghost" href="<?= e(url('kelas_template')) ?>">Template: Nama Kelas;Tingkat</a>
        </div>
        <label class="form-label">File CSV</label>
        <input class="form-control" type="file" name="csv" accept=".csv,text/csv" required>
        <div class="form-text">Maksimal 5 MB. Tingkat boleh diisi angka (10, 11, 12).</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Batal</button>
        <button class="btn btn-teal" type="submit">Proses Import</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
