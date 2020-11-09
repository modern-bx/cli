<?php

declare(strict_types=1);

use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ContainerInterface;

require __DIR__ . '/../config/autoload.php';

/**
 * @var ContainerInterface $container
 * @var Application $application
 */

$container = require __DIR__ . '/../config/cli.php';

/** @var Application $application */
$application = $container->get(Application::class);

$application->run();
