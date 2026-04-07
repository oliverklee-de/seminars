<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Domain\Model;

use OliverKlee\FeUserExtraFields\Domain\Model\FrontendUser as ExtraFieldsFrontendUser;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation\Validate;

/**
 * This class represents a frontend user with some additional data specific to the seminars extension.
 */
class FrontendUser extends ExtraFieldsFrontendUser
{
    /**
     * @phpstan-var int<0, max>
     * @Validate("NumberRange", options={"minimum": 0})
     */
    protected int $defaultOrganizerUid = 0;

    protected string $concatenatedUidsOfAvailableTopicsForFrontEndEditor = '';

    /**
     * @return int<0, max>
     */
    public function getDefaultOrganizerUid(): int
    {
        return $this->defaultOrganizerUid;
    }

    /**
     * @param int<0, max> $defaultOrganizerUid
     */
    public function setDefaultOrganizerUid(int $defaultOrganizerUid): void
    {
        $this->defaultOrganizerUid = $defaultOrganizerUid;
    }

    public function getConcatenatedUidsOfAvailableTopicsForFrontEndEditor(): string
    {
        return $this->concatenatedUidsOfAvailableTopicsForFrontEndEditor;
    }

    public function setConcatenatedUidsOfAvailableTopicsForFrontEndEditor(string $concatenatedUids): void
    {
        $this->concatenatedUidsOfAvailableTopicsForFrontEndEditor = $concatenatedUids;
    }

    /**
     * @return array<int>
     */
    public function getUidsOfAvailableTopicsForFrontEndEditor(): array
    {
        $concatenatedUids = $this->getConcatenatedUidsOfAvailableTopicsForFrontEndEditor();

        return GeneralUtility::intExplode(',', $concatenatedUids, true);
    }
}
