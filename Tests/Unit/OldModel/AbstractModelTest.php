<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\OldModel;

use OliverKlee\Seminars\OldModel\AbstractModel;
use OliverKlee\Seminars\Tests\Unit\OldModel\Fixtures\TestingModel;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\OldModel\AbstractModel
 */
final class AbstractModelTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private TestingModel $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new TestingModel();
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
        $result = TestingModel::fromData([]);

        self::assertInstanceOf(TestingModel::class, $result);
    }

    /**
     * @test
     */
    public function fromDataProvidesModelWithGivenData(): void
    {
        $result = TestingModel::fromData(['test' => true]);

        self::assertTrue($result->getBooleanTest());
    }

    /**
     * @test
     */
    public function fromDataResultsInOkay(): void
    {
        $subject = TestingModel::fromData(['title' => 'Foo']);

        self::assertTrue($subject->isOk());
    }

    /**
     * @test
     */
    public function getRecordPropertyStringByDefaultReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getRecordPropertyString('title'));
    }

    /**
     * @test
     */
    public function getRecordPropertyStringReturnsValue(): void
    {
        $value = 'A nice object!';
        $subject = TestingModel::fromData(['something' => $value]);

        self::assertSame($value, $subject->getRecordPropertyString('something'));
    }

    /**
     * @test
     */
    public function getRecordPropertyStringReturnsTrimmedValue(): void
    {
        $value = 'A nice object!';
        $subject = TestingModel::fromData(['something' => ' ' . $value . ' ']);

        self::assertSame($value, $subject->getRecordPropertyString('something'));
    }

    /**
     * @test
     */
    public function hasRecordPropertyStringForInexistentKeyReturnsFalse(): void
    {
        self::assertFalse($this->subject->hasRecordPropertyString('something'));
    }

    /**
     * @test
     */
    public function hasRecordPropertyStringForEmptyStringReturnsFalse(): void
    {
        $subject = TestingModel::fromData(['something' => '']);

        self::assertFalse($subject->hasRecordPropertyString('something'));
    }

    /**
     * @test
     */
    public function hasRecordPropertyStringForWhitespaceOnlyStringReturnsFalse(): void
    {
        $subject = TestingModel::fromData(['something' => ' ']);

        self::assertFalse($subject->hasRecordPropertyString('something'));
    }

    /**
     * @test
     */
    public function hasRecordPropertyStringForNonEmptyStringReturnsTrue(): void
    {
        $subject = TestingModel::fromData(['something' => 'The cake is a lie.']);

        self::assertTrue($subject->hasRecordPropertyString('something'));
    }

    /**
     * @test
     */
    public function getRecordPropertyDecimalByDefaultReturnsZeroWithDecimals(): void
    {
        self::assertSame('0.00', $this->subject->getRecordPropertyDecimal('something'));
    }

    /**
     * @test
     */
    public function getRecordPropertyDecimalReturnsValue(): void
    {
        $value = '12.34';
        $subject = TestingModel::fromData(['something' => $value]);

        self::assertSame($value, $subject->getRecordPropertyDecimal('something'));
    }

    /**
     * @test
     */
    public function getRecordPropertyDecimalReturnsTrimmedValue(): void
    {
        $value = '12.34';
        $subject = TestingModel::fromData(['something' => ' ' . $value . ' ']);

        self::assertSame($value, $subject->getRecordPropertyDecimal('something'));
    }

    /**
     * @test
     */
    public function hasRecordPropertyDecimalForInexistentKeyReturnsFalse(): void
    {
        self::assertFalse($this->subject->hasRecordPropertyDecimal('something'));
    }

    /**
     * @test
     */
    public function hasRecordPropertyDecimalForEmptyDecimalReturnsFalse(): void
    {
        $subject = TestingModel::fromData(['something' => '']);

        self::assertFalse($subject->hasRecordPropertyDecimal('something'));
    }

    /**
     * @test
     */
    public function hasRecordPropertyDecimalForZeroDecimalReturnsFalse(): void
    {
        $subject = TestingModel::fromData(['something' => '0.00']);

        self::assertFalse($subject->hasRecordPropertyDecimal('something'));
    }

    /**
     * @test
     */
    public function hasRecordPropertyDecimalForZeroWithoutDecimalsReturnsFalse(): void
    {
        $subject = TestingModel::fromData(['something' => '0']);

        self::assertFalse($subject->hasRecordPropertyDecimal('something'));
    }

    /**
     * @test
     */
    public function hasRecordPropertyDecimalForPositiveDecimalReturnsTrue(): void
    {
        $subject = TestingModel::fromData(['something' => '12.00']);

        self::assertTrue($subject->hasRecordPropertyDecimal('something'));
    }

    /**
     * @test
     */
    public function getRecordPropertyIntegerByDefaultReturnsZero(): void
    {
        self::assertSame(0, $this->subject->getRecordPropertyInteger('something'));
    }

    /**
     * @test
     */
    public function getRecordPropertyIntegerReturnsValueFromInteger(): void
    {
        $value = 42;
        $subject = TestingModel::fromData(['something' => $value]);

        self::assertSame($value, $subject->getRecordPropertyInteger('something'));
    }

    /**
     * @test
     */
    public function getRecordPropertyIntegerReturnsValueFromString(): void
    {
        $value = 42;
        $subject = TestingModel::fromData(['something' => (string)$value]);

        self::assertSame($value, $subject->getRecordPropertyInteger('something'));
    }

    /**
     * @test
     */
    public function hasRecordPropertyIntegerForInexistentKeyReturnsFalse(): void
    {
        self::assertFalse($this->subject->hasRecordPropertyInteger('something'));
    }

    /**
     * @test
     */
    public function hasRecordPropertyIntegerForZeroIntegerReturnsFalse(): void
    {
        $subject = TestingModel::fromData(['something' => 0]);

        self::assertFalse($subject->hasRecordPropertyInteger('something'));
    }

    /**
     * @test
     */
    public function hasRecordPropertyIntegerForZeroStringReturnsFalse(): void
    {
        $subject = TestingModel::fromData(['something' => '0']);

        self::assertFalse($subject->hasRecordPropertyInteger('something'));
    }

    /**
     * @test
     */
    public function hasRecordPropertyIntegerForPositiveIntegerReturnsTrue(): void
    {
        $subject = TestingModel::fromData(['something' => 12]);

        self::assertTrue($subject->hasRecordPropertyInteger('something'));
    }

    /**
     * @test
     */
    public function hasRecordPropertyIntegerForNegativeIntegerReturnsTrue(): void
    {
        $subject = TestingModel::fromData(['something' => -12]);

        self::assertTrue($subject->hasRecordPropertyInteger('something'));
    }

    /**
     * @test
     */
    public function getRecordPropertyBooleanByDefaultReturnsFalse(): void
    {
        self::assertFalse($this->subject->getRecordPropertyBoolean('something'));
    }

    /**
     * @return bool[][]
     */
    public function booleanDataProvider(): array
    {
        return [
            'false' => [false],
            'true' => [true],
        ];
    }

    /**
     * @test
     *
     * @param bool $value
     *
     * @dataProvider booleanDataProvider
     */
    public function getRecordPropertyBooleanReturnsValueFromString(bool $value): void
    {
        $subject = TestingModel::fromData(['something' => (string)(int)$value]);

        self::assertSame($value, $subject->getRecordPropertyBoolean('something'));
    }

    /**
     * @test
     *
     * @param bool $value
     *
     * @dataProvider booleanDataProvider
     */
    public function getRecordPropertyBooleanReturnsValueFromInt(bool $value): void
    {
        $subject = TestingModel::fromData(['something' => (int)$value]);

        self::assertSame($value, $subject->getRecordPropertyBoolean('something'));
    }

    /**
     * @test
     */
    public function createMmRecordsOnObjectWithoutUidThrowsException(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionCode(1333292371);

        $this->subject->createMmRecords('tx_seminars_test_test_mm', []);
    }
}
