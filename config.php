<?php
/* ============================================================
   PROGRAMME APD (ODA) — Configuration du serveur
   ============================================================ */

/* -------- 1. ADRESSE DE RÉCEPTION DES CANDIDATURES -------- */
define('GIRH_MAIL_TO', 'afriprogramdev@secretary.net');

/* Expéditeur technique des e-mails */
define('GIRH_MAIL_FROM', 'no-reply@' . (isset($_SERVER['HTTP_HOST']) ? preg_replace('/^www\./', '', strtok($_SERVER['HTTP_HOST'], ':')) : 'apd-programme.org'));

/* -------- 2. STOCKAGE -------- */
define('GIRH_DATA_DIR', __DIR__ . '/data');
