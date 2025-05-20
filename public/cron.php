<?php
require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';

// Пример: запускать задачи, рассылки, чистки, интеграции и т.д.
echo "Cron job started at " . date('c') . "\n";
