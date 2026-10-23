<?php
/* ============================================================
   PROGRAMME APD (ODA) — Traitement du formulaire
   - Validation serveur (types: jpg/jpeg/png/doc/docx/pdf)
   - Récapitulatif TXT structuré
   - Archivage avec identifiant unique lisible
   - Retourne l'ID pour ouverture mailto côté client
   ============================================================ */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require __DIR__ . '/config.php';

if (!function_exists('mb_strlen')) {
    function mb_strlen($s) { return strlen($s); }
}
if (!function_exists('mb_substr')) {
    function mb_substr($s, $start, $len = null) { return $len === null ? substr($s, $start) : substr($s, $start, $len); }
}

function respond($success, $message = '', $extra = null)
{
    $out = array('success' => $success, 'message' => $message);
    if ($extra !== null) { $out = array_merge($out, $extra); }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Méthode non autorisée.');
}

$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
    respond(false, "Votre dossier dépasse la capacité du serveur.");
}

function clean_text($key)
{
    if (!isset($_POST[$key])) { return ''; }
    $v = (string) $_POST[$key];
    $v = preg_replace('/[^\P{C}\n\r\t]+/u', '', $v);
    return trim($v);
}

$textFields = array(
    'nom'                       => 'Nom',
    'prenoms'                   => 'Prénoms',
    'date_naissance'            => 'Date de naissance',
    'lieu_naissance'            => 'Lieu de naissance',
    'pays_naissance'            => 'Pays de naissance',
    'nationalite_actuelle'      => 'Nationalité actuelle',
    'nationalite_naissance'     => 'Nationalité à la naissance, si différente',
    'etat_civil'                => 'État civil',
    'adresse'                   => 'Adresse du domicile',
    'email'                     => 'E-mail',
    'email_confirme'            => 'Confirmation de l\'E-mail',
    'telephone'                 => 'Numéro(s) de téléphone',
    'profession'                => 'Profession actuelle',
    'salaire'                   => 'Salaire mensuel',
    'experience'                => "Nombre d'années d'expérience",
    'profil'                    => 'Titre demandé au sein de l\'APD',
    'motivation_profil'         => 'Pourquoi ce profil ?',
    'depl_facteur'              => "Dans l'affirmative, de quel facteur s'agit-il ?",
    'autre_nationalite_details' => "Renseignements utiles (autre nationalité)",
    'securite1'                 => 'Sécurité 1',
    'securite2'                 => 'Sécurité 2',
    'securite3'                 => 'Sécurité 3',
    'parrain'                   => 'Identité du parrain',
    'judiciaire_details'        => 'Rapport détaillé (judiciaire)',
    'q_conformite_def'          => 'Définition conformité administrative',
    'q_conformite_avis'         => 'Conformité : facultative ou indispensable ?',
    'q_attitude'                => 'Attitude concernant les règles juridiques',
    'q_apd'                     => 'Que savez-vous du Programme APD ?',
    'q_acteurs_apd'             => 'Qui finance le Programme APD ?',
    'q_mission_apd'             => 'Quelle est la Mission du Programme APD ?',
    'q_objectif_apd'            => "Objectif du recensement d'agents",
    'q_formation'               => 'Disposition à suivre nos formations',
    'q_impacts'                 => "Trois (03) idées d'impacts pour la communauté",
);

$radioFields = array(
    'sexe'              => 'Sexe',
    'apt_facteurs'      => 'a) Facteurs limitent-ils votre aptitude ?',
    'depl_facteurs'     => 'b) Facteurs limitent-ils vos déplacements ?',
    'autre_nationalite' => "Démarches pour acquérir autre nationalité ?",
    'judiciaire'        => 'Déclarations judiciaires',
);

$values = array();
$missing = array();

foreach ($textFields as $key => $label) {
    $values[$key] = clean_text($key);
    if ($values[$key] === '') { $missing[] = $label; }
    if (mb_strlen($values[$key]) > 20000) {
        $values[$key] = mb_substr($values[$key], 0, 20000);
    }
}

$allowedRadio = array('OUI', 'NON', 'Masculin', 'Féminin');
foreach ($radioFields as $key => $label) {
    $v = isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
    if ($v === '' || !in_array($v, $allowedRadio, true)) { $missing[] = $label; $v = ''; }
    $values[$key] = $v;
}

$certification = isset($_POST['certification']) && $_POST['certification'] !== '';
if (!$certification) { $missing[] = "Certification de l'exactitude des déclarations"; }

$langues = array();
if (isset($_POST['langues_json'])) {
    $decoded = json_decode((string) $_POST['langues_json'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $item) {
            if (!is_array($item) || !isset($item['langue'])) { continue; }
            $nom = trim(preg_replace('/[^\P{C}]+/u', '', (string) $item['langue']));
            if ($nom === '' || mb_strlen($nom) > 120) { continue; }
            $langues[] = array(
                'langue' => $nom,
                'lu'     => !empty($item['lu']),
                'parle'  => !empty($item['parle']),
                'ecrit'  => !empty($item['ecrit']),
            );
        }
    }
}
if (count($langues) === 0) { $missing[] = 'Langues maîtrisées'; }

