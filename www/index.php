<?php
$wwwRoot = __DIR__;
$exclude = ['.git', '.idea', '.vscode', '.well-known', 'cgi-bin', 'node_modules', 'vendor', 'tmp', 'temp', 'cache'];
$palette = ['#5b8def', '#a78bfa', '#34d399', '#fbbf24', '#f472b6', '#38bdf8', '#fb923c', '#818cf8'];

$projects = [];
foreach (scandir($wwwRoot) as $entry) {
    if ($entry === '.' || $entry === '..') continue;
    if ($entry[0] === '.') continue;
    if (!is_dir($wwwRoot . '/' . $entry)) continue;
    if (in_array($entry, $exclude, true)) continue;
    $projects[] = $entry;
}
sort($projects, SORT_NATURAL | SORT_FLAG_CASE);

$cacheFile = $wwwRoot . '/.panel-cache.json';
$cacheTtl = 86400; // segundos: recalcula "atualizado há" no máximo 1x por dia, por projeto
$cache = [];
$cacheRaw = @file_get_contents($cacheFile);
if ($cacheRaw !== false) {
    $decoded = json_decode($cacheRaw, true);
    if (is_array($decoded)) $cache = $decoded;
}
$cacheDirty = false;
$now = time();

if (isset($cache['_versions']) && ($now - $cache['_versions']['t']) < $cacheTtl) {
    $versions = $cache['_versions']['data'];
} else {
    $versions = [
        'php' => PHP_VERSION,
        'nginx' => get_nginx_version($wwwRoot),
        'mysql' => get_mysql_version(),
    ];
    $cache['_versions'] = ['data' => $versions, 't' => $now];
    $cacheDirty = true;
}

function project_tags($path) {
    $tags = [];
    if (is_dir($path . '/wp-content') || file_exists($path . '/wp-login.php')) {
        $tags[] = 'WordPress';
    }
    $tags[] = 'PHP';
    if (count($tags) === 1) {
        $tags[] = 'Custom';
    }
    return $tags;
}

function accent_for($name, $palette) {
    return $palette[crc32($name) % count($palette)];
}

function slug_for($name) {
    return preg_replace('/[^a-z0-9]/', '', strtolower($name));
}

function get_nginx_version($wwwRoot) {
    $probe = $wwwRoot . '/_probe.txt';
    if (!file_exists($probe)) {
        @file_put_contents($probe, "ok\n");
    }
    $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
    $headers = @get_headers('http://nginx/_probe.txt', 1, $ctx);
    if ($headers === false) return null;
    $server = null;
    foreach ($headers as $k => $v) {
        if (is_string($k) && strcasecmp($k, 'Server') === 0) {
            $server = is_array($v) ? end($v) : $v;
            break;
        }
    }
    if ($server === null) return null;
    if (preg_match('/nginx\/([0-9.]+)/i', $server, $m)) return $m[1];
    return $server;
}

function get_mysql_version() {
    if (!function_exists('mysqli_init')) return null;
    $link = @mysqli_init();
    if (!$link) return null;
    @mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 2);
    $ok = @mysqli_real_connect($link, 'mysql', 'root', 'root', null, 3306);
    if (!$ok) return null;
    $info = $link->server_info ?: null;
    @mysqli_close($link);
    return $info;
}

function latest_mtime($dir, $skip, &$budget) {
    $latest = @filemtime($dir) ?: 0;
    if ($budget <= 0) return $latest;
    $items = @scandir($dir);
    if ($items === false) return $latest;
    foreach ($items as $item) {
        if ($budget <= 0) break;
        if ($item === '.' || $item === '..') continue;
        if ($item[0] === '.') continue;
        if (in_array($item, $skip, true)) continue;
        $full = $dir . '/' . $item;
        $budget--;
        if (is_dir($full)) {
            $latest = max($latest, latest_mtime($full, $skip, $budget));
        } else {
            $mtime = @filemtime($full);
            if ($mtime !== false) $latest = max($latest, $mtime);
        }
    }
    return $latest;
}

