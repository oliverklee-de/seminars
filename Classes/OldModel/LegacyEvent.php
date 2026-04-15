<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\OldModel;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\DataStructures\Collection;
use OliverKlee\Oelib\Interfaces\Time;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Oelib\ViewHelpers\PriceViewHelper;
use OliverKlee\Seminars\Bag\EventBag;
use OliverKlee\Seminars\Bag\OrganizerBag;
use OliverKlee\Seminars\Bag\SpeakerBag;
use OliverKlee\Seminars\Bag\TimeSlotBag;
use OliverKlee\Seminars\BagBuilder\CategoryBagBuilder;
use OliverKlee\Seminars\BagBuilder\EventBagBuilder;
use OliverKlee\Seminars\BagBuilder\OrganizerBagBuilder;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Domain\Model\Registration\Registration as ExtbaseRegistration;
use OliverKlee\Seminars\Mapper\FrontEndUserMapper;
use OliverKlee\Seminars\Mapper\PlaceMapper;
use OliverKlee\Seminars\Model\FrontEndUser;
use OliverKlee\Seminars\Model\Place;
use OliverKlee\Seminars\Service\RegistrationManager;
use OliverKlee\Seminars\Templating\TemplateHelper;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * This class represents a seminar (or similar event).
 */
class LegacyEvent extends AbstractTimeSpan
{
    /**
     * @var string the name of the SQL table this class corresponds to
     */
    protected static string $tableName = 'tx_seminars_seminars';

    private bool $registrationsHaveBeenRetrieved = false;

    /**
     * @var array<array<string, string|int>>
     */
    private array $registrations = [];

    /**
     * @var int<0, max>
     */
    protected int $numberOfAttendances = 0;

    protected bool $statisticsHaveBeenCalculated = false;

    private ?LegacyEvent $topic = null;

    public function getTopic(): ?LegacyEvent
    {
        if ($this->topic instanceof LegacyEvent) {
            return $this->topic;
        }
        if (!$this->isEventDate()) {
            return null;
        }

        $topic = $this->loadTopic();
        // Avoid infinite loops due to date records that have been converted to a topic or single event.
        if ($topic instanceof self && !$topic->isEventDate()) {
            $this->setTopic($topic);
        }

        return $this->topic;
    }

    public function setTopic(LegacyEvent $topic): void
    {
        $this->topic = $topic;
    }

    /**
     * Gets a list of the titles of (topic) records referenced by the this record.
     *
     * @param string $foreignTable the name of the foreign table (must not be empty), having the uid and title fields
     * @param string $mmTable the name of the m:m table, having the uid_local, uid_foreign and sorting fields
     *
     * @return list<string> the titles of the referenced records
     */
    protected function getTopicMmRecordTitles(string $foreignTable, string $mmTable): array
    {
        $uid = $this->isEventDate() ? $this->getTopicUid() : $this->getUid();

        return $this->getMmRecordTitlesByUid($foreignTable, $mmTable, $uid);
    }

    /**
     * Gets our topic's title. For date records, this will return the
     * corresponding topic record's title.
     *
     * @return string our topic title (or '' if there is an error)
     */
    public function getTitle(): string
    {
        return $this->getTopicString('title');
    }

    /**
     * @return string our seminar subtitle (or '' if there is an error)
     */
    public function getSubtitle(): string
    {
        return $this->getTopicString('subtitle');
    }

    public function hasSubtitle(): bool
    {
        return $this->hasTopicString('subtitle');
    }

    /**
     * @return string our seminar description (or '' if there is an error)
     */
    public function getDescription(): string
    {
        return $this->getTopicString('description');
    }

    /**
     * @param string $description the description for this event, may be empty
     */
    public function setDescription(string $description): void
    {
        $this->setRecordPropertyString('description', $description);
    }

    public function hasDescription(): bool
    {
        return $this->hasTopicString('description');
    }

    /**
     * @return string HTML of the additional information (or '' if there is an error)
     */
    public function getAdditionalInformation(): string
    {
        return $this->getTopicString('additional_information');
    }

    /**
     * @param string $additionalInformation our additional information, may be empty
     */
    public function setAdditionalInformation(string $additionalInformation): void
    {
        $this->setRecordPropertyString(
            'additional_information',
            $additionalInformation,
        );
    }

    public function hasAdditionalInformation(): bool
    {
        return $this->hasTopicString('additional_information');
    }

    /**
     * Gets the unique seminar title, consisting of the seminar title and the
     * date (comma-separated).
     *
     * If the seminar has no date, just the title is returned.
     *
     * Note: This function does not htmlspecialchar its return value.
     *
     * @param string $dash the character used to separate start date and end date
     *
     * @return string the unique seminar title (or '' if there is an error)
     */
    public function getTitleAndDate(string $dash = '–'): string
    {
        $date = $this->hasDate() ? ', ' . $this->getDate($dash) : '';

        return $this->getTitle() . $date;
    }

    /**
     * Gets the accreditation number (which actually is a string, not an integer).
     *
     * @return string the accreditation number (may be empty)
     */
    public function getAccreditationNumber(): string
    {
        return $this->getRecordPropertyString('accreditation_number');
    }

    public function hasAccreditationNumber(): bool
    {
        return $this->hasRecordPropertyString('accreditation_number');
    }

    /**
     * Gets the number of credit points for this seminar
     * (or an empty string if it is not set yet).
     *
     * @return string the number of credit points or an empty string if it is 0
     */
    public function getCreditPoints(): string
    {
        return $this->hasCreditPoints() ? (string)$this->getTopicInteger('credit_points') : '';
    }

    public function hasCreditPoints(): bool
    {
        return $this->hasTopicInteger('credit_points');
    }

    /**
     * Gets our venue(s), complete as RTE'ed HTML with address and
     * links.
     *
     * @param TemplateHelper $plugin the current FE plugin
     *
     * @return string our places description (or '' if there is an error)
     */
    public function getPlaceWithDetails(TemplateHelper $plugin): string
    {
        if (!$this->hasPlace()) {
            return '';
        }

        $result = '';
        $contentObjectRenderer = $plugin->getContentObjectRenderer();
        foreach ($this->getPlacesAsArray() as $place) {
            $encodedPlaceTitle = \htmlspecialchars((string)$place['title'], ENT_QUOTES | ENT_HTML5);
            $homepage = (string)($place['homepage'] ?? '');
            if ($contentObjectRenderer instanceof ContentObjectRenderer && $homepage !== '') {
                $placeTitleHtml = $contentObjectRenderer->typoLink($encodedPlaceTitle, ['parameter' => $homepage]);
            } else {
                $placeTitleHtml = $encodedPlaceTitle;
            }
            $plugin->setMarker('place_item_title', $placeTitleHtml);

            $descriptionParts = [];
            if ((string)$place['address'] !== '') {
                $addressParts = GeneralUtility::trimExplode("\r", (string)$place['address'], true);
                $address = \implode(', ', $addressParts);
                $descriptionParts[] = \htmlspecialchars($address, ENT_QUOTES | ENT_HTML5);
            }

            $description = \implode(', ', $descriptionParts);
            if (!empty($place['directions'])) {
                $description .= $this->renderAsRichText((string)$place['directions']);
            }
            $plugin->setMarker('place_item_description', $description);

            $result .= $plugin->getSubpart('PLACE_LIST_ITEM');
        }

        $plugin->setMarker('place_list_content', $result);

        return $plugin->getSubpart('PLACE_LIST_COMPLETE');
    }

    /**
     * Returns a comma-separated list of city names that were set in the place
     * record(s).
     * If no places are set, or no cities are selected in the set places, an
     * empty string will be returned.
     *
     * @return string comma-separated list of cities for this event, may be empty
     */
    public function getCities(): string
    {
        if (!$this->hasCities()) {
            return '';
        }

        $cityList = $this->getCitiesFromPlaces();

        // Makes sure that each city is exactly once in the array and then
        // returns this list.
        $cityListUnique = array_unique($cityList);
        return implode(', ', $cityListUnique);
    }

    /**
     * Checks whether the current event has at least one place set, and if
     * this/these pace(s) have a city set.
     * Returns a boolean TRUE if at least one of the set places has a
     * city set, returns FALSE otherwise.
     *
     * @return bool whether at least one place with city are set for the current event
     */
    public function hasCities(): bool
    {
        return $this->hasPlace() && count($this->getCitiesFromPlaces()) > 0;
    }

    /**
     * Returns the city names for this event.
     *
     * These are fetched from the referenced place records of this event. If no
     * place is set, or the set place(s) don't have any city set, an empty
     * array will be returned.
     *
     * @return list<string>
     */
    public function getCitiesFromPlaces(): array
    {
        return \array_column($this->getPlacesAsArray(), 'city');
    }

    /**
     * Gets our venue(s) with address and links as HTML, not RTE'ed yet,
     * separated by LF.
     *
     * @return string our places description (or '' if there is an error)
     */
    protected function getPlaceWithDetailsRaw(): string
    {
        if (!$this->hasPlace()) {
            return '';
        }

        $placeTexts = [];

        foreach ($this->getPlacesAsArray() as $place) {
            $placeText = (string)($place['title'] ?? '');
            if ((string)($place['homepage'] ?? '') !== '') {
                $placeText .= "\n" . $place['homepage'];
            }

            $descriptionParts = [];
            if ((string)($place['address'] ?? '') !== '') {
                $descriptionParts[] = str_replace("\r", ',', $place['address']);
            }

            if (!empty($descriptionParts)) {
                $placeText .= ', ' . implode(', ', $descriptionParts);
            }
            if ((string)($place['directions'] ?? '') !== '') {
                $placeText .= "\n" . str_replace("\r", ', ', $place['directions']);
            }

            $placeTexts[] = $placeText;
        }

        return implode("\n", $placeTexts);
    }

    /**
     * Gets all places that are related to this event as an array.
     *
     * The array will be two-dimensional: The first dimensional is just numeric.
     * The second dimension is associative with the following keys:
     * title, address, city, homepage, directions
     *
     * @return list<array<string, string|int>>
     *         all places as a two-dimensional array, will be empty if there are no places assigned
     */
    protected function getPlacesAsArray(): array
    {
        $queryBuilder = self::getQueryBuilderForTable('tx_seminars_sites');

        return $queryBuilder
            ->select('uid', 'title', 'address', 'city', 'homepage', 'directions')
            ->from('tx_seminars_sites')
            ->leftJoin(
                'tx_seminars_sites',
                'tx_seminars_seminars_place_mm',
                'mm',
                $queryBuilder->expr()->eq('tx_seminars_sites.uid', $queryBuilder->quoteIdentifier('mm.uid_foreign')),
            )
            ->where(
                $queryBuilder->expr()->eq(
                    'mm.uid_local',
                    $queryBuilder->createNamedParameter($this->getUid(), Connection::PARAM_INT),
                ),
            )
            ->orderBy('mm.sorting')
            ->executeQuery()->fetchAllAssociative();
    }

    /**
     * Gets our venue(s) as a plain text list (just the names).
     *
     * Note: This function does not htmlspecialchar the place titles.
     *
     * @return string our places list (or '' if there is an error)
     */
    public function getPlaceShort(): string
    {
        if (!$this->hasPlace()) {
            return '';
        }

        $places = $this->getPlacesAsArray();

        return implode(', ', array_column($places, 'title'));
    }

