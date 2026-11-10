<?php /** @var array $rows */ $isSuper = Auth::isSuperadmin(); ?>
<div class="page-head">
  <div>
    <h1>Data Siswa</h1>
    <p>Kelola akun peserta, impor massal, dan cetak kartu ujian.</p>
  </div>
  <div class="actions">
    <a class="btn btn-teal" href="<?= e(url('siswa_form')) ?>">+ Tambah Siswa</a>
    <button class="btn btn-ghost" data-bs-toggle="modal" data-bs-target="#modalImpor">Import CSV</button>
    <button class="btn btn-ghost" data-bs-toggle="modal" data-bs-target="#modalCetak">Cetak Kartu</button>
    <a class="btn btn-ghost" href="<?= e(url('siswa_ekspor')) ?>">Export Excel</a>
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
            <thead><tr><th>Nama</th><th>Username</th><th>Password</th><th>Kelas</th></tr></thead>
            <tbody>
              <?php foreach ($imporReport['akun'] as $a): ?>
                <tr><td><?= e($a['nama']) ?></td><td><code><?= e($a['username']) ?></code></td><td><code><?= e($a['password']) ?></code></td><td><?= e($a['kelas'] ?? '') ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="form-text mt-2">Catat atau cetak password sekarang — password disimpan terenkripsi untuk cetak kartu.</div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-body py-2">
    <form class="row g-2 align-items-center" method="get" action="<?= e(url('siswa')) ?>">
      <input type="hidden" name="r" value="siswa">
      <div class="col-md-4"><input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Cari nama atau username"></div>
      <div class="col-md-3">
        <select class="form-select form-select-sm" name="id_kelas">
          <option value="0">— Semua Kelas —</option>
          <?php foreach ($kelas as $k): ?><option value="<?= (int) $k['id'] ?>" <?= (int) $idKelas === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['nama_kelas']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select class="form-select form-select-sm" name="status">
          <option value="">— Semua Status —</option>
          <option value="Aktif" <?= $status === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
          <option value="Tidak Aktif" <?= $status === 'Tidak Aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-teal btn-sm flex-grow-1">Filter</button>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('siswa')) ?>">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex flex-wrap align-items-center gap-2">
    <span><?= count($rows) ?> siswa ditampilkan</span>
    <?php if ($resetCount > 0): ?><span class="badge text-bg-warning"><?= (int) $resetCount ?> minta reset</span><?php endif; ?>
    <div class="ms-auto d-flex gap-1 flex-wrap">
      <button class="btn btn-sm btn-ghost" data-bs-toggle="modal" data-bs-target="#modalRegenerate">Regenerate Akun</button>
      <form method="post" action="<?= e(url('siswa_reset_sesi')) ?>" class="m-0" data-confirm="Reset semua sesi login siswa?">
        <?= Csrf::field() ?><button class="btn btn-sm btn-ghost">Reset Sesi</button>
      </form>
      <button class="btn btn-sm btn-ghost" data-bs-toggle="modal" data-bs-target="#modalMassal">Aksi Massal</button>
    </div>
  </div>
  <div class="table-wrap">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th style="width:56px">No</th><th>Nama Siswa</th><th>Username / Password</th><th>Kelas</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">Data siswa tidak ditemukan.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $i => $s): ?>
        <tr>
          <td class="text-muted"><?= $i + 1 ?></td>
          <td>
            <div class="fw-semibold"><?= e($s['nama_siswa']) ?></div>
            <?php if ((int) $s['request_reset']): ?><span class="badge text-bg-warning">Minta reset password</span><?php endif; ?>
          </td>
          <td>
            <code><?= e($s['username']) ?></code>
            <span class="text-muted mx-1">/</span>
            <code><?= e($s['password_teks'] ?: '••••••') ?></code>
          </td>
          <td><?= e($s['nama_kelas']) ?></td>
          <td><?= status_badge($s['status']) ?></td>
          <td class="text-end">
            <div class="d-inline-flex gap-1">
              <a class="btn btn-sm btn-ghost" href="<?= e(url('siswa_form', ['id' => $s['id']])) ?>">Ubah</a>
              <form method="post" action="<?= e(url('siswa_reset_password')) ?>" class="m-0" data-confirm="Buat password baru untuk <?= e($s['nama_siswa']) ?>?">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button class="btn btn-sm btn-ghost">Reset PW</button>
              </form>
              <form method="post" action="<?= e(url('siswa_hapus')) ?>" class="m-0" data-confirm="Hapus siswa ini beserta seluruh hasil ujiannya?">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Hapus</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal impor -->