$langueInterface = (isset($_POST['langue_interface']) && $_POST['langue_interface'] === 'en') ? 'en' : 'fr';

/* Fichiers */
$acceptedExts = array('jpg', 'jpeg', 'png', 'doc', 'docx', 'pdf');
$acceptedExtsList = 'JPG, JPEG, PNG, DOC, DOCX, PDF';
$acceptedMimes = array(
    'image/jpeg', 'image/png',
    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/pdf',
);

$fileFields = array(
    'photo'    => 'Photo',
    'cv'       => 'CV',
    'lettre'   => 'Lettre de motivation',
    'identite' => "Pièce d'identité ou passeport",
);

$files = array();
foreach ($fileFields as $key => $label) {
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
        $missing[] = $label;
        continue;
    }
    $f = $_FILES[$key];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        respond(false, 'Erreur de téléversement : ' . $label);
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $acceptedExts, true)) {
        respond(false, 'Format refusé pour ' . $label . '. Acceptés : ' . $acceptedExtsList . '. Reçu : .' . $ext);
    }
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($fi, $f['tmp_name']);
        finfo_close($fi);
        if (!in_array($mime, $acceptedMimes, true)) {
            respond(false, 'Contenu non conforme pour ' . $label);
        }
    }
    if (!is_uploaded_file($f['tmp_name'])) {
        respond(false, 'Téléversement invalide.');
    }
    $files[$key] = array('tmp' => $f['tmp_name'], 'ext' => $ext, 'orig' => $f['name']);
}

if (count($missing) > 0) {
    respond(false, 'Informations manquantes : ' . implode(' ; ', $missing));
}

if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
    respond(false, "L'adresse e-mail saisie n'est pas valide.");
}
if (!filter_var($values['email_confirme'], FILTER_VALIDATE_EMAIL)) {
    respond(false, "L'adresse e-mail de confirmation n'est pas valide.");
}
if (strtolower(trim($values['email'])) !== strtolower(trim($values['email_confirme']))) {
    respond(false, 'Les deux adresses e-mail ne sont pas identiques.');
}

/* ID unique */
function sanitize_id_part($s) {
    $s = preg_replace('/[^A-Za-z0-9_-]+/', '_', $s);
    $s = trim($s, '_');
    if ($s === '') { $s = 'X'; }
    return mb_substr($s, 0, 10);
}
$nomPart = sanitize_id_part($values['nom']);
$prenomsPart = sanitize_id_part($values['prenoms']);
$age = '';
if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $values['date_naissance'], $m)) {
    $birth = new DateTime($values['date_naissance']);
    $now = new DateTime();
    $diff = $now->diff($birth);
    $age = (string) $diff->y;
}
if ($age === '' || $age === '0') { $age = 'NA'; }
try {
    $rand = bin2hex(random_bytes(3));
} catch (Exception $e) {
    $rand = substr(md5(uniqid('', true)), 0, 6);
}
$uniqueId = $nomPart . '-' . $prenomsPart . '-' . $age . '-' . $rand;

/* Archivage */
$dataDir = GIRH_DATA_DIR;
$subsDir = $dataDir . '/submissions';
foreach (array($dataDir, $subsDir) as $d) {
    if (!is_dir($d)) { @mkdir($d, 0755, true); }
}
$ht = $dataDir . '/.htaccess';
if (!file_exists($ht)) { @file_put_contents($ht, "Require all denied\nDeny from all\n"); }
@file_put_contents($dataDir . '/index.html', '');
@file_put_contents($subsDir . '/index.html', '');

$subDir = $subsDir . '/' . $uniqueId;
if (!@mkdir($subDir, 0755, true)) {
    respond(false, "Erreur interne d'archivage. Veuillez réessayer.");
}

$attachmentPaths = array();
foreach ($files as $key => $f) {
    $dest = $subDir . '/' . $fileFields[$key] . '.' . $f['ext'];
    if (!move_uploaded_file($f['tmp'], $dest)) {
        respond(false, "Erreur lors de l'enregistrement des fichiers.");
    }
    @chmod($dest, 0644);
    $attachmentPaths[$key] = $dest;
}

/* Récapitulatif TXT */
$txtContent = "PROGRAMME APD (ODA) — DOSSIER D'INSCRIPTION\r\n";
$txtContent .= "Recensement d'agents\r\n";
$txtContent .= str_repeat('=', 80) . "\r\n";
$txtContent .= sprintf("%-45s %s\r\n", 'Identifiant du dossier :', $uniqueId);
$txtContent .= sprintf("%-45s %s\r\n", 'Date de soumission :', date('d/m/Y H:i:s'));
$txtContent .= str_repeat('=', 80) . "\r\n\r\n";

function txt_section(&$txt, $title) {
    $txt .= "\r\n" . str_repeat('-', 80) . "\r\n";
    $txt .= strtoupper($title) . "\r\n";
    $txt .= str_repeat('-', 80) . "\r\n";
}

