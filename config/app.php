<?php

// Les valeurs viennent du fichier .env (chargé par docker-compose), jamais écrites ici.
return [
    'db' => [
        'host'     => getenv('DB_HOST') ?: 'db',
        'name'     => getenv('DB_NAME') ?: 'toptrump',
        'user'     => getenv('DB_USER') ?: 'toptrump',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
    'session_days' => (int) (getenv('SESSION_DAYS') ?: 30),

    // Le Labo (/dev) : outils de test, éteints par défaut. Il faut DEUX conditions : DEV_TOOLS=1 ET être connecté avec un pseudo de DEV_USERS.
    'dev' => [
        'enabled'       => filter_var(getenv('DEV_TOOLS') ?: '0', FILTER_VALIDATE_BOOLEAN),
        'users'         => array_values(array_filter(array_map('trim', explode(',', getenv('DEV_USERS') ?: '')))),
        'test_prefix'   => 'test_',                                // seuls les comptes qui commencent ainsi peuvent être créés / supprimés par le Labo
        'test_password' => getenv('DEV_TEST_PASSWORD') ?: 'labo-test-1234',
    ],
];
