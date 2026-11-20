<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Partials\BackEnd;

use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class FlashMessageTemplateTest extends UnitTestCase
{
    /**
     * @test
     */
    public function flashMessageAlertHasAlertRole(): void
    {
        $path = __DIR__ . '/../../../../Resources/Private/Partials/BackEnd/FlashMessage.html';
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        self::assertMatchesRegularExpression(
            '/<div class="alert alert-\{severity\}" role="alert">/',
            $contents
        );
    }
}
