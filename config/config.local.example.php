<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * MilesWeb MariaDB Private Configuration Template
 * 
 * Instructions:
 * 1. Copy this file to: config/config.local.php
 * 2. Fill in your actual MariaDB password below.
 * 3. Upload config/config.local.php to your MilesWeb cPanel.
 * 
 * This file is automatically ignored by Git (.gitignore) and will never be pushed to GitHub.
 */

return [
    'DB_TYPE'   => 'mysql',
    'DB_NAME'   => 'mubix_in1_bpl_season3',
    'DB_USER'   => 'mubix_in1_bpluser',
    'DB_PASS'   => 'YOUR_ACTUAL_MARIADB_PASSWORD_HERE', // <-- Enter your MilesWeb MariaDB user password
    'DB_SOCKET' => '/run/mysqld/mysqld.sock',
    'DB_HOST'   => '103.108.220.222',
    'DB_PORT'   => '3306',
];