    /**
     * Creates and returns a speakerbag object.
     *
     * @param 'speakers'|'tutors'|'leaders'|'partners' $speakerType
     */
    private function getSpeakerBag(string $speakerType = 'speakers'): SpeakerBag
    {
        switch ($speakerType) {
            case 'partners':
                $mmTable = 'tx_seminars_seminars_speakers_mm_partners';
                break;
            case 'tutors':
                $mmTable = 'tx_seminars_seminars_speakers_mm_tutors';
                break;
            case 'leaders':
                $mmTable = 'tx_seminars_seminars_speakers_mm_leaders';
                break;
            case 'speakers':
                // The fallthrough is intended.
            default:
                $mmTable = 'tx_seminars_seminars_speakers_mm';
        }

        return GeneralUtility::makeInstance(
            SpeakerBag::class,
            $mmTable . '.uid_local = ' . $this->getUid()
            . ' AND tx_seminars_speakers.uid = ' . $mmTable . '.uid_foreign',
            $mmTable,
            '',
            'sorting',
        );
    }

    /**
     * Gets our speaker (or speakers), complete as RTE'ed HTML with details and links.
     *
     * Returns an empty paragraph if this seminar doesn't have any speakers.
     *
     * As speakers can be related to this event as speakers, partners, tutors or
     * leaders, the type relation can be specified. The default is "speakers".
     *
     * @param TemplateHelper $plugin the live pibase object
     * @param 'speakers'|'tutors'|'leaders'|'partners' $speakerType
     *
     * @return string our speakers (or '' if there is an error)
     */
    public function getSpeakersWithDetails(TemplateHelper $plugin, string $speakerType = 'speakers'): string
    {
        if (!$this->hasSpeakersOfType($speakerType)) {
            return '';
        }

        $result = [];

        /** @var LegacySpeaker $speaker */
        foreach ($this->getSpeakerBag($speakerType) as $speaker) {
            $name = $speaker->getLinkedTitle();
            if ($speaker->hasOrganization()) {
                $name .= ', ' . \htmlspecialchars($speaker->getOrganization(), ENT_QUOTES | ENT_HTML5);
            }
            $plugin->setMarker('speaker_item_title', $name);
            $plugin->setMarker(
                'speaker_item_description',
                $speaker->hasDescription() ? $speaker->getDescription() : '',
            );
            $plugin->setMarker(
                'speaker_image',
                $speaker->hasImage() ? $this->renderSpeakerImage($speaker, $plugin) : '',
            );
            $result[] = $plugin->getSubpart('SPEAKER_LIST_ITEM');
        }

        return \implode("\n", $result);
    }

    private function renderSpeakerImage(LegacySpeaker $speaker, TemplateHelper $plugin): string
    {
        $imageConfiguration = [
            'altText' => $plugin->translate('speakerImage.alt'),
            'titleText' => $plugin->translate('speakerImage.alt'),
            'params' => 'class="speaker-image"',
            'file' => $speaker->getImage()->getPublicUrl(),
            'file.' => [
                'width' => $plugin->getConfValueInteger('speakerImageWidth') . 'c',
                'height' => $plugin->getConfValueInteger('speakerImageHeight') . 'c',
            ],
        ];

        return $plugin->getContentObjectRenderer()->cObjGetSingle('IMAGE', $imageConfiguration);
    }

    /**
     * Gets our speaker (or speakers), as HTML with details and URLs, but not
     * RTE'ed yet.
     * Returns an empty string if this event doesn't have any speakers.
     *
     * As speakers can be related to this event as speakers, partners, tutors or
     * leaders, the type relation can be specified. The default is "speakers".
     *
     * @param 'speakers'|'tutors'|'leaders'|'partners' $speakerType
     *
     * @return string our speakers (or '' if there is an error)
     */
    protected function getSpeakersWithDescriptionRaw(string $speakerType = 'speakers'): string
    {
        if (!$this->hasSpeakersOfType($speakerType)) {
            return '';
        }

        $result = '';

        /** @var LegacySpeaker $speaker */
        foreach ($this->getSpeakerBag($speakerType) as $speaker) {
            $result .= $speaker->getTitle();
            if ($speaker->hasOrganization()) {
                $result .= ', ' . $speaker->getOrganization();
            }
            if ($speaker->hasHomepage()) {
                $result .= ', ' . $speaker->getHomepage();
            }
            $result .= "\n";

            if ($speaker->hasDescription()) {
                $result .= $speaker->getDescriptionRaw() . "\n";
            }
        }

        return $result;
    }

    /**
     * Gets our speaker (or speakers) as a list (just their names),
     * linked to their homepage, if the speaker (or speakers) has one.
     * Returns an empty string if this seminar doesn't have any speakers.
     *
     * As speakers can be related to this event as speakers, partners, tutors or
     * leaders, the type relation can be specified. The default is "speakers".
     *
     * @param 'speakers'|'tutors'|'leaders'|'partners' $speakerType
     *
     * @return string our speakers list, will be empty if an error occurred during processing
     */
    public function getSpeakersShort(string $speakerType = 'speakers'): string
    {
        if (!$this->hasSpeakersOfType($speakerType)) {
            return '';
        }

        $result = [];

        $frontEndController = $GLOBALS['TSFE'] ?? null;
        $contentObject = $frontEndController instanceof TypoScriptFrontendController ? $frontEndController->cObj : null;

        /** @var LegacySpeaker $speaker */
        foreach ($this->getSpeakerBag($speakerType) as $speaker) {
            $encodedTitle = \htmlspecialchars($speaker->getTitle(), ENT_QUOTES | ENT_HTML5);

            if ($contentObject instanceof ContentObjectRenderer && $speaker->hasHomepage()) {
                $result[] = $contentObject->typoLink($encodedTitle, ['parameter' => $speaker->getHomepage()]);
            } else {
                $result[] = $encodedTitle;
            }
        }

        return implode(', ', $result);
    }

    /**
     * @return int<0, max>
     */
    public function getNumberOfSpeakers(): int
    {
        $number = $this->getRecordPropertyInteger('speakers');
        \assert($number >= 0);

        return $number;
    }

    /**
     * @return int<0, max>
     */
    public function getNumberOfPartners(): int
    {
        $number = $this->getRecordPropertyInteger('partners');
        \assert($number >= 0);

        return $number;
    }

    /**
     * @return int<0, max>
     */
    public function getNumberOfTutors(): int
    {
        $number = $this->getRecordPropertyInteger('tutors');
        \assert($number >= 0);

        return $number;
    }

    /**
     * @return int<0, max>
     */
    public function getNumberOfLeaders(): int
    {
        $number = $this->getRecordPropertyInteger('leaders');
        \assert($number >= 0);

        return $number;
    }

    /**
     * Checks whether we have speaker relations of the specified type set.
     *
     * @param 'speakers'|'tutors'|'leaders'|'partners' $speakerType
     */
    public function hasSpeakersOfType(string $speakerType = 'speakers'): bool
    {
        switch ($speakerType) {
            case 'partners':
                $hasSpeakers = $this->hasPartners();
                break;
            case 'tutors':
                $hasSpeakers = $this->hasTutors();
                break;
            case 'leaders':
                $hasSpeakers = $this->hasLeaders();
                break;
            case 'speakers':
                // The fallthrough is intended.
            default:
                $hasSpeakers = $this->hasSpeakers();
        }

        return $hasSpeakers;
    }

    public function hasSpeakers(): bool
    {
        return $this->hasRecordPropertyInteger('speakers');
    }

    public function hasPartners(): bool
    {
        return $this->hasRecordPropertyInteger('partners');
    }

    public function hasTutors(): bool
    {
        return $this->hasRecordPropertyInteger('tutors');
    }

    public function hasLeaders(): bool
    {
        return $this->hasRecordPropertyInteger('leaders');
    }

    /**
     * Returns the language key suffix for the speaker headings.
     *
     * @param 'speakers'|'tutors'|'leaders'|'partners' $speakerType
     *
     * @return string header marker for speaker heading will be
     *                'type_number'. Number will be 'single' or 'multiple'.
     *                Will be empty if no speaker of the given type exists for
     *                this seminar.
     */
    public function getLanguageKeySuffixForType(string $speakerType): string
    {
        if (!$this->hasSpeakersOfType($speakerType)) {
            return '';
        }

        return $this->getSpeakerBag($speakerType)->count() > 1 ? $speakerType : ($speakerType . '_single_unknown');
    }

    /**
     * Gets our regular price as a string containing amount and currency. If
     * no regular price has been set, this will be "free".
     */
    public function getPriceRegular(): string
    {
        if ($this->hasPriceRegular()) {
            $result = $this->formatPrice($this->getPriceRegularAmount());
        } else {
            $result = $this->translate('message_forFree');
        }

        return $result;
    }

    /**
     * Gets our regular price as a decimal.
     */
    private function getPriceRegularAmount(): string
    {
        return $this->getTopicDecimal('price_regular');
    }

    /**
     * Returns the price, formatted as configured in TS.
     *
     * @return string the price, formatted as in configured in TS
     */
    public function formatPrice(string $value): string
    {
        $currency = ConfigurationRegistry::get('plugin.tx_seminars')->getAsString('currency');
        if ($currency === '') {
            $currency = 'EUR';
        }

        $priceViewHelper = GeneralUtility::makeInstance(PriceViewHelper::class);
        $priceViewHelper->setCurrencyFromIsoAlpha3Code($currency);
        $priceViewHelper->setValue((float)$value);

        return $priceViewHelper->render();
    }

    /**
     * Returns the current regular price for this event.
     * If there is a valid early bird offer, this price will be returned,
     * otherwise the default price.
     *
     * @return string the price and the currency
     */
    public function getCurrentPriceRegular(): string
    {
        if ($this->getPriceOnRequest()) {
            return $this->translate('message_onRequest');
        }

        return $this->earlyBirdApplies() ? $this->getEarlyBirdPriceRegular() : $this->getPriceRegular();
    }

    /**
     * Returns the current price for this event.
     * If there is a valid early bird offer, this price will be returned, the
     * default special price otherwise.
     *
     * @return string the price and the currency
     */
    public function getCurrentPriceSpecial(): string
    {
        if ($this->getPriceOnRequest()) {
            return $this->translate('message_onRequest');
        }

        return $this->earlyBirdApplies() ? $this->getEarlyBirdPriceSpecial() : $this->getPriceSpecial();
    }

    /**
     * Gets our regular price during the early bird phase as a string containing
     * amount and currency.
     *
     * @return string the regular early bird event price
     */
    public function getEarlyBirdPriceRegular(): string
    {
        return $this->hasEarlyBirdPriceRegular()
            ? $this->formatPrice($this->getEarlyBirdPriceRegularAmount()) : '';
    }

    /**
     * Gets our regular price during the early bird phase as a decimal.
     *
     * If there is no regular early bird price, this function returns "0.00".
     *
     * @return string the regular early bird event price
     */
    private function getEarlyBirdPriceRegularAmount(): string
    {
        return $this->getTopicDecimal('price_regular_early');
    }

    /**
     * Gets our special price during the early bird phase as a string containing
     * amount and currency.
     *
     * @return string the regular early bird event price
     */
    public function getEarlyBirdPriceSpecial(): string
    {
        return $this->hasEarlyBirdPriceSpecial()
            ? $this->formatPrice($this->getEarlyBirdPriceSpecialAmount()) : '';
    }

