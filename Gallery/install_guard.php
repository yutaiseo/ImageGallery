<?php
if (!file_exists(__DIR__ . '/../ctrol/config/config.php')) {
    header('Location: /install/');
    exit;
}
