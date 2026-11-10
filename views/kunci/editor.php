<?php
/**
 * @var array $soal
 * @var array $butir
 * @var int $total
 * @var array $opsi
 * @var array $komposisi
 * @var bool $pakaiKomposisi
 */
$isKompleks = $soal['jenis_ujian'] === 'Kompleks';
$jmlPg = (int) $soal['jml_pg'];
$opsiHuruf = $opsi;
$labelTipe = tipe_soal_options();
$blokMulai = [];
foreach ($komposisi as $kb) {
    $blokMulai[(int) $kb['dari']] = $kb;
}
?>
<div class="page-head">
  <div>
    <h1>Kunci Jawaban</h1>
    <p><?= e($soal['nama_soal']) ?> · <?= e($soal['jenis_ujian']) ?> · token <code><?= e($soal['kode_soal']) ?></code></p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="<?= e(url('soal_form', ['id' => $soal['id']])) ?>">Ubah Setelan</a>
    <a class="btn btn-ghost" href="<?= e(url('soal')) ?>">Kembali</a>
  </div>
</div>

<div class="alert alert-info d-flex gap-2 align-items-start">
  <span>ℹ️</span>
  <div style="font-size:.86rem">
    Terisi <strong><?= count($butir) ?>/<?= $total ?></strong> nomor.
    Komposisi: <strong><?= e(komposisi_label($soal)) ?></strong>.
    Siswa membaca soal dari dokumen PDF Google Drive; isi kunci dan skor per jenis soal di sini.
    <a href="<?= e(url('soal_toggle')) ?>" class="d-none"></a>
  </div>
</div>