    /**
     * Gets our special price during the early bird phase as a decimal.
     *
     * If there is no special price during the early bird phase, this function
     * returns "0.00".
     *
     * @return string the special event price during the early bird phase
     */
    private function getEarlyBirdPriceSpecialAmount(): string
    {
        return $this->getTopicDecimal('price_special_early');
    }

    /**
     * Checks whether this seminar has a non-zero regular price set.
     */
    public function hasPriceRegular(): bool
    {
        return $this->hasTopicDecimal('price_regular');
    }

    /**
     * Checks whether this seminar has a non-zero regular early bird price set.
     */
    protected function hasEarlyBirdPriceRegular(): bool
    {
        return $this->hasTopicDecimal('price_regular_early');
    }

    /**
     * Checks whether this seminar has a non-zero special early bird price set.
     */
    protected function hasEarlyBirdPriceSpecial(): bool
    {
        return $this->hasTopicDecimal('price_special_early');
    }

    /**
     * Checks whether this event has a deadline for the early bird prices set.
     */
    private function hasEarlyBirdDeadline(): bool
    {
        return $this->hasRecordPropertyInteger('deadline_early_bird');
    }

    /**
     * Returns whether an early bird price applies.
     */
    protected function earlyBirdApplies(): bool
    {
        return $this->hasEarlyBirdPrice() && !$this->isEarlyBirdDeadlineOver();
    }

    /**
     * Checks whether this event is sold with early bird prices.
     *
     * This will return TRUE if the event has a deadline and a price defined
     * for early-bird registrations. If the special price (e.g. for students)
     * is not used, then the student's early bird price is not checked.
     *
     * Attention: Both prices (standard and special) need to have an early bird
     * version for this function to return TRUE (if there is a regular special
     * price).
     *
     * @return bool TRUE if an early bird deadline and early bird prices
     *                 are set
     */
    public function hasEarlyBirdPrice(): bool
    {
        // whether the event has regular prices set (a normal one and an early bird)
        $priceRegularIsOk = $this->hasPriceRegular()
            && $this->hasEarlyBirdPriceRegular();

        // whether no special price is set, or both special prices
        // (normal and early bird) are set
        $priceSpecialIsOk = !$this->hasPriceSpecial()
            || ($this->hasPriceSpecial() && $this->hasEarlyBirdPriceSpecial());

        return $this->hasEarlyBirdDeadline()
            && $priceRegularIsOk
            && $priceSpecialIsOk;
    }

    /**
     * Gets our special price as a string containing amount and currency.
     * Returns an empty string if there is no special price set.
     */
    public function getPriceSpecial(): string
    {
        return $this->hasPriceSpecial()
            ? $this->formatPrice($this->getPriceSpecialAmount()) : '';
    }

    /**
     * Gets our special price as a decimal.
     *
     * If there is no special price, this function returns "0.00".
     */
    private function getPriceSpecialAmount(): string
    {
        return $this->getTopicDecimal('price_special');
    }

    /**
     * Checks whether this seminar has a non-zero special price set.
     */
    public function hasPriceSpecial(): bool
    {
        return $this->hasTopicDecimal('price_special');
    }

    public function getPriceOnRequest(): bool
    {
        return $this->getTopicBoolean('price_on_request');
    }

    public function setPriceOnRequest(bool $priceOnRequest): void
    {
        $this->setRecordPropertyBoolean('price_on_request', $priceOnRequest);
    }

    /**
     * Gets the titles of allowed payment methods for this event.
     *
     * @return array<int, string> our payment method titles, will be an empty array if there are no payment methods
     */
    public function getPaymentMethods(): array
    {
        if (!$this->hasPaymentMethods()) {
            return [];
        }

        return array_column($this->getPaymentMethodsAsArray(), 'title');
    }

    /**
     * Gets our allowed payment methods, just as plain text, including the detailed description.
     * Returns an empty string if this seminar does not have any payment methods.
     *
     * @return string our payment methods as plain text (or '' if there is an error)
     */
    public function getPaymentMethodsPlain(): string
    {
        if (!$this->hasPaymentMethods()) {
            return '';
        }

        $result = '';

        foreach ($this->getPaymentMethodsAsArray() as $paymentMethod) {
            $result .= $paymentMethod['title'] . ': ';
            $result .= $paymentMethod['description'] . "\n\n";
        }

        return $result;
    }

    /**
     * Gets our allowed payment methods, just as plain text separated by LF, without the detailed description.
     *
     * Returns an empty string if this seminar does not have any payment methods.
     *
     * @return string our payment methods as plain text (or '' if there is an error)
     */
    protected function getPaymentMethodsPlainShort(): string
    {
        if (!$this->hasPaymentMethods()) {
            return '';
        }

        return implode("\n", $this->getPaymentMethods());
    }

    /**
     * Get a single payment method, just as plain text, including the detailed description.
     *
     * Returns an empty string if the corresponding payment method could not be retrieved.
     *
     * @param int $uid the UID of a single payment method, must not be zero
     *
     * @return string the selected payment method as plain text (or '' if there is an error)
     */
    public function getSinglePaymentMethodPlain(int $uid): string
    {
        if ($uid <= 0) {
            return '';
        }

        $table = 'tx_seminars_payment_methods';
        $data = self::getConnectionForTable($table)->select(['*'], $table, ['uid' => $uid])->fetchAssociative();
        if (!\is_array($data)) {
            return '';
        }

        $result = (string)$data['title'];
        $description = (string)$data['description'];
        if ($description !== '') {
            $result .= ': ' . $description;
        }

        return $result . "\n\n";
    }

    /**
     * Get a single payment method, just as plain text, without the detailed description.
     *
     * Returns an empty string if the corresponding payment method could not be retrieved.
     *
     * @param int<0, max> $uid the UID of a single payment method
     *
     * @return string the selected payment method as plain text (or '' if there is an error)
     */
    public function getSinglePaymentMethodShort(int $uid): string
    {
        if ($uid <= 0) {
            return '';
        }

        $table = 'tx_seminars_payment_methods';
        $data = self::getConnectionForTable($table)->select(['*'], $table, ['uid' => $uid])->fetchAssociative();

        return \is_array($data) ? (string)$data['title'] : '';
    }

    public function hasPaymentMethods(): bool
    {
        return $this->hasTopicInteger('payment_methods');
    }

    /**
     * @return int<0, max>
     */
    public function getNumberOfPaymentMethods(): int
    {
        $number = $this->getTopicInteger('payment_methods');
        \assert($number >= 0);

        return $number;
    }

    /**
     * Returns the type of the record. This is one out of the following values:
     * 0 = single event (and default value of older records)
     * 1 = multiple event topic record
     * 2 = multiple event date record
     */
    public function getRecordType(): int
    {
        return $this->getRecordPropertyInteger('object_type');
    }

    public function hasEventType(): bool
    {
        return $this->hasTopicInteger('event_type');
    }

    /**
     * Returns the UID of the event type that was selected for this event. If no
     * event type has been set, 0 will be returned.
     *
     * @return int UID of the event type for this event or 0 if no
     *                 event type is set
     */
    public function getEventTypeUid(): int
    {
        return $this->getTopicInteger('event_type');
    }

    /**
     * Returns the event type as a string (e.g. "Workshop" or "Lecture").
     * If the seminar has a event type selected, that one is returned.
     * Otherwise, an empty string will be returned.
     *
     * @return string the type of this event, will be empty if this event does not have a type
     */
    public function getEventType(): string
    {
        if (!$this->hasEventType()) {
            return '';
        }

        $table = 'tx_seminars_event_types';
        $data = self::getConnectionForTable($table)
            ->select(['title'], $table, ['uid' => $this->getTopicInteger('event_type')])->fetchAssociative();

        return \is_array($data) ? (string)$data['title'] : '';
    }

    /**
     * Sets the event type for this event.
     *
     * @param int<0, max> $eventType the UID of the event type to set
     */
    public function setEventType(int $eventType): void
    {
        $this->setRecordPropertyInteger('event_type', $eventType);
    }

    /**
     * Gets the minimum number of attendances required for this event
     * (ie. how many registrations are needed so this event can take place).
     *
     * @return int<0, max> the minimum number of attendances
     */
    public function getAttendancesMin(): int
    {
        $number = $this->getRecordPropertyInteger('attendees_min');
        \assert($number >= 0);

        return $number;
    }

    /**
     * Gets the maximum number of attendances for this event
     * (the total number of seats for this event).
     *
     * @return int<0, max> the maximum number of attendances
     */
    public function getAttendancesMax(): int
    {
        $number = $this->getRecordPropertyInteger('attendees_max');
        \assert($number >= 0);

        return $number;
    }

    /**
     * Gets the number of attendances for this seminar.
     *
     * @return int<0, max>
     */
    public function getAttendances(): int
    {
        $this->calculateStatisticsIfNeeded();

        return $this->numberOfAttendances;
    }

    /**
     * Checks whether there is at least one registration for this event.
     *
     * @return bool true if there is at least one registration for this event, false otherwise
     */
    public function hasAttendances(): bool
    {
        return $this->getAttendances() > 0;
    }

    /**
     * @return int<0, max> the number of vacancies (will be 0 if the seminar is overbooked)
     */
    public function getVacancies(): int
    {
        return \max(0, $this->getAttendancesMax() - $this->getAttendances());
    }

    /**
     * Gets the number of vacancies for this seminar. If there are at least as
     * many vacancies as configured as "showVacanciesThreshold" or this event
     * has an unlimited number of vacancies, a localized string "enough" is
     * returned instead. If there are no vacancies, a localized string
     * "fully booked" is returned.
     *
     * If this seminar does not require a registration or has been canceled, an empty string is returned.
     *
     * @return string string showing the number of vacancies, may be empty
     */
    public function getVacanciesString(): string
    {
        if ($this->isCanceled() || !$this->needsRegistration() || $this->isRegistrationDeadlineOver()) {
            return '';
        }

        if ($this->hasUnlimitedVacancies()) {
            return $this->translate('message_enough');
        }

        $vacancies = $this->getVacancies();
        $vacanciesThreshold = $this->getSharedConfiguration()->getAsNonNegativeInteger('showVacanciesThreshold');

        if ($vacancies === 0) {
            $result = $this->translate('message_fullyBooked');
        } elseif ($vacancies >= $vacanciesThreshold) {
            $result = $this->translate('message_enough');
        } else {
            $result = (string)$vacancies;
        }

        return $result;
    }

    /**
     * Checks whether this seminar still has vacancies (is not full yet).
     *
     * @return bool true if the seminar has vacancies, false if it is full
     */
    public function hasVacancies(): bool
    {
        return !$this->isFull();
    }

    /**
     * Checks whether this seminar already is full.
     *
     * @return bool true if the seminar is full, false if it still has
     *                 vacancies or if there are unlimited vacancies
     */
    public function isFull(): bool
    {
        return !$this->hasUnlimitedVacancies() && $this->getAttendances() >= $this->getAttendancesMax();
    }

    /**
     * Checks whether this seminar has enough attendances to take place.
     *
     * @return bool true if the seminar has enough attendances, false otherwise
     */
    public function hasEnoughAttendances(): bool
    {
        return $this->getAttendances() >= $this->getAttendancesMin();
    }

