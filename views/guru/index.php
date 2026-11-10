<?php /** @var array $rows */ ?>
<div class="page-head">
  <div>
    <h1>Kelola Guru</h1>
    <p>Akun guru dapat membuat ujian dan menilai hasil ujian miliknya sendiri.</p>
  </div>
  <div class="actions">
    <form class="d-flex gap-2" method="get" action="<?= e(url('guru')) ?>">
      <input type="hidden" name="r" value="guru">
      <input class="form-control form-control-sm" name="q" value="<?= e($q ?? '') ?>" placeholder="Cari nama / email">
      <button class="btn btn-ghost btn-sm">Cari</button>
    </form>
    <button class="btn btn-ghost" data-bs-toggle="modal" data-bs-target="#modalImporGuru">Import CSV</button>
    <a class="btn btn-teal" href="<?= e(url('guru_form')) ?>">+ Tambah Guru</a>
  </div>
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
      <?php if (!empty($imporReport['akun'])): ?>
        <div class="table-wrap" style="border:1px solid var(--line);border-radius:10px;max-height:320px;overflow:auto">
          <table class="table table-sm mb-0">
            <thead><tr><th>Nama</th><th>Email</th><th>Password</th></tr></thead>
            <tbody>
              <?php foreach ($imporReport['akun'] as $a): ?>
                <tr><td><?= e($a['nama']) ?></td><td><?= e($a['email']) ?></td><td><code><?= e($a['password']) ?></code></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="form-text mt-2">Catat password sekarang — password tidak dapat ditampilkan lagi setelah halaman ini ditutup.</div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th style="width:56px">No</th><th>Nama</th><th>Email</th><th>Status</th><th class="text-center">Ujian</th><th>Login terakhir</th><th class="text-end">Aksi</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada akun guru.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $i => $g): ?>
        <tr>
          <td class="text-muted"><?= $i + 1 ?></td>
          <td class="fw-semibold"><?= e($g['nama']) ?></td>
          <td><?= e($g['email']) ?></td>
          <td><?= (int) $g['aktif'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
          <td class="text-center"><?= (int) $g['jumlah_soal'] ?></td>
          <td class="text-muted" style="font-size:.82rem"><?= e(fmt_tanggal($g['last_login_at'])) ?></td>
          <td class="text-end">
            <div class="d-inline-flex gap-1">
              <a class="btn btn-sm btn-ghost" href="<?= e(url('guru_form', ['id' => $g['id']])) ?>">Ubah</a>
              <form method="post" action="<?= e(url('guru_toggle')) ?>" class="m-0">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                <button class="btn btn-sm btn-ghost" type="submit"><?= (int) $g['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
              </form>
              <form method="post" action="<?= e(url('guru_hapus')) ?>" class="m-0" data-confirm="Hapus akun guru ini?">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal impor guru -->
<div class="modal fade" id="modalImporGuru" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= e(url('guru_impor')) ?>" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <div class="modal-header"><h5 class="modal-title">Import Data Guru</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p class="text-muted" style="font-size:.86rem">Gunakan file CSV. Baris judul otomatis diabaikan. Password yang dikosongkan akan dibuat acak.</p>
        <div class="d-flex gap-2 mb-3">
          <a class="btn btn-sm btn-ghost" href="<?= e(url('guru_template')) ?>">Template: Nama;Email;Password;Status</a>
        </div>
        <label class="form-label">File CSV</label>
        <input class="form-control" type="file" name="csv" accept=".csv,text/csv" required>
        <div class="form-text">Maksimal 5 MB. Kolom Status boleh dikosongkan (default Aktif).</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Batal</button>
        <button class="btn btn-teal" type="submit">Proses Import</button>
      </div>
    </form>
  </div>
</div>
