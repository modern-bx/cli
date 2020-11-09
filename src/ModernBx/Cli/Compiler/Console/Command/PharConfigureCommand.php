<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

declare(strict_types=1);

namespace ModernBx\Cli\Compiler\Console\Command;

use ModernBx\Cli\Common\Console\GenericCommand;
use ModernBx\Cli\Compiler\Service\NamespaceFinder;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function ModernBx\CommonFunctions\format;

class PharConfigureCommand extends GenericCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'phar:configure';

    /**
     * @var NamespaceFinder
     */
    protected NamespaceFinder $namespaceFinder;

    /**
     * @param NamespaceFinder $namespaceFinder
     */
    public function __construct(NamespaceFinder $namespaceFinder)
    {
        parent::__construct(static::$defaultName);

        $this->namespaceFinder = $namespaceFinder;
    }

    protected function configure(): void
    {
        $this
            ->setDescription("Configure the build process")
            ->setHelp("Generate box.json out of box.json.dist and provided arguments")
            ->setDefinition(
                new InputDefinition([
                    new InputOption(
                        'bundle',
                        'B',
                        InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                        "Command bundles to be included",
                    ),
                ])
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var array<string> $targetBundles */
        $targetBundles = $input->getOption("bundle") ?: [];
        $targetBundles = array_map(
            fn (string $bundleName) => strtolower($bundleName),
            $targetBundles,
        );

        $defaultConfigFile = $_SERVER["DOCUMENT_ROOT"] . "/box.json.dist";
        $buildConfigFile = $_SERVER["DOCUMENT_ROOT"] . "/box.json";

        if (!copy($defaultConfigFile, $buildConfigFile)) {
            throw new \Exception(format("An error occurred when copying build config file: {from} to {to}", [
                "from" => $defaultConfigFile,
                "to" => $buildConfigFile,
            ]));
        }

        $contents = file_get_contents($buildConfigFile);

        if (!$contents) {
            throw new \Exception(format("Cannot read build config file: {file}", [
                "file" => $buildConfigFile,
            ]));
        }

        /** @var array<mixed> $config */
        $config = json_decode($contents, true);

        $bundles = [];
        foreach ($this->namespaceFinder->findNamespaces() as $namespace) {
            $namespace = strtolower($namespace);

            if ($targetBundles && !in_array(strtolower($namespace), $targetBundles)) {
                $config["finder"][0]["exclude"][] = "Command/" . ucfirst($namespace);
            } else {
                $bundles[] = $namespace;
            }
        }

        if (!$bundles) {
            $this->getPrinter($output)->error("No bundles selected, aborting.");
            return -1;
        }

        $config["replacements"]["build-bundles"] = join(",", $bundles);

        file_put_contents($buildConfigFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return 0;
    }
}
