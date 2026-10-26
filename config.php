<?php
/**
 * Pulsar · Configuració
 *
 * Usuaris: el valor pot ser un hash generat amb `php bin/hash.php "la-teva-clau"`
 * (recomanat) o una contrasenya en text pla.
 *
 * Usuari per defecte: admin / pulsar2026  ← CANVIA’L abans de publicar.
 */
return [
    'app_name'     => 'Pulsar',

    // Apareix a la portada i al peu dels PDF.
    'company'      => 'AFI Informàtica',
    'company_url'  => 'https://aficat.com',

    'users' => [
        'admin' => '$2y$10$ec1bKrIr6BT6h2H3qGhvf.HoAcnidgM6XOcCghZtFVTkF1QavDJM6',
    ],

    'timezone'          => 'Europe/Madrid',
    'session_name'      => 'pulsar_session',
    'session_lifetime'  => 60 * 60 * 8,      // segons d’inactivitat abans de tancar la sessió

    // Inici de sessió: bloqueig després de N intents fallits durant X segons.
    'login_max_attempts' => 5,
    'login_lock_seconds' => 900,

    // Anàlisi
    'max_urls_seo'       => 20,
    'max_urls_geo'       => 10,
    'http_timeout'       => 15,
    'user_agent'         => 'Mozilla/5.0 (compatible; PulsarAudit/1.0; +https://example.com)',
    // Permetre analitzar IP privades / localhost (desactivat per seguretat).
    'allow_private_hosts' => false,

    // Dies que es conserven els informes a storage/reports.
    'report_retention_days' => 30,
];
