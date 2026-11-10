<?php
/** @var array $r */
$sisa = max(0, strtotime((string) $r['batas_waktu']) - time());
$menit = intdiv($sisa, 60);
$detik = $sisa % 60;
?>
<div class="lock-screen" role="dialog" aria-modal="true" aria-labelledby="lock-title">
  <div class="lock-card">
    <div class="lock-symbol" aria-hidden="true">🔒</div>
    <div class="lock-kicker">Anti-Curang</div>
    <h1 id="lock-title">Ujian Dikunci</h1>
    <p class="lock-desc">
      Ujian <strong><?= e($r['nama_soal']) ?></strong> dikunci karena batas pelanggaran tercapai.
      Panggil guru atau pengawas untuk membuka kunci.
    </p>

    <div class="lock-info">
      <div>
        <span>Pelanggaran</span>
        <strong><?= (int) $r['pelanggaran'] ?> / <?= (int) pengaturan_pengawasan()['maks_pelanggaran'] ?></strong>
      </div>
      <div>
        <span>Sisa waktu</span>
        <strong id="lock-countdown"><?= $menit ?>:<?= str_pad((string) $detik, 2, '0', STR_PAD_LEFT) ?></strong>
      </div>
      <div>
        <span>Dikunci pada</span>
        <strong><?= e($r['dikunci_pada'] ? fmt_tanggal($r['dikunci_pada'], true) : '—') ?></strong>
      </div>
    </div>

    <button class="btn btn-teal lock-btn" type="button" onclick="location.reload()">Muat Ulang</button>
    <p class="lock-note">
      Guru dapat membuka kunci dari menu <strong>Hasil Ujian → Buka Rekap</strong>.
      Setelah dibuka, muat ulang halaman ini untuk melanjutkan. Halaman juga
      memeriksa status secara otomatis.
    </p>
  </div>
</div>

<script>
(function () {
  var sisa = <?= (int) $sisa ?>;
  var el = document.getElementById('lock-countdown');
  function tick() {
    if (sisa <= 0) {
      if (el) el.textContent = '00:00';
      return;
    }
    sisa--;
    var m = Math.floor(sisa / 60);
    var s = sisa % 60;
    if (el) el.textContent = m + ':' + (s < 10 ? '0' + s : s);
  }
  if (sisa > 0) {
    window.setInterval(tick, 1000);
  }
  // Cek berkala: jika guru sudah membuka kunci, halaman akan memuat ujian.
  window.setTimeout(function () { location.reload(); }, 15000);
})();
</script>
