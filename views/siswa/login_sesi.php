<?php
/** @var array $sesi @var string $q @var int $idKelas @var string $status @var array $kelas @var int $jumlahSiswa @var int $jumlahSesi */
$maks = maks_login_siswa();
$adaFilter = $q !== '' || $idKelas > 0 || $status !== '';
?>
<div class="page-head">
  <div>
    <h1>Reset Login Siswa</h1>
    <p>
      Daftar perangkat yang sedang login sebagai siswa. Keluarkan sesi bila siswa
      berganti perangkat, lupa logout, atau kehilangan perangkat.
    </p>
  </div>
  <div class="actions">
    <form method="post" action="<?= e(url('login_siswa_reset_semua')) ?>" class="m-0"
          data-confirm="Keluarkan semua siswa dari semua perangkat?">
      <?= Csrf::field() ?>
      <button class="btn btn-ghost" <?= $jumlahSesi === 0 ? 'disabled' : '' ?>>Reset Semua</button>
    </form>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-2 d-flex flex-wrap align-items-center gap-3">
    <span class="badge badge-soft"><?= (int) $jumlahSesi ?> sesi aktif</span>
    <span class="badge badge-soft"><?= (int) $jumlahSiswa ?> siswa</span>
    <span class="text-muted" style="font-size:.8rem">
      Batas login bersamaan: <strong><?= (int) $maks ?> perangkat</strong> per siswa
      (diatur di <a href="<?= e(url('pengaturan')) ?>">Pengaturan</a>).
    </span>
    <form class="row g-2 align-items-center ms-auto" method="get" action="<?= e(url('login_siswa')) ?>">
      <input type="hidden" name="r" value="login_siswa">
      <div class="col-auto">
        <input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Cari nama atau username">
      </div>
      <div class="col-auto">
        <select class="form-select form-select-sm" name="id_kelas" aria-label="Filter kelas">
          <option value="0">— Semua Kelas —</option>
          <?php foreach ($kelas as $k): ?>
            <option value="<?= (int) $k['id'] ?>" <?= (int) $idKelas === (int) $k['id'] ? 'selected' : '' ?>>
              <?= e($k['nama_kelas']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <select class="form-select form-select-sm" name="status" aria-label="Filter status akun siswa">
          <option value="">— Semua Status —</option>
          <option value="Aktif" <?= $status === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
          <option value="Tidak Aktif" <?= $status === 'Tidak Aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
        </select>
      </div>
      <div class="col-auto d-flex gap-2">
        <button class="btn btn-teal btn-sm">Filter</button>
        <?php if ($adaFilter): ?>
          <a class="btn btn-ghost btn-sm" href="<?= e(url('login_siswa')) ?>">Reset</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<form id="formResetMassal" method="post" action="<?= e(url('login_siswa_reset_massal')) ?>"
      class="d-flex flex-wrap align-items-center gap-2 mb-3"
      data-confirm="Reset login untuk semua sesi yang dicentang?"
      data-confirm-title="Reset login terpilih?"
      data-confirm-ok="Ya, reset">
  <?= Csrf::field() ?>
  <input type="hidden" name="q" value="<?= e($q) ?>">
  <?php if ($idKelas > 0): ?>
    <input type="hidden" name="id_kelas" value="<?= (int) $idKelas ?>">
  <?php endif; ?>
  <?php if ($status !== ''): ?>
    <input type="hidden" name="status" value="<?= e($status) ?>">
  <?php endif; ?>
  <button class="btn btn-teal btn-sm" id="tombol-reset-massal" type="submit" disabled>
    Reset Login Terpilih<span data-jumlah></span>
  </button>
  <span class="text-muted" style="font-size:.78rem">
    Centang sesi pada tabel atau centang <strong>Pilih semua</strong>, lalu tekan tombol ini.
  </span>
</form>

<div class="card">
  <div class="table-wrap">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th style="width:36px">
            <input class="form-check-input" type="checkbox" id="pilih-semua"
                   aria-label="Pilih semua sesi" title="Pilih semua">
          </th>
          <th>Siswa</th>
          <th>Kelas</th>
          <th>Status</th>
          <th>Perangkat</th>
          <th>IP</th>
          <th>Login</th>
          <th>Aktivitas Terakhir</th>
          <th style="width:170px">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$sesi): ?>
        <tr>
          <td colspan="9" class="text-center text-muted py-4">
            Tidak ada siswa yang sedang login
            <?= $adaFilter ? ' untuk filter ini' : '' ?>.
          </td>
        </tr>
      <?php endif; ?>
      <?php foreach ($sesi as $s): ?>
        <tr>
          <td>
            <input class="form-check-input pilih-sesi" type="checkbox" name="pilih[]"
                   value="<?= (int) $s['id'] ?>" form="formResetMassal"
                   aria-label="Pilih sesi <?= e($s['nama_siswa']) ?>">
          </td>
          <td>
            <div class="fw-semibold" style="font-size:.88rem"><?= e($s['nama_siswa']) ?></div>
            <div class="text-muted" style="font-size:.74rem"><code><?= e($s['username']) ?></code></div>
          </td>
          <td style="font-size:.84rem"><?= e($s['nama_kelas']) ?></td>
          <td><?= status_badge((string) ($s['status'] ?? '')) ?></td>
          <td style="font-size:.82rem">
            <?= e(browser_label((string) ($s['browser'] ?? ''))) ?>
            <div class="text-muted" style="font-size:.68rem;max-width:260px;word-break:break-all">
              <?= e((string) ($s['user_agent'] ?? '')) ?>
            </div>
          </td>
          <td style="font-size:.78rem"><?= e((string) ($s['ip'] ?? '—')) ?></td>
          <td style="font-size:.78rem"><?= e(fmt_tanggal($s['login_at'])) ?></td>
          <td style="font-size:.78rem"><?= e(fmt_tanggal($s['last_seen'])) ?></td>
          <td>
            <div class="d-flex gap-1">
              <form method="post" action="<?= e(url('login_siswa_reset')) ?>" class="m-0"
                    data-confirm="Keluarkan sesi perangkat ini?">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <input type="hidden" name="q" value="<?= e($q) ?>">
                <input type="hidden" name="id_kelas" value="<?= (int) $idKelas ?>">
                <?php if ($status !== ''): ?>
                  <input type="hidden" name="status" value="<?= e($status) ?>">
                <?php endif; ?>
                <button class="btn btn-sm btn-ghost">Keluarkan</button>
              </form>
              <form method="post" action="<?= e(url('login_siswa_reset_siswa')) ?>" class="m-0"
                    data-confirm="Keluarkan siswa ini dari semua perangkat?">
                <?= Csrf::field() ?>
                <input type="hidden" name="id_siswa" value="<?= (int) $s['id_siswa'] ?>">
                <input type="hidden" name="q" value="<?= e($q) ?>">
                <input type="hidden" name="id_kelas" value="<?= (int) $idKelas ?>">
                <?php if ($status !== ''): ?>
                  <input type="hidden" name="status" value="<?= e($status) ?>">
                <?php endif; ?>
                <button class="btn btn-sm btn-ghost" title="Keluarkan dari semua perangkat">Semua</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="alert alert-info mt-3" style="font-size:.82rem">
  Sesi otomatis dianggap berakhir jika siswa logout, browser ditutup lama
  (tidak ada aktivitas 2 jam), atau sesi PHP kedaluwarsa.
</div>

<script>
(function () {
  var form = document.getElementById('formResetMassal');
  var btn = document.getElementById('tombol-reset-massal');
  var semua = document.getElementById('pilih-semua');
  if (!form || !btn || !semua) return;
  var kotak = Array.prototype.slice.call(document.querySelectorAll('.pilih-sesi'));
  var label = btn.querySelector('[data-jumlah]');

  function jumlah() {
    return kotak.filter(function (k) { return k.checked; }).length;
  }
  function sinkron() {
    var n = jumlah();
    btn.disabled = n === 0;
    if (label) label.textContent = n > 0 ? ' (' + n + ')' : '';
    semua.checked = n > 0 && n === kotak.length;
    semua.indeterminate = n > 0 && n < kotak.length;
  }

  semua.addEventListener('change', function () {
    kotak.forEach(function (k) { k.checked = semua.checked; });
    sinkron();
  });
  kotak.forEach(function (k) { k.addEventListener('change', sinkron); });
  form.addEventListener('submit', function (e) {
    if (jumlah() === 0) {
      e.preventDefault();
      return;
    }
    if (semua.checked) semua.indeterminate = false;
  });
  sinkron();
})();
</script>
