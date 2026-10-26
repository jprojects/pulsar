<?php
// Ús: php bin/hash.php "la-meva-contrasenya"  → enganxa el resultat a config.php
if (PHP_SAPI !== 'cli') {
    exit;
}
if ($argc < 2) {
    fwrite(STDERR, "Ús: php bin/hash.php \"contrasenya\"\n");
    exit(1);
}
echo password_hash($argv[1], PASSWORD_DEFAULT), PHP_EOL;
