<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\Bag;

use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Bag\CategoryBag;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Bag\CategoryBag
 */
final class CategoryBagTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private CategoryBag $subject;

    private TestingFramework $testingFramework;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingFramework = new TestingFramework();

        $this->testingFramework->createRecord('tx_seminars_categories');

        $this->subject = new CategoryBag();
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    ///////////////////////////////////////////
    // Tests for the basic bag functionality.
    ///////////////////////////////////////////

    /**
     * @test
     */
    public function bagCanHaveAtLeastOneElement(): void
    {
        self::assertFalse(
            $this->subject->isEmpty(),
        );
    }
}
