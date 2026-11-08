<?php

/**
 * @file
 * Demo settings: everything comes from the environment (see docs/DEVELOPER.md).
 */

$url = parse_url((string) getenv('DATABASE_URL'));
$databases['default']['default'] = [
  'driver' => 'mysql',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
  'host' => $url['host'] ?? 'localhost',
  'port' => $url['port'] ?? 3306,
  'username' => urldecode($url['user'] ?? ''),
  'password' => urldecode($url['pass'] ?? ''),
  'database' => getenv('DRUPAL_DB_NAME') ?: (ltrim($url['path'] ?? '', '/') ?: 'drupal'),
  'collation' => 'utf8mb4_general_ci',
  // Drush's mysql client verifies the server certificate (Railway's MySQL is
  // self-signed); PHP's own connection doesn't use TLS here.
  'pdo' => [
    (defined('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT') ? \Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT : \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT) => FALSE,
  ],
  'init_commands' => [
    'isolation_level' => 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED',
  ],
];

// Derived from DATABASE_URL so sessions survive redeploys (override with DRUPAL_HASH_SALT).
$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: hash('sha256', 'drupal-demo:' . getenv('DATABASE_URL'));
$settings['config_sync_directory'] = '/opt/drupal/config/sync';
$settings['file_private_path'] = '/opt/drupal/private';
$settings['update_free_access'] = FALSE;
$settings['skip_permissions_hardening'] = TRUE;

// Railway terminates TLS in a proxy in front of the container.
$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = [$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'];
$settings['trusted_host_patterns'] = ['.*'];

// The container is rebuilt on every deploy: no module installs from the UI.
$settings['allow_authorize_operations'] = FALSE;
