<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\OldModel;

use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * This class represents a speaker.
 */
class LegacySpeaker extends AbstractModel
{
    /**
     * @var string the name of the SQL table this class corresponds to
     */
    protected static string $tableName = 'tx_seminars_speakers';

    /**
     * Gets our organization.
     *
     * @return string our organization (or '' if there is an error)
     */
    public function getOrganization(): string
    {
        return $this->getRecordPropertyString('organization');
    }

    /**
     * Returns TRUE if this speaker has an organization, FALSE otherwise.
     *
     * @return bool TRUE if this speaker has an organization, FALSE otherwise
     */
    public function hasOrganization(): bool
    {
        return $this->hasRecordPropertyString('organization');
    }

    /**
     * Gets our homepage.
     *
     * @return string our homepage (or '' if there is an error)
     */
    public function getHomepage(): string
    {
        return $this->getRecordPropertyString('homepage');
    }

    /**
     * Returns TRUE if this speaker has a homepage, FALSE otherwise.
     *
     * @return bool TRUE if this speaker has a homepage, FALSE otherwise
     */
    public function hasHomepage(): bool
    {
        return $this->hasRecordPropertyString('homepage');
    }

    public function getDescription(): string
    {
        return $this->renderAsRichText($this->getRecordPropertyString('description'));
    }

    /**
     * Gets our description without RTE processing.
     *
     * @return string our description (or '' if there is an error)
     */
    public function getDescriptionRaw(): string
    {
        return $this->getRecordPropertyString('description');
    }

    /**
     * Returns TRUE if this speaker has a description, FALSE otherwise.
     *
     * @return bool TRUE if this speaker has a description, FALSE otherwise
     */
    public function hasDescription(): bool
    {
        return $this->hasRecordPropertyString('description');
    }

    /**
     * Checks whether we have any skills set.
     *
     * @return bool TRUE if we have any skills related to this speaker,
     *                 FALSE otherwise
     */
    public function hasSkills(): bool
    {
        return $this->hasRecordPropertyInteger('skills');
    }

    /**
     * Gets our skills as a plain text list (just the skill names).
     *
     * @return string our skills list (or an empty string if there are no skills for this speaker)
     */
    public function getSkillsShort(): string
    {
        if (!$this->hasSkills()) {
            return '';
        }

        return \implode(', ', $this->getMmRecordTitles('tx_seminars_skills', 'tx_seminars_speakers_skills_mm'));
    }

    /**
     * @return int<0, max>
     */
    public function getNumberOfSkills(): int
    {
        $number = $this->getRecordPropertyInteger('skills');
        \assert($number >= 0);

        return $number;
    }

    /**
     * Gets our internal notes.
     *
     * @return string our internal notes (or '' if there is an error)
     */
    public function getNotes(): string
    {
        return $this->getRecordPropertyString('notes');
    }

    /**
     * Gets our address.
     *
     * @return string our address (or '' if there is an error)
     */
    public function getAddress(): string
    {
        return $this->getRecordPropertyString('address');
    }

    /**
     * Gets our work phone number.
     *
     * @return string our work phone number (or '' if there is an error)
     */
    public function getPhoneWork(): string
    {
        return $this->getRecordPropertyString('phone_work');
    }

    /**
     * Gets our home phone number.
     *
     * @return string our home phone number (or '' if there is an error)
     */
    public function getPhoneHome(): string
    {
        return $this->getRecordPropertyString('phone_home');
    }

    /**
     * Gets our mobile phone number.
     *
     * @return string our mobile phone number (or '' if there is an error)
     */
    public function getPhoneMobile(): string
    {
        return $this->getRecordPropertyString('phone_mobile');
    }

    /**
     * Gets our email address.
     *
     * @return string our email address (or '' if there is an error)
     */
    public function getEmail(): string
    {
        return $this->getRecordPropertyString('email');
    }

    /**
     * Creates a link to this speaker's homepage, with the title as link text.
     *
     * @return string this speaker's title wrapped in a link tag, or if the
     *                speaker has no homepage just the speaker name
     */
    public function getLinkedTitle(): string
    {
        $encodedTitle = \htmlspecialchars($this->getTitle(), ENT_QUOTES | ENT_HTML5);
        $frontEndController = $GLOBALS['TSFE'] ?? null;
        $contentObject = $frontEndController instanceof TypoScriptFrontendController ? $frontEndController->cObj : null;
        if ($contentObject instanceof ContentObjectRenderer && $this->hasHomepage()) {
            $result = $contentObject->typoLink($encodedTitle, ['parameter' => $this->getHomepage()]);
        } else {
            $result = $encodedTitle;
        }

        return $result;
    }

    /**
     * Returns TRUE if this speaker has a cancelation period.
     *
     * @return bool TRUE if the speaker has a cancelation period, FALSE
     *                 otherwise
     */
    public function hasCancelationPeriod(): bool
    {
        return $this->hasRecordPropertyInteger('cancelation_period');
    }

    /**
     * Returns the cancelation period of this speaker in days.
     *
     * @return int<0, max>
     */
    public function getCancelationPeriodInDays(): int
    {
        $period = $this->getRecordPropertyInteger('cancelation_period');
        \assert($period >= 0);

        return $period;
    }

    /**
     * Sets the cancelation period of this speaker
     *
     * @param positive-int $cancelationPeriod the cancelation period of this speaker in days
     */
    public function setCancelationPeriod(int $cancelationPeriod): void
    {
        $this->setRecordPropertyInteger('cancelation_period', $cancelationPeriod);
    }

    public function hasImage(): bool
    {
        return $this->getRecordPropertyInteger('image') > 0;
    }

    public function getImage(): ?FileReference
    {
        if (!$this->hasImage()) {
            return null;
        }

        $images = $this->getFileRepository()->findByRelation('tx_seminars_speakers', 'image', $this->getUid());

        return \array_shift($images);
    }
}
