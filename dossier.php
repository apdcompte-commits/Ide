<?php
/* ============================================================
   PROGRAMME APD (ODA) — Consultation d'un dossier
   Accessible via : /dossier  ou  /dossier?id=XXX
   Page 1 : formulaire de saisie de l'identifiant
   Page 2 : affichage des fichiers avec téléchargement + suppression
   ============================================================ */

require __DIR__ . '/config.php';

$id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';

/* -------- Si un ID est fourni, afficher les fichiers -------- */
if ($id !== '') {
    if (!preg_match('/^[A-Za-z0-9_\-]+$/', $id)) {
        $error = 'Identifiant invalide. Vérifiez les caractères utilisés.';
        $id = '';
    } else {
        $subDir = GIRH_DATA_DIR . '/submissions/' . $id;
        if (!is_dir($subDir)) {
            $error = 'Dossier introuvable. Vérifiez l\'identifiant ou contactez le secrétariat.';
            $id = '';
        }
    }
}

/* -------- Action : suppression -------- */
if ($id !== '' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
    function rrmdir($dir) {
        if (!is_dir($dir)) { return false; }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') { continue; }
            $path = $dir . '/' . $item;
            if (is_dir($path)) { rrmdir($path); } else { @unlink($path); }
        }
        return @rmdir($dir);
    }
    $subDir = GIRH_DATA_DIR . '/submissions/' . $id;
    $deleted = rrmdir($subDir);
    $showDeleted = true;
    $id = '';
}

/* -------- Préparer les fichiers si un dossier valide est chargé -------- */
$fileList = array();
$meta = null;
$nomComplet = '';
if ($id !== '') {
    $allFiles = scandir($subDir);
    foreach ($allFiles as $f) {
        if ($f === '.' || $f === '..' || $f === 'metadonnees.json') { continue; }
        $fullPath = $subDir . '/' . $f;
        if (!is_file($fullPath)) { continue; }
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        $size = filesize($fullPath);

        $label = $f;
        if (strpos($f, 'Recapitulatif_Inscription_APD') === 0) {
            $label = '📄 Récapitulatif du formulaire (.'. $ext . ')';
        } elseif (strpos($f, 'Photo') === 0) {
            $label = '🖼️ Photo (.'. $ext . ')';
        } elseif (strpos($f, 'CV') === 0) {
            $label = '📋 CV (.'. $ext . ')';
        } elseif (strpos($f, 'Lettre') === 0) {
            $label = '✉️ Lettre de motivation (.'. $ext . ')';
        } elseif (strpos($f, 'Piece_identite') === 0 || strpos($f, 'Pièce') === 0) {
            $label = '🪪 Pièce d\'identité / Passeport (.'. $ext . ')';
        }

        $fileList[] = array('name' => $f, 'label' => $label, 'size' => $size, 'ext' => $ext);
    }

    usort($fileList, function ($a, $b) {
        $order = array('xlsx' => 0, 'txt' => 0, 'jpg' => 1, 'jpeg' => 1, 'png' => 1, 'doc' => 2, 'docx' => 2, 'pdf' => 3);
        $oa = isset($order[$a['ext']]) ? $order[$a['ext']] : 99;
        $ob = isset($order[$b['ext']]) ? $order[$b['ext']] : 99;
        return $oa <=> $ob ?: strcmp($a['label'], $b['label']);
    });

    $metaFile = $subDir . '/metadonnees.json';
    if (file_exists($metaFile)) {
        $raw = @file_get_contents($metaFile);
        if ($raw !== false) { $meta = @json_decode($raw, true); }
    }
    if ($meta !== null) {
        $nomComplet = htmlspecialchars(($meta['nom'] ?? '') . ' ' . ($meta['prenoms'] ?? ''));
    }
}

