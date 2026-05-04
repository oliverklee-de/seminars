<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\Bag;

use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Bag\EventBag;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Bag\EventBag
 */
final class EventBagTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private EventBag $subject;

    private TestingFramework $testingFramework;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingFramework = new TestingFramework();

        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['title' => 'test event'],
        );

        $this->subject = new EventBag();
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
