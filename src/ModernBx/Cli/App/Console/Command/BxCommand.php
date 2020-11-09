<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command;

use ModernBx\Cli\App\Console\Mixin\BitrixAware;
use ModernBx\Url\UrlImmutable;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BxCommand extends AppCommand
{
    use BitrixAware;

    const CODE_BX_ROOT_NOT_FOUND = 1 << 4;

    /**
     * @var UrlImmutable
     */
    protected UrlImmutable $bxRoot;

    /**
     * @var UrlImmutable
     */
    protected UrlImmutable $documentRoot;

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \Exception
     */
    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        parent::executeInternal($input, $output);

        $bxRoot = $this->getBxRoot();

        if (!$bxRoot) {
            throw new \Exception(
                "Bitrix installation has not been found.",
                static::CODE_BX_ROOT_NOT_FOUND
            );
        }

        $this->bxRoot = $bxRoot;
        $this->documentRoot = $this->bxRoot->popPathSegment();
    }

    /**
     * @return UrlImmutable
     */
    public function getDocumentRoot(): UrlImmutable
    {
        return $this->documentRoot;
    }
}
