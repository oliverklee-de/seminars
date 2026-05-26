<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Domain\Model;

use OliverKlee\FeUserExtraFields\Domain\Model\FrontendUser as ExtraFieldsFrontendUser;
use OliverKlee\Seminars\Domain\Model\FrontendUser;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\FrontendUser
 */
final class FrontendUserTest extends UnitTestCase
{
    private FrontendUser $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new FrontendUser();
    }

    /**
     * @test
     */
    public function isAbstractEntity(): void
    {
        self::assertInstanceOf(AbstractEntity::class, $this->subject);
    }

    /**
     * @test
     */
    public function isExtraFieldsFrontendUserEntity(): void
    {
        self::assertInstanceOf(ExtraFieldsFrontendUser::class, $this->subject);
    }

    /**
     * @test
     */
    public function getDefaultOrganizerUidInitiallyReturnsZero(): void
    {
        self::assertSame(0, $this->subject->getDefaultOrganizerUid());
    }

    /**
     * @test
     */
    public function setDefaultOrganizerUidSetsDefaultOrganizerUid(): void
    {
        $value = 123456;
        $this->subject->setDefaultOrganizerUid($value);

        self::assertSame($value, $this->subject->getDefaultOrganizerUid());
    }

    /**
     * @test
     */
    public function getConcatenatedUidsOfAvailableTopicsForFrontEndEditorInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getConcatenatedUidsOfAvailableTopicsForFrontEndEditor());
    }

    /**
     * @test
     */
    public function setConcatenatedUidsOfAvailableTopicsForFrontEndEditorSetsValue(): void
    {
        $value = '1,2,3';
        $this->subject->setConcatenatedUidsOfAvailableTopicsForFrontEndEditor($value);

        self::assertSame($value, $this->subject->getConcatenatedUidsOfAvailableTopicsForFrontEndEditor());
    }

    /**
     * @test
     */
    public function getUidsOfAvailableTopicsForFrontEndEditorForEmptyValueReturnsEmptyArray(): void
    {
        $this->subject->setConcatenatedUidsOfAvailableTopicsForFrontEndEditor('');

        self::assertSame([], $this->subject->getUidsOfAvailableTopicsForFrontEndEditor());
    }

    /**
     * @test
     */
    public function getUidsOfAvailableTopicsForFrontEndEditorReturnsExplodedValues(): void
    {
        $this->subject->setConcatenatedUidsOfAvailableTopicsForFrontEndEditor('1,2,3');

        self::assertSame([1, 2, 3], $this->subject->getUidsOfAvailableTopicsForFrontEndEditor());
    }

    /**
     * @test
     */
    public function getUidsOfAvailableTopicsForFrontEndEditorDropsEmptyValues(): void
    {
        $this->subject->setConcatenatedUidsOfAvailableTopicsForFrontEndEditor('1,,2');

        $result = $this->subject->getUidsOfAvailableTopicsForFrontEndEditor();

        self::assertSame([1, 2], \array_values($result));
    }

    /**
     * @test
     */
    public function getUidsOfAvailableTopicsForFrontEndEditorCastsNonIntegerValuesToZero(): void
    {
        $this->subject->setConcatenatedUidsOfAvailableTopicsForFrontEndEditor('1,bla,2');

        $result = $this->subject->getUidsOfAvailableTopicsForFrontEndEditor();

        self::assertSame([1, 0, 2], \array_values($result));
    }
}
