<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Csv;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Interfaces\Configuration;
use OliverKlee\Seminars\BagBuilder\RegistrationBagBuilder;
use OliverKlee\Seminars\Hooks\HookProvider;
use OliverKlee\Seminars\Hooks\Interfaces\RegistrationListCsv;
use OliverKlee\Seminars\OldModel\LegacyRegistration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class creates a CSV export of registrations.
 *
 * @internal
 */
abstract class AbstractRegistrationListView
{
    protected const COLUMN_SEPARATOR = ';';
    protected const LINE_SEPARATOR = "\r\n";
    protected const RECURSION_DEPTH = 250;

    protected Configuration $configuration;

    /**
     * @var int<0, max>
     */
    protected int $pageUid = 0;

    /**
     * @var non-empty-string
     */
    protected string $tableName = 'tx_seminars_attendances';

    /**
     * @var int<0, max>
     */
    protected int $eventUid = 0;

    public function __construct()
    {
        $this->configuration = ConfigurationRegistry::get('plugin.tx_seminars');
    }

    /**
     * @return int<0, max>
     */
    protected function getPageUid(): int
    {
        return $this->pageUid;
    }

    /**
     * Checks whether a non-zero page UID has been set.
     */
    protected function hasPageUid(): bool
    {
        return $this->getPageUid() > 0;
    }

    /**
     * Returns the name of the main table for this CSV export.
     *
     * @return non-empty-string
     */
    protected function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * Depending on the configuration, either returns the first line containing the specification of the separator
     * character or just an empty string.
     */
    protected function createCsvSeparatorLine(): string
    {
        if (!$this->configuration->getAsBoolean('addExcelSpecificSeparatorLineToCsv')) {
            return '';
        }

        return 'sep=' . self::COLUMN_SEPARATOR . self::LINE_SEPARATOR;
    }

    /**
     * Creates the heading line for a CSV event list.
     *
     * @return string header list, will not be empty if the CSV export has been configured correctly
     */
    protected function createCsvHeading(): string
    {
        return \implode(self::COLUMN_SEPARATOR, $this->getCsvHeadings());
    }

    /**
     * Escapes a single field for CSV.
     */
    protected function escapeFieldForCsv(string $fieldContent): string
    {
        if (\str_contains($fieldContent, '"')) {
            $escapedFieldValue = '"' . \str_replace('"', '""', $fieldContent) . '"';
        } elseif (\str_contains($fieldContent, ';') || \str_contains($fieldContent, "\n")) {
            $escapedFieldValue = '"' . $fieldContent . '"';
        } else {
            $escapedFieldValue = $fieldContent;
        }

        return $escapedFieldValue;
    }

    /**
     * Sets the page UID of the records to retrieve.
     *
     * @param int<0, max> $pageUid the page UID of the records, must be >= 0
     */
    public function setPageUid(int $pageUid): void
    {
        $this->pageUid = $pageUid;
    }

    /**
     * Sets the event UID of the registrations to retrieve.
     *
     * @param int<0, max> $eventUid the event UID of the registrations, must be >= 0
     *
     * @throws \InvalidArgumentException
     */
    public function setEventUid(int $eventUid): void
    {
        $this->eventUid = $eventUid;
    }

    /**
     * Returns the event UID of the registrationsToRetrieve.
     *
     * @return int<0, max> the event UID, will be >= 0
     */
    protected function getEventUid(): int
    {
        return $this->eventUid;
    }

    /**
     * Checks whether a non-zero event UID has been set.
     */
    protected function hasEventUid(): bool
    {
        return $this->getEventUid() > 0;
    }

    /**
     * @throws \BadMethodCallException
     */
    public function render(): string
    {
        if (!$this->hasEventUid() && !$this->hasPageUid()) {
            throw new \BadMethodCallException('No event UID or page UID set', 1390320210);
        }

        $allLines = array_merge([$this->createCsvHeading()], $this->createCsvBodyLines());

        $allLines = $this->createCsvSeparatorLine()
            . implode(self::LINE_SEPARATOR, $allLines)
            . self::LINE_SEPARATOR;

        return GeneralUtility::makeInstance(HookProvider::class, RegistrationListCsv::class)
            ->executeHookReturningModifiedValue('modifyCsv', $allLines, $this);
    }

