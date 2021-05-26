<?php

declare(strict_types=1);

error_reporting(E_ALL | E_STRICT);
date_default_timezone_set('UTC');

use Github\Client as GitHubClient;
use Github\HttpClient\Builder;
use GuzzleHttp\Client as GuzzleClient;

if (!file_exists(__DIR__.'/vendor/autoload.php')) {
    throw new \RuntimeException('Did not find vendor/autoload.php. Did you run "composer install"?');
}

if (!file_exists(__DIR__.'/config.php')) {
    throw new \RuntimeException('Did not find config.php, copy the "config.php.dist" to config.php and change it.');
}

require __DIR__.'/vendor/autoload.php';

$config = require __DIR__.'/config.php';

// Some sanity checking
if (!isset($config['token']) || 'change-me' === $config['token']) {
    throw new \RuntimeException(
        'Hmm. It seems you forgot to change the "token" in config.php. Please do this before trying again.'
    );
}

if (!isset($config['labels']) || !is_array($config['labels']) || [] === $config['labels']) {
    throw new \RuntimeException('I give-up! I refuse to do nothing. Configure the labels in config.php');
}

$dryRun = !empty($_SERVER['argv'][1]) && '--dry-run' === $_SERVER['argv'][1];
$autoRemove = 'yes' === getenv('GITHUB_AUTO_REMOVE_LABEL');

if (empty($_SERVER['argv'][$dryRun ? 3 : 1]) || empty($_SERVER['argv'][2])) {
    echo "Usage: php syncer.php [--dry-run] <org-or-user> <repo-name>";

    exit(1);
}

// ------------
// Client stuff
// ------------

$httpClient = new GuzzleClient(['debug' => getenv('GITHUB_DEBUG') !== false]);
$clientBuilder = new Builder($httpClient);

$client = new GitHubClient($clientBuilder);
$client->authenticate($config['token'], null, GitHubClient::AUTH_ACCESS_TOKEN);

if (!is_array($client->api('me')->show())) {
    throw new \RuntimeException(
        'It seems you mis-configured you\'re token. Or GitHub is down. Either way, you are not logged-in.'
    );
}

// ----

if ($dryRun) {
    $org = $_SERVER['argv'][2];
    $repo = $_SERVER['argv'][3];
} else {
    $org = $_SERVER['argv'][1];
    $repo = $_SERVER['argv'][2];
}

/** @var \Github\Api\Repository\Labels $labelsApi */
$labelsApi = $client->api('repository')->labels();

$fetchedLabels = $labelsApi->all($org, $repo);
$currentLabels = [];

foreach ($fetchedLabels as $label) {
    $currentLabels[strtolower($label['name'])] = $label;
}

echo PHP_EOL.sprintf('START%s SYNCING LABELS ON "%s"/"%s"', $dryRun ? ' [dry-run]' : '', $org, $repo).PHP_EOL.PHP_EOL;

// Start syncing process
// Check if the labels are exists
// create if missing, update if casing or color is wrong
foreach ($config['labels'] as $label => $color) {
    $label = trim($label);
    $canonicalLabel = strtolower($label);

    if (isset($currentLabels[$canonicalLabel])) {
        if ($label === $currentLabels[$canonicalLabel]['name'] && ltrim($color, '#') === $currentLabels[$canonicalLabel]['color']) {
            echo sprintf('OK "%s" with color "%s"', $label, $color).PHP_EOL;

            continue;
        }

        if ($label !== $currentLabels[$canonicalLabel]['name']) {
            echo sprintf('UPDATE label "%s" rename to "%s"', $currentLabels[$canonicalLabel]['name'], $label).PHP_EOL;
        }

        if (ltrim($color, '#') !== $currentLabels[$canonicalLabel]['color']) {
            echo sprintf('UPDATE label "%s" change color to "%s"', $label, $color).PHP_EOL;
        }

        if (!$dryRun) {
            $labelsApi->update(
                $org,
                $repo,
                $currentLabels[$canonicalLabel]['name'],
                [
                    'name' => $label,
                    'color' => ltrim($color, '#'),
                ]
            );
        }
    } else {
        echo sprintf('ADDING label "%s" with color "%s"', $label, $color).PHP_EOL;

        if (!$dryRun) {
            $labelsApi->create(
                $org,
                $repo,
                [
                    'name' => $label,
                    'color' => ltrim($color, '#'),
                ]
            );
        }
    }
}

$localLabels = array_map(
    function ($value) {
        return strtolower(trim($value));
    },
    array_keys($config['labels'])
);

// Look for labels that are present in the repository
// But not in the local, these should be removed. But that's not done here ;)
foreach ($currentLabels as $label) {
    $canonicalLabel = strtolower($label['name']);

    if (!in_array($canonicalLabel, $localLabels, true)) {
        if (!$dryRun && $autoRemove) {
            $labelsApi->remove(
                $org,
                $repo,
                $label['name']
            );

            echo sprintf('REMOVING Label "%s"; present in the repository but not in the local labels list.', $label['name']).PHP_EOL;
        } else {
            echo sprintf('INF Label "%s" is present in the repository but not in the local labels list.', $label['name']).PHP_EOL;
        }
    }
}

echo PHP_EOL . 'DONE!' . PHP_EOL;
