<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Service\Remote;

use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use PHPUnit\Framework\TestCase;

final class BitrixAdminClientTest extends TestCase
{
    /**
     * @dataProvider sessidHtmlProvider
     */
    public function testExtractsSessidFromSupportedBitrixMarkup(string $html): void
    {
        $method = new \ReflectionMethod(BitrixAdminClient::class, 'extractSessid');
        $method->setAccessible(true);

        self::assertSame(
            '3f880c23d3844897f8d14cb50dddb0da',
            $method->invoke(new BitrixAdminClient(), $html),
        );
    }

    /** @return array<string, array{string}> */
    public function sessidHtmlProvider(): array
    {
        return [
            'phpVars object used by current Bitrix' => [
                "var phpVars = {'bitrix_sessid': '3f880c23d3844897f8d14cb50dddb0da'};",
            ],
            'BX message JSON' => [
                'BX.message({"bitrix_sessid":"3f880c23d3844897f8d14cb50dddb0da"});',
            ],
            'logout URL' => [
                'href="/bitrix/admin/index.php?logout=yes&amp;sessid=3f880c23d3844897f8d14cb50dddb0da"',
            ],
        ];
    }
}
