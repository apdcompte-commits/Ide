<?php
/* Redirection vers /dossier (URL propre, sans .php) */
$qs = $_SERVER['QUERY_STRING'] ?? '';
$target = '/dossier' . ($qs !== '' ? '?' . $qs : '');
header('Location: ' . $target, true, 301);
exit;
