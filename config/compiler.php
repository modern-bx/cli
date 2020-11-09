<?php

declare(strict_types=1);

use ModernBx\Cli\Compiler\DefaultContainerBuilder;

$builder = new DefaultContainerBuilder();

/** @noinspection PhpUnhandledExceptionInspection */
return $builder->getContainer();
