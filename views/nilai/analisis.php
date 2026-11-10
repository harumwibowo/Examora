<?php
/** @var array $daftar @var array|null $soal @var array $butir @var int $idSoal */
$labelTipe = [
    'PG' => 'Pilihan Ganda', 'PG_Kompleks' => 'PG Kompleks',
    'Benar_Salah' => 'Benar / Salah', 'Menjodohkan' => 'Menjodohkan',
    'Isian' => 'Isian Singkat', 'Uraian' => 'Uraian',
];
?>
<div class="page-head">
  <div>
    <h1>Analisis Butir Soal</h1>
    <p>Tingkat ketuntasan tiap nomor berdasarkan jawaban peserta yang sudah selesai.</p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="<?= e(url('nilai')) ?>">Kembali ke Hasil</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-2">
    <form method="get" action="<?= e(url('analisis')) ?>" class="row g-2 align-items-end">
      <input type="hidden" name="r" value="analisis">
      <div class="col-md-8">
        <label class="form-label">Pilih ujian</label>
        <select class="form-select form-select-sm" name="id_soal">
          <option value="0">— pilih ujian —</option>
          <?php foreach ($daftar as $s): ?>
            <option value="<?= (int) $s['id'] ?>" <?= (int) $idSoal === (int) $s['id'] ? 'selected' : '' ?>>
              <?= e($s['nama_soal']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <button class="btn btn-teal btn-sm">Tampilkan</button>
      </div>
    </form>
  </div>
</div>

<?php if ($soal === null): ?>
  <div class="card"><div class="card-body kosong">
    <div class="ic">📊</div>
    <p class="mb-0 mt-2">Pilih ujian untuk melihat analisis butir.</p>
  </div></div>
<?php elseif (!$butir): ?>
  <div class="card"><div class="card-body kosong">
    <p class="mb-0">Ujian ini belum memiliki kunci jawaban / butir soal.</p>
  </div></div>
<?php else: ?>
  <div class="card mb-3">
    <div class="card-body py-2 text-muted" style="font-size:.84rem">
      <strong><?= e($soal['nama_soal']) ?></strong> · token <code><?= e($soal['kode_soal']) ?></code> ·
      <?= (int) DB::scalar('SELECT COUNT(*) FROM riwayat_ujian WHERE id_soal = ? AND waktu_selesai IS NOT NULL', [$soal['id']]) ?> peserta selesai
    </div>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:64px">No</th><th>Tipe</th><th class="text-center">Peserta</th>
            <th class="text-center">Benar</th><th class="text-center">Salah</th><th class="text-center">Kosong</th>
            <th style="min-width:200px">Ketuntasan</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($butir as $b):
            $total = (int) $b['stat_total'];
            $benar = (int) $b['stat_benar'];
            $salah = (int) $b['stat_salah'];
            $kosong = (int) $b['stat_kosong'];
            $persen = (float) $b['persen'];
            $warna = $persen >= 70 ? 'bg-success' : ($persen >= 40 ? 'bg-warning' : 'bg-danger');
        ?>
          <tr>
            <td class="fw-bold"><?= (int) $b['nomor_soal'] ?></td>
            <td><span class="badge badge-soft"><?= e($labelTipe[$b['tipe_soal']] ?? $b['tipe_soal']) ?></span></td>
            <td class="text-center"><?= $total ?></td>
            <td class="text-center text-success fw-semibold"><?= $benar ?></td>
            <td class="text-center text-danger"><?= $salah ?></td>
            <td class="text-center text-muted"><?= $kosong ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="progress flex-grow-1" style="height:8px">
                  <div class="progress-bar <?= $warna ?>" role="progressbar" style="width:<?= e((string) $persen) ?>%"></div>
                </div>
                <span style="font-size:.8rem;min-width:48px;text-align:right"><?= e(fmt_nilai($persen, 1)) ?>%</span>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="alert alert-light border mt-3" style="font-size:.82rem">
    <strong>Catatan:</strong> untuk soal esai, angka “benar” dihitung dari jawaban yang sudah diberi skor &gt; 0.
    Persentase ketuntasan membantu mengenali butir yang perlu diperbaiki.
  </div>
<?php endif; ?>
