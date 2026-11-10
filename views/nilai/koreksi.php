<?php
/** @var array $r @var array $soal @var array $siswa @var array $butirUraian @var array $jawaban */
?>
<div class="page-head">
  <div>
    <h1>Koreksi Esai</h1>
    <p><?= e($siswa['nama_siswa'] ?? 'Siswa') ?> · <?= e($soal['nama_soal']) ?> · percobaan <?= (int) $r['percobaan_ke'] ?></p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="<?= e(url('nilai', ['id_soal' => $soal['id']])) ?>">Kembali ke Rekap</a>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card stat-card"><div class="card-body py-3">
      <div class="stat-value"><?= $r['nilai_pg'] !== null ? e(fmt_nilai($r['nilai_pg'])) : '—' ?></div>
      <div class="stat-label">Nilai objektif</div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card"><div class="card-body py-3">
      <div class="stat-value"><?= $r['nilai_uraian'] !== null ? e(fmt_nilai($r['nilai_uraian'])) : '—' ?></div>
      <div class="stat-label">Nilai esai</div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card"><div class="card-body py-3">
      <div class="stat-value"><?= $r['nilai_akhir'] !== null ? e(fmt_nilai($r['nilai_akhir'])) : '—' ?></div>
      <div class="stat-label">Nilai akhir · <?= e($r['status']) ?></div>
    </div></div>
  </div>
</div>

<?php if (!$butirUraian): ?>
  <div class="card"><div class="card-body kosong">
    <div class="ic">📝</div>
    <h2 class="h6 mt-2">Ujian ini tidak memiliki soal esai</h2>
    <p class="mb-0">Nilai objektif sudah dihitung otomatis.</p>
  </div></div>
<?php else: ?>
  <form method="post" action="<?= e(url('koreksi_simpan')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="id_soal" value="<?= (int) $soal['id'] ?>">
    <input type="hidden" name="kembali" value="nilai">

    <?php foreach ($butirUraian as $b):
        $nomor = (int) $b['nomor_soal'];
        $j = $jawaban[$nomor] ?? null;
        $teks = '';
        if ($j) {
            $decoded = json_decode((string) $j['jawaban'], true);
            if (is_array($decoded)) {
                $teks = implode(', ', array_map(static fn ($x) => (string) $x, $decoded));
            } else {
                $teks = (string) $decoded;
            }
        }
        $poinMaks = (float) $b['poin_maks'];
        $nilaiLama = ($j && $j['skor'] !== null && $poinMaks > 0) ? round((float) $j['skor'] / $poinMaks * 100, 2) : '';
    ?>
      <div class="card mb-3">
        <div class="card-header d-flex align-items-center gap-2">
          <span class="badge text-bg-dark">Soal <?= $nomor ?></span>
          <span class="badge badge-soft">Uraian</span>
          <span class="ms-auto text-muted" style="font-size:.78rem">Poin maksimal <?= e(fmt_nilai($poinMaks, 0)) ?></span>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-lg-7">
              <div class="form-label">Pertanyaan</div>
              <div class="sticky-top" style="font-size:.9rem;white-space:pre-wrap;background:#F7FAFA;border:1px solid var(--line);border-radius:10px;padding:.85rem;max-height:340px;overflow:auto">
                <?= e($b['pertanyaan'] ?: 'Soal tersedia pada dokumen PDF.') ?>
              </div>
              <?php if (!empty($b['rubrik'])): ?>
                <div class="mt-2">
                  <div class="form-label">Rubrik</div>
                  <div class="text-muted" style="font-size:.85rem;white-space:pre-wrap"><?= e($b['rubrik']) ?></div>
                </div>
              <?php endif; ?>
            </div>
            <div class="col-lg-5">
              <div class="form-label">Jawaban Siswa</div>
              <div style="font-size:.9rem;white-space:pre-wrap;background:#fff;border:1px solid var(--line);border-radius:10px;padding:.85rem;min-height:120px;max-height:340px;overflow:auto"><?= $teks !== '' ? e($teks) : '<span class="text-muted">(tidak dijawab)</span>' ?></div>

              <?php if ($j): ?>
                <div class="row g-2 mt-2 align-items-end">
                  <div class="col-5">
                    <label class="form-label">Nilai (0–100)</label>
                    <input class="form-control" type="number" min="0" max="100" step="0.5"
                           name="skor[<?= (int) $j['id'] ?>]" value="<?= e((string) $nilaiLama) ?>">
                  </div>
                  <div class="col-7">
                    <label class="form-label">Catatan</label>
                    <input class="form-control" name="catatan[<?= (int) $j['id'] ?>]" value="<?= e($j['catatan'] ?? '') ?>" placeholder="Opsional">
                  </div>
                </div>
              <?php else: ?>
                <div class="form-text mt-2">Siswa tidak menjawab soal ini.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="d-flex gap-2 mb-5">
      <button class="btn btn-teal px-4" type="submit">Simpan Koreksi</button>
      <a class="btn btn-ghost" href="<?= e(url('nilai', ['id_soal' => $soal['id']])) ?>">Batal</a>
    </div>
  </form>
<?php endif; ?>
