<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service;

final class RuntimeInfo
{
    /**
     * @param class-string $klass
     * @return class-string
     */
    public function getScopedClass(string $klass): string
    {
        $scope = $this->getScope();

        if (!$scope) {
            return $klass;
        }

        /** @var class-string $scopedClass */
        $scopedClass = $this->getScope() . "\\" . $klass;

        return $scopedClass;
    }

    /**
     * @return string
     */
    protected function getScope(): string
    {
        [$scope] = explode("\\", self::class);

        if (!str_starts_with($scope, "_")) {
            return "";
        }

        return $scope;
    }
}