    /**
     * Returns TRUE if this seminar has at least one target group, FALSE
     * otherwise.
     *
     * @return bool TRUE if this seminar has at least one target group,
     *                 FALSE otherwise
     */
    public function hasTargetGroups(): bool
    {
        return $this->hasTopicInteger('target_groups');
    }

    /**
     * Returns a string of our event's target group titles separated by a comma
     * (or an empty string if there aren't any).
     *
     * @return string the target group titles of this seminar separated by a comma (or an empty string)
     */
    public function getTargetGroupNames(): string
    {
        if (!$this->hasTargetGroups()) {
            return '';
        }

        return \implode(', ', $this->getTargetGroupsAsArray());
    }

    /**
     * Returns an array of our events's target group titles (or an empty array if there are none).
     *
     * @return string[] the target groups of this event (or an empty array)
     */
    public function getTargetGroupsAsArray(): array
    {
        if (!$this->hasTargetGroups()) {
            return [];
        }

        return $this->getTopicMmRecordTitles('tx_seminars_target_groups', 'tx_seminars_seminars_target_groups_mm');
    }

    /**
     * @return int<0, max>
     */
    public function getNumberOfTargetGroups(): int
    {
        $number = $this->getRecordPropertyInteger('target_groups');
        \assert($number >= 0);

        return $number;
    }

    /**
     * Returns the latest date/time to register for a seminar.
     * This is either the registration deadline (if set) or the begin date of an
     * event.
     *
     * @return int the latest possible moment to register for this event
     */
    public function getLatestPossibleRegistrationTime(): int
    {
        return $this->hasRegistrationDeadline()
            ? $this->getRecordPropertyInteger('deadline_registration')
            : $this->getBeginDateAsTimestamp();
    }

    /**
     * Returns the latest date/time to register with early bird rebate for an
     * event. The latest time to register with early bird rebate is exactly at
     * the early bird deadline.
     *
     * @return int the latest possible moment to register with early
     *                 bird discount for an event
     */
    private function getLatestPossibleEarlyBirdRegistrationTime(): int
    {
        return $this->getRecordPropertyInteger('deadline_early_bird');
    }

    /**
     * Returns the seminar registration deadline: the date and also the time.
     * The returned string is formatted using the format configured in
     * dateFormatYMD and timeFormat.
     *
     * This function will return an empty string if this event does not have a
     * registration deadline.
     *
     * @return string the date + time of the deadline or an empty string
     *                if this event has no registration deadline
     */
    public function getRegistrationDeadline(): string
    {
        $result = '';

        if ($this->hasRegistrationDeadline()) {
            $result = \date($this->getDateFormat(), $this->getRecordPropertyInteger('deadline_registration'));
            $result .= \date(' ' . $this->getTimeFormat(), $this->getRecordPropertyInteger('deadline_registration'));
        }

        return $result;
    }

    /**
     * Checks whether this seminar has a deadline for registration set.
     *
     * @return bool TRUE if the seminar has a datetime set.
     */
    public function hasRegistrationDeadline(): bool
    {
        return $this->hasRecordPropertyInteger('deadline_registration');
    }

    /**
     * Returns the early bird deadline.
     * The returned string is formatted using the format configured in
     * dateFormatYMD and timeFormat.
     *
     * This function will return an empty string if this event does not have an
     * early-bird deadline.
     *
     * @return string the date and time of the early bird deadline or an
     *                early string if this event has no early-bird deadline
     */
    public function getEarlyBirdDeadline(): string
    {
        $result = '';

        if ($this->hasEarlyBirdDeadline()) {
            $result = \date($this->getDateFormat(), $this->getRecordPropertyInteger('deadline_early_bird'));
            $result .= \date(' ' . $this->getTimeFormat(), $this->getRecordPropertyInteger('deadline_early_bird'));
        }

        return $result;
    }

    /**
     * Returns the seminar unregistration deadline: the date and also the time.
     * The returned string is formatted using the format configured in
     * dateFormatYMD and timeFormat.
     *
     * This function will return an empty string if this event does not have a
     * unregistration deadline.
     *
     * @return string the date + time of the deadline or an empty string
     *                if this event has no unregistration deadline
     */
    public function getUnregistrationDeadline(): string
    {
        $result = '';

        if ($this->hasUnregistrationDeadline()) {
            $result = \date($this->getDateFormat(), $this->getRecordPropertyInteger('deadline_unregistration'));
            $result .= \date(' ' . $this->getTimeFormat(), $this->getRecordPropertyInteger('deadline_unregistration'));
        }

        return $result;
    }

    /**
     * Checks whether this seminar has a deadline for unregistration set.
     *
     * @return bool whether the seminar has an unregistration deadline set
     */
    public function hasUnregistrationDeadline(): bool
    {
        return $this->hasRecordPropertyInteger('deadline_unregistration');
    }

    /**
     * Gets the event's unregistration deadline as UNIX timestamp. Will be 0
     * if the event has no unregistration deadline set.
     *
     * @return int<0, max> the unregistration deadline as UNIX timestamp
     */
    public function getUnregistrationDeadlineAsTimestamp(): int
    {
        $deadline = $this->getRecordPropertyInteger('deadline_unregistration');
        \assert($deadline >= 0);

        return $deadline;
    }

    /**
     * Creates an organizer bag and returns it.
     */
    public function getOrganizerBag(): OrganizerBag
    {
        $uid = $this->getUid();
        if ($uid <= 0) {
            throw new \UnexpectedValueException('This event has no UID.', 1727202740);
        }

        $builder = GeneralUtility::makeInstance(OrganizerBagBuilder::class);
        $builder->limitToEvent($uid);

        return $builder->build();
    }

    /**
     * @throws \UnexpectedValueException if there are no organizers
     */
    public function getFirstOrganizer(): LegacyOrganizer
    {
        $organizers = $this->getOrganizerBag();
        $organizers->rewind();
        $firstOrganizer = $organizers->current();

        if (!$firstOrganizer instanceof LegacyOrganizer) {
            throw new \UnexpectedValueException('This event does not have any organizers.', 1724278146);
        }

        return $firstOrganizer;
    }

    /**
     * Gets our organizers (as HTML code with hyperlinks to their homepage, if they have any).
     *
     * @return string the hyperlinked names of our organizers
     */
    public function getOrganizers(): string
    {
        if (!$this->hasOrganizers()) {
            return '';
        }

        $result = [];

        /** @var LegacyOrganizer $organizer */
        foreach ($this->getOrganizerBag() as $organizer) {
            $result[] = $this->buildHtmlForOrganizerWithPossibleHomepageLink($organizer);
        }

        return implode(', ', $result);
    }

    protected function buildHtmlForOrganizerWithPossibleHomepageLink(LegacyOrganizer $organizer): string
    {
        $encodedName = \htmlspecialchars($organizer->getName(), ENT_QUOTES | ENT_HTML5);
        if (!$organizer->hasHomepage()) {
            return $encodedName;
        }

        $frontEndController = $GLOBALS['TSFE'] ?? null;
        $contentObject = $frontEndController instanceof TypoScriptFrontendController ? $frontEndController->cObj : null;
        if ($contentObject instanceof ContentObjectRenderer) {
            $html = $contentObject->typoLink($encodedName, ['parameter' => $organizer->getHomepage()]);
        } else {
            $html = $encodedName;
        }

        return $html;
    }

    /**
     * Gets our organizer's names (and URLs), separated by LF.
     *
     * @return string names and homepages of our organizers or an empty string if there are no organizers
     */
    protected function getOrganizersRaw(): string
    {
        if (!$this->hasOrganizers()) {
            return '';
        }

        $result = [];

        /** @var LegacyOrganizer $organizer */
        foreach ($this->getOrganizerBag() as $organizer) {
            $result[] = $organizer->getName() . ($organizer->hasHomepage() ? ', ' . $organizer->getHomepage() : '');
        }

        return implode("\n", $result);
    }

    /**
     * Gets our organizers' names and email addresses in the format '"John Doe" <john.doe@example.com>'.
     *
     * The name is not encoded yet.
     *
     * @return string[] the organizers' names and email addresses
     */
    public function getOrganizersNameAndEmail(): array
    {
        if (!$this->hasOrganizers()) {
            return [];
        }

        $result = [];

        /** @var LegacyOrganizer $organizer */
        foreach ($this->getOrganizerBag() as $organizer) {
            $result[] = '"' . $organizer->getName() . '"' . ' <' . $organizer->getEmailAddress() . '>';
        }

        return $result;
    }

    /**
     * Gets our organizers' email addresses in the format
     * "john.doe@example.com".
     *
     * @return string[] the organizers' email addresses
     */
    public function getOrganizersEmail(): array
    {
        if (!$this->hasOrganizers()) {
            return [];
        }

        $result = [];

        /** @var LegacyOrganizer $organizer */
        foreach ($this->getOrganizerBag() as $organizer) {
            $result[] = $organizer->getEmailAddress();
        }

        return $result;
    }

    /**
     * Gets our organizers' email footers.
     *
     * @return string[] the organizers' email footers, will be empty if no
     *               organizer was set, or all organizers have no email footer
     */
    public function getOrganizersFooter(): array
    {
        if (!$this->hasOrganizers()) {
            return [];
        }

        $result = [];

        /** @var LegacyOrganizer $organizer */
        foreach ($this->getOrganizerBag() as $organizer) {
            $emailFooter = $organizer->getEmailFooter();
            if ($emailFooter !== '') {
                $result[] = $emailFooter;
            }
        }

        return $result;
    }

    /**
     * Checks whether we have any organizers set, but does not check the
     * validity of that entry.
     *
     * @return bool TRUE if we have any organizers related to this seminar, FALSE otherwise
     */
    public function hasOrganizers(): bool
    {
        return $this->hasRecordPropertyInteger('organizers');
    }

    /**
     * @return int<0, max>
     */
    public function getNumberOfOrganizers(): int
    {
        $number = $this->getRecordPropertyInteger('organizers');
        \assert($number >= 0);

        return $number;
    }

    /**
     * Gets our organizing partners comma-separated (as HTML code with
     * hyperlinks to their homepage, if they have any).
     *
     * Returns an empty string if this event has no organizing partners or
     * something went wrong with the database query.
     *
     * @return string the hyperlinked names of our organizing partners, or an empty string
     */
    public function getOrganizingPartners(): string
    {
        if (!$this->hasOrganizingPartners()) {
            return '';
        }
        $result = [];

        $organizerBag = GeneralUtility::makeInstance(
            OrganizerBag::class,
            'tx_seminars_seminars_organizing_partners_mm.uid_local = ' . $this->getUid() . ' AND ' .
            'tx_seminars_seminars_organizing_partners_mm.uid_foreign = tx_seminars_organizers.uid',
            'tx_seminars_seminars_organizing_partners_mm',
        );

        /** @var LegacyOrganizer $organizer */
        foreach ($organizerBag as $organizer) {
            $result[] = $this->buildHtmlForOrganizerWithPossibleHomepageLink($organizer);
        }

        return implode(', ', $result);
    }

    /**
     * Checks whether we have any organizing partners set.
     *
     * @return bool TRUE if we have any organizing partners related to this event, FALSE otherwise
     */
    public function hasOrganizingPartners(): bool
    {
        return $this->hasRecordPropertyInteger('organizing_partners');
    }

