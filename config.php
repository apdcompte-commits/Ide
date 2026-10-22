<?php
/* ============================================================
   GRH INTERNATIONAL (GIRH) — Configuration du serveur
   ============================================================ */

/* -------- 1. ADRESSE DE RÉCEPTION DES CANDIDATURES -------- */
define('GIRH_MAIL_TO', 'invest-initiative@africamail.com');

/* Expéditeur technique des e-mails */
define('GIRH_MAIL_FROM', 'no-reply@' . (isset($_SERVER['HTTP_HOST']) ? preg_replace('/^www\./', '', strtok($_SERVER['HTTP_HOST'], ':')) : 'girh-international.org'));

/* -------- 2. STOCKAGE -------- */
define('GIRH_DATA_DIR', __DIR__ . '/data');
