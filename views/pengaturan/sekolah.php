<?php
/** @var array $nilai */
$map = [];
foreach ($nilai as $row) {
    $map[$row['kunci']] = (string) $row['nilai'];
}
$get = static fn (string $k, string $d = '') => $map[$k] ?? $d;
$logo = $get('logo_sekolah');
$browserTerpilih = browser_siswa_allowed_list();
$browserTeks = browser_list_text($browserTerpilih);
$browserSaatIni = browser_family();
$kaw = pengaturan_pengawasan();
$maksLogin = maks_login_siswa();
$uaSaatIni = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
?>
<div class="page-head">
  <div>
    <h1>Pengaturan Sekolah</h1>
    <p>Identitas sekolah tampil pada halaman login, dashboard, dan kartu ujian.</p>
  </div>
</div>

<form method="post" action="<?= e(url('pengaturan_simpan')) ?>" enctype="multipart/form-data" class="row g-3" style="max-width:900px">
  <?= Csrf::field() ?>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">Identitas Sekolah</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Nama Sekolah / Lembaga</label>
            <input class="form-control" name="nama_sekolah" value="<?= e($get('nama_sekolah', APP_NAME)) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">NPSN</label>
            <input class="form-control" name="npsn" value="<?= e($get('npsn')) ?>" placeholder="Opsional">
          </div>
          <div class="col-md-6">
            <label class="form-label">Tahun Ajaran</label>
            <input class="form-control" name="tahun_ajaran" value="<?= e($get('tahun_ajaran')) ?>" placeholder="2025/2026">
          </div>
          <div class="col-12">
            <label class="form-label">Alamat</label>
            <textarea class="form-control" name="alamat" rows="2" placeholder="Alamat lengkap sekolah"><?= e($get('alamat')) ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Jenjang Pendidikan</label>
            <select class="form-select" name="jenjang">
              <?php foreach (jenjang_options() as $k => $label): ?>
                <option value="<?= e($k) ?>" <?= strtoupper($get('jenjang', 'SMA')) === $k ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Menentukan pilihan tingkat pada Data Kelas.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">KKM</label>
            <input class="form-control" type="number" step="0.01" min="0" max="100" name="kkm" value="<?= e($get('kkm', '75')) ?>">
            <div class="form-text">Dipakai untuk penanda tuntas / belum tuntas.</div>
          </div>
          <div class="col-12">
            <label class="form-label">Pengumuman untuk Siswa</label>
            <textarea class="form-control" name="pengumuman" rows="3"><?= e($get('pengumuman')) ?></textarea>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Logo Sekolah</div>
      <div class="card-body text-center">
        <div class="mb-3" style="width:110px;height:110px;margin:0 auto;border:1px dashed var(--line);border-radius:14px;display:grid;place-items:center;overflow:hidden;background:#F7FAFA">
          <?php if ($logo): ?>
            <img src="<?= e(asset('uploads/' . $logo)) ?>" alt="Logo" style="max-width:100%;max-height:100%;object-fit:contain">
          <?php else: ?>
            <span class="text-muted" style="font-size:.8rem">Belum ada logo</span>
          <?php endif; ?>
        </div>
        <input class="form-control form-control-sm" type="file" name="logo" accept="image/png,image/jpeg,image/gif,image/webp">
        <div class="form-text">PNG, JPG, GIF, atau WEBP. Maksimal 2 MB.</div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex align-items-center gap-2">
        <span>Pembatasan Browser Siswa</span>
        <span class="badge badge-soft ms-auto">Opsional</span>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3" style="font-size:.86rem">
          Isi dengan nama browser yang diizinkan, dipisahkan koma. Contoh:
          <code>chrome, SEB</code> berarti siswa hanya boleh login dari Google Chrome
          dan Safe Exam Browser. Jika dikosongkan, siswa boleh login dari browser apa saja.
          Pengaturan ini tidak berlaku untuk akun guru/superadmin.
        </p>
        <label class="form-label" for="browser-siswa-teks">Browser yang diizinkan</label>
        <input class="form-control" type="text" name="browser_siswa_teks" id="browser-siswa-teks"
               value="<?= e($browserTeks) ?>" placeholder="chrome, SEB" autocomplete="off"
               list="browser-siswa-pilihan">
        <datalist id="browser-siswa-pilihan">
          <?php foreach (browser_short_names() as $key => $short): ?>
            <option value="<?= e($short) ?>"><?= e(browser_label($key)) ?></option>
          <?php endforeach; ?>
        </datalist>
        <div class="form-text mb-0">
          Nama yang dikenali:
          <?php
          $daftarNama = [];
          foreach (browser_short_names() as $key => $short) {
              $daftarNama[] = $short . ' (' . browser_label($key) . ')';
          }
          echo e(implode(', ', $daftarNama));
          ?>
        </div>
        <div class="alert alert-info mt-3 mb-0" style="font-size:.8rem">
          Browser yang terdeteksi saat ini:
          <strong><?= e(browser_label($browserSaatIni)) ?></strong>.
          Deteksi memakai User-Agent; browser yang menyamarkan diri sebagai Chrome akan terbaca sebagai Chrome.
          <?php if ($uaSaatIni !== ''): ?>
            <div class="text-muted mt-1" style="word-break:break-all;font-size:.72rem"><?= e($uaSaatIni) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex align-items-center gap-2">
        <span>Pengawasan Ujian (Anti-Curang)</span>
        <span class="badge badge-soft ms-auto">Berlaku untuk semua ujian</span>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3" style="font-size:.86rem">
          Pengaturan ini dipusatkan di sini agar hanya admin yang menentukan aturan
          pengawasan. Guru tidak lagi mengaturnya pada form tambah/edit ujian.
        </p>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label" for="deteksi_tab">Deteksi Pindah Tab</label>
            <select class="form-select" name="deteksi_tab" id="deteksi_tab">
              <option value="1" <?= $kaw['deteksi_tab'] === 1 ? 'selected' : '' ?>>Aktif</option>
              <option value="0" <?= $kaw['deteksi_tab'] === 0 ? 'selected' : '' ?>>Nonaktif</option>
            </select>
            <div class="form-text">Pindah tab/jendela saat ujian dicatat sebagai pelanggaran.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="maks_pelanggaran">Maks. Pelanggaran</label>
            <input class="form-control" type="number" name="maks_pelanggaran" id="maks_pelanggaran"
                   min="1" max="20" value="<?= (int) $kaw['maks_pelanggaran'] ?>" required>
            <div class="form-text">Jumlah pelanggaran sebelum aksi dijalankan (1–20).</div>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="aksi_pelanggaran">Aksi Saat Batas Tercapai</label>
            <select class="form-select" name="aksi_pelanggaran" id="aksi_pelanggaran">
              <option value="peringatan" <?= $kaw['aksi_pelanggaran'] === 'peringatan' ? 'selected' : '' ?>>Peringatan</option>
              <option value="kunci" <?= $kaw['aksi_pelanggaran'] === 'kunci' ? 'selected' : '' ?>>Kunci layar</option>
              <option value="akhiri" <?= $kaw['aksi_pelanggaran'] === 'akhiri' ? 'selected' : '' ?>>Akhiri ujian</option>
            </select>
            <div class="form-text">Aksi otomatis ketika batas pelanggaran tercapai.</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex align-items-center gap-2">
        <span>Batas Login Siswa</span>
        <span class="badge badge-soft ms-auto">Perangkat bersamaan</span>
      </div>
      <div class="card-body">
        <div class="row g-3 align-items-start">
          <div class="col-md-4">
            <label class="form-label" for="maks_login_siswa">Maks. Perangkat Login</label>
            <input class="form-control" type="number" name="maks_login_siswa" id="maks_login_siswa"
                   min="1" max="20" value="<?= (int) $maksLogin ?>" required>
            <div class="form-text">1 = hanya satu perangkat (perilaku paling aman).</div>
          </div>
          <div class="col-md-8">
            <p class="text-muted mb-2" style="font-size:.86rem">
              Siswa hanya boleh login pada sejumlah perangkat yang ditentukan. Jika batas
              tercapai, login baru ditolak sampai siswa logout dari perangkat lain atau
              sesinya direset lewat menu
              <a href="<?= e(url('login_siswa')) ?>">Reset Login Siswa</a>.
            </p>
            <div class="alert alert-info mb-0" style="font-size:.8rem">
              Sesi dianggap berakhir bila siswa logout, browser lama tidak aktif (2 jam),
              atau sesi PHP kedaluwarsa.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-body d-flex flex-wrap justify-content-end gap-2">
        <a class="btn btn-ghost" href="<?= e(url('dashboard')) ?>">Batal</a>
        <button class="btn btn-teal px-4" type="submit">Simpan Pengaturan</button>
      </div>
    </div>
  </div>
</form>
