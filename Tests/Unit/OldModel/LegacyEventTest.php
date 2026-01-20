<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\OldModel;

use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\OldModel\AbstractModel;
use OliverKlee\Seminars\OldModel\LegacyEvent;
use OliverKlee\Seminars\Tests\Unit\OldModel\Fixtures\TestingLegacyEvent;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\OldModel\AbstractModel
 * @covers \OliverKlee\Seminars\OldModel\AbstractTimeSpan
 * @covers \OliverKlee\Seminars\OldModel\LegacyEvent
 * @covers \OliverKlee\Seminars\Templating\TemplateHelper
 */
final class LegacyEventTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private TestingLegacyEvent $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['TYPO3_CONF_VARS']['LOG'] = [];
        $begin = \mktime(10, 0, 0, 4, 8, 2020);
        \assert(\is_int($begin));
        $end = \mktime(18, 30, 0, 4, 20, 2020);
        \assert(\is_int($end));

        $this->subject = TestingLegacyEvent::fromData(
            ['title' => 'A nice event', 'begin_date' => $begin, 'end_date' => $end],
        );
    }

    /**
     * @test
     */
    public function isAbstractModel(): void
    {
        self::assertInstanceOf(AbstractModel::class, $this->subject);
    }

    /**
     * @test
     */
    public function fromDataCreatesInstanceOfSubclass(): void
    {
        $result = LegacyEvent::fromData([]);

        self::assertInstanceOf(LegacyEvent::class, $result);
    }

    /**
     * @test
     */
    public function getTopicByDefaultReturnsNull(): void
    {
        self::assertNull($this->subject->getTopic());
    }

    /**
     * @test
     */
    public function setTopicSetsTopic(): void
    {
        $topic = new LegacyEvent();

        $this->subject->setTopic($topic);

        self::assertSame($topic, $this->subject->getTopic());
    }

    /**
     * @test
     */
    public function getAttendancesMinByDefaultReturnsZero(): void
    {
        self::assertSame(0, $this->subject->getAttendancesMin());
    }

    /**
     * @test
     */
    public function getAttendancesMinReturnsAttendancesMin(): void
    {
        $number = 4;
        $subject = LegacyEvent::fromData(['attendees_min' => $number]);

        self::assertSame($number, $subject->getAttendancesMin());
    }

    /**
     * @test
     */
    public function getAttendancesMaxByDefaultReturnsZero(): void
    {
        self::assertSame(0, $this->subject->getAttendancesMax());
    }

    /**
     * @test
     */
    public function getAttendancesMaxReturnsAttendancesMax(): void
    {
        $number = 4;
        $subject = LegacyEvent::fromData(['attendees_max' => $number]);

        self::assertSame($number, $subject->getAttendancesMax());
    }

    /**
     * @test
     */
    public function getOfflineRegistrationsByDefaultReturnsZero(): void
    {
        self::assertSame(0, $this->subject->getOfflineRegistrations());
    }

    /**
     * @test
     */
    public function getOfflineRegistrationsReturnsOfflineRegistrations(): void
    {
        $number = 4;
        $subject = LegacyEvent::fromData(['offline_attendees' => $number]);

        self::assertSame($number, $subject->getOfflineRegistrations());
    }

    /**
     * @test
     */
    public function hasOfflineRegistrationsInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subject->hasOfflineRegistrations());
    }

    /**
     * @test
     */
    public function hasOfflineRegistrationsForOfflineRegistrationsReturnsTrue(): void
    {
        $subject = LegacyEvent::fromData(['offline_attendees' => 4]);

        self::assertTrue($subject->hasOfflineRegistrations());
    }

    /**
     * @test
     */
    public function hasCheckboxesForSingleEventWithNoCheckboxesReturnsFalse(): void
    {
        $subject = LegacyEvent::fromData(['checkboxes' => 0]);

        self::assertFalse($subject->hasCheckboxes());
    }

    /**
     * @test
     */
    public function hasCheckboxesForSingleEventWithOneCheckboxReturnsTrue(): void
    {
        $subject = LegacyEvent::fromData(['checkboxes' => 1]);

        self::assertTrue($subject->hasCheckboxes());
    }

    /**
     * @test
     */
    public function hasCheckboxesForDateWithOneCheckboxReturnsTrue(): void
    {
        $data = [
            'object_type' => EventInterface::TYPE_EVENT_DATE,
            'checkboxes' => 1,
        ];
        $subject = LegacyEvent::fromData($data);
        $topic = LegacyEvent::fromData(['object_type' => EventInterface::TYPE_EVENT_TOPIC]);
        $subject->setTopic($topic);

        self::assertTrue($subject->hasCheckboxes());
    }

    public function hasImageForNoDataReturnsFalse(): void
    {
        $subject = LegacyEvent::fromData([]);

        self::assertFalse($subject->hasImage());
    }

    /**
     * @return array<string, array{0: string|int}>
     */
    public function noImageDataProvider(): array
    {
        return [
            'empty string' => [''],
            'file name before migration' => ['image.jpg'],
            'zero as string' => ['0'],
            'zero as integer' => [0],
        ];
    }

    /**
     * @test
     *
     * @param string|int $icon
     *
     * @dataProvider noImageDataProvider
     */
    public function hasImageForNoImageReturnsFalse($icon): void
    {
        $subject = LegacyEvent::fromData(['image' => $icon]);

        self::assertFalse($subject->hasImage());
    }

    public function hasImageWithPositiveNumberOfImagesReturnsTrue(): void
    {
        $subject = LegacyEvent::fromData(['image' => 1]);

        self::assertTrue($subject->hasImage());
    }

    /**
     * @test
     */
    public function hasAttachedFilesInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subject->hasAttachedFiles());
    }

    /**
     * @test
     */
    public function hasAttachedFilesForNoAttachedFilesReturnsFalse(): void
    {
        $subject = LegacyEvent::fromData(['attached_files' => 0]);

        self::assertFalse($subject->hasAttachedFiles());
    }

    /**
     * @test
     */
    public function hasAttachedFilesForNotMigratedFilesReturnsFalse(): void
    {
        $subject = LegacyEvent::fromData(['attached_files' => 'handout.pdf']);

        self::assertFalse($subject->hasAttachedFiles());
    }

    /**
     * @test
     */
    public function hasAttachedFilesWithOneAttachedFileReturnsTrue(): void
    {
        $subject = LegacyEvent::fromData(['attached_files' => 1]);

        self::assertTrue($subject->hasAttachedFiles());
    }

    /**
     * @test
     */
    public function hasAttachedFilesWithTwoAttachedFilesReturnsTrue(): void
    {
        $subject = LegacyEvent::fromData(['attached_files' => 2]);

        self::assertTrue($subject->hasAttachedFiles());
    }

    /**
     * @test
     */
    public function hasAttachedFilesForDateWithoutFilesAndTopicWithOneFileReturnsTrue(): void
    {
        $topic = LegacyEvent::fromData(['object_type' => EventInterface::TYPE_EVENT_TOPIC, 'attached_files' => 1]);
        $date = LegacyEvent::fromData(['object_type' => EventInterface::TYPE_EVENT_DATE]);
        $date->setTopic($topic);

        self::assertTrue($date->hasAttachedFiles());
    }

    /**
     * @test
     */
    public function hasAttachedFilesForDateWithOneFileAndTopicWithoutFilesReturnsTrue(): void
    {
        $topic = LegacyEvent::fromData(['object_type' => EventInterface::TYPE_EVENT_TOPIC]);
        $date = LegacyEvent::fromData(['object_type' => EventInterface::TYPE_EVENT_DATE, 'attached_files' => 1]);
        $date->setTopic($topic);

        self::assertTrue($date->hasAttachedFiles());
    }

    /**
     * @test
     */
    public function hasAttachedFilesForDateAndTopicWithoutFilesReturnsFalse(): void
    {
        $topic = LegacyEvent::fromData(['object_type' => EventInterface::TYPE_EVENT_TOPIC]);
        $date = LegacyEvent::fromData(['object_type' => EventInterface::TYPE_EVENT_DATE]);
        $date->setTopic($topic);

        self::assertFalse($date->hasAttachedFiles());
    }
}
