<?php
/** @var array $kelasList @var array|null $kelas @var array $ujian @var array $siswa @var array $matriks */
$kkm = (float) setting('kkm', '75');
?>
<?php if ($kelas === null): ?>
  <div class="page-head">
    <div>
      <h1>Hasil Kelas</h1>
      <p>Rekap nilai seluruh ujian per kelas dalam satu tampilan.</p>
    </div>
  </div>
  <div class="row g-3">
    <?php if (!$kelasList): ?>
      <div class="col-12"><div class="card"><div class="card-body kosong">Belum ada kelas. <a href="<?= e(url('kelas')) ?>">Buat kelas</a> terlebih dahulu.</div></div></div>
    <?php endif; ?>
    <?php foreach ($kelasList as $k): ?>
      <div class="col-md-6 col-xl-4">
        <a class="card h-100 text-reset" href="<?= e(url('nilai_kelas', ['id_kelas' => $k['id']])) ?>">
          <div class="card-body">
            <div class="d-flex align-items-center gap-2">
              <div class="flex-grow-1">
                <div class="fw-bold"><?= e($k['nama_kelas']) ?></div>
                <?php if ($k['tingkat'] !== '-'): ?>
                <div class="text-muted" style="font-size:.78rem">Tingkat <?= e($k['tingkat']) ?></div>
                <?php endif; ?>
              </div>
              <span class="badge badge-soft"><?= (int) DB::scalar('SELECT COUNT(*) FROM siswa WHERE id_kelas = ?', [$k['id']]) ?> siswa</span>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="page-head">
    <div>
      <h1>Rekap Kelas <?= e($kelas['nama_kelas']) ?></h1>
      <p><?= count($siswa) ?> siswa · <?= count($ujian) ?> ujian · KKM <?= e(fmt_nilai($kkm, 0)) ?></p>
    </div>
    <div class="actions">
      <a class="btn btn-ghost" href="<?= e(url('nilai_kelas')) ?>">Pilih Kelas Lain</a>
      <a class="btn btn-ghost" href="<?= e(url('siswa', ['id_kelas' => $kelas['id']])) ?>">Data Siswa</a>
    </div>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:56px">No</th>
            <th style="min-width:200px">Nama Siswa</th>
            <?php foreach ($ujian as $u): ?>
              <th class="text-center" title="<?= e($u['nama_soal']) ?>" style="min-width:110px"><?= e(mb_strimwidth($u['nama_soal'], 0, 22, '…')) ?></th>
            <?php endforeach; ?>
            <?php if (!$ujian): ?><th class="text-center">Nilai</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php if (!$siswa): ?>
          <tr><td colspan="<?= 3 + count($ujian) ?>" class="text-center text-muted py-4">Belum ada siswa pada kelas ini.</td></tr>
        <?php endif; ?>
        <?php foreach ($siswa as $i => $s): ?>
          <tr>
            <td class="text-muted"><?= $i + 1 ?></td>
            <td>
              <div class="fw-semibold"><?= e($s['nama_siswa']) ?></div>
              <div class="text-muted" style="font-size:.74rem"><code><?= e($s['username']) ?></code></div>
            </td>
            <?php foreach ($ujian as $u):
                $nilai = $matriks[(int) $s['id']][(int) $u['id']] ?? null;
                $cls = '';
                if ($nilai !== null) {
                    $cls = (float) $nilai >= $kkm ? 'text-success fw-semibold' : 'text-danger fw-semibold';
                }
            ?>
              <td class="text-center <?= $cls ?>"><?= $nilai !== null ? e(fmt_nilai($nilai)) : '—' ?></td>
            <?php endforeach; ?>
            <?php if (!$ujian): ?><td class="text-center text-muted">—</td><?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($ujian): ?>
    <div class="alert alert-light border mt-3" style="font-size:.82rem">
      <strong>Keterangan:</strong> hijau = mencapai KKM, merah = di bawah KKM, “—” = belum ada nilai akhir.
      Nilai yang ditampilkan adalah nilai terbaik tiap siswa pada setiap ujian.
    </div>
  <?php endif; ?>
<?php endif; ?>
