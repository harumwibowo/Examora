<?php /** @var array|null $row @var array $kelas */ $edit = $row !== null; ?>
<div class="page-head">
  <div>
    <h1><?= $edit ? 'Ubah Data Siswa' : 'Tambah Siswa' ?></h1>
    <p><?= $edit ? 'Kosongkan password bila tidak ingin mengubahnya.' : 'Password dapat dikosongkan dan akan dibuat otomatis.' ?></p>
  </div>
  <div class="actions"><a class="btn btn-ghost" href="<?= e(url('siswa')) ?>">Kembali</a></div>
</div>

<form method="post" action="<?= e(url('siswa_simpan')) ?>" class="card" style="max-width:820px">
  <div class="card-body">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Nama Siswa</label>
        <input class="form-control" name="nama_siswa" value="<?= e(old('nama_siswa', $row['nama_siswa'] ?? '')) ?>" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Kelas</label>
        <select class="form-select" name="id_kelas" required>
          <option value="">— pilih kelas —</option>
          <?php foreach ($kelas as $k): ?>
            <option value="<?= (int) $k['id'] ?>" <?= (int) old('id_kelas', $row['id_kelas'] ?? 0) === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['nama_kelas']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Username</label>
        <input class="form-control" name="username" value="<?= e(old('username', $row['username'] ?? '')) ?>" placeholder="Kosongkan untuk otomatis (U + 7 digit)">
        <div class="form-text">4–20 karakter. Huruf, angka, titik, garis bawah, strip.</div>
      </div>
      <div class="col-md-6">
        <label class="form-label">Agama</label>
        <input class="form-control" name="agama" value="<?= e(old('agama', $row['agama'] ?? '')) ?>" placeholder="Opsional">
      </div>
      <div class="col-md-6">
        <label class="form-label">Password <?= $edit ? '(opsional)' : '(opsional)' ?></label>
        <input class="form-control" type="text" name="password" autocomplete="off" placeholder="Kosongkan untuk otomatis">
        <div class="form-text">Minimal 5 karakter. Disimpan dengan hash + terenkripsi untuk kartu.</div>
      </div>
      <div class="col-md-6">
        <label class="form-label">Status</label>
        <select class="form-select" name="status">
          <option value="Aktif" <?= ($row['status'] ?? 'Aktif') === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
          <option value="Tidak Aktif" <?= ($row['status'] ?? '') === 'Tidak Aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
        </select>
      </div>
    </div>
  </div>
  <div class="card-body border-top d-flex gap-2">
    <button class="btn btn-teal px-4" type="submit"><?= $edit ? 'Simpan Perubahan' : 'Tambah Siswa' ?></button>
    <a class="btn btn-ghost" href="<?= e(url('siswa')) ?>">Batal</a>
  </div>
</form>
