<?php
/* ============================================================
   GRH INTERNATIONAL (GIRH) — Traitement du formulaire
   - Validation serveur (types acceptés: jpg/jpeg/png/doc/docx/pdf)
   - Aucune limite de taille
   - Génération d'un récapitulatif Excel (.xlsx)
   - Archivage des fichiers avec identifiant unique lisible
   - Retourne l'ID pour ouverture du mailto côté client
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

/* ------------------------------------------------------------
   0. DÉTECTION D'UNE REQUÊTE TROP VOLUMINEUSE POUR LE SERVEUR
   ------------------------------------------------------------ */
$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
    respond(false, "Votre dossier dépasse la capacité du serveur. "
        . "Veuillez réduire le poids de vos fichiers puis soumettre à nouveau.");
}

/* ------------------------------------------------------------
   1. LECTURE ET NETTOYAGE DES CHAMPS TEXTE
   ------------------------------------------------------------ */
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
    'salaire'                   => 'Salaire mensuel (dernier poste ou poste actuel)',
    'experience'                => "Nombre d'années d'expérience",
    'profil'                    => 'Profil de partenariat demandé',
    'motivation_profil'         => 'Pourquoi êtes-vous intéressé(e) par ce profil ?',
    'depl_pays'                 => "Dans l'affirmative, de quel pays s'agit-il ?",
    'autre_nationalite_details' => "Renseignements utiles (autre nationalité)",
    'securite1'                 => 'Sécurité 1',
    'securite2'                 => 'Sécurité 2',
    'securite3'                 => 'Sécurité 3',
    'parrain'                   => 'Identité complète du parrain / de la personne ayant informé',
    'judiciaire_details'        => 'Rapport détaillé et complet de chaque cas (judiciaire)',
    'q_conformite_def'          => 'Définition de la conformité administrative et juridique',
    'q_conformite_avis'         => 'Conformité : facultative ou indispensable ?',
    'q_attitude'                => 'Attitude concernant les règles juridiques en vigueur',
    'q_ide'                     => "Résumé sur l'Investissement Direct Étranger en Afrique",
    'q_acteurs'                 => 'Acteurs qui financent les initiatives IDE(s) en Afrique',
    'q_grh'                     => 'Connaissances sur le GRH International',
    'q_implication'             => "Implication du GRH International dans les initiatives IDE(s)",
    'q_objectif'                => 'Objectif du recensement des partenaires associés',
    'q_formation'               => 'Disposition à suivre les formations (une phrase)',
    'q_impacts'                 => "Trois (03) idées d'impacts positifs pour la communauté",
);

$radioFields = array(
    'sexe'              => 'Sexe',
    'apt_facteurs'      => 'a) Certains facteurs limitent-ils votre aptitude ?',
    'depl_facteurs'     => 'b) Certains facteurs limitent-ils vos possibilités de déplacement sur le territoire national ?',
    'autre_nationalite' => "Démarches officielles pour acquérir une autre nationalité ?",
    'judiciaire'        => 'Déclarations judiciaires (arrestation, inculpation, condamnation) ?',
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

/* Langues maîtrisées */
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

/* ------------------------------------------------------------
   2. VALIDATION DES FICHIERS JOINTS
   Types acceptés pour chaque champ : JPG, JPEG, PNG, DOC, DOCX, PDF
   Aucune limite de taille (sauf celle imposée par le serveur)
   ------------------------------------------------------------ */
$acceptedExts = array('jpg', 'jpeg', 'png', 'doc', 'docx', 'pdf');
$acceptedExtsList = 'JPG, JPEG, PNG, DOC, DOCX, PDF';

$acceptedMimes = array(
    'image/jpeg',
    'image/png',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
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
        respond(false, 'Erreur de téléversement pour : ' . $label
            . '. Formats acceptés : ' . $acceptedExtsList . '.');
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $acceptedExts, true)) {
        respond(false, 'Format refusé pour : ' . $label
            . '. Formats acceptés : ' . $acceptedExtsList
            . '. Extension reçue : .' . $ext);
    }
    /* Vérification MIME si disponible */
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($fi, $f['tmp_name']);
        finfo_close($fi);
        if (!in_array($mime, $acceptedMimes, true)) {
            respond(false, 'Contenu non conforme pour : ' . $label
                . '. Formats acceptés : ' . $acceptedExtsList . '.');
        }
    }
    if (!is_uploaded_file($f['tmp_name'])) {
        respond(false, 'Téléversement invalide pour : ' . $label . '.');
    }
    $files[$key] = array('tmp' => $f['tmp_name'], 'ext' => $ext, 'orig' => $f['name']);
}