/* -------- Gestion du téléchargement direct -------- */
if ($id !== '' && isset($_GET['dl']) && $_GET['dl'] !== '') {
    $dlFile = trim((string) $_GET['dl']);
    if (strpos($dlFile, '/') !== false || strpos($dlFile, '\\') !== false || $dlFile === '.' || $dlFile === '..') {
        http_response_code(403); exit;
    }
    $dlPath = $subDir . '/' . $dlFile;
    if (!is_file($dlPath)) { http_response_code(404); exit; }

    $mimes = array(
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pdf'  => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png'  => 'image/png', 'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt'  => 'text/plain',
    );
    $ext = strtolower(pathinfo($dlFile, PATHINFO_EXTENSION));
    $mime = $mimes[$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . addslashes($dlFile) . '"');
    header('Content-Length: ' . filesize($dlPath));
    header('X-Content-Type-Options: nosniff');
    readfile($dlPath);
    exit;
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Programme APD (ODA)
<?php if ($id !== ''): ?>— Dossier <?php echo htmlspecialchars($id); endif; ?></title>
<style>
:root{--navy:#122a44;--gold:#b98a3e;--paper:#f7f5f0;--ink:#233042;--ink-soft:#54617a;--danger:#a33327;--ok:#2e6e4e;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',-apple-system,Arial,sans-serif;font-size:13.5px;line-height:1.75;color:var(--ink);background:var(--paper);min-height:100vh;display:flex;flex-direction:column;}
.header{background:var(--navy);color:#eef2f7;padding:18px 24px;text-align:center;border-bottom:3px solid var(--gold);}
.header h1{font-size:18px;font-weight:400;letter-spacing:.04em;margin:0;}
.header p{font-size:11px;color:#b9c8da;letter-spacing:.06em;margin:4px 0 0;}
.main{flex:1;max-width:640px;width:100%;margin:0 auto;padding:30px 20px 50px;}

/* Formulaire de saisie */
.search-card{background:#fff;border:1px solid #e0dacd;border-radius:6px;padding:32px 28px;box-shadow:0 18px 45px -18px rgba(11,29,49,.28);text-align:center;}
.search-card h2{font-size:17px;color:var(--navy);margin-bottom:6px;}
.search-card .sub{font-size:12px;color:var(--ink-soft);margin-bottom:20px;line-height:1.6;}
.search-row{display:flex;gap:10px;max-width:440px;margin:0 auto;}
.search-row input{flex:1;font-family:inherit;font-size:13px;color:var(--ink);background:var(--paper);border:1px solid #e0dacd;border-radius:4px;padding:11px 14px;outline:none;transition:border-color .2s;}
.search-row input:focus{border-color:var(--navy);background:#fff;}
.search-row button{font-family:inherit;background:var(--navy);color:#fff;border:none;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:11px 22px;border-radius:4px;cursor:pointer;transition:background .15s;white-space:nowrap;}
.search-row button:hover{background:var(--gold);}
.error-msg{color:var(--danger);font-size:12px;font-weight:600;margin-top:14px;text-align:center;}

/* Carte info dossier */
.info-card{background:#fff;border:1px solid #e0dacd;border-radius:4px;padding:18px 22px;margin-bottom:18px;box-shadow:0 8px 25px -10px rgba(11,29,49,.15);}
.info-card h3{font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--navy);margin-bottom:10px;}
.info-row{display:flex;gap:8px;font-size:12px;margin:4px 0;}
.info-row .k{color:var(--ink-soft);min-width:130px;font-weight:600;}

/* Fichiers */
.file-item{display:flex;align-items:center;gap:14px;background:#fff;border:1px solid #e0dacd;border-radius:4px;padding:14px 18px;margin-bottom:8px;box-shadow:0 4px 12px -5px rgba(11,29,49,.08);transition:border-color .15s;}
.file-item:hover{border-color:var(--gold);}
.file-item .fi-label{flex:1;font-weight:600;font-size:12.5px;color:var(--ink);}
.file-item .fi-size{font-size:10.5px;color:var(--ink-soft);min-width:65px;text-align:right;}
.file-item .fi-dl{display:inline-block;background:var(--navy);color:#fff;text-decoration:none;font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:7px 16px;border-radius:3px;transition:background .15s;}
.file-item .fi-dl:hover{background:var(--gold);}

/* Zone danger */
.danger-zone{margin-top:28px;text-align:center;}
.btn-delete{display:inline-block;background:transparent;color:var(--danger);border:1px solid var(--danger);font-family:inherit;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:12px 28px;border-radius:3px;cursor:pointer;transition:all .18s;}
.btn-delete:hover{background:var(--danger);color:#fff;}
.delete-warning{font-size:11px;color:var(--ink-soft);margin-top:10px;}
.success-msg{background:#e8f3ed;border:1px solid #cfe5d8;color:var(--ok);font-size:13px;font-weight:600;padding:16px 20px;border-radius:4px;text-align:center;}
.back-link{text-align:center;margin-top:20px;}
.back-link a{color:var(--navy);font-weight:700;font-size:12px;}
.footer{text-align:center;padding:20px;font-size:10.5px;color:var(--ink-soft);border-top:1px solid #e0dacd;margin-top:30px;}
@media(max-width:500px){
  .search-row{flex-direction:column;}
  .info-row{flex-direction:column;gap:1px;}
  .file-item{flex-wrap:wrap;gap:8px;}
  .file-item .fi-dl{width:100%;text-align:center;}
}
</style>
</head>
<body>

<div class="header">
  <h1>Programme APD (ODA)</h1>
  <p>Consultation des dossiers d'inscription</p>
</div>

<div class="main">

<?php if (!empty($showDeleted)): ?>
  <div class="success-msg">✅ Le dossier et tous ses fichiers ont été définitivement supprimés du serveur.</div>
  <div class="back-link"><a href="/dossier">← Consulter un autre dossier</a></div>

<?php elseif ($id !== ''): ?>
  <?php /* -------- AFFICHAGE DU DOSSIER -------- */ ?>
  <div class="info-card">
    <h3>📁 Informations du dossier</h3>
    <div class="info-row"><span class="k">Identifiant :</span><span><strong><?php echo htmlspecialchars($id); ?></strong></span></div>
    <?php if ($nomComplet !== ''): ?>
    <div class="info-row"><span class="k">Candidat :</span><span><strong><?php echo $nomComplet; ?></strong></span></div>
    <?php endif; ?>
    <?php if ($meta !== null && isset($meta['age'])): ?>
    <div class="info-row"><span class="k">Âge :</span><span><?php echo htmlspecialchars($meta['age']); ?> ans</span></div>
    <?php endif; ?>
    <?php if ($meta !== null && isset($meta['date'])): ?>
    <div class="info-row"><span class="k">Soumis le :</span><span><?php echo htmlspecialchars(date('d/m/Y à H:i', strtotime($meta['date']))); ?></span></div>
    <?php endif; ?>
    <div class="info-row"><span class="k">Fichiers :</span><span><?php echo count($fileList); ?> fichier(s)</span></div>
  </div>

  <?php if (count($fileList) > 0): ?>
    <?php foreach ($fileList as $f): ?>
    <div class="file-item">
      <div class="fi-label"><?php echo htmlspecialchars($f['label']); ?></div>
      <div class="fi-size"><?php echo round($f['size'] / 1024, 1); ?> Ko</div>
      <a class="fi-dl" href="?id=<?php echo urlencode($id); ?>&amp;dl=<?php echo urlencode($f['name']); ?>" download>Télécharger</a>
    </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="success-msg" style="background:var(--paper);border-color:#e0dacd;color:var(--ink-soft);">Aucun fichier trouvé dans ce dossier.</div>
  <?php endif; ?>

  <div class="danger-zone">
    <form method="POST" onsubmit="return confirm('Confirmez-vous la suppression définitive du dossier <?php echo htmlspecialchars(addslashes($id)); ?> et de tous ses fichiers ? Cette action est irréversible.');">
      <input type="hidden" name="action" value="supprimer">
      <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
      <button type="submit" class="btn-delete">🗑️ Supprimer définitivement ce dossier</button>
    </form>
    <div class="delete-warning">Après consultation, pensez à supprimer pour libérer de l'espace sur le serveur.</div>
  </div>

  <div class="back-link"><a href="/dossier">← Consulter un autre dossier</a></div>

<?php else: ?>
  <?php /* -------- FORMULAIRE DE SAISIE DE L'ID -------- */ ?>
  <div class="search-card">
    <h2>Consultation d'un dossier</h2>
    <p class="sub">Veuillez insérer l'identifiant du dossier que vous avez reçu par e-mail, puis validez pour accéder aux fichiers.</p>
    <form method="GET" action="/dossier">
      <div class="search-row">
        <input type="text" name="id" placeholder="Exemple : DUPONT-Jean-34-a3f8c2" value="<?php echo htmlspecialchars($id); ?>" autofocus>
        <button type="submit">Valider</button>
      </div>
    </form>
    <?php if (!empty($error)): ?>
    <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

</div>

<div class="footer">
  © Programme APD (ODA) — Gestion Internationale des Ressources Humaines
</div>

</body>
</html>
