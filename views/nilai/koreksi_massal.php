<?php
/** @var array $daftar @var array|null $soal @var array $butirUraian @var int $nomor @var array $items */
$butirAktif = null;
foreach ($butirUraian as $b) {
    if ((int) $b['nomor_soal'] === (int) $nomor) {
        $butirAktif = $b;
        break;
    }
}
$poinMaks = $butirAktif ? (float) $butirAktif['poin_maks'] : 0;
?>
<div class="page-head">
  <div>
    <h1>Koreksi Esai Massal</h1>
    <p>Nilai satu nomor esai untuk seluruh peserta sekaligus.</p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="<?= e(url('nilai')) ?>">Kembali ke Hasil</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <?php if ($soal === null): ?>
      <div class="form-label">Pilih ujian</div>
      <div class="row g-2">
        <?php if (!$daftar): ?>
          <div class="col-12 text-muted">Belum ada ujian.</div>
        <?php endif; ?>
        <?php foreach ($daftar as $s): ?>
          <div class="col-md-6 col-xl-4">
            <a class="card text-reset h-100" href="<?= e(url('koreksi_massal', ['id_soal' => $s['id']])) ?>">
              <div class="card-body py-2">
                <div class="fw-semibold" style="font-size:.9rem"><?= e($s['nama_soal']) ?></div>
                <div class="text-muted" style="font-size:.74rem"><?= e($s['mapel'] ?: 'Tanpa mapel') ?></div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <form method="get" action="<?= e(url('koreksi_massal')) ?>" class="row g-2 align-items-end">
        <input type="hidden" name="r" value="koreksi_massal">
        <input type="hidden" name="id_soal" value="<?= (int) $soal['id'] ?>">
        <div class="col-md-8">
          <label class="form-label">Nomor soal esai</label>
          <select class="form-select" name="nomor">
            <option value="0">— pilih nomor —</option>
            <?php foreach ($butirUraian as $b): ?>
              <option value="<?= (int) $b['nomor_soal'] ?>" <?= (int) $nomor === (int) $b['nomor_soal'] ? 'selected' : '' ?>>
                Soal <?= (int) $b['nomor_soal'] ?> (poin maks <?= e(fmt_nilai($b['poin_maks'], 0)) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
          <button class="btn btn-teal">Tampilkan Jawaban</button>
          <a class="btn btn-ghost" href="<?= e(url('koreksi_massal')) ?>">Ganti Ujian</a>
        </div>
      </form>
      <?php if (!$butirUraian): ?>
        <div class="alert alert-warning mt-3 mb-0">Ujian <strong><?= e($soal['nama_soal']) ?></strong> tidak memiliki soal esai.</div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($soal !== null && $nomor > 0): ?>
  <?php if (!$items): ?>
    <div class="card"><div class="card-body kosong">Belum ada jawaban siswa untuk soal nomor <?= (int) $nomor ?>.</div></div>
  <?php else: ?>
    <form method="post" action="<?= e(url('koreksi_simpan')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="id_soal" value="<?= (int) $soal['id'] ?>">
      <input type="hidden" name="nomor" value="<?= (int) $nomor ?>">
      <input type="hidden" name="kembali" value="massal">

      <?php if ($butirAktif && !empty($butirAktif['rubrik'])): ?>
        <div class="alert alert-info">
          <strong>Rubrik:</strong> <?= e($butirAktif['rubrik']) ?>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="table-wrap">
          <table class="table align-middle mb-0">
            <thead>
              <tr><th style="width:56px">No</th><th>Siswa</th><th style="min-width:260px">Jawaban</th><th style="width:130px">Nilai (0–100)</th><th style="width:220px">Catatan</th></tr>
            </thead>
            <tbody>
            <?php foreach ($items as $i => $j):
                $decoded = json_decode((string) $j['jawaban'], true);
                $teks = is_array($decoded) ? implode(', ', array_map(static fn ($x) => (string) $x, $decoded)) : (string) $decoded;
                $nilaiLama = ($j['skor'] !== null && $poinMaks > 0) ? round((float) $j['skor'] / $poinMaks * 100, 2) : '';
            ?>
              <tr>
                <td class="text-muted"><?= $i + 1 ?></td>
                <td>
                  <div class="fw-semibold" style="font-size:.9rem"><?= e($j['nama_siswa']) ?></div>
                  <div class="text-muted" style="font-size:.74rem"><?= e($j['nama_kelas']) ?></div>
                </td>
                <td>
                  <div style="font-size:.86rem;white-space:pre-wrap;max-height:150px;overflow:auto"><?= $teks !== '' ? e($teks) : '<span class="text-muted">(kosong)</span>' ?></div>
                </td>
                <td>
                  <input class="form-control form-control-sm" type="number" min="0" max="100" step="0.5"
                         name="skor[<?= (int) $j['id'] ?>]" value="<?= e((string) $nilaiLama) ?>">
                </td>
                <td>
                  <input class="form-control form-control-sm" name="catatan[<?= (int) $j['id'] ?>]" value="<?= e($j['catatan'] ?? '') ?>" placeholder="Opsional">
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="d-flex gap-2 mt-3 mb-5">
        <button class="btn btn-teal px-4" type="submit">Simpan Semua Koreksi</button>
        <a class="btn btn-ghost" href="<?= e(url('nilai', ['id_soal' => $soal['id']])) ?>">Batal</a>
      </div>
    </form>
  <?php endif; ?>
<?php endif; ?>
