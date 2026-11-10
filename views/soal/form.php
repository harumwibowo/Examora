<?php
/** @var array|null $row @var array $kelas @var array $terpilih @var int $jumlahButir */
$edit = $row !== null;
$v = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);

// Komposisi soal: baris lama (setelah gagal validasi) → blok tersimpan → default.
$komposisiSeed = [];
$oldTipe = old_array('komposisi_tipe');
if ($oldTipe !== []) {
    $oldDari = old_array('komposisi_dari');
    $oldSampai = old_array('komposisi_sampai');
    $oldBobot = old_array('komposisi_bobot');
    foreach ($oldTipe as $i => $t) {
        $komposisiSeed[] = [
            'tipe' => (string) $t,
            'dari' => $oldDari[$i] ?? '',
            'sampai' => $oldSampai[$i] ?? '',
            'bobot' => $oldBobot[$i] ?? '',
        ];
    }
} elseif ($row !== null) {
    $komposisiSeed = komposisi_efektif($row);
}
if ($komposisiSeed === []) {
    $komposisiSeed = [['tipe' => 'PG', 'dari' => 1, 'sampai' => 30, 'bobot' => 100]];
}
$tipeOpsi = tipe_soal_options();
?>
<div class="page-head">
  <div>
    <h1><?= $edit ? 'Ubah Ujian' : 'Ujian Baru' ?></h1>
    <p>Token ujian dibagikan kepada siswa saat akan mulai. Kunci jawaban diisi pada halaman berikutnya.</p>
  </div>
  <div class="actions"><a class="btn btn-ghost" href="<?= e(url('soal')) ?>">Kembali</a></div>
</div>

