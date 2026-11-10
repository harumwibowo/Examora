<?php
/** @var array $counts @var array $backupGroups @var string $uploadMax */
$fmt = static fn (int $n): string => number_format($n, 0, ',', '.');
$items = [
    'riwayat' => [
        'label' => 'Hasil & Riwayat Ujian',
        'desc' => 'Seluruh percobaan ujian, jawaban siswa, dan log pelanggaran terkait.',
        'count' => $counts['riwayat'],
        'danger' => false,
    ],
    'soal' => [
        'label' => 'Ujian & Bank Soal',
        'desc' => 'Seluruh ujian, butir soal, kunci, target peserta, dan hasil ujiannya.',
        'count' => $counts['soal'],
        'danger' => true,
    ],
    'siswa' => [
        'label' => 'Data Siswa',
        'desc' => 'Akun siswa, kartu ujian, serta seluruh riwayat ujiannya.',
        'count' => $counts['siswa'],
        'danger' => true,
    ],
    'kelas' => [
        'label' => 'Data Kelas',
        'desc' => 'Kelas beserta seluruh siswa di dalamnya. Ujian tetap tersimpan.',
        'count' => $counts['kelas'],
        'danger' => true,
    ],
    'guru' => [
        'label' => 'Akun Guru',
        'desc' => 'Akun guru beserta ujian miliknya. Akun superadmin tidak dihapus.',
        'count' => $counts['guru'],
        'danger' => true,
    ],
    'audit' => [
        'label' => 'Audit Log',
        'desc' => 'Riwayat aktivitas pengguna di aplikasi.',
        'count' => $counts['audit'],
        'danger' => false,
    ],
    'pelanggaran' => [
        'label' => 'Log Pelanggaran',
        'desc' => 'Catatan pelanggaran siswa saja, tanpa menghapus hasil ujian.',
        'count' => $counts['pelanggaran'],
        'danger' => false,
    ],
];
?>
<div class="page-head">
  <div>
    <h1>Database</h1>
    <p>Hapus data tertentu secara selektif, unduh backup, atau pulihkan dari file backup.</p>
  </div>
</div>

<div class="alert alert-warning d-flex gap-2 align-items-start" style="max-width:1050px">
  <div style="font-size:1.1rem;line-height:1.2">⚠</div>
  <div>
    <strong>Lakukan backup sebelum menghapus data.</strong>
    <div style="font-size:.86rem">Tindakan hapus dan restore bersifat permanen, tidak dapat dibatalkan, dan sebaiknya dilakukan saat tidak ada ujian berlangsung.</div>
  </div>
</div>

<div class="row g-3" style="max-width:1200px">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header d-flex align-items-center gap-2">
        <span>Hapus Data Selektif</span>
        <span class="badge badge-soft ms-auto">Superadmin</span>
      </div>
      <div class="card-body">
        <form method="post" action="<?= e(url('database_hapus')) ?>" id="form-hapus"
              data-confirm="Data yang dipilih akan dihapus permanen. Lanjutkan?"
              data-confirm-title="Hapus data terpilih?"
              data-confirm-ok="Ya, hapus permanen">
          <?= Csrf::field() ?>
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <div class="form-check m-0">
              <input class="form-check-input" type="checkbox" id="pilih-semua-hapus">
              <label class="form-check-label" for="pilih-semua-hapus" style="font-size:.85rem">
                Pilih semua data
              </label>
            </div>
            <span class="text-muted ms-auto" id="jumlah-dipilih" style="font-size:.78rem">
              0 dari <?= count($items) ?> dipilih
            </span>
          </div>
          <div class="d-grid gap-2">
            <?php foreach ($items as $key => $item): ?>
              <label class="db-item" for="hapus-<?= e($key) ?>">
                <input class="form-check-input mt-1" type="checkbox" name="hapus[]" value="<?= e($key) ?>" id="hapus-<?= e($key) ?>">
                <span class="db-item-body">
                  <span class="db-item-head">
                    <span class="fw-semibold"><?= e($item['label']) ?></span>
                    <span class="badge <?= $item['danger'] ? 'text-bg-danger' : 'badge-soft' ?>"><?= $fmt((int) $item['count']) ?> data</span>
                  </span>
                  <span class="db-item-desc"><?= e($item['desc']) ?></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>

          <hr>
          <label class="form-label" for="konfirmasi">Ketik <strong>HAPUS</strong> untuk mengonfirmasi</label>
          <input class="form-control" type="text" name="konfirmasi" id="konfirmasi" placeholder="HAPUS"
                 autocomplete="off" spellcheck="false">
          <div class="form-text">Tombol hapus aktif setelah memilih data dan mengetik HAPUS.</div>

          <button class="btn btn-danger w-100 mt-3" type="submit" id="btn-hapus" disabled>Hapus Data Terpilih</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">Backup Database</div>
      <div class="card-body">
        <p class="text-muted" style="font-size:.86rem">
          Pilih bagian data yang ingin disertakan, lalu unduh dalam satu file <code>.sql</code>.
          Restore hanya akan mengganti tabel yang ada di dalam file backup.
        </p>
        <form method="post" action="<?= e(url('database_backup')) ?>" id="form-backup">
          <?= Csrf::field() ?>
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <div class="form-check m-0">
              <input class="form-check-input" type="checkbox" id="backup-semua" checked>
              <label class="form-check-label" for="backup-semua" style="font-size:.85rem">
                Pilih semua data
              </label>
            </div>
            <span class="text-muted ms-auto" id="backup-jumlah" style="font-size:.78rem">
              <?= count($backupGroups) ?> dari <?= count($backupGroups) ?> dipilih
            </span>
          </div>
          <div class="d-grid gap-2">
            <?php foreach ($backupGroups as $key => $g): ?>
              <label class="db-item" for="backup-<?= e($key) ?>">
                <input class="form-check-input mt-1" type="checkbox" name="backup[]"
                       value="<?= e($key) ?>" id="backup-<?= e($key) ?>" checked>
                <span class="db-item-body">
                  <span class="db-item-head">
                    <span class="fw-semibold"><?= e($g['label']) ?></span>
                  </span>
                  <span class="db-item-desc"><?= e($g['desc']) ?></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="form-text mt-2">
            Tabel yang saling terhubung disertakan otomatis sesuai kelompoknya.
          </div>
          <button class="btn btn-teal w-100 mt-2" type="submit">⬇ Unduh Backup (.sql)</button>
        </form>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header">Restore dari Backup</div>
      <div class="card-body">
        <form method="post" action="<?= e(url('database_restore')) ?>" enctype="multipart/form-data"
              data-confirm="Restore akan MENGGANTI seluruh data saat ini dengan isi file backup. Pastikan Anda sudah membuat backup terbaru. Lanjutkan?"
              data-confirm-title="Pulihkan database?"
              data-confirm-ok="Ya, restore sekarang">
          <?= Csrf::field() ?>
          <label class="form-label" for="backup">File backup Examora</label>
          <input class="form-control" type="file" name="backup" id="backup" accept=".sql,application/sql" required>
          <div class="form-text">Hanya file backup yang dibuat oleh menu ini. Maksimal <?= e($uploadMax) ?>.</div>

          <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" name="yakin" value="1" id="yakin" required>
            <label class="form-check-label" for="yakin" style="font-size:.86rem">
              Saya mengerti seluruh data saat ini akan diganti dan sudah memiliki backup terbaru.
            </label>
          </div>

          <button class="btn btn-outline-danger w-100 mt-3" type="submit">Pulihkan Database</button>
        </form>
      </div>
    </div>
  </div>