    /**
     * @return int<0, max>
     */
    public function getNumberOfOrganizingPartners(): int
    {
        $number = $this->getRecordPropertyInteger('organizing_partners');
        \assert($number >= 0);

        return $number;
    }

    /**
     * Checks whether this event has a separate details page set (which may be an internal or external URL).
     *
     * @return bool TRUE if this event has a separate details page, FALSE otherwise
     */
    public function hasSeparateDetailsPage(): bool
    {
        return $this->hasRecordPropertyString('details_page');
    }

    /**
     * Returns this event's separate details page URL (which may be
     * internal or external) or page ID.
     *
     * @return string the URL to this events separate details page, will be
     *                empty if this event has no separate details page set
     */
    public function getDetailsPage(): string
    {
        return $this->getRecordPropertyString('details_page');
    }

    /**
     * Gets a plain text list of property values (if they exist),
     * formatted as strings (and nicely lined up) in the following format:
     *
     * key1: value1
     *
     * @param string $keysList comma-separated list of key names
     *
     * @return string formatted output (may be empty)
     */
    public function dumpSeminarValues(string $keysList): string
    {
        $keys = GeneralUtility::trimExplode(',', $keysList, true);
        $keysWithLabels = [];

        $maxLength = 0;
        foreach ($keys as $currentKey) {
            $loweredKey = \strtolower($currentKey);
            $currentLabel = \rtrim($this->translate('label_' . $currentKey), ':');
            $keysWithLabels[$loweredKey] = $currentLabel;
            $maxLength = \max($maxLength, \mb_strlen($currentLabel, 'utf-8'));
        }
        $result = '';
        foreach ($keysWithLabels as $currentKey => $currentLabel) {
            switch ($currentKey) {
                case 'date':
                    $value = $this->getDate('-');
                    break;
                case 'place':
                    $value = $this->getPlaceShort();
                    break;
                case 'price_regular':
                    $value = $this->getPriceRegular();
                    break;
                case 'price_regular_early':
                    $value = $this->getEarlyBirdPriceRegular();
                    break;
                case 'price_special':
                    $value = $this->getPriceSpecial();
                    break;
                case 'price_special_early':
                    $value = $this->getEarlyBirdPriceSpecial();
                    break;
                case 'speakers':
                    $value = $this->getSpeakersShort();
                    break;
                case 'time':
                    $value = $this->getTime('-');
                    break;
                case 'titleanddate':
                    $value = $this->getTitleAndDate('-');
                    break;
                case 'event_type':
                    $value = $this->getEventType();
                    break;
                case 'vacancies':
                    if ($this->hasUnlimitedVacancies()) {
                        $value = $this->translate('label_unlimited');
                    } else {
                        $value = (string)$this->getVacancies();
                    }
                    break;
                case 'title':
                    $value = $this->getTitle();
                    break;
                case 'attendees':
                    $value = $this->getAttendances();
                    break;
                case 'enough_attendees':
                    $value = $this->hasEnoughAttendances()
                        ? $this->translate('label_yes')
                        : $this->translate('label_no');
                    break;
                case 'is_full':
                    $value = $this->isFull()
                        ? $this->translate('label_yes')
                        : $this->translate('label_no');
                    break;
                default:
                    $value = $this->getRecordPropertyString($currentKey);
            }

            // Check whether there is a value to display.
            // If not, we will not use the padding and break the line directly after the label.
            if ($value !== '') {
                $padding = \str_pad('', $maxLength - \mb_strlen($currentLabel, 'utf-8'));
                $result .= $currentLabel . ': ' . $padding . $value . "\n";
            } else {
                $result .= $currentLabel . ":\n";
            }
        }

        return $result;
    }

    /**
     * Checks whether a certain user already is registered for this seminar.
     *
     * @param int<0, max> $uid UID of the FE user to check
     *
     * @return bool whether if the user already is registered
     */
    public function isUserRegistered(int $uid): bool
    {
        $table = 'tx_seminars_attendances';
        $count = self::getConnectionForTable($table)
            ->count('*', $table, ['seminar' => $this->getUid(), 'user' => $uid]);

        return $count > 0;
    }

    /**
     * Checks whether a certain user already is registered for this seminar.
     *
     * @param positive-int $feUserUid UID of the FE user to check
     *
     * @return string empty string if everything is OK, else a localized error message
     */
    public function isUserRegisteredMessage(int $feUserUid): string
    {
        return $this->isUserRegistered($feUserUid) ? $this->translate('message_alreadyRegistered') : '';
    }

    /**
     * Checks whether a certain user is entered as a default VIP for all events
     * but also checks whether this user is entered as a VIP for this event,
     * i.e., he/she is allowed to view the list of registrations for this event.
     *
     * @param positive-int $userUid UID of the FE user to check
     *
     * @return bool whether the user is a VIP for this event
     */
    public function isUserVip(int $userUid): bool
    {
        $table = 'tx_seminars_seminars_feusers_mm';
        $count = self::getConnectionForTable($table)
            ->count('*', $table, ['uid_local' => $this->getUid(), 'uid_foreign' => $userUid]);

        return $count > 0;
    }

    /**
     * Checks whether a FE user is logged in and whether he/she may view this
     * seminar's registrations list or see a link to it.
     * This function can be used to check whether
     * a) a link may be created to the page with the list of registrations
     *    (for $whichPlugin = (seminar_list|my_events|my_vip_events))
     * b) the user is allowed to view the list of registrations
     *    (for $whichPlugin = (list_registrations|list_vip_registrations))
     *
     * @param 'seminar_list'|'my_events'|'my_vip_events'|'list_registrations'|'list_vip_registrations' $whichPlugin
     * @param int<0, max> $registrationsListPID
     *        the value of the registrationsListPID parameter
     *        (only relevant for (seminar_list|my_events|my_vip_events))
     * @param int<0, max> $registrationsVipListPID
     *        the value of the registrationsVipListPID parameter
     *        (only relevant for (seminar_list|my_events|my_vip_events))
     * @param 'attendees_and_managers'|'login' $accessLevel
     *
     * @return bool TRUE if a FE user is logged in and the user may view
     *                 the registrations list or may see a link to that
     *                 page, FALSE otherwise
     */
    public function canViewRegistrationsList(
        string $whichPlugin,
        int $registrationsListPID = 0,
        int $registrationsVipListPID = 0,
        string $accessLevel = 'attendees_and_managers'
    ): bool {
        if (!$this->needsRegistration()) {
            return false;
        }

        switch ($accessLevel) {
            case 'login':
                $result = $this->canViewRegistrationsListForLoginAccess(
                    $whichPlugin,
                    $registrationsListPID,
                    $registrationsVipListPID,
                );
                break;
            case 'attendees_and_managers':
                // The fall-through is intended.
            default:
                $result = $this->canViewRegistrationsListForAttendeesAndManagersAccess(
                    $whichPlugin,
                    $registrationsListPID,
                    $registrationsVipListPID,
                );
        }

        return $result;
    }

    /**
     * Checks whether a FE user is logged in and whether he/she may view this
     * seminar's registrations list or see a link to it.
     *
     * This function assumes that the access level for FE registration lists is
     * "attendees and managers".
     *
     * @param 'seminar_list'|'my_events'|'my_vip_events'|'list_registrations'|'list_vip_registrations' $whichPlugin
     * @param int<0, max> $registrationsListPID
     *        the value of the registrationsListPID parameter
     *        (only relevant for (seminar_list|my_events|my_vip_events))
     * @param int<0, max> $registrationsVipListPID
     *        the value of the registrationsVipListPID parameter
     *        (only relevant for (seminar_list|my_events|my_vip_events))
     *
     * @return bool TRUE if a FE user is logged in and the user may view
     *                 the registrations list or may see a link to that
     *                 page, FALSE otherwise
     */
    protected function canViewRegistrationsListForAttendeesAndManagersAccess(
        string $whichPlugin,
        int $registrationsListPID = 0,
        int $registrationsVipListPID = 0
    ): bool {
        $currentUserUid = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('frontend.user', 'id');
        \assert(\is_int($currentUserUid) && $currentUserUid >= 0);
        if ($currentUserUid <= 0) {
            return false;
        }

        $hasListPid = ($registrationsListPID > 0);
        $hasVipListPid = ($registrationsVipListPID > 0);

        switch ($whichPlugin) {
            case 'seminar_list':
                // In the standard list view, we could have any kind of link.
                $result = $this->canViewRegistrationsList('my_events', $registrationsListPID)
                    || $this->canViewRegistrationsList(
                        'my_vip_events',
                        0,
                        $registrationsVipListPID,
                    );
                break;
            case 'my_events':
                $result = $this->isUserRegistered($currentUserUid) && $hasListPid;
                break;
            case 'my_vip_events':
                $result = $this->isUserVip($currentUserUid) && $hasVipListPid;
                break;
            case 'list_registrations':
                $result = $this->isUserRegistered($currentUserUid);
                break;
            case 'list_vip_registrations':
                $result = $this->isUserVip($currentUserUid);
                break;
            default:
                $result = false;
        }

        return $result;
    }

    /**
     * Checks whether a FE user is logged in and whether he/she may view this
     * seminar's registrations list or see a link to it.
     *
     * This function assumes that the access level for FE registration lists is
     * "login".
     *
     * @param 'seminar_list'|'my_events'|'my_vip_events'|'list_registrations'|'list_vip_registrations' $whichPlugin
     * @param int<0, max> $registrationsListPID
     *        the value of the registrationsListPID parameter
     *        (only relevant for (seminar_list|my_events|my_vip_events))
     * @param int<0, max> $registrationsVipListPID
     *        the value of the registrationsVipListPID parameter
     *        (only relevant for (seminar_list|my_events|my_vip_events))
     *
     * @return bool TRUE if a FE user is logged in and the user may view
     *                 the registrations list or may see a link to that
     *                 page, FALSE otherwise
     */
    protected function canViewRegistrationsListForLoginAccess(
        string $whichPlugin,
        int $registrationsListPID = 0,
        int $registrationsVipListPID = 0
    ): bool {
        $currentUserUid = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('frontend.user', 'id');
        if ($currentUserUid <= 0) {
            return false;
        }

        $hasListPid = ($registrationsListPID > 0);
        $hasVipListPid = ($registrationsVipListPID > 0);

        switch ($whichPlugin) {
            case 'my_vip_events':
                $result = $this->isUserVip($currentUserUid) && $hasVipListPid;
                break;
            case 'list_vip_registrations':
                $result = $this->isUserVip($currentUserUid);
                break;
            case 'list_registrations':
                $result = true;
                break;
            default:
                $result = $hasListPid;
        }

        return $result;
    }

    /**
     * Checks whether a FE user is logged in and whether he/she may view this
     * seminar's registrations list.
     * This function is intended to be used from the registrations list,
     * NOT to check whether a link to that list should be shown.
     *
     * @param 'seminar_list'|'my_events'|'my_vip_events'|'list_registrations'|'list_vip_registrations' $whichPlugin
     * @param 'attendees_and_managers'|'login' $accessLevel
     *
     * @return string an empty string if everything is OK, a localized error
     *                message otherwise
     */
    public function canViewRegistrationsListMessage(
        string $whichPlugin,
        string $accessLevel = 'attendees_and_managers'
    ): string {
        if (!$this->needsRegistration()) {
            return $this->translate('message_noRegistrationNecessary');
        }
        if (!GeneralUtility::makeInstance(Context::class)->getAspect('frontend.user')->isLoggedIn()) {
            return $this->translate('message_notLoggedIn');
        }
        if (
            !$this->canViewRegistrationsList(
                $whichPlugin,
                0,
                0,
                $accessLevel,
            )
        ) {
            return $this->translate('message_accessDenied');
        }

        return '';
    }