foreach (array('nom','prenoms','date_naissance','lieu_naissance','pays_naissance','nationalite_actuelle','nationalite_naissance') as $k) {
    $txtContent .= sprintf("%-45s %s\r\n", $textFields[$k] . ' :', $values[$k]);
}
$txtContent .= sprintf("%-45s %s\r\n", "Sexe :", $values['sexe']);
$txtContent .= sprintf("%-45s %s\r\n", "État civil :", $values['etat_civil']);
foreach (array('adresse','email','email_confirme','telephone') as $k) {
    $txtContent .= sprintf("%-45s %s\r\n", $textFields[$k] . ' :', $values[$k]);
}

txt_section($txtContent, 'SITUATION PROFESSIONNELLE');
foreach (array('profession','salaire','experience','profil','motivation_profil') as $k) {
    $txtContent .= sprintf("%-45s %s\r\n", $textFields[$k] . ' :', $values[$k]);
}

txt_section($txtContent, 'LANGUES MAÎTRISÉES');
foreach ($langues as $lg) {
    $caps = array();
    if ($lg['lu'])    { $caps[] = 'Lu'; }
    if ($lg['parle']) { $caps[] = 'Parlé'; }
    if ($lg['ecrit']) { $caps[] = 'Écrit'; }
    $txtContent .= sprintf("  %s (%s)\r\n", $lg['langue'], implode(' / ', $caps));
}

txt_section($txtContent, 'APTITUDES ET DÉPLACEMENTS');
$txtContent .= sprintf("%-45s %s\r\n", $radioFields['apt_facteurs'] . ' :', $values['apt_facteurs']);
$txtContent .= sprintf("%-45s %s\r\n", $radioFields['depl_facteurs'] . ' :', $values['depl_facteurs']);
$txtContent .= sprintf("%-45s %s\r\n", $textFields['depl_facteur'] . ' :', $values['depl_facteur']);
$txtContent .= sprintf("%-45s %s\r\n", $radioFields['autre_nationalite'] . ' :', $values['autre_nationalite']);
$txtContent .= sprintf("%-45s %s\r\n", $textFields['autre_nationalite_details'] . ' :', $values['autre_nationalite_details']);

txt_section($txtContent, 'ESPACES NUMÉRIQUES SÉCURISÉS');
$txtContent .= sprintf("%-45s %s\r\n", "Sécurité 1 :", $values['securite1']);
$txtContent .= sprintf("%-45s %s\r\n", "Sécurité 2 :", $values['securite2']);
$txtContent .= sprintf("%-45s %s\r\n", "Sécurité 3 :", $values['securite3']);
$txtContent .= sprintf("%-45s %s\r\n", $textFields['parrain'] . ' :', $values['parrain']);

txt_section($txtContent, 'DÉCLARATIONS JUDICIAIRES');
$txtContent .= sprintf("%-45s %s\r\n", $radioFields['judiciaire'] . ' :', $values['judiciaire']);
$txtContent .= sprintf("%-45s %s\r\n", $textFields['judiciaire_details'] . ' :', $values['judiciaire_details']);

txt_section($txtContent, "QUESTIONNAIRE D'ÉVALUATION");
foreach (array('q_conformite_def','q_conformite_avis','q_attitude','q_apd','q_acteurs_apd','q_mission_apd','q_objectif_apd','q_formation','q_impacts') as $k) {
    $txtContent .= sprintf("%-45s %s\r\n", $textFields[$k] . ' :', $values[$k]);
}

txt_section($txtContent, 'CERTIFICATION');
$txtContent .= "Je certifie que mes déclarations sont vraies, complètes et exactes. : OUI\r\n\r\n";
$txtContent .= "Fichiers joints :\r\n";
foreach ($files as $key => $f) {
    $txtContent .= "  - " . $fileFields[$key] . ' (' . $f['ext'] . ') : ' . $f['orig'] . "\r\n";
}

$txtPath = $subDir . '/Recapitulatif_Inscription_APD.txt';
@file_put_contents($txtPath, $txtContent, LOCK_EX);

@file_put_contents(
    $subDir . '/metadonnees.json',
    json_encode(array(
        'id'              => $uniqueId,
        'date'            => date('c'),
        'langue_interface' => $langueInterface,
        'nom'             => $values['nom'],
        'prenoms'         => $values['prenoms'],
        'age'             => $age,
        'valeurs'         => $values,
        'langues'         => $langues,
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    LOCK_EX
);

$registry = $dataDir . '/registre.csv';
$line = '"' . str_replace('"', '""', $uniqueId) . '","' . date('c') . '","'
    . str_replace('"', '""', $values['nom']) . '","'
    . str_replace('"', '""', $values['prenoms']) . '","'
    . str_replace('"', '""', $values['email']) . '","'
    . str_replace('"', '""', $values['profil']) . '"' . "\n";
$fh = @fopen($registry, 'ab');
if ($fh) {
    if (flock($fh, LOCK_EX)) { fwrite($fh, $line); fflush($fh); flock($fh, LOCK_UN); }
    fclose($fh);
}

respond(true, 'Dossier archivé.', array(
    'id' => $uniqueId,
));