if (count($missing) > 0) {
    respond(false, 'Informations manquantes : ' . implode(' ; ', $missing));
}

/* Validation stricte des adresses e-mail */
if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
    respond(false, "L'adresse e-mail saisie n'est pas valide.");
}
if (!filter_var($values['email_confirme'], FILTER_VALIDATE_EMAIL)) {
    respond(false, "L'adresse e-mail de confirmation n'est pas valide.");
}
if (strtolower(trim($values['email'])) !== strtolower(trim($values['email_confirme']))) {
    respond(false, 'Les deux adresses e-mail saisies ne sont pas identiques.');
}

/* ------------------------------------------------------------
   3. GÉNÉRATION DE L'IDENTIFIANT UNIQUE
   Format : Nom-Prenoms-Age-Hex6
   Exemple : DUPONT-Jean-34-a3f8c2
   ------------------------------------------------------------ */
function sanitize_id_part($s) {
    $s = preg_replace('/[^A-Za-z0-9_-]+/', '_', $s);
    $s = trim($s, '_');
    if ($s === '') { $s = 'X'; }
    return mb_substr($s, 0, 10);
}

$nomPart = sanitize_id_part($values['nom']);
$prenomsPart = sanitize_id_part($values['prenoms']);

/* Calcul de l'âge à partir de la date de naissance */
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

/* ID unique complet */
$uniqueId = $nomPart . '-' . $prenomsPart . '-' . $age . '-' . $rand;

/* ------------------------------------------------------------
   4. ARCHIVAGE DES FICHIERS
   Chaque dossier = data/submissions/{uniqueId}/
   ------------------------------------------------------------ */
$dataDir = GIRH_DATA_DIR;
$subsDir = $dataDir . '/submissions';
foreach (array($dataDir, $subsDir) as $d) {
    if (!is_dir($d)) { @mkdir($d, 0755, true); }
}
$ht = $dataDir . '/.htaccess';
if (!file_exists($ht)) {
    @file_put_contents($ht, "Require all denied\nDeny from all\n");
}
@file_put_contents($dataDir . '/index.html', '');
@file_put_contents($subsDir . '/index.html', '');

$subDir = $subsDir . '/' . $uniqueId;
if (!@mkdir($subDir, 0755, true)) {
    respond(false, "Erreur interne d'archivage. Veuillez réessayer.");
}

/* Déplacement des pièces jointes */
$attachmentPaths = array();
foreach ($files as $key => $f) {
    $dest = $subDir . '/' . $fileFields[$key] . '.' . $f['ext'];
    if (!move_uploaded_file($f['tmp'], $dest)) {
        respond(false, "Erreur interne lors de l'enregistrement des pièces jointes.");
    }
    @chmod($dest, 0644);
    $attachmentPaths[$key] = $dest;
}

/* ------------------------------------------------------------
   5. GÉNÉRATION DU RÉCAPITULATIF TXT STRUCTURÉ
   ------------------------------------------------------------ */
