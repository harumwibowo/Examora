<?php
/** @var array $daftar @var array|null $soal @var array $kelasList @var array $rows @var int $idKelas @var array|null $stat */
$kkm = (float) setting('kkm', '75');
?>
<?php if ($soal === null): ?>
  <div class="page-head">
    <div>
      <h1>Hasil Ujian</h1>
      <p>Pilih ujian untuk melihat rekap nilai, mengoreksi esai, dan mengekspor data.</p>
    </div>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th style="width:56px">No</th><th>Ujian</th><th>Token</th><th>Status</th><th class="text-end">Peserta selesai</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
        <?php if (!$daftar): ?>
          <tr><td colspan="6" class="text-center text-muted py-5">Belum ada ujian. <a href="<?= e(url('soal_form')) ?>">Buat ujian terlebih dahulu</a>.</td></tr>
        <?php endif; ?>
        <?php foreach ($daftar as $i => $s): ?>
          <tr>
            <td class="text-muted"><?= $i + 1 ?></td>
            <td>
              <div class="fw-semibold"><?= e($s['nama_soal']) ?></div>
              <div class="text-muted" style="font-size:.76rem"><?= e($s['mapel'] ?: 'Tanpa mapel') ?> · <?= e($s['guru_nama']) ?></div>
            </td>
            <td><code><?= e($s['kode_soal']) ?></code></td>
            <td><?= status_badge($s['status']) ?></td>
            <td class="text-end">
              <?= (int) DB::scalar("SELECT COUNT(*) FROM riwayat_ujian WHERE id_soal = ? AND waktu_selesai IS NOT NULL", [$s['id']]) ?>
              <?php $jmlTerkunci = (int) DB::scalar("SELECT COUNT(*) FROM riwayat_ujian WHERE id_soal = ? AND terkunci = 1 AND waktu_selesai IS NULL", [$s['id']]); ?>
              <?php if ($jmlTerkunci > 0): ?>
                <div><span class="badge text-bg-danger"><?= $jmlTerkunci ?> terkunci</span></div>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <a class="btn btn-sm btn-teal" href="<?= e(url('nilai', ['id_soal' => $s['id']])) ?>">Buka Rekap</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php else: ?>
  <div class="page-head">
    <div>
      <h1><?= e($soal['nama_soal']) ?></h1>
      <p>Rekap nilai peserta · token <code><?= e($soal['kode_soal']) ?></code></p>
    </div>
    <div class="actions">
      <a class="btn btn-teal" href="<?= e(url('ekspor', ['id_soal' => $soal['id'], 'id_kelas' => $idKelas])) ?>">Export Excel</a>
      <a class="btn btn-ghost" href="<?= e(url('koreksi_massal', ['id_soal' => $soal['id']])) ?>">Koreksi Esai</a>
      <form method="post" action="<?= e(url('regrade')) ?>" class="m-0" data-confirm="Hitung ulang nilai seluruh peserta ujian ini?">
        <?= Csrf::field() ?>
        <input type="hidden" name="id_soal" value="<?= (int) $soal['id'] ?>">
        <button class="btn btn-ghost" type="submit">Re-grade</button>
      </form>
      <a class="btn btn-ghost" href="<?= e(url('nilai')) ?>">Pilih Ujian Lain</a>
    </div>
  </div>

  <?php if ($stat): ?>
    <div class="row g-3 mb-3">
      <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body py-3">
          <div class="stat-value"><?= e(fmt_nilai($stat['rata'])) ?></div>
          <div class="stat-label">Rata-rata</div>
        </div></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body py-3">
          <div class="stat-value"><?= e(fmt_nilai($stat['min'])) ?> – <?= e(fmt_nilai($stat['max'])) ?></div>
          <div class="stat-label">Nilai terendah – tertinggi</div>
        </div></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body py-3">
          <div class="stat-value"><?= e(fmt_nilai($stat['median'])) ?></div>
          <div class="stat-label">Median</div>
        </div></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body py-3">
          <div class="stat-value"><?= (int) $stat['tuntas'] ?>/<?= (int) $stat['jumlah'] ?></div>
          <div class="stat-label">Tuntas (KKM <?= e(fmt_nilai($kkm, 0)) ?>)</div>
        </div></div>
      </div>
    </div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-body py-2">
      <form method="get" action="<?= e(url('nilai')) ?>" class="row g-2 align-items-center">
        <input type="hidden" name="r" value="nilai">
        <input type="hidden" name="id_soal" value="<?= (int) $soal['id'] ?>">
        <div class="col-md-4">
          <select class="form-select form-select-sm" name="id_kelas">
            <option value="0">Semua kelas peserta</option>
            <?php foreach ($kelasList as $k): ?>
              <option value="<?= (int) $k['id'] ?>" <?= (int) $idKelas === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['nama_kelas']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button class="btn btn-teal btn-sm">Filter</button>
          <?php if ($idKelas > 0): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('nilai', ['id_soal' => $soal['id']])) ?>">Reset</a>
          <?php endif; ?>
        </div>
        <div class="col-md-5 text-md-end text-muted" style="font-size:.82rem">
          <?= count($rows) ?> peserta terdaftar · <?= e(komposisi_label($soal)) ?>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:56px">No</th><th>Nama Siswa</th><th>Kelas</th><th class="text-center">Percobaan</th>
            <th class="text-center">Nilai PG</th><th class="text-center">Nilai Esai</th><th class="text-center">Nilai Akhir</th>
            <th class="text-center">Status</th><th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada peserta pada filter ini.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $i => $row):
            $sw = $row['siswa'];
            $best = $row['best'];
            $aktif = $row['aktif'];
        ?>
          <tr>
            <td class="text-muted"><?= $i + 1 ?></td>
            <td>
              <div class="fw-semibold"><?= e($sw['nama_siswa']) ?></div>
              <div class="text-muted" style="font-size:.74rem"><code><?= e($sw['username']) ?></code></div>
            </td>
            <td><?= e($sw['nama_kelas']) ?></td>
            <td class="text-center"><?= (int) $row['percobaan'] ?></td>
            <td class="text-center"><?= $best && $best['nilai_pg'] !== null ? e(fmt_nilai($best['nilai_pg'])) : '—' ?></td>
            <td class="text-center"><?= $best && $best['nilai_uraian'] !== null ? e(fmt_nilai($best['nilai_uraian'])) : '—' ?></td>
            <td class="text-center fw-bold" style="color:var(--teal-dark)"><?= $best && $best['nilai_akhir'] !== null ? e(fmt_nilai($best['nilai_akhir'])) : '—' ?></td>
            <td class="text-center">
              <?php if ($aktif): ?>
                <?php if ((int) ($aktif['terkunci'] ?? 0) === 1): ?>
                  <span class="badge text-bg-danger">Terkunci</span>
                <?php else: ?>
                  <span class="badge text-bg-warning">Berlangsung</span>
                <?php endif; ?>
              <?php elseif ($best): ?>
                <?= status_badge($best['status']) ?>
              <?php else: ?>
                <span class="badge text-bg-secondary">Belum mengerjakan</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <?php if ($aktif && (int) ($aktif['terkunci'] ?? 0) === 1): ?>
                <form method="post" action="<?= e(url('nilai_buka_kunci')) ?>" class="m-0 d-inline-block"
                      data-confirm="Buka kunci ujian <?= e($sw['nama_siswa']) ?>? Pastikan siswa sudah diarahkan kembali ke ujian."
                      data-confirm-title="Buka kunci ujian?"
                      data-confirm-ok="Ya, buka kunci">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="id" value="<?= (int) $aktif['id'] ?>">
                  <button class="btn btn-sm btn-teal" type="submit">Buka Kunci</button>
                </form>
              <?php endif; ?>
              <?php if ($best): ?>
                <div class="d-inline-flex gap-1">
                  <a class="btn btn-sm btn-ghost" href="<?= e(url('koreksi', ['id' => $best['id']])) ?>">Koreksi</a>
                  <form method="post" action="<?= e(url('hasil_hapus')) ?>" class="m-0" data-confirm="Hapus hasil ujian <?= e($sw['nama_siswa']) ?>?">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int) $best['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                  </form>
                </div>
              <?php elseif (!$aktif): ?>
                <span class="text-muted" style="font-size:.78rem">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