    /**
     * Checks whether it is possible at all to register for this seminar,
     * ie., it needs registration at all,
     *     has not been canceled,
     *     has not begun yet,
     *     the registration deadline is not over yet,
     *     and there are still vacancies.
     *
     * @return bool TRUE if registration is possible, FALSE otherwise
     *
     * @deprecated will be removed in version 6.0.0 in #3397
     */
    public function canSomebodyRegister(): bool
    {
        $registrationManager = GeneralUtility::makeInstance(RegistrationManager::class);
        $allowsRegistrationByDate = $registrationManager->allowsRegistrationByDate($this);
        $allowsRegistrationBySeats = $registrationManager->allowsRegistrationBySeats($this);

        return $this->needsRegistration() && !$this->isCanceled()
            && $allowsRegistrationByDate
            && $allowsRegistrationBySeats;
    }

    /**
     * Checks whether it is possible at all to register for this seminar,
     * i.e., it needs registration at all,
     *     has not been canceled,
     *     has not begun yet,
     *     the registration deadline is not over yet
     *     and there are still vacancies,
     * and returns a localized error message if registration is not possible.
     *
     * @return string empty string if everything is OK, else a localized error message
     *
     * @deprecated will be removed in version 6.0.0 in #3397
     */
    public function canSomebodyRegisterMessage(): string
    {
        $message = '';
        $registrationManager = GeneralUtility::makeInstance(RegistrationManager::class);

        if (!$this->needsRegistration()) {
            $message = $this->translate('message_noRegistrationNecessary');
        } elseif ($this->isCanceled()) {
            $message = $this->translate('message_seminarCancelled');
        } elseif ($this->hasDate() && $this->isRegistrationDeadlineOver()) {
            $message = $this->translate('message_seminarRegistrationIsClosed');
        } elseif (!$registrationManager->allowsRegistrationBySeats($this)) {
            $message = $this->translate('message_noVacancies');
        } elseif (!$registrationManager->registrationHasStarted($this)) {
            $message = \sprintf($this->translate('message_registrationOpensOn'), $this->getRegistrationBegin());
        }

        return $message;
    }

    public function isCanceled(): bool
    {
        return $this->getStatus() === EventInterface::STATUS_CANCELED;
    }

    /**
     * Checks whether the latest possibility to register for this event is over.
     *
     * The latest moment is either the time the event starts, or a set registration deadline.
     *
     * @deprecated will be removed in version 6.0.0 in #3397
     */
    public function isRegistrationDeadlineOver(): bool
    {
        $registrationDeadline = $this->getLatestPossibleRegistrationTime();
        if ($registrationDeadline === 0) {
            return false;
        }

        $now = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');

        return $now >= $registrationDeadline;
    }

    /**
     * Checks whether the latest possibility to register with early bird rebate for this event is over.
     *
     * The latest moment is just before a set early bird deadline.
     */
    public function isEarlyBirdDeadlineOver(): bool
    {
        $now = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');

        return $now >= $this->getLatestPossibleEarlyBirdRegistrationTime();
    }

    /**
     * Checks whether registration is necessary for this event.
     */
    public function needsRegistration(): bool
    {
        return !$this->isEventTopic() && $this->getRecordPropertyBoolean('needs_registration');
    }

    /**
     * Checks whether this event has unlimited vacancies (needs_registration true and max_attendances 0).
     */
    public function hasUnlimitedVacancies(): bool
    {
        return $this->needsRegistration() && $this->getAttendancesMax() === 0;
    }

    /**
     * Checks whether this event allows multiple registrations by the same
     * FE user.
     */
    public function allowsMultipleRegistrations(): bool
    {
        return $this->getTopicBoolean('allows_multiple_registrations');
    }

    /**
     * Calculates the attendee statistics. If these numbers already are available, this method is a no-op.
     */
    protected function calculateStatisticsIfNeeded(): void
    {
        if (!$this->statisticsHaveBeenCalculated) {
            $this->calculateStatistics();
        }
    }