<form method="post" action="<?= e(url('soal_simpan')) ?>" class="row g-3" id="form-soal">
  <?= Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">Informasi Ujian</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Mata Pelajaran</label>
            <input class="form-control" name="mapel" value="<?= e($v('mapel')) ?>" placeholder="Informatika">
            <div class="form-text">
              Judul ujian otomatis: <em>Ujian &lt;mata pelajaran&gt;</em>. Kosongkan bila belum ditentukan.
            </div>
          </div>
          <div class="col-12" id="wrap-link">
            <label class="form-label" for="link_pdf">Tautan / ID File Google Drive <span class="text-danger">*</span></label>
            <input class="form-control" type="text" name="link_pdf" id="link_pdf"
                   value="<?= e($v('link_pdf')) ?>"
                   placeholder="https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz/view" required
                   autocomplete="off" spellcheck="false" style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace">
            <div class="form-text">
              Di Google Drive: klik kanan berkas PDF → <strong>Bagikan</strong> →
              "Siapa saja yang memiliki link" (Viewer) → <strong>Salin tautan</strong>, lalu tempel utuh di sini
              (berakhiran <code>.../file/d/<strong>ID</strong>/view</code>).
              Tautan yang memuat <code>resourcekey=...</code> harus dipertahankan — berkas lama tidak bisa dibuka tanpanya.
              Aplikasi menyajikan sendiri berkas PDF-nya, jadi siswa hanya melihat dokumen
              (tanpa halaman Google Drive).
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Durasi (menit)</label>
            <input class="form-control" type="number" name="durasi" id="durasi" min="1" value="<?= e($v('durasi', '60')) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Min. Pengerjaan (menit)</label>
            <input class="form-control" type="number" name="min_pengerjaan" id="min_pengerjaan" min="0" value="<?= e($v('min_pengerjaan', '0')) ?>">
            <div class="form-text" id="min-hint">Tombol kumpulkan terkunci sampai durasi minimal ini terpenuhi. 0 = tanpa batas minimal.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Token Ujian</label>
            <input class="form-control" value="<?= e($row['kode_soal'] ?? 'Otomatis') ?>" disabled>
            <div class="form-text">Dibuat otomatis 6 karakter.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header d-flex align-items-center gap-2 flex-wrap">
        <span>Komposisi Soal &amp; Bobot</span>
        <span class="badge text-bg-secondary ms-auto" id="komposisi-ringkas">—</span>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3" style="font-size:.82rem">
          Tentukan jenis soal untuk setiap rentang nomor urut, misal nomor <strong>1–30</strong> pilihan ganda,
          <strong>31–35</strong> isian singkat, dan <strong>36–40</strong> esai. Tambah atau kurangi baris sesuai kebutuhan.
        </p>

        <div class="table-responsive">
          <table class="table align-middle mb-2" id="komposisi-table">
            <thead>
              <tr>
                <th style="min-width:190px">Jenis Soal</th>
                <th style="width:110px">Nomor Awal</th>
                <th style="width:110px">Nomor Akhir</th>
                <th style="width:90px" class="text-end">Jumlah</th>
                <th style="width:150px">Bobot (%)</th>
                <th style="width:44px"></th>
              </tr>
            </thead>
            <tbody id="komposisi-body">
              <?php foreach ($komposisiSeed as $b): ?>
                <tr class="komposisi-row">
                  <td>
                    <select class="form-select form-select-sm" name="komposisi_tipe[]" data-f="tipe">
                      <?php foreach ($tipeOpsi as $key => $lbl): ?>
                        <option value="<?= e($key) ?>" <?= ($b['tipe'] ?? '') === $key ? 'selected' : '' ?>><?= e($lbl) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td><input class="form-control form-control-sm" type="number" min="1" name="komposisi_dari[]" data-f="dari" value="<?= e((string) ($b['dari'] ?? '')) ?>"></td>
                  <td><input class="form-control form-control-sm" type="number" min="1" name="komposisi_sampai[]" data-f="sampai" value="<?= e((string) ($b['sampai'] ?? '')) ?>"></td>
                  <td class="text-end"><span class="badge badge-soft" data-jumlah>—</span></td>
                  <td>
                    <div class="d-flex align-items-center gap-1">
                      <input class="form-control form-control-sm bobot-input" type="number" min="0" max="100" step="0.01" name="komposisi_bobot[]" data-f="bobot" value="<?= e((string) ($b['bobot'] ?? '')) ?>">
                      <span class="text-muted" style="font-size:.8rem">%</span>
                    </div>
                  </td>
                  <td class="text-end">
                    <button type="button" class="btn btn-sm btn-ghost" data-hapus-row title="Hapus jenis soal" aria-label="Hapus jenis soal">✕</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center">
          <button type="button" class="btn btn-sm btn-ghost" id="komposisi-tambah">+ Tambah jenis soal</button>
          <button type="button" class="btn btn-sm btn-ghost" id="komposisi-rapikan">Rapikan nomor urut</button>
          <span class="ms-auto"><span class="badge text-bg-secondary" id="komposisi-total-bobot">Bobot 0%</span></span>
        </div>

        <div id="komposisi-error" class="form-text text-danger" aria-live="polite"></div>

        <hr>
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label" for="opsi_pg">Bentuk Opsi PG</label>
            <select class="form-select" name="opsi_pg" id="opsi_pg">
              <?php foreach ([3, 4, 5] as $o): ?>
                <option value="<?= $o ?>" <?= (int) $v('opsi_pg', 4) === $o ? 'selected' : '' ?>><?= $o ?> opsi (A–<?= chr(64 + $o) ?>)</option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Berlaku untuk soal Pilihan Ganda dan PG Kompleks.</div>
          </div>
          <div class="col-md-8">
            <div class="form-text mb-0">
              Bobot menentukan kontribusi nilai tiap jenis soal terhadap skor akhir. Total seluruh bobot harus 100%.
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header">Setelan Ujian</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Kesempatan</label>
            <select class="form-select" name="kesempatan">
              <option value="Sekali Saja" <?= $v('kesempatan', 'Sekali Saja') === 'Sekali Saja' ? 'selected' : '' ?>>Sekali Saja</option>
              <option value="Dapat Diulang" <?= $v('kesempatan') === 'Dapat Diulang' ? 'selected' : '' ?>>Dapat Diulang</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Tampilkan Nilai?</label>
            <select class="form-select" name="tampil_nilai">
              <option value="Tidak" <?= $v('tampil_nilai', 'Tidak') === 'Tidak' ? 'selected' : '' ?>>Tidak</option>
              <option value="Ya" <?= $v('tampil_nilai') === 'Ya' ? 'selected' : '' ?>>Ya</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
              <option value="Draft" <?= $v('status', 'Draft') === 'Draft' ? 'selected' : '' ?>>Draft</option>
              <option value="Aktif" <?= $v('status') === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
              <option value="Arsip" <?= $v('status') === 'Arsip' ? 'selected' : '' ?>>Arsip</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Mulai (opsional)</label>
            <input class="form-control" type="datetime-local" name="mulai_at" value="<?= e(!empty($row['mulai_at']) ? date('Y-m-d\TH:i', strtotime($row['mulai_at'])) : '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Selesai (opsional)</label>
            <input class="form-control" type="datetime-local" name="selesai_at" value="<?= e(!empty($row['selesai_at']) ? date('Y-m-d\TH:i', strtotime($row['selesai_at'])) : '') ?>">
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Target Peserta</div>
      <div class="card-body">
        <?php
        $targetMode = (string) $v('target_mode', 'kelas');
        if (!in_array($targetMode, ['kelas', 'jenjang', 'siswa'], true)) {
            $targetMode = 'kelas';
        }
        $jumlahKelasPerJenjang = [];
        foreach ($kelas as $k) {
            $t = trim((string) $k['tingkat']);
            if ($t !== '') {
                $jumlahKelasPerJenjang[$t] = ($jumlahKelasPerJenjang[$t] ?? 0) + 1;
            }
        }
        ?>
        <div class="d-flex flex-wrap gap-2 mb-2 target-mode">
          <input type="radio" class="btn-check" name="target_mode" id="target_jenjang" value="jenjang" <?= $targetMode === 'jenjang' ? 'checked' : '' ?>>
          <label class="btn btn-sm btn-outline-teal" for="target_jenjang">Per Tingkat</label>
          <input type="radio" class="btn-check" name="target_mode" id="target_kelas" value="kelas" <?= $targetMode === 'kelas' ? 'checked' : '' ?>>
          <label class="btn btn-sm btn-outline-teal" for="target_kelas">Per Kelas</label>
          <input type="radio" class="btn-check" name="target_mode" id="target_siswa" value="siswa" <?= $targetMode === 'siswa' ? 'checked' : '' ?>>
          <label class="btn btn-sm btn-outline-teal" for="target_siswa">Siswa Spesifik</label>
        </div>
        <div class="form-text mb-2">Pilih cakupan peserta ujian. Target agama di bawah tetap berlaku untuk semua mode.</div>

        <div data-target-panel="kelas" <?= $targetMode !== 'kelas' ? 'style="display:none"' : '' ?>>
          <?php if (!$kelas): ?>
            <p class="text-muted mb-0">Belum ada kelas. <a href="<?= e(url('kelas')) ?>">Buat kelas</a> terlebih dahulu.</p>
          <?php endif; ?>
          <div style="max-height:300px;overflow:auto">
            <?php foreach ($kelas as $k): ?>
              <div class="form-check mb-2">
                <input class="form-check-input target-kelas" type="checkbox" name="id_kelas[]" value="<?= (int) $k['id'] ?>" id="k<?= (int) $k['id'] ?>"
                  <?= in_array((int) $k['id'], $terpilih, true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="k<?= (int) $k['id'] ?>"><?= e($k['nama_kelas']) ?> <span class="text-muted">· Tingkat <?= e($k['tingkat'] !== '' ? $k['tingkat'] : '-') ?></span></label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div data-target-panel="jenjang" <?= $targetMode !== 'jenjang' ? 'style="display:none"' : '' ?>>
          <div style="max-height:300px;overflow:auto">
            <?php foreach ($jenjangList as $t): ?>
              <div class="form-check mb-2">
                <input class="form-check-input target-jenjang" type="checkbox" name="target_jenjang[]" value="<?= e($t) ?>" id="j-<?= e($t) ?>"
                  <?= in_array($t, $terpilihJenjang, true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="j-<?= e($t) ?>">Tingkat <?= e($t) ?> <span class="text-muted">· <?= (int) ($jumlahKelasPerJenjang[$t] ?? 0) ?> kelas</span></label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div data-target-panel="siswa" <?= $targetMode !== 'siswa' ? 'style="display:none"' : '' ?>>
          <input type="search" class="form-control form-control-sm mb-2" id="cari-siswa" placeholder="Cari nama atau kelas..." autocomplete="off">
          <div style="max-height:300px;overflow:auto" id="siswa-list">
            <?php foreach ($siswaList as $sw): ?>
              <label class="form-check mb-1 siswa-item" data-cari="<?= e(mb_strtolower($sw['nama_siswa'] . ' ' . $sw['nama_kelas'])) ?>">
                <input class="form-check-input target-siswa" type="checkbox" name="id_siswa[]" value="<?= (int) $sw['id'] ?>"
                  <?= in_array((int) $sw['id'], $terpilihSiswa, true) ? 'checked' : '' ?>>
                <span class="form-check-label"><?= e($sw['nama_siswa']) ?> <span class="text-muted">· <?= e($sw['nama_kelas']) ?></span></span>
              </label>
            <?php endforeach; ?>
            <?php if (!$siswaList): ?>
              <p class="text-muted mb-0">Belum ada siswa aktif.</p>
            <?php endif; ?>
          </div>
        </div>

        <hr>
        <label class="form-label" for="agama_target">Target Agama <span class="text-muted fw-normal">(opsional)</span></label>
        <?php
        $opsiAgama = agama_options();
        $agamaTerpilih = trim((string) $v('agama_target', ''));
        $opsiBawah = array_map(static fn ($a) => mb_strtolower($a), $opsiAgama);
        if ($agamaTerpilih !== '' && !in_array(mb_strtolower($agamaTerpilih), $opsiBawah, true)) {
            $opsiAgama[] = $agamaTerpilih;
        }
        ?>
        <select class="form-select" name="agama_target" id="agama_target">
          <option value="">Semua agama — tanpa filter</option>
          <?php foreach ($opsiAgama as $a): ?>
            <option value="<?= e($a) ?>" <?= agama_sama($a, $agamaTerpilih) ? 'selected' : '' ?>><?= e($a) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Jika dipilih, hanya siswa beragama tersebut pada target peserta di atas yang bisa melihat dan mengerjakan ujian ini. Siswa tanpa data agama tidak akan melihat ujian bertarget.</div>
        <div class="form-text fw-semibold mt-1" id="agama-count" aria-live="polite"></div>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-body d-grid gap-2">
        <button class="btn btn-teal py-2" type="submit"><?= $edit ? 'Simpan Perubahan' : 'Buat Ujian & Lanjut ke Kunci' ?></button>
        <a class="btn btn-ghost" href="<?= e(url('soal')) ?>">Batal</a>
      </div>
    </div>
  </div>
</form>

<script>
(function () {
  // ----------------------------------------------------------------
  // Komposisi soal: baris jenis soal + rentang nomor urut + bobot.
  // ----------------------------------------------------------------
  var body = document.getElementById('komposisi-body');
  var btnTambah = document.getElementById('komposisi-tambah');
  var btnRapikan = document.getElementById('komposisi-rapikan');
  var totalBobotEl = document.getElementById('komposisi-total-bobot');
  var ringkasEl = document.getElementById('komposisi-ringkas');
  var errorEl = document.getElementById('komposisi-error');
  var LABEL_TIPE = <?= json_encode($tipeOpsi, JSON_UNESCAPED_UNICODE) ?>;

  function baris() {
    return Array.prototype.slice.call(body.querySelectorAll('tr.komposisi-row'));
  }

  function bacaRow(tr) {
    var el = function (f) { return tr.querySelector('[data-f="' + f + '"]'); };
    return {
      tr: tr,
      tipe: el('tipe').value,
      dari: parseInt(el('dari').value || '0', 10) || 0,
      sampai: parseInt(el('sampai').value || '0', 10) || 0,
      bobot: parseFloat(el('bobot').value || '0') || 0
    };
  }

  function syncKomposisi() {
    var data = baris().map(bacaRow);
    var totalSoal = 0;
    var totalBobot = 0;

    data.forEach(function (r) {
      var n = (r.dari >= 1 && r.sampai >= r.dari) ? (r.sampai - r.dari + 1) : 0;
      var badge = r.tr.querySelector('[data-jumlah]');
      badge.textContent = n > 0 ? String(n) : '—';
      totalSoal = Math.max(totalSoal, r.sampai > 0 ? r.sampai : 0);
      totalBobot += r.bobot;
    });

    var pesan = [];
    var urut = data.filter(function (r) { return r.dari > 0 || r.sampai > 0; })
      .sort(function (a, b) { return a.dari - b.dari; });
    var harus = 1;
    urut.forEach(function (r) {
      if (r.dari < 1 || r.sampai < r.dari) {
        pesan.push('Nomor urut tidak valid pada salah satu baris.');
        return;
      }
      if (r.dari !== harus) {
        pesan.push('Nomor harus berurutan tanpa celah atau tumpang tindih (periksa nomor ' + harus + ').');
      }
      harus = r.sampai + 1;
    });
    if (Math.abs(totalBobot - 100) > 0.01) {
      pesan.push('Total bobot harus 100% (saat ini ' + (Math.round(totalBobot * 100) / 100) + '%).');
    }

    errorEl.textContent = pesan.join(' ');
    errorEl.style.display = pesan.length ? '' : 'none';

    totalBobotEl.textContent = 'Bobot ' + (Math.round(totalBobot * 100) / 100) + '%';
    totalBobotEl.className = 'badge ' + (Math.abs(totalBobot - 100) < 0.01 ? 'text-bg-success' : 'text-bg-danger');
    ringkasEl.textContent = totalSoal + ' soal · ' + data.length + ' jenis';
    return { totalSoal: totalSoal, data: data };
  }

  function tambahRow(tipe, dari, sampai, bobot) {
    var tr = document.createElement('tr');
    tr.className = 'komposisi-row';
    var opsi = Object.keys(LABEL_TIPE).map(function (k) {
      return '<option value="' + k + '"' + (k === tipe ? ' selected' : '') + '>' + LABEL_TIPE[k] + '</option>';
    }).join('');
    tr.innerHTML =
      '<td><select class="form-select form-select-sm" name="komposisi_tipe[]" data-f="tipe">' + opsi + '</select></td>' +
      '<td><input class="form-control form-control-sm" type="number" min="1" name="komposisi_dari[]" data-f="dari" value="' + dari + '"></td>' +
      '<td><input class="form-control form-control-sm" type="number" min="1" name="komposisi_sampai[]" data-f="sampai" value="' + sampai + '"></td>' +
      '<td class="text-end"><span class="badge badge-soft" data-jumlah>—</span></td>' +
      '<td><div class="d-flex align-items-center gap-1"><input class="form-control form-control-sm bobot-input" type="number" min="0" max="100" step="0.01" name="komposisi_bobot[]" data-f="bobot" value="' + bobot + '"><span class="text-muted" style="font-size:.8rem">%</span></div></td>' +
      '<td class="text-end"><button type="button" class="btn btn-sm btn-ghost" data-hapus-row title="Hapus jenis soal" aria-label="Hapus jenis soal">✕</button></td>';
    body.appendChild(tr);
    syncKomposisi();
    return tr;
  }

  btnTambah.addEventListener('click', function () {
    var s = syncKomposisi();
    var dari = s.totalSoal + 1;
    var sisa = Math.round((100 - s.data.reduce(function (n, r) { return n + r.bobot; }, 0)) * 100) / 100;
    tambahRow('Isian', dari, dari + 9, sisa > 0 ? sisa : 0);
  });

  btnRapikan.addEventListener('click', function () {
    var harus = 1;
    baris().forEach(function (tr) {
      var r = bacaRow(tr);
      var n = (r.sampai >= r.dari && r.dari >= 1) ? (r.sampai - r.dari + 1) : 0;
      if (n <= 0) { return; }
      tr.querySelector('[data-f="dari"]').value = String(harus);
      tr.querySelector('[data-f="sampai"]').value = String(harus + n - 1);
      harus += n;
    });
    syncKomposisi();
  });

  body.addEventListener('input', syncKomposisi);
  body.addEventListener('change', syncKomposisi);
  body.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-hapus-row]');
    if (!btn) { return; }
    if (baris().length <= 1) {
      errorEl.textContent = 'Minimal satu jenis soal harus ada.';
      errorEl.style.display = '';
      return;
    }
    btn.closest('tr').remove();
    syncKomposisi();
  });

  syncKomposisi();

  var durasiInput = document.getElementById('durasi');
  var minInput = document.getElementById('min_pengerjaan');
  var minHint = document.getElementById('min-hint');

  function syncMin() {
    var d = parseInt(durasiInput.value || '0', 10) || 0;
    var m = parseInt(minInput.value || '0', 10) || 0;
    minInput.max = d > 0 ? String(d) : '';
    if (m > 0 && d > 0 && m > d) {
      minHint.textContent = 'Durasi minimal melebihi durasi ujian (' + d + ' menit). Perbaiki sebelum menyimpan.';
      minHint.className = 'form-text text-danger';
    } else if (m > 0) {
      minHint.textContent = 'Tombol Selesai & Kumpulkan terkunci sampai ' + m + ' menit pengerjaan tercapai.';
      minHint.className = 'form-text';
    } else {
      minHint.textContent = 'Tanpa batas minimal: tombol Selesai & Kumpulkan langsung aktif.';
      minHint.className = 'form-text';
    }
  }
  durasiInput.addEventListener('input', syncMin);
  minInput.addEventListener('input', syncMin);
  syncMin();

  // Target peserta: per kelas / per jenjang / siswa spesifik + filter agama.
  var SISWA = <?= json_encode(array_map(static fn ($s) => [
      'id' => (int) $s['id'],
      'kelas' => (int) $s['id_kelas'],
      'tingkat' => (string) $s['tingkat'],
      'agama' => mb_strtolower(trim((string) $s['agama'])),
  ], $siswaList), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var agamaSel = document.getElementById('agama_target');
  var agamaCount = document.getElementById('agama-count');
  var modeRadios = document.querySelectorAll('input[name="target_mode"]');
  var targetPanels = document.querySelectorAll('[data-target-panel]');

  function currentTargetMode() {
    var v = 'kelas';
    modeRadios.forEach(function (r) { if (r.checked) v = r.value; });
    return v;
  }

  function syncPanels() {
    var mode = currentTargetMode();
    targetPanels.forEach(function (p) {
      p.style.display = p.getAttribute('data-target-panel') === mode ? '' : 'none';
    });
  }

  function checkedValues(selector) {
    return Array.prototype.map.call(document.querySelectorAll(selector + ':checked'), function (c) {
      return String(c.value);
    });
  }

  function syncCount() {
    var mode = currentTargetMode();
    var agama = String(agamaSel.value || '').trim().toLowerCase();
    var kelas = checkedValues('.target-kelas');
    var jenjang = checkedValues('.target-jenjang');
    var siswa = checkedValues('.target-siswa');
    var kosong = (mode === 'kelas' && kelas.length === 0)
      || (mode === 'jenjang' && jenjang.length === 0)
      || (mode === 'siswa' && siswa.length === 0);

    if (kosong) {
      var label = mode === 'kelas' ? 'kelas' : (mode === 'jenjang' ? 'jenjang' : 'siswa');
      agamaCount.textContent = 'Pilih ' + label + ' peserta terlebih dahulu.';
      agamaCount.className = 'form-text text-muted mt-1';
      return;
    }

    var total = SISWA.filter(function (s) {
      if (mode === 'kelas' && kelas.indexOf(String(s.kelas)) < 0) return false;
      if (mode === 'jenjang' && jenjang.indexOf(String(s.tingkat)) < 0) return false;
      if (mode === 'siswa' && siswa.indexOf(String(s.id)) < 0) return false;
      if (agama !== '' && String(s.agama) !== agama) return false;
      return true;
    }).length;

    agamaCount.textContent = total > 0
      ? ('≈ ' + total + ' siswa aktif ' + (agama ? 'beragama ' + agamaSel.value + ' ' : '') + 'memenuhi syarat.')
      : 'Tidak ada siswa aktif yang memenuhi syarat pada target terpilih.';
    agamaCount.className = 'form-text mt-1 ' + (total > 0 ? 'text-success' : 'text-danger');
  }

  modeRadios.forEach(function (r) {
    r.addEventListener('change', function () { syncPanels(); syncCount(); });
  });
  document.querySelectorAll('.target-kelas, .target-jenjang, .target-siswa').forEach(function (c) {
    c.addEventListener('change', syncCount);
  });
  agamaSel.addEventListener('change', syncCount);

  var cariSiswa = document.getElementById('cari-siswa');
  if (cariSiswa) {
    cariSiswa.addEventListener('input', function () {
      var q = cariSiswa.value.trim().toLowerCase();
      document.querySelectorAll('.siswa-item').forEach(function (item) {
        var cocok = q === '' || (item.getAttribute('data-cari') || '').indexOf(q) >= 0;
        item.style.display = cocok ? '' : 'none';
      });
    });
  }

  syncPanels();
  syncCount();
})();
</script>
