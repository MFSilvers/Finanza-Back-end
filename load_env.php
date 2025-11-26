<?php

/**
 * Carica variabili d'ambiente da file .env
 * Questo file può essere incluso all'inizio di index.php o nei file di configurazione
 */

function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignora commenti
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Rimuovi virgolette se presenti
            $value = trim($value, '"\'');
            
            // Imposta variabile d'ambiente solo se non esiste già
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

// Carica .env dalla root del backend
loadEnv(__DIR__ . '/.env');

