<?php /** @var array $rows */ ?>
<div class="page-head">
  <div>
    <h1>Data Soal</h1>
    <p>Buat ujian, atur bobot, lalu isi kunci jawaban. Semua soal ditampilkan dari dokumen PDF Google Drive.</p>
  </div>
  <div class="actions">
    <a class="btn btn-teal" href="<?= e(url('soal_form')) ?>">+ Ujian Baru</a>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr><th style="width:56px">No</th><th>Ujian</th><th>Token</th><th>Soal</th><th>Target</th><th>Durasi</th><th>Status</th><th class="text-end">Aksi</th></tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="text-center text-muted py-5">Belum ada ujian. Klik <strong>+ Ujian Baru</strong> untuk memulai.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $i => $s):
          $total = komposisi_total($s);
          $lengkap = (int) $s['jumlah_kunci'] >= $total && $total > 0;
      ?>
        <tr>
          <td class="text-muted"><?= $i + 1 ?></td>
          <td>
            <div class="fw-semibold"><?= e($s['nama_soal']) ?></div>
            <div class="text-muted" style="font-size:.75rem">
              <?= e($s['mapel'] ?: 'Tanpa mapel') ?> · <?= e($s['guru_nama']) ?>
              <?php if ((int) ($s['jumlah_kolaborator'] ?? 0) > 0): ?>
                · <span class="badge badge-soft" style="font-size:.62rem"><?= (int) $s['jumlah_kolaborator'] ?> kolaborator</span>
              <?php endif; ?>
            </div>
          </td>
          <td>
            <span role="button" tabindex="0" class="token-copy" data-copy="<?= e($s['kode_soal']) ?>"
                  data-copy-title="Token disalin" title="Klik untuk menyalin token"
                  aria-label="Salin token <?= e($s['kode_soal']) ?>"><code class="fw-bold"><?= e($s['kode_soal']) ?></code></span>
          </td>
          <td>
            <span class="<?= $lengkap ? 'text-success' : 'text-warning' ?>"><?= (int) $s['jumlah_kunci'] ?>/<?= $total ?></span>
            <div class="text-muted" style="font-size:.72rem"><?= e(komposisi_label($s)) ?></div>
          </td>
          <td>
            <?php $tm = $s['target_mode'] ?? 'kelas'; ?>
            <?php if ($tm === 'jenjang'): ?>
              <span class="badge badge-soft">Tingkat</span>
              <div class="fw-semibold" style="font-size:.8rem"><?= e($s['label_jenjang'] ?: '-') ?></div>
            <?php elseif ($tm === 'siswa'): ?>
              <span class="badge badge-soft">Siswa</span>
              <div class="fw-semibold" style="font-size:.8rem"><?= (int) $s['jumlah_siswa'] ?> siswa</div>
            <?php else: ?>
              <span class="badge badge-soft">Kelas</span>
              <div class="fw-semibold" style="font-size:.8rem"><?= (int) $s['jumlah_kelas'] ?> kelas</div>
            <?php endif; ?>
            <?php if (!empty($s['agama_target'])): ?>
              <div class="text-muted" style="font-size:.72rem">Khusus <?= e($s['agama_target']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= (int) $s['durasi'] ?> mnt</td>
          <td><?= status_badge($s['status']) ?></td>
          <td class="text-end">
            <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
              <a class="btn btn-sm btn-teal" href="<?= e(url('kunci', ['id' => $s['id']])) ?>">Kunci</a>
              <a class="btn btn-sm btn-ghost" href="<?= e(url('nilai', ['id_soal' => $s['id']])) ?>">Nilai</a>
              <a class="btn btn-sm btn-ghost" href="<?= e(url('soal_form', ['id' => $s['id']])) ?>">Ubah</a>
              <?php if (Auth::pemilikSoal($s)): ?>
                <a class="btn btn-sm btn-ghost" href="<?= e(url('soal_kolaborator', ['id' => $s['id']])) ?>"
                   title="Tambah guru sebagai kolaborator">Kolaborator</a>
              <?php endif; ?>
              <form method="post" action="<?= e(url('soal_duplikat')) ?>" class="m-0">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button class="btn btn-sm btn-ghost" type="submit">Duplikat</button>
              </form>
              <form method="post" action="<?= e(url('soal_toggle')) ?>" class="m-0">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button class="btn btn-sm <?= $s['status'] === 'Aktif' ? 'btn-outline-secondary' : 'btn-outline-success' ?>" type="submit">
                  <?= $s['status'] === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                </button>
              </form>
              <form method="post" action="<?= e(url('soal_hapus')) ?>" class="m-0" data-confirm="Hapus ujian ini beserta kunci dan seluruh hasil ujiannya?">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
