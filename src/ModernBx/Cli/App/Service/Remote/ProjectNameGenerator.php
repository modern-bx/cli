<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class ProjectNameGenerator
{
    /**
     * @return string[]
     */
    public function getAdjectives(): array
    {
        return [
            'brave',
            'bright',
            'calm',
            'clever',
            'cosmic',
            'eager',
            'gentle',
            'golden',
            'happy',
            'lucky',
            'merry',
            'nimble',
            'proud',
            'rapid',
            'silent',
            'silver',
        ];
    }

    /**
     * @return string[]
     */
    public function getNouns(): array
    {
        return [
            'badger',
            'beaver',
            'cougar',
            'dolphin',
            'eagle',
            'falcon',
            'fox',
            'lynx',
            'otter',
            'panda',
            'raven',
            'tiger',
            'turtle',
            'whale',
            'wolf',
            'zebra',
        ];
    }

    /**
     * @param callable(string): bool $isNameTaken
     */
    public function generate(callable $isNameTaken): ?string
    {
        $variants = $this->getVariants();

        while ($variants !== []) {
            $index = random_int(0, count($variants) - 1);
            $projectName = $variants[$index];

            if (!$isNameTaken($projectName)) {
                return $projectName;
            }

            array_splice($variants, $index, 1);
        }

        return null;
    }

    /**
     * @return string[]
     */
    protected function getVariants(): array
    {
        $variants = [];

        foreach ($this->getAdjectives() as $adjective) {
            foreach ($this->getNouns() as $noun) {
                $variants[] = $adjective . '-' . $noun;
            }
        }

        return $variants;
    }
}
