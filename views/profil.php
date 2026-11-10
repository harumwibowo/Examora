<?php /** @var array|null $authUser */ $u = $authUser ?? []; ?>
<div class="page-head">
  <div>
    <h1>Akun Saya</h1>
    <p>Informasi akun dan perubahan password.</p>
  </div>
</div>

<div class="row g-3" style="max-width:900px">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">Informasi Akun</div>
      <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="avatar" style="width:52px;height:52px;font-size:.95rem"><?= e(inisial_nama($u['nama'] ?? '?')) ?></div>
          <div>
            <div class="fw-bold"><?= e($u['nama'] ?? '') ?></div>
            <div class="text-muted" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.06em"><?= e($u['role'] ?? '') ?></div>
          </div>
        </div>
        <table class="table table-sm mb-0">
          <tbody>
            <tr><th style="width:120px">Email</th><td><?= e($u['email'] ?? '') ?></td></tr>
            <tr><th>Peran</th><td><?= e($u['role'] ?? '') ?></td></tr>
            <tr><th>Tipe sesi</th><td><?= e($u['type'] ?? '') ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">Ubah Password</div>
      <div class="card-body">
        <form method="post" action="<?= e(url('profil_simpan')) ?>" class="vstack gap-3">
          <?= Csrf::field() ?>
          <div>
            <label class="form-label" for="lama">Password Lama</label>
            <input class="form-control" type="password" id="lama" name="lama" required autocomplete="current-password">
          </div>
          <div>
            <label class="form-label" for="baru">Password Baru</label>
            <input class="form-control" type="password" id="baru" name="baru" required minlength="8" autocomplete="new-password">
            <div class="form-text">Minimal 8 karakter.</div>
          </div>
          <div>
            <label class="form-label" for="ulang">Ulangi Password Baru</label>
            <input class="form-control" type="password" id="ulang" name="ulang" required minlength="8" autocomplete="new-password">
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-teal px-4" type="submit">Simpan Password</button>
            <a class="btn btn-ghost" href="<?= e(url('dashboard')) ?>">Batal</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