    /**
     * (Re-)calculates the number of participants for this seminar.
     */
    public function calculateStatistics(): void
    {
        $this->registrationsHaveBeenRetrieved = false;

        $this->numberOfAttendances = $this->getOfflineRegistrations()
            + $this->sumSeatsOfRegistrations($this->getRegularRegistrations());

        $this->statisticsHaveBeenCalculated = true;
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    private function getRegularRegistrations(): array
    {
        $this->retrieveRegistrations();

        return \array_filter(
            $this->registrations,
            static fn (array $registration): bool
                => (int)$registration['registration_queue']
                === ExtbaseRegistration::STATUS_REGULAR,
        );
    }

    /**
     * @param array<array<string, string|int>> $registrations
     *
     * @return int<0, max>
     */
    private function sumSeatsOfRegistrations(array $registrations): int
    {
        $total = 0;
        foreach ($registrations as $registration) {
            $total += \max(1, (int)$registration['seats']);
        }

        return $total;
    }

    private function retrieveRegistrations(): void
    {
        if ($this->registrationsHaveBeenRetrieved) {
            return;
        }

        $table = 'tx_seminars_attendances';
        $this->registrations = self::getConnectionForTable($table)
            ->select(['*'], $table, ['seminar' => $this->getUid()])
            ->fetchAllAssociative();

        $this->registrationsHaveBeenRetrieved = true;
    }

    /**
     * Retrieves the topic from the DB and returns it as an object.
     *
     * In case of an error, the return value will be null.
     */
    private function loadTopic(): ?LegacyEvent
    {
        return self::fromUid($this->getTopicUid());
    }

    /**
     * @return int<0, max>
     */
    private function getTopicUid(): int
    {
        $topicUid = $this->getRecordPropertyInteger('topic');
        \assert($topicUid >= 0);

        return $topicUid;
    }

    /**
     * Checks whether we are a date record.
     */
    public function isEventDate(): bool
    {
        return $this->getRecordPropertyInteger('object_type') === EventInterface::TYPE_EVENT_DATE;
    }

    /**
     * Checks whether we are a topic record.
     */
    public function isEventTopic(): bool
    {
        return $this->getRecordPropertyInteger('object_type') === EventInterface::TYPE_EVENT_TOPIC;
    }

    /**
     * Gets the UID of the topic record if we are a date record. Otherwise, the
     * UID of this record is returned.
     *
     * @return int either the UID of this record or its topic record,
     *                 depending on whether we are a date record
     */
    public function getTopicOrSelfUid(): int
    {
        $topic = $this->getTopic();

        return $topic !== null ? $topic->getUid() : $this->getUid();
    }

    /**
     * Checks a integer element of the record data array for existence and
     * non-emptiness. If we are a date record, it'll be retrieved from the
     * corresponding topic record.
     *
     * @param non-empty-string $key
     *
     * @return bool TRUE if the corresponding integer exists and is non-empty
     */
    protected function hasTopicInteger(string $key): bool
    {
        $topic = $this->getTopic();

        return $topic !== null ? $topic->hasRecordPropertyInteger($key) : $this->hasRecordPropertyInteger($key);
    }

    /**
     * Gets an int element of the record data array.
     * If the array has not been initialized properly, 0 is returned instead.
     * If we are a date record, it'll be retrieved from the corresponding
     * topic record.
     *
     * @param non-empty-string $key
     *
     * @return int the corresponding element from the record data array
     */
    protected function getTopicInteger(string $key): int
    {
        $topic = $this->getTopic();

        return $topic !== null ? $topic->getRecordPropertyInteger($key) : $this->getRecordPropertyInteger($key);
    }

    /**
     * Checks a string element of the record data array for existence and
     * non-emptiness. If we are a date record, it'll be retrieved from the
     * corresponding topic record.
     *
     * @param non-empty-string $key
     *
     * @return bool TRUE if the corresponding string exists and is non-empty
     */
    private function hasTopicString(string $key): bool
    {
        $topic = $this->getTopic();

        return $topic !== null ? $topic->hasRecordPropertyString($key) : $this->hasRecordPropertyString($key);
    }

    /**
     * Gets a trimmed string element of the record data array.
     * If the array has not been initialized properly, an empty string is
     * returned instead. If we are a date record, it'll be retrieved from the
     * corresponding topic record.
     *
     * @param non-empty-string $key
     *
     * @return string the corresponding element from the record data array
     */
    protected function getTopicString(string $key): string
    {
        $topic = $this->getTopic();

        return $topic !== null ? $topic->getRecordPropertyString($key) : $this->getRecordPropertyString($key);
    }

    /**
     * Checks a decimal element of the record data array for existence and a
     * value != 0.00. If we are a date record, it'll be retrieved from the
     * corresponding topic record.
     *
     * @param non-empty-string $key
     *
     * @return bool TRUE if the corresponding decimal value exists and is not 0.00
     */
    private function hasTopicDecimal(string $key): bool
    {
        $topic = $this->getTopic();

        return $topic !== null ? $topic->hasRecordPropertyDecimal($key) : $this->hasRecordPropertyDecimal($key);
    }

    /**
     * Gets a decimal element of the record data array.
     * If the array has not been initialized properly, an empty string is
     * returned instead. If we are a date record, it'll be retrieved from the
     * corresponding topic record.
     *
     * @param non-empty-string $key
     *
     * @return string the corresponding element from the record data array
     */
    protected function getTopicDecimal(string $key): string
    {
        $topic = $this->getTopic();

        return $topic !== null ? $topic->getRecordPropertyDecimal($key) : $this->getRecordPropertyDecimal($key);
    }

    /**
     * Gets an element of the record data array, converted to a boolean.
     * If the array has not been initialized properly, FALSE is returned.
     *
     * If we are a date record, it'll be retrieved from the corresponding topic
     * record.
     *
     * @param non-empty-string $key
     *
     * @return bool the corresponding element from the record data array
     */
    protected function getTopicBoolean(string $key): bool
    {
        $topic = $this->getTopic();

        return $topic !== null ? $topic->getRecordPropertyBoolean($key) : $this->getRecordPropertyBoolean($key);
    }

    public function hasLodgings(): bool
    {
        return $this->hasRecordPropertyInteger('lodgings');
    }

    /**
     * Gets the lodging options associated with this event.
     */
    protected function getLodgingTitles(): string
    {
        return $this->hasLodgings()
            ? \implode("\n", $this->getMmRecordTitles('tx_seminars_lodgings', 'tx_seminars_seminars_lodgings_mm'))
            : '';
    }

    /**
     * Checks whether we have any food options.
     */
    public function hasFoods(): bool
    {
        return $this->hasRecordPropertyInteger('foods');
    }

    protected function getFoodTitles(): string
    {
        return $this->hasFoods()
            ? \implode("\n", $this->getMmRecordTitles('tx_seminars_foods', 'tx_seminars_seminars_foods_mm'))
            : '';
    }

    /**
     * Checks whether this event has any option checkboxes.
     *
     * @return bool whether this event has at least one option checkbox
     */
    public function hasCheckboxes(): bool
    {
        return $this->hasRecordPropertyInteger('checkboxes');
    }

    /**
     * Gets the option checkboxes associated with this event. If we are a date
     * record, the option checkboxes of the corresponding topic record will be retrieved.
     *
     * @return array[] option checkboxes, consisting each of a nested array
     *                 with the keys "caption" (for the title) and "value" (for the UID), might be empty
     */
    public function getCheckboxes(): array
    {
        return $this->hasCheckboxes()
            ? $this->getMmRecordsForSelection('tx_seminars_checkboxes', 'tx_seminars_seminars_checkboxes_mm') : [];
    }

    /**
     * @param non-empty-string $foreignTable
     * @param non-empty-string $mmTable
     *
     * @return list<array{caption: string, value: int<0, max>}>
     */
    protected function getMmRecordsForSelection(string $foreignTable, string $mmTable): array
    {
        return $this->mmRecordsToSelection($this->getMmRecords($foreignTable, $mmTable));
    }

    /**
     * @param array<array<string, string|int|bool|null>> $records
     *
     * @return list<array{caption: string, value: int<0, max>}>
     */
    private function mmRecordsToSelection(array $records): array
    {
        $options = [];
        foreach ($records as $record) {
            $uid = (int)$record['uid'];
            \assert($uid >= 0);
            $options[] = ['caption' => (string)$record['title'], 'value' => $uid];
        }

        return $options;
    }

    /**
     * Gets this event's owner (the FE user who has created this event).
     */
    public function getOwner(): ?FrontEndUser
    {
        if (!$this->hasRecordPropertyInteger('owner_feuser')) {
            return null;
        }

        $ownerUid = $this->getRecordPropertyInteger('owner_feuser');
        \assert($ownerUid > 0);

        return MapperRegistry::get(FrontEndUserMapper::class)->find($ownerUid);
    }

    /**
     * Checks whether this event has an existing owner (the FE user who has
     * created this event).
     *
     * @return bool TRUE if this event has an existing owner, FALSE otherwise
     */
    public function hasOwner(): bool
    {
        return $this->hasRecordPropertyInteger('owner_feuser');
    }

    /**
     * Checks whether the logged-in FE user is the owner of this event.
     *
     * @return bool TRUE if a FE user is logged in and the user is the owner of this event, FALSE otherwise
     */
    public function isOwnerFeUser(): bool
    {
        $loggedInUserUid = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('frontend.user', 'id');

        return $loggedInUserUid > 0 && ($this->getRecordPropertyInteger('owner_feuser') === $loggedInUserUid);
    }

    /**
     * Checkes whether the "additional terms" checkbox should be displayed in the registration form for this event.
     *
     * If we are a date record, this is checked for the corresponding topic record.
     *
     * @return bool whether if the "additional terms" checkbox should be displayed
     */
    public function hasTerms2(): bool
    {
        return $this->getTopicBoolean('uses_terms_2');
    }

    /**
     * Gets the teaser text (not RTE'ed). If this is a date record, the
     * corresponding topic's teaser text is retrieved.
     *
     * @return string this event's teaser text (or '' if there is an error)
     */
    public function getTeaser(): string
    {
        return $this->getTopicString('teaser');
    }

    /**
     * Retrieves a value from this record. The return value will be an empty
     * string if the key is not defined in $this->recordData or if it has not
     * been filled in.
     *
     * If the data needs to be decoded to be readable (e.g., the speaker's payment),
     * this function will already return the clear text
     * version.
     *
     * @param string $key the key of the data to retrieve (the key doesn't need to be trimmed)
     *
     * @return string the data retrieved from $this->recordData, may be empty
     */
    public function getEventData(string $key): string
    {
        /** @var non-empty-string $trimmedKey */
        $trimmedKey = trim($key);

        switch ($trimmedKey) {
            case 'uid':
                $result = (string)$this->getUid();
                break;
            case 'tstamp':
                // The fallthrough is intended.
            case 'crdate':
                $result = \date(
                    $this->getDateFormat() . ' ' . $this->getTimeFormat(),
                    $this->getRecordPropertyInteger($trimmedKey),
                );
                break;
            case 'title':
                $result = $this->getTitle();
                break;
            case 'subtitle':
                $result = $this->getSubtitle();
                break;
            case 'teaser':
                $result = $this->getTeaser();
                break;
            case 'description':
                $result = $this->getDescription();
                break;
            case 'event_type':
                $result = $this->getEventType();
                break;
            case 'accreditation_number':
                $result = $this->getAccreditationNumber();
                break;
            case 'credit_points':
                $result = $this->getCreditPoints();
                break;
            case 'date':
                $result = $this->getDate('-');
                break;
            case 'time':
                $result = $this->getTime('-');
                break;
            case 'deadline_registration':
                $result = $this->getRegistrationDeadline();
                break;
            case 'deadline_early_bird':
                $result = $this->getEarlyBirdDeadline();
                break;
            case 'deadline_unregistration':
                $result = $this->getUnregistrationDeadline();
                break;
            case 'place':
                $result = $this->getPlaceWithDetailsRaw();
                break;
            case 'room':
                $result = $this->getRoom();
                break;
            case 'lodgings':
                $result = $this->getLodgingTitles();
                break;
            case 'foods':
                $result = $this->getFoodTitles();
                break;
            case 'speakers':
                // The fallthrough is intended.
            case 'partners':
                // The fallthrough is intended.
            case 'tutors':
                // The fallthrough is intended.
            case 'leaders':
                $result = $this->getSpeakersWithDescriptionRaw($trimmedKey);
                break;
            case 'price_regular':
                $result = $this->getPriceRegular();
                break;
            case 'price_regular_early':
                $result = $this->getEarlyBirdPriceRegular();
                break;
            case 'price_special':
                $result = $this->getPriceSpecial();
                break;
            case 'price_special_early':
                $result = $this->getEarlyBirdPriceSpecial();
                break;
            case 'additional_information':
                $result = $this->getAdditionalInformation();
                break;
            case 'payment_methods':
                $result = $this->getPaymentMethodsPlainShort();
                break;
            case 'organizers':
                $result = $this->getOrganizersRaw();
                break;
            case 'attendees_min':
                $result = (string)$this->getAttendancesMin();
                break;
            case 'attendees_max':
                $result = (string)$this->getAttendancesMax();
                break;
            case 'attendees':
                $result = (string)$this->getAttendances();
                break;
            case 'vacancies':
                $result = (string)$this->getVacancies();
                break;
            case 'enough_attendees':
                $result = $this->hasEnoughAttendances() ? $this->translate('label_yes') : $this->translate('label_no');
                break;
            case 'is_full':
                $result = $this->isFull() ? $this->translate('label_yes') : $this->translate('label_no');
                break;
            case 'cancelled':
                $result = $this->isCanceled() ? $this->translate('label_yes') : $this->translate('label_no');
                break;
            default:
                $result = '';
        }

        $carriageReturnRemoved = !str_contains($result, "\r")
            ? $result
            : str_replace("\r", "\n", $result);

        return preg_replace('/\\x0a{2,}/', "\n", $carriageReturnRemoved);
    }

    /**
     * Gets the date.
     * Returns an empty string if the seminar record is a topic record.
     * Otherwise, it will return the date or a localized string "will be announced" if there's no date set.
     *
     * Returns just one day if we take place on only one day.
     * Returns a date range if we take several days.
     *
     * @param string $dash the character or HTML entity used to separate start date and end date
     *
     * @return string the seminar date (or an empty string or a localized message)
     */
    public function getDate(string $dash = '&#8211;'): string
    {
        $result = '';

        if ($this->getRecordPropertyInteger('object_type') !== EventInterface::TYPE_EVENT_TOPIC) {
            $result = parent::getDate($dash);
        }

        return $result;
    }

    /**
     * Returns TRUE if the seminar is hidden, otherwise FALSE.
     *
     * @return bool TRUE if the seminar is hidden, FALSE otherwise
     */
    public function isHidden(): bool
    {
        return $this->getRecordPropertyBoolean('hidden');
    }

    /**
     * Returns whether unregistration is possible. That means the unregistration deadline hasn't passed yet.
     */
    public function isUnregistrationPossible(): bool
    {
        if (!$this->needsRegistration()) {
            return false;
        }

        $deadline = $this->getEffectiveUnregistrationDeadline();
        if (\is_int($deadline)) {
            $now = (int)GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
            $canUnregisterByDate = $now < $deadline;
        } else {
            $canUnregisterByDate = true;
        }

        return $canUnregisterByDate;
    }

    /**
     * Checks if this event has a registration queue.
     *
     * @return bool true if this event has a registration queue, false otherwise
     */
    public function hasRegistrationQueue(): bool
    {
        return $this->getRecordPropertyBoolean('queue_size');
    }

    public function hasTimeslots(): bool
    {
        return $this->hasRecordPropertyInteger('timeslots');
    }

    /**
     * @return list<array{
     *     uid: int,
     *     date: string,
     *     time: string,
     *     room: string,
     *     place: string
     * }>
     */
    public function getTimeSlotsAsArrayWithMarkers(): array
    {
        $result = [];

        /** @var LegacyTimeSlot $timeSlot */
        foreach ($this->getTimeSlots() as $timeSlot) {
            $result[] = [
                'uid' => $timeSlot->getUid(),
                'date' => $timeSlot->getDate(),
                'time' => $timeSlot->getTime(),
                'room' => $timeSlot->getRoom(),
                'place' => $timeSlot->getPlaceShort(),
            ];
        }

        return $result;
    }

    public function getTimeSlots(): TimeSlotBag
    {
        return GeneralUtility::makeInstance(
            TimeSlotBag::class,
            'tx_seminars_timeslots.seminar = ' . $this->getUid(),
            '',
            '',
            'tx_seminars_timeslots.begin_date ASC',
        );
    }

    /**
     * Checks whether this seminar has at least one category.
     *
     * @return bool TRUE if the seminar has at least one category,
     *                 FALSE otherwise
     */
    public function hasCategories(): bool
    {
        return $this->hasTopicInteger('categories');
    }

    /**
     * @return int<0, max>
     */
    public function getNumberOfCategories(): int
    {
        $number = $this->getTopicInteger('categories');
        \assert($number >= 0);

        return $number;
    }

    /**
     * Gets this event's category titles and icons as an associative
     * array (which may be empty), using the category UIDs as keys.
     *
     * @return array<int, array{title: string}>
     */
    public function getCategories(): array
    {
        if (!$this->hasCategories()) {
            return [];
        }

        $builder = GeneralUtility::makeInstance(CategoryBagBuilder::class);
        $builder->limitToEvents((string)$this->getTopicOrSelfUid());
        $builder->sortByRelationOrder();

        $result = [];
        foreach ($builder->build() as $category) {
            $result[$category->getUid()] = ['title' => $category->getTitle()];
        }

        return $result;
    }

    /**
     * Returns whether this event has at least one attached file.
     *
     * If this is an event date, this function will return true if the date
     * record or the topic record has at least one file.
     */
    public function hasAttachedFiles(): bool
    {
        return $this->hasRecordPropertyInteger('attached_files') || $this->hasTopicInteger('attached_files');
    }

    /**
     * Returns the attached files of this event.
     *
     * If this event is an event date, this function will return the date's files as well as the topic's files
     * (in that order).
     *
     * @return array<int, FileReference>
     */
    public function getAttachedFiles(): array
    {
        if (!$this->hasAttachedFiles()) {
            return [];
        }

        $attachments = $this
            ->getFileRepository()
            ->findByRelation('tx_seminars_seminars', 'attached_files', $this->getUid());
        if ($this->isEventDate()) {
            $attachmentsFromTopic = $this
                ->getFileRepository()
                ->findByRelation('tx_seminars_seminars', 'attached_files', $this->getTopicUid());
            $attachments = \array_merge($attachments, $attachmentsFromTopic);
        }

        return $attachments;
    }

    public function hasImage(): bool
    {
        return $this->hasTopicInteger('image');
    }

    public function getImage(): ?FileReference
    {
        if (!$this->hasImage()) {
            return null;
        }

        $uid = $this->isEventDate() ? $this->getTopicUid() : $this->getUid();
        $images = $this->getFileRepository()->findByRelation('tx_seminars_seminars', 'image', $uid);

        return \array_shift($images);
    }

    /**
     * Checks whether this event has any requiring events, ie. topics that are
     * prerequisite for this event
     *
     * @return bool TRUE if this event has any requiring events, FALSE
     *                 otherwise
     */
    public function hasRequirements(): bool
    {
        return $this->hasTopicInteger('requirements');
    }

    /**
     * Checks whether this event has any depending events, ie. topics for which
     * this event is prerequisite.
     *
     * @return bool TRUE if this event has any depending events, FALSE
     *                 otherwise
     */
    public function hasDependencies(): bool
    {
        return $this->hasTopicInteger('dependencies');
    }

    /**
     * Returns the required events for the current event topic, i.e., topics that
     * are prerequisites for this event.
     *
     * @return EventBag the required events, will be empty if this event has no required events
     */
    public function getRequirements(): EventBag
    {
        $builder = GeneralUtility::makeInstance(EventBagBuilder::class);
        $builder->limitToRequiredEventTopics($this->getTopicOrSelfUid());

        return $builder->build();
    }

    /**
     * Returns the depending events for the current event topic, ie. topics for
     * which this event is a prerequisite.
     *
     * @return EventBag the depending events, will be empty if
     *                               this event has no depending events
     */
    public function getDependencies(): EventBag
    {
        $builder = GeneralUtility::makeInstance(EventBagBuilder::class);
        $builder->limitToDependingEventTopics($this->getTopicOrSelfUid());

        return $builder->build();
    }

    /**
     * Checks whether this event has been confirmed.
     *
     * @return bool TRUE if the event has been confirmed, FALSE otherwise
     */
    public function isConfirmed(): bool
    {
        return $this->getStatus() === EventInterface::STATUS_CONFIRMED;
    }

    /**
     * Checks whether this event has been planned.
     *
     * @return bool TRUE if the event has been planned, FALSE otherwise
     */
    public function isPlanned(): bool
    {
        return $this->getStatus() === EventInterface::STATUS_PLANNED;
    }

    /**
     * Gets the staus of this event.
     *
     * @return int<0, max> the status of this event
     */
    private function getStatus(): int
    {
        $status = $this->getRecordPropertyInteger('cancelled');
        \assert($status >= 0);

        return $status;
    }

    /**
     * Sets whether this event is planned, canceled or confirmed.
     *
     * @param EventInterface::STATUS_* $status
     */
    public function setStatus(int $status): void
    {
        $this->setRecordPropertyInteger('cancelled', $status);
    }

    /**
     * Returns the cancelation deadline of this event, depending on the
     * cancelation deadlines of the speakers.
     *
     * Before this function is called assure that this event has a begin date.
     *
     * @return int<0, max> the cancelation deadline of this event as timestamp
     *
     * @throws \BadMethodCallException
     */
    public function getCancelationDeadline(): int
    {
        if (!$this->hasBeginDate()) {
            throw new \BadMethodCallException(
                'The event has no begin date. Please call this function only if the event has a begin date.',
                1333291877,
            );
        }
        if (!$this->hasSpeakers()) {
            return $this->getBeginDateAsTimestamp();
        }

        $beginDate = $this->getBeginDateAsTimestamp();
        $deadline = $beginDate;
        /** @var LegacySpeaker $speaker */
        foreach ($this->getSpeakerBag() as $speaker) {
            $speakerDeadline = $beginDate - ($speaker->getCancelationPeriodInDays() * Time::SECONDS_PER_DAY);
            $deadline = \max(0, \min($speakerDeadline, $deadline));
        }

        return $deadline;
    }

    /**
     * Sets the "cancelation_deadline_reminder_sent" flag.
     */
    public function setCancelationDeadlineReminderSentFlag(): void
    {
        $this->setRecordPropertyBoolean(
            'cancelation_deadline_reminder_sent',
            true,
        );
    }

    /**
     * Sets the "event_takes_place_reminder_sent" flag.
     */
    public function setEventTakesPlaceReminderSentFlag(): void
    {
        $this->setRecordPropertyBoolean(
            'event_takes_place_reminder_sent',
            true,
        );
    }

    /**
     * Checks whether this event has a license expiry.
     *
     * @return bool TRUE if this event has a license expiry, FALSE otherwise
     */
    public function hasExpiry(): bool
    {
        return $this->hasRecordPropertyInteger('expiry');
    }

    /**
     * Gets this event's license expiry date as a formatted date.
     *
     * @return string this event's license expiry date as a formatted date,
     *                will be empty if this event has no license expiry
     */
    public function getExpiry(): string
    {
        if (!$this->hasExpiry()) {
            return '';
        }

        return \date($this->getDateFormat(), $this->getRecordPropertyInteger('expiry'));
    }

    /**
     * Checks whether this event has a begin date for the registration.
     *
     * @return bool TRUE if this event has a begin date for the registration,
     *                 FALSE otherwise
     */
    public function hasRegistrationBegin(): bool
    {
        return $this->hasRecordPropertyInteger('begin_date_registration');
    }

    /**
     * Returns the begin date for the registration of this event as UNIX
     * time-stamp.
     *
     * @return int the begin date for the registration of this event as UNIX
     *                 time-stamp, will be 0 if no begin date for the
     *                 registration is set
     */
    public function getRegistrationBeginAsUnixTimestamp(): int
    {
        return $this->getRecordPropertyInteger('begin_date_registration');
    }

    /**
     * Returns the begin date for the registration of this event.
     * The returned string is formatted using the format configured in
     * dateFormatYMD and timeFormat.
     *
     * This function will return an empty string if this event does not have a
     * registration begin date.
     *
     * @return string the date and time of the registration begin date, will be
     *                an empty string if this event registration begin date
     */
    public function getRegistrationBegin(): string
    {
        if (!$this->hasRegistrationBegin()) {
            return '';
        }

        return \date(
            $this->getDateFormat() . ' ' . $this->getTimeFormat(),
            $this->getRecordPropertyInteger('begin_date_registration'),
        );
    }

    /**
     * Returns the places associated with this event.
     *
     * @return Collection<Place>
     */
    public function getPlaces(): Collection
    {
        if (!$this->hasPlace()) {
            /** @var Collection<Place> $emptyPlaces */
            $emptyPlaces = new Collection();
            return $emptyPlaces;
        }

        return GeneralUtility::makeInstance(PlaceMapper::class)->getListOfModels($this->getPlacesAsArray());
    }

    /**
     * Checks whether this event has any offline registrations.
     *
     * @return bool TRUE if this event has at least one offline registration,
     *                 FALSE otherwise
     */
    public function hasOfflineRegistrations(): bool
    {
        return $this->hasRecordPropertyInteger('offline_attendees');
    }

    /**
     * Returns the number of offline registrations for this event.
     *
     * @return int<0, max> the number of offline registrations for this event, will
     *                 be 0 if this event has no offline registrations
     */
    public function getOfflineRegistrations(): int
    {
        $number = $this->getRecordPropertyInteger('offline_attendees');
        \assert($number >= 0);

        return $number;
    }

    /**
     * Checks whether the organizers have already been informed that the event has enough registrations.
     */
    public function haveOrganizersBeenNotifiedAboutEnoughAttendees(): bool
    {
        return $this->getRecordPropertyBoolean('organizers_notified_about_minimum_reached');
    }

    /**
     * Sets that the organizers have already been informed that the event has enough registrations.
     */
    public function setOrganizersBeenNotifiedAboutEnoughAttendees(): void
    {
        $this->setRecordPropertyBoolean('organizers_notified_about_minimum_reached', true);
    }

    /**
     * Checks whether notification email to the organizers are muted.
     */
    public function shouldMuteNotificationEmails(): bool
    {
        return $this->getRecordPropertyBoolean('mute_notification_emails');
    }

    /**
     * Makes sure that notification email to the organizers are muted.
     */
    public function muteNotificationEmails(): void
    {
        $this->setRecordPropertyBoolean('mute_notification_emails', true);
    }

    /**
     * Checks whether automatic confirmation/cancelation for this event is enabled.
     */
    public function shouldAutomaticallyConfirmOrCancel(): bool
    {
        return $this->getRecordPropertyBoolean('automatic_confirmation_cancelation');
    }

    /**
     * Returns the effective unregistration deadline for this event as UNIX timestamp.
     *
     * @return int<1, max>|null the unregistration deadline for this event as UNIX timestamp,
     *         will be `null` if this event has no unregistration deadline
     *
     * @internal
     */
    public function getEffectiveUnregistrationDeadline(): ?int
    {
        if ($this->hasUnregistrationDeadline()) {
            $deadline = $this->getUnregistrationDeadlineAsTimestamp();
            \assert($deadline > 0);
        } elseif ($this->hasBeginDate()) {
            $deadline = $this->getBeginDateAsTimestamp();
            \assert($deadline > 0);
        } else {
            $deadline = null;
        }

        return $deadline;
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    private function getPaymentMethodsAsArray(): array
    {
        return $this->getAssociationAsArray(
            'tx_seminars_payment_methods',
            'tx_seminars_seminars_payment_methods_mm',
            ['title', 'description'],
        );
    }

    /**
     * @param string[] $foreignFields
     *
     * @return array<int, array<string, string|int>>
     */
    private function getAssociationAsArray(string $foreignTableName, string $mmTableName, array $foreignFields): array
    {
        $queryBuilder = self::getQueryBuilderForTable($foreignTableName);

        foreach ($foreignFields as $foreignField) {
            $queryBuilder->addSelect($foreignField);
        }

        $queryResult = $queryBuilder
            ->from($foreignTableName)
            ->join(
                $foreignTableName,
                $mmTableName,
                'mm',
                $queryBuilder->expr()->eq(
                    $foreignTableName . '.uid',
                    $queryBuilder->quoteIdentifier('mm.uid_foreign'),
                ),
            )
            ->where(
                $queryBuilder->expr()->eq(
                    'mm.uid_local',
                    $queryBuilder->createNamedParameter($this->getTopicOrSelfUid(), Connection::PARAM_INT),
                ),
            )
            ->orderBy('mm.sorting')
            ->orderBy('mm.sorting')
            ->executeQuery();

        return $queryResult->fetchAllAssociative();
    }

    public function hasTime(): bool
    {
        // Events with time slots should only display their time with the time slots,
        // but not as general times in order to avoid confusion.
        return !$this->hasTimeslots() && parent::hasTime();
    }
}
