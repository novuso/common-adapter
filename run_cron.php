<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Novuso\Common\Adapter\Cron\BackgroundJob;

$autoloadPaths = [
    dirname(dirname(__DIR__)).'/autoload.php',
    dirname(__DIR__).'/autoload.php',
    __DIR__.'/vendor/autoload.php'
];

foreach ($autoloadPaths as $filePath) {
    if (file_exists($filePath)) {
        define('RUN_CRON_COMPOSER_INSTALL', $filePath);

        break;
    }
}

unset($filePath);

if (!defined('RUN_CRON_COMPOSER_INSTALL')) {
    fwrite(STDERR, "Composer install required\n");
    exit(1);
}

require RUN_CRON_COMPOSER_INSTALL;

/** @var ContainerInterface $container */
$container = require $argv[1];
/** @var string $name */
$name = $argv[2];
/** @var array $config */
parse_str($argv[3], $config);
/** @var string $fromEmail */
$fromEmail = $argv[4];
/** @var string $tempDirectory */
$tempDirectory = rtrim(sys_get_temp_dir(), '/');

$job = new BackgroundJob($container, $name, $config, $tempDirectory, $fromEmail);

try {
    $job->run();
} catch (Exception $e) {
    if ($container->has(LoggerInterface::class)) {
        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        $logger->error($e->getMessage(), ['exception' => $e]);
    }
}
