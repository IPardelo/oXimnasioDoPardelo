<?php
// Copy this file to config.php (same folder) and fill in your own values.
// config.php is never overwritten by an update — it holds your secrets, keep it out of git.

// Everything else — site URL, Google Sign-In client ID, app name, admin lists, session length,
// the invite code required to create an account — lives in the database instead, in the
// ximnasio_pardelo_configuracion table (see schema.sql). Import schema.sql, then edit that table's rows
// via phpMyAdmin to set them for your own instance. This file only holds what the app needs
// just to reach that table in the first place: the database credentials, and two signing
// secrets that are more sensitive than the rest and don't need editing after you generate them.

return [
  // Database — create these in your hosting's control panel (IONOS: "Databases & FTP" →
  // "MariaDB databases"), then import schema.sql into it once via phpMyAdmin.
  'db' => [
    'host' => 'localhost',
    'name' => 'dbXXXXXXXX',
    'user' => 'dbXXXXXXXX',
    'pass' => 'change-me',
    // Optional — only add this line if your host's panel gives you a specific port. Leave it
    // out entirely to use MariaDB's default (3306), which covers most hosts, IONOS included.
    // 'port' => 3306,
  ],

  // Random long string used to sign session cookies. Generate one with:
  //   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
  // Changing this signs everyone out everywhere.
  'session_secret' => 'change-me-to-a-random-64-char-hex-string',

  // Optional: require ?key=... on cron.php so a random URL guess can't trigger it. Generate one
  // the same way as session_secret. Leave blank to leave cron.php open (fine — it only ever
  // sends notifications, it can't read or change anyone's data).
  'cron_secret' => '',
];
