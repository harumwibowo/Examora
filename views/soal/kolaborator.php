<?php
/** @var array $soal @var array|null $pemilik @var array $kolaborator @var array $terpilih @var array $kandidat */
$sisa = array_values(array_filter($kandidat, static fn ($g) => !in_array((int) $g['id'], $terpilih, true)));
?>
<div class="page-head">
  <div>
    <h1>Kolaborator Ujian</h1>
    <p>
      <strong><?= e($soal['nama_soal']) ?></strong>
      <span class="text-muted">· <?= e($soal['mapel'] ?: 'Tanpa mata pelajaran') ?></span>
    </p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="<?= e(url('soal')) ?>">Kembali</a>
    <a class="btn btn-teal" href="<?= e(url('kunci', ['id' => $soal['id']])) ?>">Buka Ujian</a>
  </div>
</div>

<div class="alert alert-info d-flex gap-2 align-items-start">
  <span>ℹ</span>
  <div>
    Kolaborator adalah guru yang bisa <strong>mengubah ujian, kunci jawaban, mengoreksi, dan melihat hasil</strong>
    persis seperti pemilik ujian. Hanya pemilik ujian yang dapat menambah atau mengeluarkan kolaborator.
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">Pemilik Ujian</div>
      <div class="card-body">
        <div class="fw-semibold"><?= e($pemilik['nama'] ?? '-') ?></div>
        <div class="text-muted" style="font-size:.8rem"><?= e($pemilik['email'] ?? '-') ?></div>
        <div class="form-text mt-2">Pemilik selalu memiliki akses penuh dan tidak dapat dikeluarkan.</div>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center">
        Tambah Kolaborator
        <span class="badge badge-soft ms-auto"><?= count($kolaborator) ?> aktif</span>
      </div>
      <div class="card-body">
        <?php if (!$kandidat): ?>
          <p class="text-muted mb-0">Belum ada akun guru lain. Tambahkan guru di menu <a href="<?= e(url('guru')) ?>">Akun Guru</a>.</p>
        <?php else: ?>
          <form method="post" action="<?= e(url('soal_kolaborator_tambah')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int) $soal['id'] ?>">
            <?php if (!$sisa): ?>
              <p class="text-muted mb-2">Semua guru aktif sudah menjadi kolaborator ujian ini.</p>
            <?php else: ?>
              <div style="max-height:260px;overflow:auto" class="border rounded p-2 mb-2">
                <?php foreach ($sisa as $g): ?>
                  <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" name="id_guru[]" value="<?= (int) $g['id'] ?>" id="g<?= (int) $g['id'] ?>">
                    <label class="form-check-label" for="g<?= (int) $g['id'] ?>">
                      <?= e($g['nama']) ?> <span class="text-muted" style="font-size:.75rem">· <?= e($g['email']) ?></span>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="pilih-semua-guru">
                <label class="form-check-label" for="pilih-semua-guru">Pilih semua</label>
              </div>
              <button class="btn btn-teal w-100" type="submit">Tambahkan sebagai Kolaborator</button>
            <?php endif; ?>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header">Daftar Kolaborator</div>
      <?php if (!$kolaborator): ?>
        <div class="card-body text-muted">Belum ada kolaborator. Ujian ini hanya bisa dikelola pemilik.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Guru</th><th>Email</th><th>Ditambahkan</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
              <?php foreach ($kolaborator as $k): ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= e($k['nama']) ?></div>
                    <?php if (!(int) $k['aktif']): ?><span class="badge badge-soft">Nonaktif</span><?php endif; ?>
                  </td>
                  <td class="text-muted"><?= e($k['email']) ?></td>
                  <td class="text-muted" style="font-size:.8rem"><?= e(date('d/m/Y H:i', strtotime((string) $k['created_at']))) ?></td>
                  <td class="text-end">
                    <form method="post" action="<?= e(url('soal_kolaborator_hapus')) ?>" class="m-0"
                          data-confirm="Keluarkan <?= e($k['nama']) ?> dari kolaborator ujian ini?">
                      <?= Csrf::field() ?>
                      <input type="hidden" name="id" value="<?= (int) $soal['id'] ?>">
                      <input type="hidden" name="id_user" value="<?= (int) $k['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit">Keluarkan</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function () {
  var all = document.getElementById('pilih-semua-guru');
  if (!all) return;
  var kotak = Array.prototype.slice.call(document.querySelectorAll('input[name="id_guru[]"]'));
  all.addEventListener('change', function () {
    kotak.forEach(function (c) { c.checked = all.checked; });
  });
  kotak.forEach(function (c) {
    c.addEventListener('change', function () {
      var dipilih = kotak.filter(function (x) { return x.checked; }).length;
      all.checked = dipilih === kotak.length;
      all.indeterminate = dipilih > 0 && dipilih < kotak.length;
    });
  });
})();
</script>