$txtContent = '';
$txtContent .= "GRH INTERNATIONAL (GIRH) — DOSSIER D'INSCRIPTION\r\n";
$txtContent .= "Recensement des partenaires associés\r\n";
$txtContent .= str_repeat('=', 80) . "\r\n";
$txtContent .= sprintf("%-45s %s\r\n", 'Identifiant du dossier :', $uniqueId);
$txtContent .= sprintf("%-45s %s\r\n", 'Date de soumission :', date('d/m/Y H:i:s'));
$txtContent .= str_repeat('=', 80) . "\r\n\r\n";

function txt_section(&$txt, $title) {
    $txt .= "\r\n" . str_repeat('-', 80) . "\r\n";
    $txt .= strtoupper($title) . "\r\n";
    $txt .= str_repeat('-', 80) . "\r\n";
}

foreach (array('nom', 'prenoms', 'date_naissance', 'lieu_naissance', 'pays_naissance', 'nationalite_actuelle', 'nationalite_naissance') as $k) {
    $txtContent .= sprintf("%-45s %s\r\n", $textFields[$k] . ' :', $values[$k]);
}
$txtContent .= sprintf("%-45s %s\r\n", "Sexe :", $values['sexe']);
$txtContent .= sprintf("%-45s %s\r\n", "État civil :", $values['etat_civil']);
foreach (array('adresse', 'email', 'email_confirme', 'telephone') as $k) {
    $txtContent .= sprintf("%-45s %s\r\n", $textFields[$k] . ' :', $values[$k]);
}

txt_section($txtContent, 'SITUATION PROFESSIONNELLE');
foreach (array('profession', 'salaire', 'experience', 'profil', 'motivation_profil') as $k) {
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
$txtContent .= sprintf("%-45s %s\r\n", $textFields['depl_pays'] . ' :', $values['depl_pays']);
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
foreach (array('q_conformite_def', 'q_conformite_avis', 'q_attitude', 'q_ide', 'q_acteurs', 'q_grh', 'q_implication', 'q_objectif', 'q_formation', 'q_impacts') as $k) {
    $txtContent .= sprintf("%-45s %s\r\n", $textFields[$k] . ' :', $values[$k]);
}

txt_section($txtContent, 'CERTIFICATION');
$txtContent .= "Je certifie que mes déclarations en réponse aux questions sont vraies, complètes et exactes. : OUI\r\n\r\n";
$txtContent .= "Fichiers joints :\r\n";
foreach ($files as $key => $f) {
    $txtContent .= "  - " . $fileFields[$key] . ' (' . $f['ext'] . ') : ' . $f['orig'] . "\r\n";
}

$txtPath = $subDir . '/Recapitulatif_Inscription_GIRH.txt';
@file_put_contents($txtPath, $txtContent, LOCK_EX);

/* Métadonnées JSON */
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
        'fichiers'         => array(),
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    LOCK_EX
);

/* Registre global */
$registry = $dataDir . '/registre.csv';
$line = '"' . str_replace('"', '""', $uniqueId) . '","' . date('c') . '","'
    . str_replace('"', '""', $values['nom']) . '","'
    . str_replace('"', '""', $values['prenoms']) . '","'
    . str_replace('"', '""', $values['email']) . '","'
    . str_replace('"', '""', $values['profil']) . '"' . "\n";
$fh = @fopen($registry, 'ab');
if ($fh) {
    if (flock($fh, LOCK_EX)) {
        fwrite($fh, $line);
        fflush($fh);
        flock($fh, LOCK_UN);
    }
    fclose($fh);
}

/* ------------------------------------------------------------
   6. RÉPONSE — retourne l'ID unique pour le mailto
   ------------------------------------------------------------ */
$siteUrl = (isset($_SERVER['HTTP_HOST']) ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) : 'monsite.com');
$linkRecuperation = $siteUrl . '/recuperer.php?id=' . urlencode($uniqueId);

respond(true, 'Dossier archivé.', array(
    'id' => $uniqueId,
    'url' => $linkRecuperation,
));