<form method="post" action="<?= e(url('kunci_simpan')) ?>" id="form-kunci">
  <?= Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int) $soal['id'] ?>">

  <?php for ($n = 1; $n <= $total; $n++):
      $b = $butir[$n] ?? null;
      $k = $b ? (json_decode((string) $b['kunci'], true) ?: []) : [];
      $tipe = $pakaiKomposisi
          ? komposisi_tipe($soal, $n)
          : ($b['tipe_soal'] ?? ($n <= $jmlPg ? 'PG' : 'Uraian'));
      if (!$pakaiKomposisi && !$isKompleks) {
          $tipe = $n <= $jmlPg ? 'PG' : 'Uraian';
      }
      if (!isset($labelTipe[$tipe])) { $tipe = 'PG'; }
      $sb = $b['skor_benar'] ?? 1;
      $ss = $b['skor_salah'] ?? (in_array($tipe, ['PG_Kompleks', 'Benar_Salah'], true) ? -1 : 0);
      $pm = $b['poin_maks'] ?? 1;
      $opsiTeks = $b ? (json_decode((string) ($b['opsi'] ?? ''), true) ?: []) : [];
      $kunciArr = array_values((array) $k);
      $blok = $blokMulai[$n] ?? null;
  ?>
  <?php if ($blok !== null): ?>
    <div class="d-flex align-items-center gap-2 mt-4 mb-2" style="font-size:.82rem">
      <span class="badge text-bg-dark">Nomor <?= (int) $blok['dari'] ?><?= (int) $blok['sampai'] > (int) $blok['dari'] ? '–' . (int) $blok['sampai'] : '' ?></span>
      <strong><?= e($labelTipe[$blok['tipe']] ?? $blok['tipe']) ?></strong>
      <span class="text-muted">· <?= (int) $blok['sampai'] - (int) $blok['dari'] + 1 ?> soal · bobot <?= e(fmt_nilai($blok['bobot'], 2)) ?>%</span>
    </div>
  <?php endif; ?>
  <div class="card mb-3 kunci-card" data-nomor="<?= $n ?>">
    <div class="card-header d-flex align-items-center gap-2 flex-wrap">
      <span class="badge text-bg-dark">Nomor <?= $n ?></span>
      <?php if (!$pakaiKomposisi && $isKompleks): ?>
        <select class="form-select form-select-sm" name="tipe[<?= $n ?>]" data-tipe style="width:210px">
          <?php foreach ($labelTipe as $key => $lbl): ?>
            <option value="<?= $key ?>" <?= $tipe === $key ? 'selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        <span class="badge badge-soft"><?= e($labelTipe[$tipe] ?? $tipe) ?></span>
      <?php endif; ?>
      <div class="ms-auto d-flex align-items-center gap-2">
        <label class="form-label mb-0" style="font-size:.76rem">Skor benar</label>
        <input type="number" step="0.01" class="form-control form-control-sm" name="skor_benar[<?= $n ?>]" value="<?= e((string) $sb) ?>" style="width:82px">
        <label class="form-label mb-0" style="font-size:.76rem">Skor salah</label>
        <input type="number" step="0.01" class="form-control form-control-sm" name="skor_salah[<?= $n ?>]" value="<?= e((string) $ss) ?>" style="width:82px">
      </div>
    </div>
    <div class="card-body">

      <!-- PG -->
      <div data-block="PG" style="<?= $tipe === 'PG' ? '' : 'display:none' ?>">
        <div class="d-flex flex-wrap gap-3 mb-2">
          <?php foreach ($opsiHuruf as $h): ?>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="pg_kunci[<?= $n ?>]" value="<?= $h ?>" id="pg<?= $n . $h ?>" <?= (($k['jawaban'] ?? '') === $h) ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold" for="pg<?= $n . $h ?>"><?= $h ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- PG Kompleks -->
      <div data-block="PG_Kompleks" style="<?= $tipe === 'PG_Kompleks' ? '' : 'display:none' ?>">
        <div class="d-flex flex-wrap gap-3 mb-2">
          <?php foreach ($opsiHuruf as $h): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="pgk_kunci[<?= $n ?>][]" value="<?= $h ?>" id="pgk<?= $n . $h ?>" <?= in_array($h, (array) ($k['jawaban'] ?? []), true) ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold" for="pgk<?= $n . $h ?>"><?= $h ?></label>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="form-text">Centang semua jawaban yang benar. Skor diberikan bila himpunan jawaban sama persis.</div>
      </div>

      <!-- Benar / Salah -->
      <div data-block="Benar_Salah" style="<?= $tipe === 'Benar_Salah' ? '' : 'display:none' ?>">
        <div class="bs-rows">
          <?php
          $bsRows = $kunciArr !== [] ? $kunciArr : array_fill(0, 4, ['pernyataan' => '', 'kunci' => true]);
          foreach ($bsRows as $row):
          ?>
            <div class="row g-2 mb-2">
              <div class="col-9"><input class="form-control form-control-sm" name="bs_pernyataan[<?= $n ?>][]" value="<?= e($row['pernyataan'] ?? '') ?>" placeholder="Pernyataan"></div>
              <div class="col-3">
                <select class="form-select form-select-sm" name="bs_kunci[<?= $n ?>][]">
                  <option value="1" <?= !empty($row['kunci']) ? 'selected' : '' ?>>Benar</option>
                  <option value="0" <?= empty($row['kunci']) ? 'selected' : '' ?>>Salah</option>
                </select>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-ghost" data-add-bs="<?= $n ?>">+ Tambah pernyataan</button>
      </div>

      <!-- Menjodohkan -->
      <div data-block="Menjodohkan" style="<?= $tipe === 'Menjodohkan' ? '' : 'display:none' ?>">
        <div class="mj-rows">
          <?php
          $mjRows = $kunciArr !== [] ? $kunciArr : array_fill(0, 4, ['kiri' => '', 'kanan' => '']);
          foreach ($mjRows as $row):
          ?>
            <div class="row g-2 mb-2">
              <div class="col-6"><input class="form-control form-control-sm" name="mj_kiri[<?= $n ?>][]" value="<?= e($row['kiri'] ?? '') ?>" placeholder="Kiri (pernyataan)"></div>
              <div class="col-6"><input class="form-control form-control-sm" name="mj_kanan[<?= $n ?>][]" value="<?= e($row['kanan'] ?? '') ?>" placeholder="Kanan (pasangan benar)"></div>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-ghost" data-add-mj="<?= $n ?>">+ Tambah pasangan</button>
      </div>

      <!-- Isian -->
      <div data-block="Isian" style="<?= $tipe === 'Isian' ? '' : 'display:none' ?>">
        <?php $alt = array_values((array) ($k['jawaban'] ?? [])); $alt = $alt !== [] ? $alt : ['']; ?>
        <div class="row g-2">
          <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="col-md-6">
              <div class="input-group input-group-sm">
                <span class="input-group-text">Alt <?= $i + 1 ?></span>
                <input class="form-control" name="isian[<?= $n ?>][]" value="<?= e($alt[$i] ?? '') ?>" placeholder="Alternatif jawaban">
              </div>
            </div>
          <?php endfor; ?>
        </div>
        <div class="form-text">Cocok jika jawaban siswa sama dengan salah satu alternatif (tidak peka huruf besar/kecil).</div>
      </div>

      <!-- Uraian -->
      <div data-block="Uraian" style="<?= $tipe === 'Uraian' ? '' : 'display:none' ?>">
        <label class="form-label">Rubrik / Panduan Koreksi</label>
        <textarea class="form-control" name="rubrik[<?= $n ?>]" rows="3" placeholder="Poin-poin penting yang harus muncul pada jawaban siswa"><?= e($b['rubrik'] ?? ($k['rubrik'] ?? '')) ?></textarea>
        <div class="row g-2 mt-1">
          <div class="col-md-4">
            <label class="form-label">Poin Maksimal</label>
            <input class="form-control form-control-sm" type="number" step="0.5" min="1" name="poin_maks[<?= $n ?>]" value="<?= e((string) $pm) ?>">
          </div>
        </div>
      </div>

      <details class="mt-2">
        <summary class="text-muted" style="font-size:.8rem;cursor:pointer">Pembahasan (opsional)</summary>
        <textarea class="form-control mt-2" name="pembahasan[<?= $n ?>]" rows="2"><?= e($b['pembahasan'] ?? '') ?></textarea>
      </details>
    </div>
  </div>
  <?php endfor; ?>

  <div class="d-flex gap-2 mb-5">
    <button class="btn btn-teal px-4" type="submit">Simpan Kunci Jawaban</button>
    <a class="btn btn-ghost" href="<?= e(url('soal')) ?>">Kembali</a>
  </div>
