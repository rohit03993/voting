<?php
/**
 * Copy this file to config.php and fill in your hosting details.
 * Never upload config.sample.php with real passwords as config.php in public repos.
 */
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'hcs_voting',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'HCS Student Council Elections',
        'base_url' => '', // e.g. https://horizonclasses.com/vote  (no trailing slash). Leave blank to auto-detect.
        'timezone' => 'Asia/Kolkata',
        'session_name' => 'hcs_vote_sess',
    ],
    'security' => [
        // Change these after install
        'admin_user' => 'admin',
        'admin_pass' => 'Horizon@Vote2026',
        'principal_passcode' => '654321',
        'director_passcode' => '987654',
    ],
];
