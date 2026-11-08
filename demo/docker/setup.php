<?php

/**
 * @file
 * Demo setup, run with `drush php:script` on every start (idempotent): languages,
 * content translation, the demo accounts (created once, never changed), the Supertext translator and sample content.
 */

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\tmgmt\Entity\Translator;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

$log = static function (string $msg): void {
  echo "[demo] $msg\n";
};

// Languages: English (default), German, French, Italian.
foreach (['de', 'fr', 'it'] as $langcode) {
  if (!ConfigurableLanguage::load($langcode)) {
    ConfigurableLanguage::createFromLangcode($langcode)->save();
    $log("Added language $langcode");
  }
}

// Translatable pages and articles.
$translation_manager = \Drupal::service('content_translation.manager');
foreach (['page', 'article'] as $bundle) {
  $settings = ContentLanguageSettings::loadByEntityTypeBundle('node', $bundle);
  if (!$translation_manager->isEnabled('node', $bundle)) {
    $settings->setLanguageAlterable(TRUE)->setDefaultLangcode('site_default')->save();
    $translation_manager->setEnabled('node', $bundle, TRUE);
    $log("Content translation on for $bundle");
  }
}
\Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
\Drupal::service('router.builder')->rebuild();

// Content editor role (core recipe): also translate content and run translation jobs.
$role_id = Role::load('content_editor') ? 'content_editor' : 'editor';
$role = Role::load($role_id) ?: Role::create(['id' => $role_id, 'label' => 'Editor']);
$wanted = [
  'access administration pages', 'access content overview', 'access toolbar',
  'view the administration theme', 'access contextual links',
  'create page content', 'edit any page content', 'delete any page content',
  'create article content', 'edit any article content', 'delete any article content',
  'view own unpublished content', 'view all revisions',
  'create content translations', 'update content translations', 'delete content translations',
  'translate any entity', 'translate editable entities',
  'create translation jobs', 'submit translation jobs', 'accept translation jobs',
  'access tmgmt sources overview',
];
$available = array_keys(\Drupal::service('user.permissions')->getPermissions());
foreach (array_intersect($wanted, $available) as $permission) {
  $role->grantPermission($permission);
}
$role->save();

// Accounts from the variables, created once. Existing accounts are never changed
// (no password resets from variables); the log names the variable, never the password.
$account = static function (string $name, string $prefix, ?string $role) use ($log): void {
  $mail = (string) getenv($prefix . '_EMAIL');
  $pass = (string) getenv($prefix . '_PASSWORD');
  if ($mail === '' || $pass === '') {
    return;
  }
  $storage = \Drupal::entityTypeManager()->getStorage('user');
  if ($storage->loadByProperties(['mail' => $mail])) {
    $log("{$prefix}_EMAIL: account exists, left unchanged");
    return;
  }
  $user = User::create([
    'name' => $storage->loadByProperties(['name' => $name]) ? $mail : $name,
    'mail' => $mail,
    'pass' => $pass,
    'status' => 1,
  ]);
  if ($role) {
    $user->addRole($role);
  }
  $violations = $user->validate();
  if ($violations->count()) {
    $log("{$prefix}_EMAIL: account skipped: " . (string) $violations->get(0)->getMessage());
    return;
  }
  $user->save();
  $log("{$prefix}_EMAIL: created the account");
};
// The administrator is created by the installer from DEMO_ADMIN_*.
$account('admin', 'DEMO_ADMIN', 'administrator');
$account('editor', 'DEMO_EDITOR', $role_id);

// The Supertext AI translator. The API key is only seeded while empty, so a key
// entered in the UI stays.
$translator = Translator::load('supertext_ai') ?: Translator::create([
  'name' => 'supertext_ai',
  'label' => 'Supertext AI',
  'description' => 'AI translation by Supertext',
  'plugin' => 'supertext_ai',
  'auto_accept' => FALSE,
  'settings' => ['api_key' => '', 'api_server' => 'live', 'api_server_other' => ''],
]);
$changed = $translator->isNew();
$key = (string) getenv('SUPERTEXT_API_KEY');
if ($key !== '' && !$translator->getSetting('api_key')) {
  $translator->setSetting('api_key', $key);
  $changed = TRUE;
}
$server = (string) getenv('SUPERTEXT_ENVIRONMENT');
if ($server !== '' && $translator->isNew()) {
  $translator->setSetting('api_server', $server);
}
// Supertext needs regional target codes (a bare "de" is rejected with
// INVALID_LANGUAGE_PAIR); the source stays the bare language. Swiss variants for
// the demo, set only while a language has no mapping of its own.
$mappings = (array) $translator->get('remote_languages_mappings');
foreach (['de' => 'de-CH', 'fr' => 'fr-CH', 'it' => 'it-CH'] as $local => $remote) {
  if (empty($mappings[$local]) || $mappings[$local] === $local) {
    $mappings[$local] = $remote;
    $changed = TRUE;
  }
}
$mappings['en'] = $mappings['en'] ?? 'en';
$translator->set('remote_languages_mappings', $mappings);
if ($changed) {
  $translator->save();
  $log('Saved the Supertext AI translator');
}
// Supertext first in the translator lists.
if ((int) $translator->get('weight') !== -10) {
  $translator->set('weight', -10)->save();
}

// Sample content in English, created once.
$state = \Drupal::state();
if (!$state->get('supertext_demo.content')) {
  $items = [
    ['page', 'Welcome to the Supertext demo', '<p>This Drupal site runs the Translation Management Tool (TMGMT) with the Supertext AI translator. Write in English, then request a translation into German, French or Italian — it comes back in seconds.</p><h2>Why Supertext?</h2><ul><li>Swiss quality since 2005</li><li>AI translation trained on your terminology</li><li>Human review when it matters</li></ul>', TRUE],
    ['page', 'About us', '<p>We are a small team from Zurich that loves good language. Our products help companies speak to their customers in every language they need, without losing their voice.</p>', FALSE],
    ['page', 'Contact', '<p>Questions about the demo? Write to us at hello@supertext.com or call us during office hours. We usually answer within one working day.</p>', FALSE],
    ['article', 'Five tips for translating your website', '<p>Start with the pages your visitors see most. Keep sentences short and clear. Agree on key terms before you translate. Let a native speaker review headlines and calls to action. And plan updates: a translated site is never finished.</p>', FALSE],
    ['article', 'Swiss German, Standard German or both?', '<p>Many Swiss companies write in Standard German with Swiss spelling — no ß, and words like Velo instead of Fahrrad. For marketing, a touch of Swiss German can make all the difference.</p>', FALSE],
  ];
  foreach ($items as [$type, $title, $body, $front]) {
    $node = Node::create([
      'type' => $type,
      'title' => $title,
      'langcode' => 'en',
      'body' => ['value' => $body, 'format' => 'basic_html'],
      'status' => 1,
      'uid' => 1,
      'promote' => $type === 'article' ? 1 : 0,
    ]);
    $node->save();
    if ($front) {
      \Drupal::configFactory()->getEditable('system.site')->set('page.front', '/node/' . $node->id())->save();
    }
  }
  $state->set('supertext_demo.content', TRUE);
  $log('Created the sample content');
}
