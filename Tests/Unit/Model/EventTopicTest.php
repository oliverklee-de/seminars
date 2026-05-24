<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Model;

use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Model\Event;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * This test case holds all tests specific to event topics.
 *
 * @covers \OliverKlee\Seminars\Model\Event
 */
final class EventTopicTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private Event $subject;

    protected function setUp(): void
    {
        parent::setUp();

        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('2018-04-26 12:42:23')));

        $this->subject = new Event();
    }

    ////////////////////////////////
    // Tests concerning the title.
    ////////////////////////////////

    /**
     * @test
     */
    public function getTitleWithNonEmptyTitleReturnsTitle(): void
    {
        $this->subject->setData(['title' => 'Superhero']);

        self::assertSame(
            'Superhero',
            $this->subject->getTitle(),
        );
    }

    /**
     * @test
     */
    public function getRawTitleWithNonEmptyTitleReturnsTitle(): void
    {
        $this->subject->setData(['title' => 'Superhero']);

        self::assertSame(
            'Superhero',
            $this->subject->getRawTitle(),
        );
    }

    ////////////////////////////////
    // Tests regarding the teaser.
    ////////////////////////////////

    /**
     * @test
     */
    public function getTeaserForEventTopicWithoutTeaserReturnsAnEmptyString(): void
    {
        $this->subject->setData(
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );

        self::assertSame(
            '',
            $this->subject->getTeaser(),
        );
    }

    /**
     * @test
     */
    public function getTeaserForEventTopicWithTeaserReturnsTeaser(): void
    {
        $this->subject->setData(
            [
                'teaser' => 'wow, this is teasing',
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
            ],
        );

        self::assertSame(
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
    public function hasDescriptionForEventTopicWithoutDescriptionReturnsFalse(): void
    {
        $this->subject->setData(
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );

        self::assertFalse(
            $this->subject->hasDescription(),
        );
    }
}
