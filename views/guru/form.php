<?php /** @var array|null $row */ $edit = $row !== null; ?>
<div class="page-head">
  <div>
    <h1><?= $edit ? 'Ubah Akun Guru' : 'Tambah Akun Guru' ?></h1>
    <p>Guru hanya melihat ujian dan data yang terkait dengan akunnya.</p>
  </div>
  <div class="actions"><a class="btn btn-ghost" href="<?= e(url('guru')) ?>">Kembali</a></div>
</div>

<form method="post" action="<?= e(url('guru_simpan')) ?>" class="card" style="max-width:720px">
  <div class="card-body">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Nama Lengkap</label>
        <input class="form-control" name="nama" value="<?= e(old('nama', $row['nama'] ?? '')) ?>" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Email</label>
        <input class="form-control" type="email" name="email" value="<?= e(old('email', $row['email'] ?? '')) ?>" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Password <?= $edit ? '(kosongkan bila tidak diubah)' : '' ?></label>
        <input class="form-control" type="password" name="password" <?= $edit ? '' : 'required' ?> minlength="8" autocomplete="new-password">
        <div class="form-text">Minimal 8 karakter.</div>
      </div>
      <div class="col-md-6">
        <label class="form-label">Status</label>
        <select class="form-select" name="aktif">
          <option value="1" <?= (int) ($row['aktif'] ?? 1) === 1 ? 'selected' : '' ?>>Aktif</option>
          <option value="0" <?= isset($row['aktif']) && (int) $row['aktif'] === 0 ? 'selected' : '' ?>>Nonaktif</option>
        </select>
      </div>
    </div>
  </div>
  <div class="card-body border-top d-flex gap-2">
    <button class="btn btn-teal px-4" type="submit"><?= $edit ? 'Simpan Perubahan' : 'Buat Akun' ?></button>
    <a class="btn btn-ghost" href="<?= e(url('guru')) ?>">Batal</a>
  </div>
</form>
