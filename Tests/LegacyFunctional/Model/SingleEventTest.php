<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\Model;

use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Model\Event;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * This test case holds all tests specific to single events.
 *
 * @covers \OliverKlee\Seminars\Model\Event
 */
final class SingleEventTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    protected bool $initializeDatabase = false;

    private Event $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new Event();
    }

    ////////////////////////////////
    // Tests regarding the teaser.
    ////////////////////////////////

    /**
     * @test
     */
    public function getTeaserForSingleEventWithoutTeaserReturnsAnEmptyString(): void
    {
        $this->subject->setData(
            ['object_type' => EventInterface::TYPE_SINGLE_EVENT],
        );

        self::assertEquals(
            '',
            $this->subject->getTeaser(),
        );
    }

    /**
     * @test
     */
    public function getTeaserForSingleEventWithTeaserReturnsTeaser(): void
    {
        $this->subject->setData(
            [
                'teaser' => 'wow, this is teasing',
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
            ],
        );

        self::assertEquals(
            'wow, this is teasing',
            $this->subject->getTeaser(),
        );
    }

    /////////////////////////////////////
    // Tests regarding the description.
    /////////////////////////////////////

    /**
     * @test
     */
    public function hasDescriptionForSingleEventWithoutDescriptionReturnsFalse(): void
    {
        $this->subject->setData(
            ['object_type' => EventInterface::TYPE_SINGLE_EVENT],
        );

        self::assertFalse(
            $this->subject->hasDescription(),
        );
    }

    /**
     * @test
     */
    public function hasDescriptionForSingleEventWithDescriptionReturnsTrue(): void
    {
        $this->subject->setData(
            [
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'description' => 'this is a great event.',
            ],
        );

        self::assertTrue(
            $this->subject->hasDescription(),
        );
    }
}