</div>

<style>
.db-item { display: flex; gap: .7rem; padding: .7rem .8rem; border: 1px solid var(--line); border-radius: 10px; cursor: pointer; }
.db-item:hover { border-color: var(--teal); background: #F7FAFA; }
.db-item:has(input:checked) { border-color: var(--teal); background: #EFF8F7; }
.db-item-body { display: block; min-width: 0; }
.db-item-head { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
.db-item-desc { display: block; color: #6B7C7B; font-size: .8rem; margin-top: .1rem; }
</style>

<script>
(function () {
  var form = document.getElementById('form-hapus');
  if (!form) return;
  var boxes = form.querySelectorAll('input[name="hapus[]"]');
  var input = document.getElementById('konfirmasi');
  var btn = document.getElementById('btn-hapus');
  var semua = document.getElementById('pilih-semua-hapus');
  var info = document.getElementById('jumlah-dipilih');
  var total = boxes.length;

  function jumlah() {
    var n = 0;
    boxes.forEach(function (b) { if (b.checked) n++; });
    return n;
  }

  function sync() {
    var n = jumlah();
    var cocok = input.value.trim().toUpperCase() === 'HAPUS';
    btn.disabled = !(n > 0 && cocok);
    if (info) info.textContent = n + ' dari ' + total + ' dipilih';
    if (semua) {
      semua.checked = n > 0 && n === total;
      semua.indeterminate = n > 0 && n < total;
    }
  }

  if (semua) {
    semua.addEventListener('change', function () {
      boxes.forEach(function (b) { b.checked = semua.checked; });
      sync();
    });
  }

  boxes.forEach(function (b) { b.addEventListener('change', sync); });
  input.addEventListener('input', sync);
  sync();
})();

(function () {
  var form = document.getElementById('form-backup');
  if (!form) return;
  var boxes = form.querySelectorAll('input[name="backup[]"]');
  var semua = document.getElementById('backup-semua');
  var info = document.getElementById('backup-jumlah');
  var btn = form.querySelector('button[type="submit"]');
  var total = boxes.length;

  function jumlah() {
    var n = 0;
    boxes.forEach(function (b) { if (b.checked) n++; });
    return n;
  }

  function sync() {
    var n = jumlah();
    if (info) info.textContent = n + ' dari ' + total + ' dipilih';
    if (btn) btn.disabled = n === 0;
    if (semua) {
      semua.checked = n > 0 && n === total;
      semua.indeterminate = n > 0 && n < total;
    }
  }

  if (semua) {
    semua.addEventListener('change', function () {
      boxes.forEach(function (b) { b.checked = semua.checked; });
      sync();
    });
  }

  boxes.forEach(function (b) { b.addEventListener('change', sync); });
  sync();
})();
</script>
