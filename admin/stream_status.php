<?php
// stream_status.php — Shared stream status bar for admin panel
// Fetches live status from Solumedia API
$streamApiUrl = 'https://vivo.solumedia.com:2020/json/stream/bragadato';
$streamStatus = ['status' => false, 'connections' => 0, 'bitrate' => 0];
$streamError = false;
$streamRaw = @file_get_contents($streamApiUrl, false, stream_context_create(['http' => ['timeout' => 3]]));
if ($streamRaw !== false) {
    $streamStatus = json_decode($streamRaw, true) ?: $streamStatus;
} else {
    $streamError = true;
}
?>
<div id="stream-status-bar" class="bg-slate-900/80 border-b border-white/5">
  <div class="max-w-3xl mx-auto px-4 py-2.5 flex items-center justify-between">
    <div class="flex items-center gap-4 text-sm">
      <span id="ss-dot" class="inline-block w-2.5 h-2.5 rounded-full <?php echo $streamStatus['status'] ? 'bg-green-400 animate-pulse' : 'bg-red-500'; ?>"></span>
      <span id="ss-label" class="font-semibold <?php echo $streamStatus['status'] ? 'text-green-400' : 'text-red-400'; ?>">
        <?php echo $streamStatus['status'] ? 'EN VIVO' : 'OFFLINE'; ?>
      </span>
      <span id="ss-viewers" class="text-slate-400">
        <svg class="w-3.5 h-3.5 inline -mt-0.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
        <span id="ss-viewers-num"><?php echo intval($streamStatus['connections']); ?></span> viewers
      </span>
      <span id="ss-bitrate" class="text-slate-400">
        <svg class="w-3.5 h-3.5 inline -mt-0.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        <span id="ss-bitrate-num"><?php echo intval($streamStatus['bitrate']); ?></span> kbps
      </span>
    </div>
    <button onclick="refreshStreamStatus()" id="ss-refresh-btn" class="text-slate-500 hover:text-slate-300 transition p-1 rounded-lg hover:bg-white/5" title="Actualizar estado">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
    </button>
  </div>
</div>
<script>
const SS_URL = <?php echo json_encode($streamApiUrl); ?>;
let ssTimer = null;

function refreshStreamStatus() {
  const btn = document.getElementById('ss-refresh-btn');
  if (btn) btn.classList.add('animate-spin');
  fetch(SS_URL)
    .then(r => r.json())
    .then(d => {
      const dot = document.getElementById('ss-dot');
      const label = document.getElementById('ss-label');
      const viewers = document.getElementById('ss-viewers-num');
      const bitrate = document.getElementById('ss-bitrate-num');
      if (dot) { dot.className = 'inline-block w-2.5 h-2.5 rounded-full ' + (d.status ? 'bg-green-400 animate-pulse' : 'bg-red-500'); }
      if (label) { label.textContent = d.status ? 'EN VIVO' : 'OFFLINE'; label.className = 'font-semibold ' + (d.status ? 'text-green-400' : 'text-red-400'); }
      if (viewers) viewers.textContent = d.connections || 0;
      if (bitrate) bitrate.textContent = d.bitrate || 0;
    })
    .catch(() => {})
    .finally(() => { if (btn) setTimeout(() => btn.classList.remove('animate-spin'), 300); });
}

document.addEventListener('DOMContentLoaded', () => {
  refreshStreamStatus();
  ssTimer = setInterval(refreshStreamStatus, 30000);
});
</script>