    /**
     * Returns the localized field names.
     *
     * @return list<string> field keys, will be empty if no fields should be exported
     */
    protected function getCsvHeadings(): array
    {
        $headerLabels = [];
        foreach ($this->getFrontEndUserFieldKeys() as $key) {
            $headerLabels[] = 'fe_users.' . $key;
        }
        foreach ($this->getRegistrationFieldKeys() as $key) {
            $headerLabels[] = $this->getTableName() . '.' . $key;
        }

        return $headerLabels;
    }

    /**
     * Returns the keys of the front-end user fields to export.
     *
     * @return list<non-empty-string>
     */
    abstract protected function getFrontEndUserFieldKeys(): array;

    /**
     * Returns the keys of the registration fields to export.
     *
     * @return list<non-empty-string>
     */
    abstract protected function getRegistrationFieldKeys(): array;

    /**
     * Creates the body lines of the CSV export.
     *
     * @return list<string>
     */
    protected function createCsvBodyLines(): array
    {
        $registrationBagBuilder = $this->createRegistrationBagBuilder();

        if ($this->hasEventUid()) {
            $eventUid = $this->getEventUid();
            \assert($eventUid > 0);
            $registrationBagBuilder->limitToEvent($eventUid);
        } elseif ($this->hasPageUid()) {
            $registrationBagBuilder->setSourcePages((string)$this->getPageUid(), self::RECURSION_DEPTH);
        }

        return $this->getRegistrationsCsvList($registrationBagBuilder);
    }

    /**
     * Creates a registrationBagBuilder with some preset limitations.
     */
    protected function createRegistrationBagBuilder(): RegistrationBagBuilder
    {
        $registrationBagBuilder = GeneralUtility::makeInstance(RegistrationBagBuilder::class);
        $registrationBagBuilder->limitToRegular();
        $registrationBagBuilder->limitToExistingUsers();

        return $registrationBagBuilder;
    }

    /**
     * Returns the list of registrations as CSV separated values.
     *
     * The fields are separated by semicolons and the lines by CRLF.
     *
     * @param RegistrationBagBuilder $builder the bag builder already limited to the registrations
     *        which should be returned
     *
     * @return list<string> the list of registrations, will be empty if no registrations have been given
     *
     * @throws \RuntimeException
     */
    protected function getRegistrationsCsvList(RegistrationBagBuilder $builder): array
    {
        $csvLines = [];

        foreach ($builder->build() as $registration) {
            $userData = $this->createCsvColumnsForFrontEndUser($registration);
            $registrationData = $this->createCsvColumnsForRegistration($registration);
            $csvLines[] = implode(self::COLUMN_SEPARATOR, array_merge($userData, $registrationData));
        }

        return $csvLines;
    }

    /**
     * Retrieves data from an object and returns that data as an array of values. The individual values are already
     * wrapped in double quotes, with the contents having all quotes escaped.
     *
     * @param LegacyRegistration $model object that will deliver the data
     *
     * @return list<string> the data for the keys provided in $keys (may be empty)
     */
    protected function createCsvColumnsForRegistration(LegacyRegistration $model): array
    {
        $csvLines = [];

        foreach ($this->getRegistrationFieldKeys() as $key) {
            $csvLines[] = $this->escapeFieldForCsv($model->getRegistrationData($key));
        }

        return $csvLines;
    }

    /**
     * Retrieves data from an object and returns that data as an array of values. The individual values are already
     * wrapped in double quotes, with the contents having all quotes escaped.
     *
     * @param LegacyRegistration $model object that will deliver the data
     *
     * @return list<string> the data for the keys provided in $keys (may be empty)
     */
    protected function createCsvColumnsForFrontEndUser(LegacyRegistration $model): array
    {
        $csvLines = [];

        foreach ($this->getFrontEndUserFieldKeys() as $key) {
            $csvLines[] = $this->escapeFieldForCsv($model->getUserData($key));
        }

        return $csvLines;
    }
}
