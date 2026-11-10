<?php /** @var array $rows */ ?>
<div class="page-head">
  <div>
    <h1>Audit Log</h1>
    <p>300 aktivitas terakhir pada sistem. Berguna untuk menelusuri perubahan data dan login.</p>
  </div>
</div>

<div class="card">
  <div class="table-wrap" style="max-height:calc(100vh - 230px);overflow:auto">
    <table class="table table-hover align-middle mb-0">
      <thead style="position:sticky;top:0;z-index:1">
        <tr>
          <th style="width:64px">No</th><th style="width:150px">Waktu</th><th>Pengguna</th>
          <th>Aksi</th><th>Entitas</th><th>IP</th><th>Detail</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada aktivitas tercatat.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $i => $r):
          $detail = '';
          if (!empty($r['detail'])) {
              $decoded = json_decode((string) $r['detail'], true);
              $detail = is_array($decoded) ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $r['detail'];
          }
      ?>
        <tr>
          <td class="text-muted"><?= $i + 1 ?></td>
          <td style="font-size:.8rem"><?= e(fmt_tanggal($r['waktu'])) ?></td>
          <td>
            <div class="fw-semibold" style="font-size:.86rem"><?= e($r['peran'] ?: 'sistem') ?></div>
            <?php if ($r['user_id'] !== null): ?><div class="text-muted" style="font-size:.72rem">ID <?= (int) $r['user_id'] ?></div><?php endif; ?>
          </td>
          <td><code style="font-size:.8rem"><?= e($r['aksi']) ?></code></td>
          <td style="font-size:.82rem"><?= e($r['entitas'] ?: '—') ?><?= $r['entitas_id'] !== null ? ' #' . (int) $r['entitas_id'] : '' ?></td>
          <td style="font-size:.78rem"><?= e($r['ip'] ?: '—') ?></td>
          <td style="font-size:.76rem;max-width:320px;word-break:break-word"><?= e($detail) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