</form>

<form method="post" action="<?= e(url('kunci_reset')) ?>" data-confirm="Hapus SEMUA kunci jawaban ujian ini? Tindakan ini tidak dapat dibatalkan." class="mb-5">
  <?= Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int) $soal['id'] ?>">
  <button class="btn btn-outline-danger btn-sm" type="submit">Reset Seluruh Kunci</button>
</form>

<script>
(function () {
  document.querySelectorAll('select[data-tipe]').forEach(function (sel) {
    var card = sel.closest('.kunci-card');
    function sync() {
      card.querySelectorAll('[data-block]').forEach(function (b) {
        b.style.display = b.getAttribute('data-block') === sel.value ? '' : 'none';
      });
    }
    sel.addEventListener('change', sync);
    sync();
  });

  document.querySelectorAll('[data-add-bs]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var n = btn.getAttribute('data-add-bs');
      var wrap = document.querySelector('.kunci-card[data-nomor="' + n + '"] .bs-rows');
      var div = document.createElement('div');
      div.className = 'row g-2 mb-2';
      div.innerHTML = '<div class="col-9"><input class="form-control form-control-sm" name="bs_pernyataan[' + n + '][]" placeholder="Pernyataan"></div>'
        + '<div class="col-3"><select class="form-select form-select-sm" name="bs_kunci[' + n + '][]"><option value="1">Benar</option><option value="0">Salah</option></select></div>';
      wrap.appendChild(div);
    });
  });

  document.querySelectorAll('[data-add-mj]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var n = btn.getAttribute('data-add-mj');
      var wrap = document.querySelector('.kunci-card[data-nomor="' + n + '"] .mj-rows');
      var div = document.createElement('div');
      div.className = 'row g-2 mb-2';
      div.innerHTML = '<div class="col-6"><input class="form-control form-control-sm" name="mj_kiri[' + n + '][]" placeholder="Kiri (pernyataan)"></div>'
        + '<div class="col-6"><input class="form-control form-control-sm" name="mj_kanan[' + n + '][]" placeholder="Kanan (pasangan benar)"></div>';
      wrap.appendChild(div);
    });
  });
})();
</script>