<div class="modal fade" id="modalImpor" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= e(url('siswa_impor')) ?>" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <div class="modal-header"><h5 class="modal-title">Import Data Siswa</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p class="text-muted" style="font-size:.86rem">Gunakan file CSV. Baris judul otomatis diabaikan. Password dibuat acak.</p>
        <div class="d-flex gap-2 mb-3">
          <a class="btn btn-sm btn-ghost" href="<?= e(url('siswa_template', ['format' => 1])) ?>">Template 1: Nama;Kelas;Agama</a>
          <a class="btn btn-sm btn-ghost" href="<?= e(url('siswa_template', ['format' => 2])) ?>">Template 2: Nama;Username;Kelas;Agama</a>
        </div>
        <label class="form-label">File CSV</label>
        <input class="form-control" type="file" name="csv" accept=".csv,text/csv" required>
        <div class="form-text">Maksimal 5 MB. Kelas baru otomatis dibuat.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Batal</button>
        <button class="btn btn-teal" type="submit">Proses Import</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal regenerate -->
<div class="modal fade" id="modalRegenerate" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= e(url('siswa_regenerate')) ?>">
      <?= Csrf::field() ?>
      <div class="modal-header"><h5 class="modal-title">Regenerate Akun</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p class="text-muted" style="font-size:.86rem">Semua password baru akan dibuat acak dan sesi login lama dihentikan.</p>
        <label class="form-label">Lingkup</label>
        <select class="form-select" name="id_kelas">
          <option value="0">Semua siswa</option>
          <?php foreach ($kelas as $k): ?><option value="<?= (int) $k['id'] ?>">Kelas <?= e($k['nama_kelas']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Batal</button>
        <button class="btn btn-teal" type="submit">Regenerate</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal cetak -->
<div class="modal fade" id="modalCetak" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="get" action="<?= e(url('siswa_kartu')) ?>" target="_blank">
      <input type="hidden" name="r" value="siswa_kartu">
      <div class="modal-header"><h5 class="modal-title">Cetak Kartu Ujian</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <label class="form-label">Pilih Kelas</label>
        <select class="form-select" name="id_kelas">
          <option value="0">Semua kelas</option>
          <?php foreach ($kelas as $k): ?><option value="<?= (int) $k['id'] ?>"><?= e($k['nama_kelas']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Batal</button>
        <button class="btn btn-teal" type="submit">Buka Halaman Cetak</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal aksi massal -->
<div class="modal fade" id="modalMassal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Aksi Massal</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form method="post" action="<?= e(url('siswa_toggle_all')) ?>" class="mb-3">
          <?= Csrf::field() ?>
          <label class="form-label">Ubah status seluruh siswa</label>
          <div class="d-flex gap-2">
            <select class="form-select" name="status"><option value="Aktif">Aktif</option><option value="Tidak Aktif">Tidak Aktif</option></select>
            <select class="form-select" name="id_kelas"><option value="0">Semua kelas</option><?php foreach ($kelas as $k): ?><option value="<?= (int) $k['id'] ?>"><?= e($k['nama_kelas']) ?></option><?php endforeach; ?></select>
            <button class="btn btn-ghost">Terapkan</button>
          </div>
        </form>
        <hr>
        <form method="post" action="<?= e(url('siswa_delete_all')) ?>" data-confirm="PERINGATAN: seluruh data siswa pada lingkup ini akan dihapus permanen. Lanjutkan?">
          <?= Csrf::field() ?>
          <label class="form-label text-danger">Hapus massal</label>
          <div class="d-flex gap-2">
            <select class="form-select" name="id_kelas"><option value="0">Semua kelas</option><?php foreach ($kelas as $k): ?><option value="<?= (int) $k['id'] ?>"><?= e($k['nama_kelas']) ?></option><?php endforeach; ?></select>
            <input class="form-control" name="konfirmasi" placeholder="Ketik HAPUS" required>
            <button class="btn btn-outline-danger">Hapus</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
