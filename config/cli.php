<?php

declare(strict_types=1);

use ModernBx\Cli\App\DefaultContainerBuilder;

$builder = new DefaultContainerBuilder();

/** @noinspection PhpUnhandledExceptionInspection */
return $builder->getContainer();