function time_ago($ts) {
    if (!$ts) return 'data desconhecida';
    $diff = time() - $ts;
    if ($diff < 60) return 'agora mesmo';
    $mins = floor($diff / 60);
    if ($mins < 60) return 'há ' . $mins . ($mins == 1 ? ' minuto' : ' minutos');
    $hours = floor($diff / 3600);
    if ($hours < 24) return 'há ' . $hours . ($hours == 1 ? ' hora' : ' horas');
    $days = floor($diff / 86400);
    if ($days < 7) return 'há ' . $days . ($days == 1 ? ' dia' : ' dias');
    $weeks = floor($days / 7);
    if ($weeks < 5) return 'há ' . $weeks . ($weeks == 1 ? ' semana' : ' semanas');
    $months = floor($days / 30);
    if ($months < 12) return 'há ' . $months . ($months == 1 ? ' mês' : ' meses');
    $years = floor($days / 365);
    return 'há ' . $years . ($years == 1 ? ' ano' : ' anos');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Painel Local · Docker Server</title>
<style>
  :root{
    --bg:#0a0c11;
    --bg-radial: radial-gradient(1200px 600px at 15% -10%, rgba(91,141,239,0.14), transparent 60%),
                 radial-gradient(1000px 500px at 100% 0%, rgba(167,139,250,0.10), transparent 55%);
    --surface:#12151d;
    --surface-2:#171b25;
    --border:#232838;
    --border-soft:#1b2030;
    --text:#e8eaf1;
    --text-dim:#8990a3;
    --text-faint:#5b6275;
    --blue:#5b8def;
    --purple:#a78bfa;
    --teal:#34d399;
    --amber:#fbbf24;
    --green:#34d399;
    --red:#f87171;
    --gray:#6b7280;
  }
  *{box-sizing:border-box;}
  html,body{height:100%;}
  body{
    margin:0;
    background-color:var(--bg);
    background-image:var(--bg-radial);
    background-attachment:fixed;
    color:var(--text);
    font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,Roboto,Helvetica,Arial,sans-serif;
    display:flex;
    min-height:100vh;
  }

  /* ---------- Sidebar ---------- */
  .sidebar{
    width:84px;
    flex-shrink:0;
    background:linear-gradient(180deg, var(--surface), #0d0f16);
    border-right:1px solid var(--border-soft);
    display:flex;
    flex-direction:column;
    align-items:center;
    padding:22px 0;
    gap:8px;
    position:sticky;
    top:0;
    height:100vh;
  }
  .sidebar-logo{
    width:44px;height:44px;
    border-radius:14px;
    background:linear-gradient(135deg, var(--blue), var(--purple));
    display:flex;align-items:center;justify-content:center;
    margin-bottom:22px;
    box-shadow:0 6px 18px rgba(91,141,239,0.35);
  }
  .sidebar-logo svg{width:24px;height:24px;stroke:#fff;}
  .sidebar-sep{
    width:32px;height:1px;background:var(--border);
    margin:10px 0 16px 0;
  }
  .side-btn{
    position:relative;
    width:52px;height:52px;
    border-radius:16px;
    background:var(--surface-2);
    border:1px solid var(--border);
    display:flex;align-items:center;justify-content:center;
    text-decoration:none;
    color:var(--text-dim);
    transition:all .18s ease;
    cursor:pointer;
  }
  .side-btn svg{width:22px;height:22px;stroke:currentColor;fill:none;transition:stroke .18s ease;}
  .side-btn:hover{
    color:#fff;
    background:var(--surface-2);
    border-color:var(--accent,var(--blue));
    transform:translateY(-2px);
    box-shadow:0 8px 20px rgba(0,0,0,0.35);
  }
  .side-btn .dot{
    position:absolute;top:6px;right:6px;
    width:9px;height:9px;border-radius:50%;
    background:var(--gray);
    border:2px solid var(--surface-2);
  }
  .side-btn .dot.online{background:var(--green);box-shadow:0 0 8px var(--green);}
  .side-btn .dot.offline{background:var(--red);}
  .side-btn .tip{
    position:absolute;
    left:64px;
    top:50%;transform:translateY(-50%) translateX(-6px);
    background:#1c212e;
    color:var(--text);
    font-size:12.5px;
    font-weight:600;
    padding:7px 12px;
    border-radius:9px;
    white-space:nowrap;
    border:1px solid var(--border);
    opacity:0;
    pointer-events:none;
    transition:all .18s ease;
    box-shadow:0 8px 20px rgba(0,0,0,0.4);
  }
  .side-btn .tip small{display:block;font-weight:400;color:var(--text-dim);font-size:11px;margin-top:2px;}
  .side-btn:hover .tip{opacity:1;transform:translateY(-50%) translateX(0);}

  .sidebar-bottom{margin-top:auto;display:flex;flex-direction:column;align-items:center;gap:8px;}
  .sidebar-time{font-size:10.5px;color:var(--text-faint);text-align:center;line-height:1.4;}

  /* ---------- Main ---------- */
  .main{flex:1;padding:48px 56px 60px;max-width:1180px;}
  .header{margin-bottom:38px;}
  .header .eyebrow{
    color:var(--blue);font-size:12.5px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:10px;
  }
  .header h1{font-size:32px;margin:0 0 8px;font-weight:700;letter-spacing:-.02em;}
  .header p{color:var(--text-dim);font-size:14.5px;margin:0;max-width:560px;line-height:1.6;}
  .stack-badges{display:flex;gap:10px;flex-wrap:wrap;margin-top:6px;}
  .badge{
    display:flex;align-items:center;gap:10px;
    background:var(--surface-2);border:1px solid var(--border);
    border-radius:12px;padding:9px 14px;
  }
  .badge-icon{
    width:30px;height:30px;border-radius:9px;flex-shrink:0;
    background:color-mix(in srgb, var(--accent, var(--blue)) 18%, transparent);
    display:flex;align-items:center;justify-content:center;
  }
  .badge-icon svg{width:16px;height:16px;stroke:var(--accent, var(--blue));fill:none;}
  .badge-text{display:flex;flex-direction:column;line-height:1.25;}
  .badge-label{font-size:10.5px;font-weight:700;color:var(--text-faint);text-transform:uppercase;letter-spacing:.06em;}
  .badge-value{font-size:13.5px;font-weight:600;color:var(--text);font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;}

  .section-title{
    font-size:13px;font-weight:700;color:var(--text-faint);text-transform:uppercase;letter-spacing:.1em;
    margin:0 0 18px 2px;display:flex;align-items:center;gap:10px;
  }
  .section-title::after{content:"";flex:1;height:1px;background:var(--border-soft);}
  .section-title .count{
    background:var(--surface-2);border:1px solid var(--border);color:var(--text-dim);
    font-size:11px;padding:2px 9px;border-radius:999px;text-transform:none;letter-spacing:0;
  }

  .grid{
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));
    gap:18px;
  }

  .card{
    position:relative;
    background:linear-gradient(180deg, var(--surface-2), var(--surface));
    border:1px solid var(--border);
    border-radius:18px;
    padding:22px 22px 20px;
    overflow:hidden;
    transition:transform .2s ease, border-color .2s ease, box-shadow .2s ease;
  }
  .card::before{
    content:"";
    position:absolute;top:0;left:0;right:0;height:3px;
    background:var(--accent, var(--blue));
  }
  .card:hover{
    transform:translateY(-4px);
    border-color:var(--border-soft);
    box-shadow:0 16px 32px rgba(0,0,0,0.45);
  }
  .card-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;}
  .card-icon{
    width:42px;height:42px;border-radius:12px;
    background:color-mix(in srgb, var(--accent, var(--blue)) 16%, transparent);
    display:flex;align-items:center;justify-content:center;
  }
  .card-icon svg{width:22px;height:22px;stroke:var(--accent, var(--blue));fill:none;}
  .status{display:flex;align-items:center;gap:6px;font-size:11.5px;color:var(--text-faint);font-weight:600;}
  .status .dot{width:8px;height:8px;border-radius:50%;background:var(--gray);}
  .status .dot.online{background:var(--green);box-shadow:0 0 8px var(--green);}
  .status .dot.offline{background:var(--red);}

  .card h3{margin:0 0 6px;font-size:17.5px;font-weight:700;letter-spacing:-.01em;}
  .card .updated{
    font-size:12px;color:var(--text-faint);margin:0 0 14px;
    display:flex;align-items:center;gap:6px;
  }
  .card .updated::before{
    content:"";width:5px;height:5px;border-radius:50%;background:var(--text-faint);flex-shrink:0;
  }
  .tags{display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap;}
  .tag{
    font-size:10.5px;font-weight:700;padding:4px 9px;border-radius:999px;
    background:var(--surface);border:1px solid var(--border);color:var(--text-dim);
    text-transform:uppercase;letter-spacing:.04em;
  }
  .card a.open{
    display:flex;align-items:center;justify-content:space-between;
    background:var(--surface);
    border:1px solid var(--border);
    color:var(--text);
    text-decoration:none;
    font-size:13px;font-weight:600;
    padding:10px 14px;border-radius:11px;
    transition:all .18s ease;
  }
  .card a.open span.url{color:var(--text-dim);font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:11.5px;}
  .card a.open:hover{background:var(--accent,var(--blue));border-color:var(--accent,var(--blue));color:#0a0c11;}
  .card a.open:hover span.url{color:#0a0c11;opacity:.75;}
  .card a.open svg{width:15px;height:15px;stroke:currentColor;flex-shrink:0;}

  .empty-state{
    border:1px dashed var(--border-soft);border-radius:18px;padding:38px 24px;
    text-align:center;color:var(--text-faint);font-size:13.5px;
  }

  footer{margin-top:36px;color:var(--text-faint);font-size:12px;text-align:left;}
  footer code{color:var(--text-dim);}

  @media (max-width:640px){
    .sidebar{width:64px;}
    .side-btn{width:44px;height:44px;}
    .main{padding:32px 20px 40px;}
  }
</style>
</head>
<body>

  <nav class="sidebar">
    <div class="sidebar-logo">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 3 7l9 5 9-5-9-5Z"/><path d="M3 12l9 5 9-5"/><path d="M3 17l9 5 9-5"/></svg>
    </div>
    <div class="sidebar-sep"></div>

    <a class="side-btn" id="btn-phpmyadmin" style="--accent:#a78bfa" href="http://localhost:10000/" target="_blank" rel="noopener">
      <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/><path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/></svg>
      <span class="dot" id="dot-phpmyadmin"></span>
      <span class="tip">phpMyAdmin<small>localhost:10000</small></span>
    </a>

    <a class="side-btn" id="btn-mailpit" style="--accent:#fbbf24" href="http://localhost:8025/" target="_blank" rel="noopener">
      <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
      <span class="dot" id="dot-mailpit"></span>
      <span class="tip">Mailpit<small>localhost:8025</small></span>
    </a>

    <div class="sidebar-bottom">
      <div class="sidebar-time" id="clock"></div>
    </div>
  </nav>

  <main class="main">
    <div class="header">
      <div class="eyebrow">Docker Server · Ambiente Local</div>
      <h1>Painel de Desenvolvimento</h1>
      <div class="stack-badges">
        <div class="badge" style="--accent:#009639">
          <div class="badge-icon"><svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="6" rx="1.5"/><rect x="3" y="14" width="18" height="6" rx="1.5"/><path d="M7 7h.01"/><path d="M7 17h.01"/></svg></div>
          <div class="badge-text"><span class="badge-label">Nginx</span><span class="badge-value"><?= htmlspecialchars($versions['nginx'] ?: 'indisponível', ENT_QUOTES) ?></span></div>
        </div>
        <div class="badge" style="--accent:#787cb5">
          <div class="badge-icon"><svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m8 16-4-4 4-4"/><path d="m16 8 4 4-4 4"/><path d="m14 4-4 16"/></svg></div>
          <div class="badge-text"><span class="badge-label">PHP</span><span class="badge-value"><?= htmlspecialchars($versions['php'] ?: 'indisponível', ENT_QUOTES) ?></span></div>
        </div>
        <div class="badge" style="--accent:#00758f">
          <div class="badge-icon"><svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/><path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/></svg></div>
          <div class="badge-text"><span class="badge-label">MySQL</span><span class="badge-value"><?= htmlspecialchars($versions['mysql'] ?: 'indisponível', ENT_QUOTES) ?></span></div>
        </div>
      </div>
    </div>

    <div class="section-title">Projetos <span class="count"><?= count($projects) ?></span></div>
    <div class="grid" id="projects-grid">
<?php if (empty($projects)): ?>
      <div class="empty-state">Nenhum projeto encontrado em <code>www</code> ainda. Crie uma pasta ali e ela aparece aqui automaticamente.</div>
<?php else: foreach ($projects as $name):
        $path = $wwwRoot . '/' . $name;
        $tags = project_tags($path);
        $accent = accent_for($name, $palette);
        $slug = slug_for($name);
        $url = 'http://localhost/' . rawurlencode($name) . '/';
        $safeName = htmlspecialchars($name, ENT_QUOTES);
        if (isset($cache[$name]) && ($now - $cache[$name]['t']) < $cacheTtl) {
            $lastUpdate = $cache[$name]['mtime'];
        } else {
            $budget = 2500; // limite de arquivos verificados por projeto (mounts do Docker/Windows são lentos)
            $lastUpdate = latest_mtime($path, ['.git', 'node_modules', 'vendor', 'wp-admin', 'wp-includes', 'cache', 'tmp', 'temp'], $budget);
            $cache[$name] = ['mtime' => $lastUpdate, 't' => $now];
            $cacheDirty = true;
        }
?>
      <div class="card" style="--accent:<?= $accent ?>">
        <div class="card-top">
          <div class="card-icon"><svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-6 9 6-9 6-9-6Z"/><path d="M3 9v6l9 6 9-6V9"/></svg></div>
          <div class="status"><span class="dot" id="status-<?= $slug ?>"></span><span id="status-text-<?= $slug ?>">verificando</span></div>
        </div>
        <h3><?= $safeName ?></h3>
        <p class="updated" title="<?= htmlspecialchars(date('d/m/Y H:i', $lastUpdate), ENT_QUOTES) ?>">Atualizado <?= time_ago($lastUpdate) ?></p>
        <div class="tags"><?php foreach ($tags as $tag): ?><span class="tag"><?= htmlspecialchars($tag, ENT_QUOTES) ?></span><?php endforeach; ?></div>
        <a class="open" href="<?= $url ?>" target="_blank" rel="noopener" data-check-url="<?= $url ?>" data-check-id="<?= $slug ?>">
          <span class="url">localhost/<?= $safeName ?></span>
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7"/><path d="M7 7h10v10"/></svg>
        </a>
      </div>
<?php endforeach; endif; ?>
<?php if ($cacheDirty): @file_put_contents($cacheFile, json_encode($cache), LOCK_EX); endif; ?>
    </div>

    <footer>
      Servido por <code>nginx</code> + <code>php</code> · pasta compartilhada <code>C:\Docker\www</code> → <code>/var/www/html</code>
    </footer>
  </main>

<script>
  function updateClock(){
    var d = new Date();
    document.getElementById('clock').textContent = d.toLocaleDateString('pt-BR') + '\n' + d.toLocaleTimeString('pt-BR');
  }
  updateClock();
  setInterval(updateClock, 1000);

  function withTimeout(promise, ms){
    var controller = new AbortController();
    var timer = setTimeout(function(){ controller.abort(); }, ms);
    return promise(controller.signal).finally(function(){ clearTimeout(timer); });
  }

  function checkSameOrigin(url, dotEl, textEl){
    withTimeout(function(signal){
      return fetch(url, {method:'GET', signal:signal, cache:'no-store'});
    }, 2500).then(function(res){
      setStatus(dotEl, textEl, true);
    }).catch(function(){
      setStatus(dotEl, textEl, false);
    });
  }

  function checkCrossOrigin(url, dotEl, textEl){
    withTimeout(function(signal){
      return fetch(url, {method:'GET', mode:'no-cors', signal:signal, cache:'no-store'});
    }, 2500).then(function(){
      setStatus(dotEl, textEl, true);
    }).catch(function(){
      setStatus(dotEl, textEl, false);
    });
  }

  function setStatus(dotEl, textEl, online){
    if(dotEl){ dotEl.classList.add(online ? 'online' : 'offline'); }
    if(textEl){ textEl.textContent = online ? 'online' : 'offline'; }
  }

  document.querySelectorAll('[data-check-url]').forEach(function(el){
    var id = el.getAttribute('data-check-id');
    checkSameOrigin(el.getAttribute('data-check-url'), document.getElementById('status-'+id), document.getElementById('status-text-'+id));
  });

  checkCrossOrigin('http://localhost:10000/', document.getElementById('dot-phpmyadmin'), null);
  checkCrossOrigin('http://localhost:8025/', document.getElementById('dot-mailpit'), null);
</script>
</body>
</html>
