<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\FrontEnd;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\FallbackConfiguration;
use OliverKlee\Oelib\Configuration\FlexformsConfiguration;
use OliverKlee\Oelib\Interfaces\Configuration;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Seminars\Bag\AbstractBag;
use OliverKlee\Seminars\Bag\EventBag;
use OliverKlee\Seminars\Bag\RegistrationBag;
use OliverKlee\Seminars\BagBuilder\EventBagBuilder;
use OliverKlee\Seminars\BagBuilder\RegistrationBagBuilder;
use OliverKlee\Seminars\Configuration\CategoryListConfigurationCheck;
use OliverKlee\Seminars\Configuration\ListViewConfigurationCheck;
use OliverKlee\Seminars\Configuration\MyVipEventsConfigurationCheck;
use OliverKlee\Seminars\Configuration\RegistrationListConfigurationCheck;
use OliverKlee\Seminars\Configuration\SharedConfigurationCheck;
use OliverKlee\Seminars\Configuration\SingleViewConfigurationCheck;
use OliverKlee\Seminars\Configuration\Traits\SharedPluginConfiguration;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Hooks\HookProvider;
use OliverKlee\Seminars\Hooks\Interfaces\SeminarListView;
use OliverKlee\Seminars\Hooks\Interfaces\SeminarSingleView;
use OliverKlee\Seminars\Mapper\EventMapper;
use OliverKlee\Seminars\Mapper\FrontEndUserMapper;
use OliverKlee\Seminars\Middleware\ResponseHeadersModifier;
use OliverKlee\Seminars\Model\Event;
use OliverKlee\Seminars\OldModel\LegacyEvent;
use OliverKlee\Seminars\OldModel\LegacyOrganizer;
use OliverKlee\Seminars\OldModel\LegacyRegistration;
use OliverKlee\Seminars\Seo\SingleViewPageTitleProvider;
use OliverKlee\Seminars\Service\RegistrationManager;
use OliverKlee\Seminars\Service\SingleViewLinkBuilder;
use OliverKlee\Seminars\Templating\TemplateHelper;
use OliverKlee\Seminars\ViewHelpers\RichTextViewHelper;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Plugin "Seminar Manager".
 */
class DefaultController extends TemplateHelper
{
    use SharedPluginConfiguration;

    private const VALID_SPEAKER_TYPES = ['speakers', 'partners', 'tutors', 'leaders'];

    protected ?EventMapper $eventMapper = null;

    /**
     * @var LegacyEvent|null the seminar which we want to list/show or for which the user wants to register
     */
    private ?LegacyEvent $seminar = null;

    /**
     * @var LegacyRegistration|null the registration which we want to list/show in the "my events" view
     */
    private ?LegacyRegistration $registration = null;

    /**
     * @var string the previous event's category (used for the list view)
     */
    private string $previousCategory = '';

    /**
     * @var array<non-empty-string, non-empty-string> field names (as keys) by which we can sort plus the corresponding
     *                                                SQL sort criteria (as value).
     *
     * We cannot use the database table name constants here because default
     * values for member variable don't allow for compound expression.
     */
    public array $orderByList = [
        // The MIN gives us the first category if there are more than one.
        // The clause before the OR gets the events made up of topics (type=1)
        // and concrete dates (type=2).
        // After the OR we get the straight events.
        'category' => '(SELECT MIN(tx_seminars_categories.title)
            FROM tx_seminars_seminars_categories_mm, tx_seminars_categories,
                    tx_seminars_seminars s1, tx_seminars_seminars s2
            WHERE ( ( s1.uid=s2.topic
                        AND s1.object_type <> 2
                        AND s2.object_type=2
                        AND s2.uid=tx_seminars_seminars.uid
                ) OR ( s1.uid=s2.uid
                        AND s2.object_type=0
                        AND s1.uid=tx_seminars_seminars.uid
                        )
                )
                AND tx_seminars_seminars_categories_mm.uid_foreign=tx_seminars_categories.uid
                AND tx_seminars_seminars_categories_mm.uid_local=s1.uid)',
        // Sort by title.
        // Complete event records get the title directly.
        // Date records get it from their topic record.
        'title' => '(SELECT s1.title
            FROM tx_seminars_seminars s1, tx_seminars_seminars s2
            WHERE ((s1.uid=s2.topic AND s2.object_type=2) OR (s1.uid=s2.uid AND s1.object_type <> 2))
                AND s2.uid=tx_seminars_seminars.uid)',
        'subtitle' => '(SELECT s1.subtitle
            FROM tx_seminars_seminars s1, tx_seminars_seminars s2
            WHERE ((s1.uid=s2.topic AND s2.object_type=2) OR (s1.uid=s2.uid AND s1.object_type <> 2))
                AND s2.uid=tx_seminars_seminars.uid)',
        'uid' => 'tx_seminars_seminars.uid',
        'event_type' => '(SELECT s1.event_type
            FROM tx_seminars_seminars s1, tx_seminars_seminars s2
            WHERE ((s1.uid=s2.topic AND s2.object_type=2) OR (s1.uid=s2.uid AND s1.object_type <> 2))
                AND s2.uid=tx_seminars_seminars.uid)',
        'accreditation_number' => 'tx_seminars_seminars.accreditation_number',
        'credit_points' => '(SELECT s1.credit_points
            FROM tx_seminars_seminars s1, tx_seminars_seminars s2
            WHERE ((s1.uid=s2.topic AND s2.object_type=2) OR (s1.uid=s2.uid AND s1.object_type <> 2))
                AND s2.uid=tx_seminars_seminars.uid)',
        // This will sort by the speaker names or the alphabetically lowest
        // speaker name (if there is more than one speaker).
        'speakers' => '(SELECT MIN(tx_seminars_speakers.title)
            FROM tx_seminars_seminars_speakers_mm, tx_seminars_speakers
            WHERE tx_seminars_seminars_speakers_mm.uid_local=tx_seminars_seminars.uid
                AND tx_seminars_seminars_speakers_mm.uid_foreign=tx_seminars_speakers.uid)',
        'date' => 'tx_seminars_seminars.begin_date',
        // 86400 seconds are one day, so this calculates us just the time of day.
        'time' => 'tx_seminars_seminars.begin_date % 86400',
        // This will sort by the place names or the alphabetically lowest
        // place name (if there is more than one place).
        'place' => '(SELECT MIN(tx_seminars_sites.title)
            FROM tx_seminars_seminars_place_mm, tx_seminars_sites
            WHERE tx_seminars_seminars_place_mm.uid_local=tx_seminars_seminars.uid
                AND tx_seminars_seminars_place_mm.uid_foreign=tx_seminars_sites.uid)',
        'price_regular' => '(SELECT s1.price_regular
            FROM tx_seminars_seminars s1, tx_seminars_seminars s2
            WHERE ((s1.uid=s2.topic AND s2.object_type=2) OR (s1.uid=s2.uid AND s1.object_type <> 2))
                AND s2.uid=tx_seminars_seminars.uid)',
        'price_special' => '(SELECT s1.price_special
            FROM tx_seminars_seminars s1, tx_seminars_seminars s2
            WHERE ((s1.uid=s2.topic AND s2.object_type=2) OR (s1.uid=s2.uid AND s1.object_type <> 2))
                AND s2.uid=tx_seminars_seminars.uid)',
        'organizers' => '(SELECT MIN(tx_seminars_organizers.title)
            FROM tx_seminars_seminars_organizers_mm, tx_seminars_organizers
            WHERE tx_seminars_seminars_organizers_mm.uid_local=tx_seminars_seminars.uid
                AND tx_seminars_seminars_organizers_mm.uid_foreign=tx_seminars_organizers.uid)',
        'vacancies' => 'tx_seminars_seminars.attendees_max
                -(
                    (SELECT COUNT(*)
                    FROM tx_seminars_attendances
                    WHERE tx_seminars_attendances.seminar=tx_seminars_seminars.uid
                        AND tx_seminars_attendances.seats=0
                        AND tx_seminars_attendances.deleted=0)
                    +(SELECT SUM(tx_seminars_attendances.seats)
                    FROM tx_seminars_attendances
                    WHERE tx_seminars_attendances.seminar=tx_seminars_seminars.uid
                        AND  tx_seminars_attendances.seats <> 0
                        AND tx_seminars_attendances.deleted=0)
                )',
        // This will sort by the target groups titles or the alphabetically lowest
        // target group title (if there is more than one speaker).
        'target_groups' => '(SELECT MIN(tx_seminars_target_groups.title)
            FROM tx_seminars_seminars_target_groups_mm, tx_seminars_target_groups
            WHERE tx_seminars_seminars_target_groups_mm.uid_local=tx_seminars_seminars.uid
                AND tx_seminars_seminars_target_groups_mm.uid_foreign=tx_seminars_target_groups.uid)',
        'status_registration' => 'tx_seminars_attendances.registration_queue',
    ];

    protected ?HookProvider $listViewHookProvider = null;

    protected ?HookProvider $singleViewHookProvider = null;

    private ?SingleViewLinkBuilder $linkBuilder = null;

    protected int $showUid = 0;

    protected string $whatToDisplay = '';

    private ?Configuration $configuration = null;

    private ResponseHeadersModifier $responseHeadersModifier;

    /**
     * Displays the seminar manager HTML.
     *
     * @param array $conf TypoScript configuration for the plugin
     *
     * @return string HTML for the plugin
     */
    public function main(string $unused, array $conf): string
    {
        $this->init($conf);
        $this->pi_initPIflexForm();

        $this->responseHeadersModifier = GeneralUtility::makeInstance(ResponseHeadersModifier::class);

        $this->getTemplateCode();
        $this->setLabels();
        $this->createHelperObjects();

        // Sets the UID of a single event that is requested (either by the
        // configuration in the flexform or by a parameter in the URL).
        if ($this->hasConfValueInteger('showSingleEvent', 's_template_special')) {
            $this->showUid = $this->getConfValueInteger('showSingleEvent', 's_template_special');
        } else {
            $this->showUid = (int)($this->piVars['showUid'] ?? 0);
        }

        $this->whatToDisplay = $this->getConfValueString('what_to_display');
        switch ($this->whatToDisplay) {
            case 'single_view':
                $result = $this->createSingleView();
                break;
            case 'list_vip_registrations':
                // The fallthrough is intended
                // because createRegistrationsListPage() will differentiate later.
            case 'list_registrations':
                $registrationsList = GeneralUtility::makeInstance(
                    RegistrationsList::class,
                    $this->conf,
                    $this->whatToDisplay,
                    (int)($this->piVars['seminar'] ?? 0),
                    $this->cObj,
                );
                $result = $registrationsList->render();
                if ($this->isConfigurationCheckEnabled()) {
                    $configurationCheck = new RegistrationListConfigurationCheck(
                        $this->getConfigurationWithFlexForms(),
                        'plugin.tx_seminars_pi1',
                    );
                    $configurationCheck->check();
                    $result .= \implode("\n", $configurationCheck->getWarningsAsHtml());
                }
                break;
            case 'category_list':
                $categoryList = GeneralUtility::makeInstance(
                    CategoryList::class,
                    $this->conf,
                    $this->cObj,
                );
                $result = $categoryList->render();
                if ($this->isConfigurationCheckEnabled()) {
                    $configurationCheck = new CategoryListConfigurationCheck(
                        $this->getConfigurationWithFlexForms(),
                        'plugin.tx_seminars_pi1',
                    );
                    $configurationCheck->check();
                    $result .= \implode("\n", $configurationCheck->getWarningsAsHtml());
                }
                break;
            case 'my_vip_events':
                // The fallthrough is intended
                // because createListView() will differentiate later.
            case 'topic_list':
                // The fallthrough is intended
                // because createListView() will differentiate later.
            case 'my_events':
                // The fallthrough is intended
                // because createListView() will differentiate later.
            case 'seminar_list':
                // The fallthrough is intended
                // because createListView() will differentiate later.
            default:
                $result = $this->createListView($this->whatToDisplay);
        }

        if ($this->isConfigurationCheckEnabled()) {
            $configuration = ConfigurationRegistry::get('plugin.tx_seminars');
            $configurationCheck = new SharedConfigurationCheck($configuration, 'plugin.tx_seminars');
            $configurationCheck->check();
            $result .= \implode("\n", $configurationCheck->getWarningsAsHtml());
        }

        return $this->pi_wrapInBaseClass($result);
    }

    ///////////////////////
    // General functions.
    ///////////////////////

    protected function getListViewHookProvider(): HookProvider
    {
        if (!$this->listViewHookProvider instanceof HookProvider) {
            $this->listViewHookProvider = GeneralUtility::makeInstance(HookProvider::class, SeminarListView::class);
        }

        return $this->listViewHookProvider;
    }

    protected function getSingleViewHookProvider(): HookProvider
    {
        if ($this->singleViewHookProvider === null) {
            $this->singleViewHookProvider = GeneralUtility::makeInstance(HookProvider::class, SeminarSingleView::class);
        }

        return $this->singleViewHookProvider;
    }

    /**
     * Creates a seminar in `$this->seminar`.
     * If the seminar cannot be created, `$this->seminar` will be `null`, and
     * this function will return `false`.
     *
     * @param positive-int $seminarUid
     * @param bool $showHidden whether hidden records should be retrieved as well
     *
     * @return bool TRUE if the seminar UID is valid and the object has been created, FALSE otherwise
     */
    public function createSeminar(int $seminarUid, bool $showHidden = false): bool
    {
        $this->seminar = null;

        /** @var LegacyEvent|null $event */
        $event = LegacyEvent::fromUid($seminarUid, $showHidden);
        if ($event instanceof LegacyEvent) {
            $this->setSeminar($event);
            $result = $showHidden ? $this->canShowCurrentEvent() : true;
        } else {
            $this->setSeminar();
            $result = false;
        }

        return $result;
    }

    /**
     * Sets the current seminar for the list view.
     */
    protected function setSeminar(?LegacyEvent $event = null): void
    {
        $this->seminar = $event;
    }

    public function createHelperObjects(): void
    {
        if (!$this->eventMapper instanceof EventMapper) {
            $this->eventMapper = GeneralUtility::makeInstance(EventMapper::class);
        }
    }

    public function getSeminar(): ?LegacyEvent
    {
        return $this->seminar;
    }

    public function getRegistrationManager(): RegistrationManager
    {
        return GeneralUtility::makeInstance(RegistrationManager::class);
    }

    /**
     * Creates the link to the list of registrations for the current seminar.
     * Returns an empty string if this link is not allowed.
     * For standard lists, a link is created if either the user is a VIP or is
     * registered for that seminar (with the link to the VIP list taking
     * precedence).
     *
     * @return string HTML for the link (may be an empty string)
     */
    protected function getRegistrationsListLink(): string
    {
        $result = '';
        $targetPageId = 0;

        if (
            $this->seminar->canViewRegistrationsList(
                $this->whatToDisplay,
                0,
                $this->getConfValueInteger('registrationsVipListPID'),
                $this->getConfValueString('accessToFrontEndRegistrationLists'),
            )
        ) {
            // So a link to the VIP list is possible.
            $targetPageId = $this->getConfValueInteger('registrationsVipListPID');
        } elseif (
            $this->seminar->canViewRegistrationsList(
                $this->whatToDisplay,
                $this->getConfValueInteger('registrationsListPID'),
                0,
                $this->getConfValueString('accessToFrontEndRegistrationLists'),
            )
        ) {
            // No link to the VIP list ... so maybe to the list for the participants.
            $targetPageId = $this->getConfValueInteger('registrationsListPID');
        }

        if ($targetPageId) {
            $result = $this->cObj->getTypoLink(
                $this->translate('label_listRegistrationsLink'),
                (string)$targetPageId,
                ['tx_seminars_pi1[seminar]' => $this->seminar->getUid()],
            );
        }

        return $result;
    }

    /**
     * Returns a label wrapped in <a> tags. The link points to the login page
     * and contains a redirect parameter that points back to a certain page
     * (must be provided as a parameter to this function). The user will be
     * redirected to this page after a successful login.
     *
     * If an event uid is provided, the return parameter will contain a showUid
     * parameter with this UID.
     *
     * @param string $label the label to wrap into a link
     * @param int $pageId the PID of the page to redirect to after login (must not be empty)
     * @param int $eventId the UID of the event (may be empty)
     *
     * @return string the wrapped label
     */
    public function getLoginLink(string $label, int $pageId, int $eventId = 0): string
    {
        $linkConfiguration = ['parameter' => $pageId];

        if ($eventId > 0) {
            $linkConfiguration['additionalParams'] = GeneralUtility::implodeArrayForUrl(
                'tx_seminars_eventregistration',
                ['event' => $eventId],
                '',
                false,
                true,
            );
        }

        $redirectUrl = GeneralUtility::locationHeaderUrl(
            $this->cObj->typoLink_URL($linkConfiguration),
        );

        // XXX We need to do this workaround of manually encoding brackets in
        // the URL due to a bug in the TYPO3 core:
        // https://bugs.typo3.org/view.php?id=3808
        $redirectUrl = preg_replace(
            ['/\\[/', '/\\]/'],
            ['%5B', '%5D'],
            $redirectUrl,
        );

        return $this->cObj->typoLink(
            $label,
            [
                'parameter' => $this->getConfValueInteger('loginPID'),
                'additionalParams' => GeneralUtility::implodeArrayForUrl(
                    '',
                    [
                        rawurlencode('tx_seminars_pi1[uid]') => $eventId,
                        'redirect_url' => $redirectUrl,
                    ],
                ),
            ],
        );
    }

    // Single view functions.

    /**
     * Displays detailed data for an event.
     *
     * Fields listed in $this->subpartsToHide get hidden (i.e., not displayed).
     */
    protected function createSingleView(): string
    {
        $this->hideSubparts($this->getConfValueString('hideFields', 's_template_special'), 'FIELD_WRAPPER');
        $this->hideSubparts('language', 'FIELD_WRAPPER');

        if ($this->showUid <= 0) {
            $this->setMarker('error_text', $this->translate('message_missingSeminarNumber'));
            $result = $this->getSubpart('ERROR_VIEW');
            $this->responseHeadersModifier->setOverrideStatusCode(404);
        } elseif ($this->createSeminar($this->showUid, $this->isLoggedIn())) {
            $result = $this->createSingleViewForExistingEvent();
        } else {
            $this->setMarker('error_text', $this->translate('message_wrongSeminarNumber'));
            $result = $this->getSubpart('ERROR_VIEW');
            $this->responseHeadersModifier->setOverrideStatusCode(404);
        }

        $listPid = \max(0, $this->getConfValueInteger('listPID'));
        $this->setMarker('backlink', $this->pi_linkTP($this->translate('label_back'), [], true, $listPid));
        $result .= $this->getSubpart('BACK_VIEW');

        if ($this->isConfigurationCheckEnabled()) {
            $configurationCheck = new SingleViewConfigurationCheck(
                $this->getConfigurationWithFlexForms(),
                'plugin.tx_seminars_pi1',
            );
            $configurationCheck->check();
            $result .= \implode("\n", $configurationCheck->getWarningsAsHtml());
        }

        return $result;
    }

    /**
     * Creates the single view for the event with the event in $this->seminar.
     *
     * @return string the rendered single view
     */
    protected function createSingleViewForExistingEvent(): string
    {
        $title = $this->seminar->getTitle();
        GeneralUtility::makeInstance(SingleViewPageTitleProvider::class)->setTitle($title);

        $this->setEventTypeMarker();

        // This is for old templates that still have the removed marker.
        $this->setMarker('STYLE_SINGLEVIEWTITLE', '');

        if ($this->seminar->hasImage()) {
            $this->setMarker('SINGLE_VIEW_IMAGE', $this->createImageForSingleView());
        } else {
            $this->hideSubparts('image', 'FIELD_WRAPPER');
        }

        $this->setMarker('title', \htmlspecialchars($title, ENT_QUOTES | ENT_HTML5));
        $this->setMarker('uid', $this->seminar->getUid());

        $this->setSubtitleMarker();
        $this->setDescriptionMarker();

        $this->setAccreditationNumberMarker();
        $this->setCreditPointsMarker();

        $this->setCategoriesMarker();

        $this->setMarker('date', $this->seminar->getDate());
        $this->setMarker('time', $this->seminar->getTime());
        $this->setPlaceMarker();
        $this->setRoomMarker();

        $this->setTimeSlotsMarkers();

        $this->setExpiryMarker();

        $this->setHeading('speakers');
        $this->setSpeakersMarker();
        $this->setHeading('partners');
        $this->setPartnersMarker();
        $this->setHeading('tutors');
        $this->setTutorsMarker();
        $this->setHeading('leaders');
        $this->setLeadersMarker();

        $this->setSingleViewPriceMarkers();
        $this->setPaymentMethodsMarker();

        $this->setAdditionalInformationMarker();

        $this->setTargetGroupsMarkers();

        $this->setRequirementsMarker();
        $this->setDependenciesMarker();

        $this->setMarker('organizers', $this->getOrganizersMarkerContent());
        $this->setOrganizingPartnersMarker();

        $this->setAttachedFilesMarkers();

        $this->setVacanciesMarker();

        $this->setRegistrationDeadlineMarker();
        $this->setRegistrationMarker();
        $this->setListOfRegistrationMarker();

        $this->hideUnneededSubpartsForTopicRecords();

        $this->getSingleViewHookProvider()->executeHook('modifySingleView', $this);

        $result = $this->getSubpart('SINGLE_VIEW');

        // Caches $this->seminar because the list view will overwrite
        // $this->seminar.
        // TODO: This needs to be removed as soon as the list view is moved
        // to its own class.
        $seminar = $this->seminar;
        if ($this->seminar->hasEndDate()) {
            $result .= $this->createEventsOnNextDayList();
        }
        $this->setSeminar($seminar);
        if ($this->seminar->isEventTopic() || $this->seminar->isEventDate()) {
            $result .= $this->createOtherDatesList();
        }

        return $result;
    }

    private function createImageForSingleView(): string
    {
        $image = $this->seminar->getImage();
        if (!$image instanceof FileReference) {
            return '';
        }

        $imageConfiguration = [
            'altText' => '',
            'file' => $image->getPublicUrl(),
            'file.' => [
                'width' => $this->getConfValueInteger('seminarImageSingleViewWidth') . 'm',
                'height' => $this->getConfValueInteger('seminarImageSingleViewHeight') . 'm',
            ],
        ];

        return $this->cObj->cObjGetSingle('IMAGE', $imageConfiguration);
    }

    /**
     * Fills in the matching marker for the event type or hides the subpart if there is no event type.
     */
    private function setEventTypeMarker(): void
    {
        if (!$this->seminar->hasEventType()) {
            $this->hideSubparts('event_type', 'field_wrapper');
            return;
        }

        $this->setMarker('event_type', \htmlspecialchars($this->seminar->getEventType(), ENT_QUOTES | ENT_HTML5));
    }

    /**
     * Fills in the matching marker for the subtitle or hides the subpart if there is no subtitle.
     */
    private function setSubtitleMarker(): void
    {
        if (!$this->seminar->hasSubtitle()) {
            $this->hideSubparts('subtitle', 'field_wrapper');
            return;
        }

        $this->setMarker('subtitle', \htmlspecialchars($this->seminar->getSubtitle(), ENT_QUOTES | ENT_HTML5));
    }

    /**
     * Fills in the matching marker for the description or hides the subpart if there is no description.
     */
    private function setDescriptionMarker(): void
    {
        if (!$this->seminar->hasDescription()) {
            $this->hideSubparts('description', 'field_wrapper');
            return;
        }

        $this->setMarker('description', $this->renderAsRichText($this->seminar->getDescription()));
    }

    /**
     * Fills in the matching marker for the accreditation number or hides the subpart if there is no accreditation
     * number.
     */
    private function setAccreditationNumberMarker(): void
    {
        if (!$this->seminar->hasAccreditationNumber()) {
            $this->hideSubparts('accreditation_number', 'field_wrapper');
            return;
        }

        $this->setMarker(
            'accreditation_number',
            \htmlspecialchars($this->seminar->getAccreditationNumber(), ENT_QUOTES | ENT_HTML5),
        );
    }

    /**
     * Fills in the matching marker for the credit points or hides the subpart if there are no credit points.
     */
    private function setCreditPointsMarker(): void
    {
        if (!$this->seminar->hasCreditPoints()) {
            $this->hideSubparts('credit_points', 'field_wrapper');
            return;
        }

        $this->setMarker('credit_points', $this->seminar->getCreditPoints());
    }

    /**
     * Fills in the matching marker for the categories or hides the subpart if there are no categories.
     */
    private function setCategoriesMarker(): void
    {
        if (!$this->seminar->hasCategories()) {
            $this->hideSubparts('category', 'field_wrapper');
            return;
        }

        $categoryMarker = '';
        $this->setMarker('category_icon', '');
        foreach ($this->seminar->getCategories() as $category) {
            $this->setMarker('category_title', \htmlspecialchars($category['title'], ENT_QUOTES | ENT_HTML5));
            $categoryMarker .= $this->getSubpart('SINGLE_CATEGORY');
        }
        $this->setSubpart('SINGLE_CATEGORY', $categoryMarker);
    }

    /**
     * Fills in the matching marker for the place.
     */
    private function setPlaceMarker(): void
    {
        $this->setMarker(
            'place',
            $this->getConfValueBoolean('showSiteDetails', 's_template_special')
                ? $this->seminar->getPlaceWithDetails($this)
                : \htmlspecialchars($this->seminar->getPlaceShort(), ENT_QUOTES | ENT_HTML5),
        );
    }

    /**
     * Fills in the matching marker for the room or hides the subpart if there is no room.
     */
    private function setRoomMarker(): void
    {
        if (!$this->seminar->hasRoom()) {
            $this->hideSubparts('room', 'field_wrapper');
            return;
        }

        $this->setMarker('room', \htmlspecialchars($this->seminar->getRoom(), ENT_QUOTES | ENT_HTML5));
    }

    /**
     * Fills in the matching markers for the time slots or hides the subpart if there are no time slots.
     */
    protected function setTimeSlotsMarkers(): void
    {
        if (!$this->seminar->hasTimeslots()) {
            $this->hideSubparts('timeslots', 'field_wrapper');
            return;
        }

        $this->hideSubparts('date,time', 'field_wrapper');

        $timeSlotsOutput = '';
        foreach ($this->seminar->getTimeSlotsAsArrayWithMarkers() as $timeSlotData) {
            $this->setMarker('timeslot_date', $timeSlotData['date']);
            $this->setMarker('timeslot_time', $timeSlotData['time']);
            $this->setMarker('timeslot_entry_date', '');
            $this->setMarker('label_timeslot_entry_date', '');
            $this->setMarker('timeslot_room', \htmlspecialchars($timeSlotData['room'], ENT_QUOTES | ENT_HTML5));
            $this->setMarker('timeslot_place', \htmlspecialchars($timeSlotData['place'], ENT_QUOTES | ENT_HTML5));
            $this->setMarker('timeslot_speakers', '');
            $this->setMarker('label_timeslot_speakers', '');

            $timeSlotsOutput .= $this->getSubpart('SINGLE_TIMESLOT');
        }

        $this->setSubpart('SINGLE_TIMESLOT', $timeSlotsOutput);
    }

    /**
     * Fills in the matching marker for the expiry or hides the subpart if there is no expiry.
     */
    private function setExpiryMarker(): void
    {
        if (!$this->seminar->hasExpiry()) {
            $this->hideSubparts('expiry', 'field_wrapper');
            return;
        }

        $this->setMarker('expiry', $this->seminar->getExpiry());
    }

    /**
     * Fills in the matching markers for the speakers or hides the subpart if there are no speakers.
     */
    private function setSpeakersMarker(): void
    {
        if (!$this->seminar->hasSpeakers()) {
            $this->hideSubparts('speakers', 'field_wrapper');
            return;
        }

        $this->setSpeakersMarkerWithoutCheck('speakers');
    }

    /**
     * Fills in the matching markers for the partners or hides the subpart if there are no partners.
     */
    private function setPartnersMarker(): void
    {
        if (!$this->seminar->hasPartners()) {
            $this->hideSubparts('partners', 'field_wrapper');
            return;
        }

        $this->setSpeakersMarkerWithoutCheck('partners');
    }

    /**
     * Fills in the matching markers for the tutors or hides the subpart if there are no tutors.
     */
    private function setTutorsMarker(): void
    {
        if (!$this->seminar->hasTutors()) {
            $this->hideSubparts('tutors', 'field_wrapper');
            return;
        }

        $this->setSpeakersMarkerWithoutCheck('tutors');
    }

    /**
     * Fills in the matching markers for the leaders or hides the subpart if there are no leaders.
     */
    private function setLeadersMarker(): void
    {
        if (!$this->seminar->hasLeaders()) {
            $this->hideSubparts('leaders', 'field_wrapper');
            return;
        }

        $this->setSpeakersMarkerWithoutCheck('leaders');
    }

    /**
     * Sets the speaker markers for the type given in $speakerType without
     * checking whether the current event has any speakers of the given type.
     *
     * @param 'speakers'|'partners'|'tutors'|'leaders' $speakerType
     */
    private function setSpeakersMarkerWithoutCheck(string $speakerType): void
    {
        if (!\in_array($speakerType, self::VALID_SPEAKER_TYPES, true)) {
            throw new \InvalidArgumentException('The given speaker type is not valid.', 1333293083);
        }

        $speakerContent = $this->getConfValueBoolean('showSpeakerDetails', 's_template_special')
            ? $this->seminar->getSpeakersWithDetails($this, $speakerType)
            : $this->seminar->getSpeakersShort($speakerType);
        $this->setMarker($speakerType, $speakerContent);
    }

    /**
     * Fills in the matching markers for the prices or hides the unused subparts.
     */
    private function setSingleViewPriceMarkers(): void
    {
        if ($this->seminar->getPriceOnRequest()) {
            $this->setSingleViewPriceMarkersForOnRequest();
            return;
        }

        $this->setSingleViewRegularPriceMarkers();
        $this->setSingleViewSpecialPriceMarkers();
    }

    /**
     * Sets the price to "on request" and hides all other price markers
     *
     * This method may only be called if the current event is set to "price on request".
     */
    private function setSingleViewPriceMarkersForOnRequest(): void
    {
        if (!$this->seminar->getPriceOnRequest()) {
            return;
        }

        $this->setMarker('price_regular', $this->seminar->getCurrentPriceRegular());
        $this->hideSubparts(
            'price_special,price_earlybird_regular,price_earlybird_special',
            'field_wrapper',
        );
    }

    /**
     * Set the regular price (with or without early bird rebate).
     */
    private function setSingleViewRegularPriceMarkers(): void
    {
        if ($this->seminar->hasEarlyBirdPrice() && !$this->seminar->isEarlyBirdDeadlineOver()) {
            $this->setMarker('price_earlybird_regular', $this->seminar->getEarlyBirdPriceRegular());
            $this->setMarker(
                'message_earlybird_price_regular',
                sprintf($this->translate('message_earlybird_price'), $this->seminar->getEarlyBirdDeadline()),
            );
            $this->setMarker('price_regular', $this->seminar->getPriceRegular());
        } else {
            $this->setMarker('price_regular', $this->seminar->getPriceRegular());
            if ($this->getConfValueBoolean('generalPriceInSingle', 's_template_special')) {
                $this->setMarker('label_price_regular', $this->translate('label_price_general'));
            }
            $this->hideSubparts('price_earlybird_regular', 'field_wrapper');
        }
    }

    /**
     * Set the special price (with or without early bird rebate).
     */
    private function setSingleViewSpecialPriceMarkers(): void
    {
        if ($this->seminar->hasPriceSpecial()) {
            if ($this->seminar->hasEarlyBirdPrice() && !$this->seminar->isEarlyBirdDeadlineOver()) {
                $this->setMarker('price_earlybird_special', $this->seminar->getEarlyBirdPriceSpecial());
                $this->setMarker(
                    'message_earlybird_price_special',
                    sprintf($this->translate('message_earlybird_price'), $this->seminar->getEarlyBirdDeadline()),
                );
                $this->setMarker('price_special', $this->seminar->getPriceSpecial());
            } else {
                $this->setMarker('price_special', $this->seminar->getPriceSpecial());
                $this->hideSubparts('price_earlybird_special', 'field_wrapper');
            }
        } else {
            $this->hideSubparts('price_special', 'field_wrapper');
            $this->hideSubparts('price_earlybird_special', 'field_wrapper');
        }
    }

    /**
     * Fills in the matching marker for the payment methods or hides the subpart
     * if there are no payment methods.
     */
    private function setPaymentMethodsMarker(): void
    {
        if (!$this->seminar->hasPaymentMethods()) {
            $this->hideSubparts('paymentmethods', 'field_wrapper');
            return;
        }

        $paymentMethods = $this->seminar->getPaymentMethods();

        $paymentMethodOutput = '';
        foreach ($paymentMethods as $paymentMethod) {
            $this->setMarker('payment_method', \htmlspecialchars($paymentMethod, ENT_QUOTES | ENT_HTML5));
            $paymentMethodOutput .= $this->getSubpart('SINGLE_PAYMENT_METHOD');
        }

        $this->setSubpart('SINGLE_PAYMENT_METHOD', $paymentMethodOutput);
    }

    /**
     * Fills in the matching marker for the additional information or hides the
     * subpart if there is no additional information.
     */
    private function setAdditionalInformationMarker(): void
    {
        if (!$this->seminar->hasAdditionalInformation()) {
            $this->hideSubparts('additional_information', 'field_wrapper');
            return;
        }

        $this->setMarker(
            'additional_information',
            $this->renderAsRichText($this->seminar->getAdditionalInformation()),
        );
    }

    /**
     * Fills in the matching markers for the attached files or hides the subpart if there are no attached files.
     */
    private function setAttachedFilesMarkers(): void
    {
        if (!$this->seminar->hasAttachedFiles() || !$this->mayUserAccessAttachedFiles()) {
            $this->hideSubparts('attached_files', 'field_wrapper');
            return;
        }

        $attachedFilesOutput = '';

        foreach ($this->seminar->getAttachedFiles() as $file) {
            $encodedUrl = \htmlspecialchars((string)$file->getPublicUrl(), ENT_QUOTES | ENT_HTML5);
            // @phpstan-ignore-next-line The string cast works around a bug in the Core that was fixed in V11.
            $encodedTitle = \htmlspecialchars((string)$file->getTitle(), ENT_QUOTES | ENT_HTML5);
            $encodedFileName = \htmlspecialchars(
                $file->getNameWithoutExtension() . '.' . $file->getExtension(),
                ENT_QUOTES | ENT_HTML5,
            );
            $link = '<a href="' . $encodedUrl . '" title="' . $encodedTitle . '">' . $encodedFileName . '</a>';
            $this->setMarker('attached_file_name', $link);
            $this->setMarker('attached_file_size', GeneralUtility::formatSize($file->getSize()));
            $encodedExtension = \htmlspecialchars($file->getExtension(), ENT_QUOTES | ENT_HTML5);
            $this->setMarker('attached_file_type', $encodedExtension);

            $attachedFilesOutput .= $this->getSubpart('ATTACHED_FILES_LIST_ITEM');
        }

        $this->setSubpart('ATTACHED_FILES_LIST_ITEM', $attachedFilesOutput);
    }

    /**
     * Fills in the matching marker for the target groups or hides the subpart if there are no target groups.
     */
    private function setTargetGroupsMarkers(): void
    {
        if (!$this->seminar->hasTargetGroups()) {
            $this->hideSubparts('target_groups', 'field_wrapper');
            return;
        }

        $targetGroupsOutput = '';

        foreach ($this->seminar->getTargetGroupsAsArray() as $targetGroup) {
            $this->setMarker('target_group', \htmlspecialchars($targetGroup, ENT_QUOTES | ENT_HTML5));
            $targetGroupsOutput .= $this->getSubpart('SINGLE_TARGET_GROUP');
        }

        $this->setSubpart('SINGLE_TARGET_GROUP', $targetGroupsOutput);
    }

    /**
     * Fills the matching marker for the requirements or hides the subpart
     * if there are no requirements for the current event.
     */
    private function setRequirementsMarker(): void
    {
        if (!$this->seminar->hasRequirements()) {
            $this->hideSubparts('requirements', 'field_wrapper');
            return;
        }

        $requirementsLists = GeneralUtility::makeInstance(RequirementsList::class, $this->conf, $this->cObj);
        $requirementsLists->setEvent($this->seminar);

        $this->setSubpart(
            'FIELD_WRAPPER_REQUIREMENTS',
            $requirementsLists->render(),
        );
    }

    /**
     * Fills the matching marker for the dependencies or hides the subpart
     * if there are no dependencies for the current event.
     */
    private function setDependenciesMarker(): void
    {
        if (!$this->seminar->hasDependencies()) {
            $this->hideSubparts('dependencies', 'field_wrapper');
            return;
        }

        $output = '';

        $eventMapper = MapperRegistry::get(EventMapper::class);

        foreach ($this->seminar->getDependencies() as $dependency) {
            $dependencyUid = $dependency->getUid();
            \assert($dependencyUid > 0);
            $event = $eventMapper->find($dependencyUid);
            $this->setMarker(
                'dependency_title',
                $this->createSingleViewLink($event, $event->getTitle()),
            );
            $output .= $this->getSubpart('SINGLE_DEPENDENCY');
        }

        $this->setSubpart('SINGLE_DEPENDENCY', $output);
    }

    /**
     * Fills in the matching marker for the organizing partners or hides the
     * subpart if there are no organizing partners.
     */
    private function setOrganizingPartnersMarker(): void
    {
        if (!$this->seminar->hasOrganizingPartners()) {
            $this->hideSubparts('organizing_partners', 'field_wrapper');
            return;
        }

        $this->setMarker('organizing_partners', $this->seminar->getOrganizingPartners());
    }

    /**
     * Fills in the matching marker for the vacancies or hides the subpart no registration is possible.
     */
    private function setVacanciesMarker(): void
    {
        $vacancies = $this->seminar->getVacanciesString();
        if ($vacancies !== '') {
            $this->setMarker('vacancies', $vacancies);
        } else {
            $this->hideSubparts('vacancies', 'field_wrapper');
        }
    }

    /**
     * Fills in the matching marker for the registration deadline or hides the
     * subpart if there is no registration deadline.
     */
    private function setRegistrationDeadlineMarker(): void
    {
        if (!$this->seminar->hasRegistrationDeadline()) {
            $this->hideSubparts('deadline_registration', 'field_wrapper');
            return;
        }

        $this->setMarker('deadline_registration', $this->seminar->getRegistrationDeadline());
    }

    /**
     * Checks whether online registration is enabled at all by configuration.
     */
    protected function isRegistrationEnabled(): bool
    {
        return $this->getConfValueBoolean('enableRegistration');
    }

    /**
     * Checks whether a front-end user is logged in.
     */
    public function isLoggedIn(): bool
    {
        return GeneralUtility::makeInstance(Context::class)->getAspect('frontend.user')->isLoggedIn();
    }

    /**
     * Returns the UID of the logged-in front-end user (or 0 if no user is logged in).
     */
    protected function getLoggedInFrontEndUserUid(): int
    {
        return GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('frontend.user', 'id');
    }

    /**
     * Fills in the matching marker for the link to the registration form or
     * hides the subpart if the registration is disabled.
     */
    private function setRegistrationMarker(): void
    {
        if (!$this->isRegistrationEnabled() || $this->seminar->getPriceOnRequest()) {
            $this->hideSubparts('registration', 'field_wrapper');
            return;
        }

        $this->setMarker(
            'registration',
            $this->getRegistrationManager()->canRegisterIfLoggedIn($this->seminar)
                ? $this->getRegistrationManager()->getLinkToRegistrationPage($this, $this->seminar)
                : $this->getRegistrationManager()->canRegisterIfLoggedInMessage($this->seminar),
        );
    }

    /**
     * Fills in the matching marker for the link to the list of registrations
     * or hides the subpart if the currently logged in FE user is not allowed
     * to view the list of registrations.
     */
    private function setListOfRegistrationMarker(): void
    {
        $canViewListOfRegistrations = $this->seminar->canViewRegistrationsList(
            $this->whatToDisplay,
            $this->getConfValueInteger('registrationsListPID'),
            $this->getConfValueInteger('registrationsVipListPID'),
            $this->getConfValueString('accessToFrontEndRegistrationLists'),
        );

        if (!$canViewListOfRegistrations) {
            $this->hideSubparts('list_registrations', 'field_wrapper');
            return;
        }

        $this->setMarker('list_registrations', $this->getRegistrationsListLink());
    }

    /**
     * Hides unneeded subparts for topic records.
     */
    private function hideUnneededSubpartsForTopicRecords(): void
    {
        if ($this->seminar->getRecordType() !== EventInterface::TYPE_EVENT_TOPIC) {
            return;
        }

        $this->hideSubparts(
            'accreditation_number,date,time,place,room,speakers,organizers,' .
            'vacancies,deadline_registration,registration,' .
            'list_registrations,eventsnextday',
            'field_wrapper',
        );
    }

    /**
     * Creates the list of events that start the next day (after the current
     * event has ended). Practically, this is just a special kind of list view.
     * In case the current record is a topic record, this function will return
     * an empty string.
     *
     * Note: This function relies on $this->seminar, but also overwrites
     * $this->seminar.
     *
     * @return string HTML for the events list (may be an empty string)
     */
    private function createEventsOnNextDayList(): string
    {
        $result = '';

        $seminarBag = $this->initListView('events_next_day');

        if ($this->internal['res_count'] > 0) {
            $tableEventsNextDay = $this->createListTable($seminarBag, 'events_next_day');

            $this->setMarker('table_eventsnextday', $tableEventsNextDay);

            $result = $this->getSubpart('EVENTSNEXTDAY_VIEW');
        }
        $result .= $this->checkListViewConfiguration();

        return $result;
    }

    /**
     * @return string error messages as HTML; will be empty if there are none of if the configuration check is disabled
     */
    private function checkListViewConfiguration(): string
    {
        if (!$this->isConfigurationCheckEnabled()) {
            return '';
        }

        $configurationCheck = new ListViewConfigurationCheck(
            $this->getConfigurationWithFlexForms(),
            'plugin.tx_seminars_pi1',
        );
        $configurationCheck->check();

        return \implode("\n", $configurationCheck->getWarningsAsHtml());
    }

    /**
     * Creates the list of (other) dates for this topic. Practically, this is
     * just a special kind of list view. In case this topic has no other dates,
     * this function will return an empty string.
     *
     * Note: This function relies on $this->seminar, but also overwrites
     * $this->seminar.
     *
     * @return string HTML for the events list (may be an empty string)
     */
    private function createOtherDatesList(): string
    {
        $result = '';

        $seminarBag = $this->initListView('other_dates');

        if ($this->internal['res_count'] > 0) {
            // If we are on a topic record, overwrite the label with an alternative text.
            if (
                \in_array(
                    $this->seminar->getRecordType(),
                    [EventInterface::TYPE_SINGLE_EVENT, EventInterface::TYPE_EVENT_TOPIC],
                    true,
                )
            ) {
                $this->setMarker('label_list_otherdates', $this->translate('label_list_dates'));
            }

            // Hides unneeded columns from the list.
            $temporaryHiddenColumns = ['title', 'list_registrations'];
            $this->hideColumns($temporaryHiddenColumns);

            $tableOtherDates = $this->createListTable($seminarBag, 'other_dates');

            $this->setMarker('table_otherdates', $tableOtherDates);

            $result = $this->getSubpart('OTHERDATES_VIEW');

            // Un-hides the previously hidden columns.
            $this->unhideColumns($temporaryHiddenColumns);
        }
        $result .= $this->checkListViewConfiguration();

        return $result;
    }

    /////////////////////////
    // List view functions.
    /////////////////////////

    /**
     * Creates the HTML for the event list view.
     * This function is used for the normal event list as well as the
     * "my events" and the "my VIP events" list.
     *
     * @param string $whatToDisplay a string selecting the flavor of list view: either an empty string
     *        (for the default list view), the value from "what_to_display" or "other_dates"
     *
     * @return string HTML code with the event list
     */
    protected function createListView(string $whatToDisplay): string
    {
        $configurationCheckResult = $this->checkListViewConfiguration();
        if ($configurationCheckResult !== '') {
            // There are configuration check errors. As some of those detected configuration problems
            // could cause exceptions, we'd rather display the (more helpful) warnings than crash.
            return $configurationCheckResult;
        }

        $result = '';
        $isOkay = true;
        $this->ensureIntegerPiVars(
            [
                'from_day',
                'from_month',
                'from_year',
                'to_day',
                'to_month',
                'to_year',
                'age',
                'price_from',
                'price_to',
            ],
        );

        switch ($whatToDisplay) {
            case 'my_events':
                if ($this->isLoggedIn()) {
                    $result .= $this->getSubpart('MESSAGE_MY_EVENTS');
                } else {
                    $this->setMarker('error_text', $this->translate('message_notLoggedIn'));
                    $result .= $this->getSubpart('ERROR_VIEW');
                    $result .= $this->getLoginLink(
                        $this->translate('message_pleaseLogIn'),
                        (int)$this->getFrontEndController()->id,
                    );
                    $isOkay = false;
                }
                break;
            case 'my_vip_events':
                if ($this->isLoggedIn()) {
                    $result .= $this->getSubpart('MESSAGE_MY_VIP_EVENTS');
                } else {
                    $this->setMarker('error_text', $this->translate('message_notLoggedIn'));
                    $result .= $this->getSubpart('ERROR_VIEW');
                    $result .= $this->getLoginLink(
                        $this->translate('message_pleaseLogIn'),
                        (int)$this->getFrontEndController()->id,
                    );
                    $isOkay = false;
                }

                if ($this->isConfigurationCheckEnabled()) {
                    $configurationCheck = new MyVipEventsConfigurationCheck(
                        $this->getConfigurationWithFlexForms(),
                        'plugin.tx_seminars_pi1',
                    );
                    $configurationCheck->check();
                    $result .= \implode("\n", $configurationCheck->getWarningsAsHtml());
                }

                break;
            default:
                // nothing to do
        }

        if ($isOkay) {
            $result .= $this->getSelectorWidgetIfNecessary($whatToDisplay);

            // Creates the seminar or registration bag for the list view (with
            // all the filtering applied).
            $seminarOrRegistrationBag = $this->initListView($whatToDisplay);

            if ($this->internal['res_count'] > 0) {
                $result .= $this->createListTable($seminarOrRegistrationBag, $whatToDisplay);
            } else {
                $this->setMarker(
                    'error_text',
                    $this->translate('message_noResults'),
                );
                $result .= $this->getSubpart('ERROR_VIEW');
            }

            // Shows the page browser (if not deactivated in the configuration).
            if (!$this->getConfValueBoolean('hidePageBrowser', 's_template_special')) {
                $result .= $this->pi_list_browseresults();
            }
        }

        return $result;
    }

    /**
     * Initializes the list view (normal list, my events or my VIP events) and
     * creates a seminar bag or a registration bag (for the "my events" view),
     * but does not create any actual HTML output.
     *
     * @param string $whatToDisplay the flavor of list view: either an empty string (for the default list view),
     *        the value from "what_to_display", or "other_dates"
     *
     * @return RegistrationBag|EventBag a bag containing the items for the list view
     */
    public function initListView(string $whatToDisplay = ''): AbstractBag
    {
        if (\str_contains($this->cObj->currentRecord, 'tt_content')) {
            $this->conf['pidList'] = $this->getConfValueString('pages');
            $this->conf['recursive'] = $this->getConfValueInteger('recursive');
        }

        $this->hideColumnsForAllViewsFromTypoScriptSetup();
        $this->hideRegisterColumnIfNecessary($whatToDisplay);
        $this->hideColumnsForAllViewsExceptMyEvents($whatToDisplay);
        $this->hideListRegistrationsColumnIfNecessary($whatToDisplay);
        $this->hideFilesColumnIfUserCannotAccessFiles();

        if (!isset($this->piVars['pointer'])) {
            $this->piVars['pointer'] = 0;
        }

        $this->internal['descFlag'] = $this->getListViewConfValueBoolean('descFlag');
        $this->internal['orderBy'] = $this->getListViewConfValueString('orderBy');

        // number of results to show in a listing
        $this->internal['results_at_a_time'] = MathUtility::forceIntegerInRange(
            $this->getListViewConfValueInteger('results_at_a_time'),
            0,
            1000,
            20,
        );
        // maximum number of 'pages' in the browse-box: 'Page 1', 'Page 2', etc.
        $this->internal['maxPages'] = MathUtility::forceIntegerInRange(
            $this->getListViewConfValueInteger('maxPages'),
            0,
            1000,
            2,
        );

        if ($whatToDisplay === 'my_events') {
            $builder = $this->createRegistrationBagBuilder();
        } else {
            $builder = $this->createSeminarBagBuilder();
        }

        if ($whatToDisplay !== 'my_events') {
            $this->limitForAdditionalParameters($builder);
        }
        if (!in_array($whatToDisplay, ['my_events', 'topic_list'], true)) {
            $builder->limitToDateAndSingleRecords();
            $this->limitToTimeFrameSetting($builder);
        }

        $userUid = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('frontend.user', 'id');
        $user = $userUid > 0 ? MapperRegistry::get(FrontEndUserMapper::class)->find($userUid) : null;

        switch ($whatToDisplay) {
            case 'topic_list':
                $builder->limitToTopicRecords();
                $this->hideColumnsForTheTopicListView();
                break;
            case 'my_events':
                $builder->limitToAttendee($user);
                break;
            case 'my_vip_events':
                $builder->limitToEventManager($this->getLoggedInFrontEndUserUid());
                break;
            case 'events_next_day':
                $builder->limitToEventsNextDay($this->seminar);
                break;
            case 'other_dates':
                $builder->limitToOtherDatesForTopic($this->seminar);
                break;
            default:
                // nothing to do
        }

        if (($whatToDisplay === 'other_dates') || ($whatToDisplay === 'seminar_list')) {
            $hideBookedOutEvents = $this->getConfValueBoolean('showOnlyEventsWithVacancies', 's_listView');
            if ($hideBookedOutEvents) {
                $builder->limitToEventsWithVacancies();
            }
        }

        $pointer = (int)($this->piVars['pointer'] ?? 0);
        $resultsAtATime = MathUtility::forceIntegerInRange($this->internal['results_at_a_time'], 1, 1000);
        $builder->setLimit(($pointer * $resultsAtATime) . ',' . $resultsAtATime);

        if ($builder instanceof EventBagBuilder) {
            $this->getListViewHookProvider()->executeHook('modifyEventBagBuilder', $this, $builder, $whatToDisplay);
        } elseif ($builder instanceof RegistrationBagBuilder) {
            $this
                ->getListViewHookProvider()
                ->executeHook('modifyRegistrationBagBuilder', $this, $builder, $whatToDisplay);
        }

        $seminarOrRegistrationBag = $builder->build();

        $this->internal['res_count'] = $seminarOrRegistrationBag->countWithoutLimit();

        $this->previousCategory = '';

        return $seminarOrRegistrationBag;
    }

    /**
     * Creates just the table for the list view (without any result browser or
     * search form).
     * This function should only be called when there are actually any list
     * items.
     *
     * @param RegistrationBag|EventBag $seminarOrRegistrationBag initialized bag
     * @param string $whatToDisplay a string selecting the flavor of list view: either an empty string
     *        (for the default list view), the value from "what_to_display" or "other_dates"
     *
     * @return string HTML for the table (will not be empty)
     */
    protected function createListTable(AbstractBag $seminarOrRegistrationBag, string $whatToDisplay): string
    {
        $result = $this->createListHeader();
        $rowCounter = 0;

        foreach ($seminarOrRegistrationBag as $currentItem) {
            if ($whatToDisplay === 'my_events') {
                /** @var LegacyRegistration $currentItem */
                $this->registration = $currentItem;
                $this->setSeminar($this->registration->getSeminarObject());
            } else {
                /** @var LegacyEvent $currentItem */
                $this->setSeminar($currentItem);
            }

            $result .= $this->createListRow($rowCounter, $whatToDisplay);
            $rowCounter++;
        }

        $result .= $this->createListFooter();

        return $result;
    }

    /**
     * Returns the list view header: Start of table, header row, start of table body.
     *
     * Columns listed in `$this->subpartsToHide` are hidden (i.e., not displayed).
     *
     * @return string HTML output: the table header
     */
    protected function createListHeader(): string
    {
        $availableColumns = [
            'image',
            'category',
            'title',
            'subtitle',
            'uid',
            'event_type',
            'accreditation_number',
            'credit_points',
            'speakers',
            'date',
            'time',
            'place',
            'city',
            'seats',
            'price_regular',
            'price_special',
            'total_price',
            'organizers',
            'target_groups',
            'attached_files',
            'vacancies',
            'status_registration',
            'registration',
            'list_registrations',
            'edit',
        ];

        foreach ($availableColumns as $column) {
            $this->setMarker('header_' . $column, $this->getFieldHeader($column));
        }

        $this->getListViewHookProvider()->executeHook('modifyListHeader', $this);

        return $this->getSubpart('LIST_HEADER');
    }

    /**
     * Returns the list view footer: end of table body, end of table.
     *
     * @return string HTML output: the table footer
     */
    protected function createListFooter(): string
    {
        $this->getListViewHookProvider()->executeHook('modifyListFooter', $this);

        return $this->getSubpart('LIST_FOOTER');
    }

    /**
     * Returns a list row as a TR. Gets data from $this->seminar.
     *
     * Columns listed in $this->subpartsToHide are hidden (ie. not displayed).
     * If $this->seminar is invalid, an empty string is returned.
     *
     * @param int $rowCounter Row counter. Starts at 0 (zero). Used for alternating class values in the output rows.
     * @param string $whatToDisplay a string selecting the flavor of list view: either an empty string
     *        (for the default list view), the value from "what_to_display", or "other_dates"
     *
     * @return string HTML output, a table row with a class attribute set (alternative based on odd/even rows)
     */
    protected function createListRow(int $rowCounter = 0, string $whatToDisplay = ''): string
    {
        $result = '';

        $eventUid = $this->seminar->getUid();
        if ($eventUid > 0) {
            $event = MapperRegistry::get(EventMapper::class)->find($eventUid);

            $cssClasses = [];

            $cssClasses[] = ($rowCounter % 2) ? 'listrow-odd' : 'listrow-even';
            if ($this->seminar->isCanceled()) {
                $cssClasses[] = $this->pi_getClassName('canceled');
            }
            if ($this->seminar->isOwnerFeUser()) {
                $cssClasses[] = $this->pi_getClassName('owner');
            }
            $completeClass = implode(' ', $cssClasses);

            $this->setMarker('class_itemrow', $completeClass);

            // Retrieves the data for the columns "number of seats", "total
            // price" and "status", but only if we are on the "my_events" list.
            if ($whatToDisplay === 'my_events') {
                $attendanceData = [
                    'seats' => $this->registration->getSeats(),
                    'total_price' => $this->registration->getTotalPrice(),
                ];
                $this->setMarker('status_registration', $this->registration->getStatus());
            } else {
                $attendanceData = ['seats' => '', 'total_price' => ''];
            }

            $image = $this->seminar->getImage();
            if ($image instanceof FileReference) {
                $imageConfiguration = [
                    'altText' => $this->seminar->getTitle(),
                    'titleText' => $this->seminar->getTitle(),
                    'file' => $image->getPublicUrl(),
                    'file.' => [
                        'width' => $this->getConfValueInteger('seminarImageListViewWidth') . 'c',
                        'height' => $this->getConfValueInteger('seminarImageListViewHeight') . 'c',
                    ],
                ];
                $image = $this->cObj->cObjGetSingle('IMAGE', $imageConfiguration);
            } else {
                $image = '';
            }
            $this->setMarker('image', $image);

            $listOfCategories = GeneralUtility::makeInstance(CategoryList::class, $this->conf, $this->cObj)
                ->createCategoryList($this->seminar->getCategories());

            if (
                $listOfCategories === $this->previousCategory
                && $this->getConfValueBoolean('sortListViewByCategory', 's_template_special')
            ) {
                $listOfCategories = '';
            } else {
                $this->previousCategory = $listOfCategories;
            }
            $this->setMarker('category', $listOfCategories);

            $this->setMarker('title_link', $this->createSingleViewLink($event, $this->seminar->getTitle()));
            $this->setMarker('subtitle', \htmlspecialchars($this->seminar->getSubtitle(), ENT_QUOTES | ENT_HTML5));
            $this->setMarker('uid', $this->seminar->getUid());
            $this->setMarker('event_type', \htmlspecialchars($this->seminar->getEventType(), ENT_QUOTES | ENT_HTML5));
            $this->setMarker(
                'accreditation_number',
                \htmlspecialchars($this->seminar->getAccreditationNumber(), ENT_QUOTES | ENT_HTML5),
            );
            $this->setMarker('credit_points', $this->seminar->getCreditPoints());
            $this->setMarker('teaser', $this->createSingleViewLink($event, $event->getTeaser()));
            $this->setMarker('speakers', $this->seminar->getSpeakersShort());

            if ($whatToDisplay === 'other_dates') {
                $dateToShow = $this->createSingleViewLink($event, $this->seminar->getDate(), false);
            } else {
                $dateToShow = $this->seminar->getDate();
            }
            $this->setMarker('date', $dateToShow);

            $this->setMarker('time', $this->seminar->getTime());
            $this->setMarker('expiry', $this->seminar->getExpiry());

            $this->setMarker('place', \htmlspecialchars($this->seminar->getPlaceShort(), ENT_QUOTES | ENT_HTML5));
            $this->setMarker('city', \htmlspecialchars($this->seminar->getCities(), ENT_QUOTES | ENT_HTML5));
            $this->setMarker('seats', $attendanceData['seats']);
            $this->setMarker('price_regular', $this->seminar->getCurrentPriceRegular());
            $this->setMarker('price_special', $this->seminar->getCurrentPriceSpecial());
            $this->setMarker('total_price', $attendanceData['total_price']);
            $this->setMarker('organizers', $this->seminar->getOrganizers($this));
            $this->setMarker(
                'target_groups',
                \htmlspecialchars($this->seminar->getTargetGroupNames(), ENT_QUOTES | ENT_HTML5),
            );
            $this->setMarker('attached_files', $this->getAttachedFilesListMarkerContent());
            $this->setMarker('vacancies', $this->seminar->getVacanciesString());
            $this->setMarker('class_listvacancies', $this->getVacanciesClasses($this->seminar));

            $this->setRegistrationLinkMarker($whatToDisplay);

            $this->setMarker('list_registrations', $this->getRegistrationsListLink());

            $this->getListViewHookProvider()->executeHook('modifyListRow', $this);

            if ($whatToDisplay === 'my_events') {
                $this->getListViewHookProvider()->executeHook('modifyMyEventsListRow', $this);
            }

            $result = $this->getSubpart('LIST_ITEM');
        }

        return $result;
    }

    /**
     * Returns a seminarBagBuilder object with the source pages set for the list view.
     */
    private function createSeminarBagBuilder(): EventBagBuilder
    {
        $seminarBagBuilder = GeneralUtility::makeInstance(EventBagBuilder::class);

        $recursive = \max(0, $this->getConfValueInteger('recursive'));
        $seminarBagBuilder->setSourcePages($this->getConfValueString('pidList'), $recursive);
        $seminarBagBuilder->setOrderBy($this->getOrderByForListView());

        return $seminarBagBuilder;
    }

    /**
     * Returns a registrationBagBuilder object limited for registrations of the
     * currently logged in front-end user as attendee for the "my events" list view.
     *
     * @return RegistrationBagBuilder the registrations for the "my events" list
     */
    private function createRegistrationBagBuilder(): RegistrationBagBuilder
    {
        $registrationBagBuilder = GeneralUtility::makeInstance(RegistrationBagBuilder::class);

        $userUid = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('frontend.user', 'id');
        $user = $userUid > 0 ? MapperRegistry::get(FrontEndUserMapper::class)->find($userUid) : null;
        $registrationBagBuilder->limitToAttendee($user);
        $registrationBagBuilder->setOrderByEventColumn($this->getOrderByForListView());

        return $registrationBagBuilder;
    }

    /**
     * Returns the ORDER BY statement for the list view.
     *
     * @return string the ORDER BY statement for the list view, may be empty
     */
    private function getOrderByForListView(): string
    {
        $orderBy = [];

        if ($this->getConfValueBoolean('sortListViewByCategory', 's_template_special')) {
            $orderBy[] = $this->orderByList['category'];
        }

        // Overwrites the default sort order with values given by the browser.
        // This happens if the user changes the sort order manually.
        if (!empty($this->piVars['sort'])) {
            [$this->internal['orderBy'], $this->internal['descFlag']] = \explode(':', $this->piVars['sort']);
        }

        if (isset($this->internal['orderBy'], $this->orderByList[$this->internal['orderBy']])) {
            $orderBy[] = $this->orderByList[$this->internal['orderBy']] . ($this->internal['descFlag'] ? ' DESC' : '');
        }

        return implode(', ', $orderBy);
    }

    /**
     * Gets the heading for a field type, automatically wrapped in a hyperlink
     * that sorts by that column if sorting by that column is available.
     *
     * @param string $fieldName key of the field type for which the heading should be retrieved, must not be empty
     *
     * @return string the heading label, may be completely wrapped in a hyperlink for sorting
     */
    public function getFieldHeader(string $fieldName): string
    {
        $label = $this->translate('label_' . $fieldName);
        if ($fieldName === 'price_regular' && $this->getConfValueBoolean('generalPriceInList', 's_template_special')) {
            $label = $this->translate('label_price_general');
        }

        // Can we sort by that field?
        if (isset($this->orderByList[$fieldName]) && $this->getConfValueBoolean('enableSortingLinksInListView')) {
            $result = $this->pi_linkTP_keepPIvars(
                $label,
                ['sort' => $fieldName . ':' . ($this->internal['descFlag'] ? 0 : 1)],
                false,
                true,
            );
        } else {
            $result = \htmlspecialchars($label, ENT_QUOTES | ENT_HTML5);
        }

        return $result;
    }

    /**
     * Returns the selector widget for the "seminars_list" view.
     *
     * @param string $whatToDisplay a string selecting the flavor of list view:
     *        either an empty string (for the default list view), the value from "what_to_display" or "other_dates"
     *
     * @return string the HTML code of the selector widget, may be empty
     */
    private function getSelectorWidgetIfNecessary(string $whatToDisplay): string
    {
        if ($whatToDisplay !== 'seminar_list') {
            return '';
        }

        $selectorWidget = GeneralUtility::makeInstance(
            SelectorWidget::class,
            $this->conf,
            $this->cObj,
        );

        return $selectorWidget->render();
    }

    /**
     * Limits the given bag builder for additional parameters needed to build the list view.
     */
    protected function limitForAdditionalParameters(EventBagBuilder $builder): void
    {
        // Adds the query parameter that result from the user selection in the
        // selector widget (including the search form).
        $placeUids = $this->getIntegerArrayFromRequest('place');
        if (\is_array($placeUids)) {
            $builder->limitToPlaces(SelectorWidget::removeDummyOptionFromFormData($placeUids));
        } else {
            // TODO: This needs to be changed as soon as we are using the new
            // TypoScript configuration class from tx_oelib which offers a getAsIntegerArray() method.
            $builder->limitToPlaces(
                GeneralUtility::intExplode(
                    ',',
                    $this->getConfValueString('limitListViewToPlaces', 's_listView'),
                    true,
                ),
            );
        }

        if (\is_array($this->piVars['city'] ?? null)) {
            $builder->limitToCities(
                SelectorWidget::removeDummyOptionFromFormData($this->piVars['city']),
            );
        }
        $organizerUids = $this->getIntegerArrayFromRequest('organizer');
        if (\is_array($organizerUids)) {
            $builder->limitToOrganizers(\implode(',', SelectorWidget::removeDummyOptionFromFormData($organizerUids)));
        } else {
            $builder->limitToOrganizers($this->getConfValueString('limitListViewToOrganizers', 's_listView'));
        }
        if (!empty($this->piVars['sword'])) {
            $builder->limitToFullTextSearch($this->piVars['sword']);
        }

        if ($this->getConfValueBoolean('hideCanceledEvents', 's_template_special')) {
            $builder->ignoreCanceledEvents();
        }

        $eventTypeUids = $this->getIntegerArrayFromRequest('event_type');
        if (\is_array($eventTypeUids)) {
            $builder->limitToEventTypes(SelectorWidget::removeDummyOptionFromFormData($eventTypeUids));
        } else {
            // TODO: This needs to be changed as soon as we are using the new
            // TypoScript configuration class from tx_oelib which offers a
            // getAsIntegerArray() method.
            $builder->limitToEventTypes(
                GeneralUtility::intExplode(
                    ',',
                    $this->getConfValueString('limitListViewToEventTypes', 's_listView'),
                    true,
                ),
            );
        }

        $categoryUid = (int)($this->piVars['category'] ?? 0);
        $categoryUids = (array)($this->piVars['categories'] ?? []);
        array_walk($categoryUids, '\\intval');
        if ($categoryUid > 0) {
            $categories = (string)$categoryUid;
        } elseif (empty($categoryUids)) {
            $categories = $this->getConfValueString('limitListViewToCategories', 's_listView');
        } else {
            $categories = implode(',', $categoryUids);
        }
        $builder->limitToCategories($categories);

        if ((int)($this->piVars['age'] ?? 0) > 0) {
            $builder->limitToAge((int)$this->piVars['age']);
        }

        if ((int)($this->piVars['price_from'] ?? 0) > 0) {
            $builder->limitToMinimumPrice((int)$this->piVars['price_from']);
        }
        if ((int)($this->piVars['price_to'] ?? 0) > 0) {
            $builder->limitToMaximumPrice((int)$this->piVars['price_to']);
        }

        $this->filterByDate($builder);
    }

    /**
     * @param non-empty-string $key
     *
     * @return int[]|null
     */
    private function getIntegerArrayFromRequest(string $key): ?array
    {
        $values = $this->piVars[$key] ?? null;
        if (!\is_array($values)) {
            return null;
        }

        \array_walk($values, '\\intval');

        return $values;
    }

    /**
     * Gets the CSS classes (space-separated) for the Vacancies TD.
     *
     * @param LegacyEvent $event the current seminar object
     *
     * @return string class attribute value filled with a list a space-separated CSS classes
     */
    public function getVacanciesClasses(LegacyEvent $event): string
    {
        if (!$event->needsRegistration()) {
            return '';
        }

        /** @var list<non-empty-string> $classes */
        $classes = [];

        if ($event->hasDate() && $event->hasStarted()) {
            $classes[] = 'event-begin-date-over';
        }

        if ($event->hasVacancies()) {
            $classes[] = 'vacancies-available';
            if ($event->hasUnlimitedVacancies()) {
                $classes[] = 'vacancies-unlimited';
            } else {
                $classes[] = 'vacancies-' . $event->getVacancies();
            }
        } else {
            $classes[] = 'vacancies-0';
            if ($event->hasRegistrationQueue()) {
                $classes[] = 'has-registration-queue';
            }
        }

        // We add this class in addition to the number of vacancies so that
        // user stylesheets still can use the number of vacancies even for
        // events for which the registration deadline is over.
        if ($event->hasDate() && $event->isRegistrationDeadlineOver()) {
            $classes[] = 'registration-deadline-over';
        }

        $prefixedClasses = array_map(
            [$this, 'pi_getClassName'],
            $classes,
        );

        return ' ' . implode(' ', $prefixedClasses);
    }

    /**
     * Hides the columns specified in the parameter.
     *
     * @param array<string|int, non-empty-string> $columnsToHide the columns to hide, may be empty
     */
    protected function hideColumns(array $columnsToHide): void
    {
        $this->hideSubpartsArray($columnsToHide, 'LISTHEADER_WRAPPER');
        $this->hideSubpartsArray($columnsToHide, 'LISTITEM_WRAPPER');
    }

    /**
     * Un-hides the columns specified in the parameter.
     *
     * @param array<non-empty-string> $columnsToUnhide the columns to un-hide, may be empty
     */
    protected function unhideColumns(array $columnsToUnhide): void
    {
        $permanentlyHiddenColumns = GeneralUtility::trimExplode(
            ',',
            $this->getConfValueString('hideColumns', 's_template_special'),
            true,
        );

        $this->unhideSubpartsArray($columnsToUnhide, $permanentlyHiddenColumns, 'LISTHEADER_WRAPPER');
        $this->unhideSubpartsArray($columnsToUnhide, $permanentlyHiddenColumns, 'LISTITEM_WRAPPER');
    }

    /**
     * Hides the column with the link to the list of registrations if online
     * registration is disabled, no user is logged in or there is no page
     * specified to link to.
     *
     * Also hides it for the "other_dates" and "events_next_day" lists.
     *
     * @param string $whatToDisplay the flavor of list view: either an empty string (for the default list view),
     *        the value from "what_to_display", or "other_dates"
     */
    public function hideListRegistrationsColumnIfNecessary(string $whatToDisplay): void
    {
        $alwaysHideInViews = ['topic_list', 'other_dates', 'events_next_day'];
        if (
            !$this->isRegistrationEnabled() || !$this->isLoggedIn()
            || \in_array($whatToDisplay, $alwaysHideInViews, true)
        ) {
            $this->hideColumns(['list_registrations']);
            return;
        }

        switch ($whatToDisplay) {
            case 'seminar_list':
                $hideIt = !$this->hasConfValueInteger('registrationsListPID')
                    && !$this->hasConfValueInteger('registrationsVipListPID');
                break;
            case 'my_events':
                $hideIt = !$this->hasConfValueInteger('registrationsListPID');
                break;
            case 'my_vip_events':
                $hideIt = !$this->hasConfValueInteger('registrationsVipListPID');
                break;
            default:
                $hideIt = false;
        }

        if ($hideIt) {
            $this->hideColumns(['list_registrations']);
        }
    }

    /**
     * Hides the registration column if online registration is disabled.
     *
     * @param string $whatToDisplay a string selecting the flavor of list view: either an empty string
     *        (for the default list view), the value from "what_to_display" or "other_dates"
     */
    private function hideRegisterColumnIfNecessary(string $whatToDisplay): void
    {
        if ($whatToDisplay === 'my_vip_events' || !$this->isRegistrationEnabled()) {
            $this->hideColumns(['registration']);
        }
    }

    /**
     * Hides columns which are not needed for the "topic_list" view.
     */
    private function hideColumnsForTheTopicListView(): void
    {
        $this->hideColumns(
            [
                'uid',
                'accreditation_number',
                'speakers',
                'date',
                'time',
                'place',
                'organizers',
                'vacancies',
                'registration',
            ],
        );
    }

    /**
     * Hides the number of seats, the total price and the registration status
     * columns when we're not on the "my_events" list view.
     *
     * @param string $whatToDisplay a string selecting the flavor of list view: either an empty string
     *        (for the default list view), the value from "what_to_display" or "other_dates"
     */
    private function hideColumnsForAllViewsExceptMyEvents(string $whatToDisplay): void
    {
        if ($whatToDisplay !== 'my_events') {
            $this->hideColumns(
                ['expiry', 'seats', 'total_price', 'status_registration'],
            );
        }
    }

    /**
     * Hides the columns which are listed in the TypoScript setup variable "hideColumns".
     */
    private function hideColumnsForAllViewsFromTypoScriptSetup(): void
    {
        $columns = GeneralUtility::trimExplode(
            ',',
            $this->getConfValueString('hideColumns', 's_template_special'),
            true,
        );
        $columns[] = 'country';
        $columns[] = 'language';
        $this->hideColumns($columns);
    }

    /**
     * Sets a heading for speakers, tutors, leaders or partners,
     * depending on the speakers, tutors, leaders or partners belonging to the current seminar.
     *
     * @param string $speakerType type of heading, must be 'speaker', 'tutors', 'leaders' or 'partners'
     */
    private function setHeading(string $speakerType): void
    {
        if (!\in_array($speakerType, ['speakers', 'partners', 'tutors', 'leaders'], true)) {
            throw new \InvalidArgumentException(
                'The given speaker type "' . $speakerType .
                '" is not an allowed type. Allowed types are "speakers", "partners", "tutors" or "leaders".',
                1333293103,
            );
        }

        $this->setMarker(
            'label_' . $speakerType,
            $this->translate(
                'label_' . $this->seminar->getLanguageKeySuffixForType($speakerType),
            ),
        );
    }

    /**
     * Returns the data for the organizers marker.
     *
     * @return string the organizers subpart with the data of the organizers,
     *                will be empty if the event has no organizers
     */
    private function getOrganizersMarkerContent(): string
    {
        if (!$this->seminar->hasOrganizers()) {
            return '';
        }

        $result = '';
        /** @var LegacyOrganizer $organizer */
        foreach ($this->seminar->getOrganizerBag() as $organizer) {
            $encodedName = \htmlspecialchars($organizer->getName(), ENT_QUOTES | ENT_HTML5);
            if ($organizer->hasHomepage()) {
                $organizerHtml = $this->cObj->getTypoLink($encodedName, $organizer->getHomepage());
            } else {
                $organizerHtml = $encodedName;
            }
            $this->setMarker('organizer_item_title', $organizerHtml);

            if ($organizer->hasDescription()) {
                $this->setMarker(
                    'organizer_description_content',
                    $this->renderAsRichText($organizer->getDescription()),
                );
                $description = $this->getSubpart('ORGANIZER_DESCRIPTION_ITEM');
            } else {
                $description = '';
            }
            $this->setMarker('organizer_item_description', $description);

            $result .= $this->getSubpart('ORGANIZER_LIST_ITEM');
        }

        return $result;
    }

    /**
     * Sets the marker for the registration link in the list view.
     *
     * @param string $whatToDisplay the list type which should be shown, must not be empty
     */
    private function setRegistrationLinkMarker(string $whatToDisplay): void
    {
        if ($whatToDisplay === 'my_events') {
            $this->setMarker(
                'registration',
                $this->seminar->isUnregistrationPossible()
                    ? $this->getRegistrationManager()->getLinkToUnregistrationPage($this, $this->registration) : '',
            );

            return;
        }

        $registrationLink = $this->getRegistrationManager()->getRegistrationLink($this, $this->seminar);

        if ($registrationLink === '' && !$this->getRegistrationManager()->registrationHasStarted($this->seminar)) {
            $registrationLink = sprintf(
                $this->translate('message_registrationOpensOn'),
                $this->seminar->getRegistrationBegin(),
            );
        }

        $this->setMarker('registration', $registrationLink);
    }

    /**
     * Filters the given seminar bag builder to the date set in piVars.
     *
     * @param EventBagBuilder $builder the bag builder to limit by date
     */
    private function filterByDate(EventBagBuilder $builder): void
    {
        $dateFrom = $this->getTimestampFromDatePiVars('from');
        if ($dateFrom > 0) {
            $builder->limitToEarliestBeginOrEndDate($dateFrom);
        }

        $dateTo = $this->getTimestampFromDatePiVars('to');
        if ($dateTo > 0) {
            $builder->limitToLatestBeginOrEndDate($dateTo);
        }
    }

    /**
     * Retrieves the date which was sent via piVars and returns it as timestamp.
     *
     * @param string $fromOrTo must be "from" or "to", depending on the date part which should be retrieved.
     *
     * @return int the timestamp for the date set in piVars, will be 0 if no date was set
     */
    private function getTimestampFromDatePiVars(string $fromOrTo): int
    {
        if (
            (int)($this->piVars[$fromOrTo . '_day'] ?? 0) === 0
            && (int)($this->piVars[$fromOrTo . '_month'] ?? 0) === 0
            && (int)($this->piVars[$fromOrTo . '_year'] ?? 0) === 0
        ) {
            return 0;
        }

        return ($fromOrTo === 'from') ? $this->getFromDate() : $this->getToDate();
    }

    /**
     * Gets the fromDate for the filtering of the list view, replacing empty
     * values with default values.
     *
     * Before this function is called, the piVars from_day, from_month and
     * from_year must be run through ensureIntegerPiVars.
     *
     * @return int the timestamp for the fromDate, will be > 0
     */
    private function getFromDate(): int
    {
        $day = (int)($this->piVars['from_day'] ?? 0) > 0 ? (int)($this->piVars['from_day'] ?? 0) : 1;
        $month = (int)($this->piVars['from_month'] ?? 0) > 0 ? (int)($this->piVars['from_month'] ?? 0) : 1;
        $year = (int)($this->piVars['from_year'] ?? 0) > 0
            ? (int)($this->piVars['from_year'] ?? 0)
            : (int)date(
                'Y',
                (int)GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp'),
            );

        return mktime(0, 0, 0, $month, $day, $year);
    }

    /**
     * Gets the toDate for the filtering of the list view, replacing empty
     * values with default values.
     *
     * Before this function is called, the piVars to_day, to_month and to_year
     * must be run through ensureIntegerPiVars.
     *
     * @return int the timestamp for the toDate, will be > 0
     */
    private function getToDate(): int
    {
        $longMonths = [1, 3, 5, 7, 8, 10, 12];

        $month = (int)($this->piVars['to_month'] ?? 0) > 0 ? (int)($this->piVars['to_month'] ?? 0) : 12;
        $year = (int)($this->piVars['to_year'] ?? 0) > 0
            ? (int)($this->piVars['to_year'] ?? 0)
            : (int)date(
                'Y',
                (int)GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp'),
            );

        $day = (int)($this->piVars['to_day'] ?? 0);

        if ($month === 2) {
            // the last day of february can be 29 or 28, depending on the year
            // so we use a behaviour of mktime which gives us the timestamp for
            // the last day of february when asking for the 0 day of march
            if (($day > 28) || ($day === 0)) {
                $day = 0;
                $month++;
            }
        } elseif (in_array($month, $longMonths, true)) {
            $day = ($day > 0) ? $day : 31;
        } else {
            $day = ($day > 0) ? $day : 30;
        }

        return mktime(23, 59, 59, $month, $day, $year);
    }

    /**
     * Hides the list view subparts for the attached files if the user is not
     * allowed to access the attached files.
     */
    private function hideFilesColumnIfUserCannotAccessFiles(): void
    {
        $limitToAttendees = $this->getConfValueBoolean('limitFileDownloadToAttendees');

        if ($limitToAttendees && !$this->isLoggedIn()) {
            $this->hideColumns(['attached_files']);
        }
    }

    /**
     * Creates the marker content for the "attached files" list item.
     *
     * @return string the marker content for the "attached files" list item, will
     *                be empty if the user does not have the permissions to
     *                download the files, or no user is logged in at the front
     *                end
     */
    private function getAttachedFilesListMarkerContent(): string
    {
        if (!$this->seminar->hasAttachedFiles()) {
            return '';
        }
        if (!$this->mayUserAccessAttachedFiles()) {
            return '';
        }

        $attachedFilesHtml = '';
        foreach ($this->seminar->getAttachedFiles() as $file) {
            $encodedUrl = \htmlspecialchars((string)$file->getPublicUrl(), ENT_QUOTES | ENT_HTML5);
            // @phpstan-ignore-next-line The string cast works around a bug in the Core that was fixed in V11.
            $encodedTitle = \htmlspecialchars((string)$file->getTitle(), ENT_QUOTES | ENT_HTML5);
            $encodedFileName = \htmlspecialchars(
                $file->getNameWithoutExtension() . '.' . $file->getExtension(),
                ENT_QUOTES | ENT_HTML5,
            );
            $link = '<a href="' . $encodedUrl . '" title="' . $encodedTitle . '">' . $encodedFileName . '</a>';
            $this->setMarker('attached_files_single_title', $link);

            $attachedFilesHtml .= $this->getSubpart('ATTACHED_FILES_SINGLE_ITEM');
        }

        $this->setMarker('attached_files_items', $attachedFilesHtml);

        return $attachedFilesHtml !== '' ? $this->getSubpart('ATTACHED_FILES_LIST_VIEW_ITEM') : '';
    }

    /**
     * Checks if the current user has permission to access the attached files of an event.
     */
    private function mayUserAccessAttachedFiles(): bool
    {
        $limitToAttendees = $this->getConfValueBoolean('limitFileDownloadToAttendees');

        return !$limitToAttendees
            || ($this->isLoggedIn() && $this->seminar->isUserRegistered($this->getLoggedInFrontEndUserUid()));
    }

    /**
     * Checks whether the currently logged-in user can display the current event.
     *
     * When this function is called, $this->seminar must contain a seminar, and
     * a user must be logged in at the front end.
     */
    private function canShowCurrentEvent(): bool
    {
        if (!$this->seminar->isHidden()) {
            return true;
        }
        if (!$this->seminar->hasOwner()) {
            return false;
        }

        return $this->seminar->getOwner()->getUid() === $this->getLoggedInFrontEndUserUid();
    }

    /**
     * Limits the bag to events within the time frame set by setup.
     */
    private function limitToTimeFrameSetting(EventBagBuilder $builder): void
    {
        try {
            // @phpstan-ignore-next-line We're allowing invalid values to be passed and rely on the exception for this.
            $builder->setTimeFrame($this->getConfValueString('timeframeInList', 's_template_special'));
        } catch (\Exception $exception) {
            // Ignores the exception because the user will be warned of the
            // problem by the configuration check.
        }
    }

    /**
     * Creates a hyperlink to the single view page of the given event.
     *
     * @param string $linkText the link text, must not be empty
     *
     * @return string HTML  for the link to the event's single view page
     */
    public function createSingleViewLink(
        Event $event,
        string $linkText,
        bool $htmlspecialcharLinkText = true
    ): string {
        $processedLinkText = $htmlspecialcharLinkText
            ? \htmlspecialchars($linkText, ENT_QUOTES | ENT_HTML5) : $linkText;
        $linkConditionConfiguration = $this->getConfValueString('linkToSingleView', 's_listView');
        $createLink = ($linkConditionConfiguration === 'always')
            || (($linkConditionConfiguration === 'onlyForNonEmptyDescription') && $event->hasDescription());
        if (!$createLink) {
            return $processedLinkText;
        }

        $url = $this->getLinkBuilder()->createRelativeUrlForEvent($event);
        return '<a href="' . \htmlspecialchars($url, ENT_QUOTES | ENT_HTML5) . '">' . $processedLinkText . '</a>';
    }

    protected function getLinkBuilder(): SingleViewLinkBuilder
    {
        if (!$this->linkBuilder instanceof SingleViewLinkBuilder) {
            $configuration = $this->getConfigurationWithFlexForms();
            $linkBuilder = GeneralUtility::makeInstance(SingleViewLinkBuilder::class, $configuration);
            $this->setLinkBuilder($linkBuilder);
        }

        return $this->linkBuilder;
    }

    public function setLinkBuilder(SingleViewLinkBuilder $linkBuilder): void
    {
        $this->linkBuilder = $linkBuilder;
    }

    protected function getConfigurationWithFlexForms(): Configuration
    {
        if ($this->configuration instanceof Configuration) {
            return $this->configuration;
        }

        $typoScriptConfiguration = ConfigurationRegistry::get('plugin.tx_seminars_pi1');
        if (!$this->cObj instanceof ContentObjectRenderer) {
            $this->configuration = $typoScriptConfiguration;
            return $typoScriptConfiguration;
        }

        $flexFormsConfiguration = new FlexformsConfiguration($this->cObj);
        $this->configuration = new FallbackConfiguration($flexFormsConfiguration, $typoScriptConfiguration);

        return $this->configuration;
    }

    protected function renderAsRichText(string $rawData): string
    {
        return GeneralUtility::makeInstance(RichTextViewHelper::class)->render($rawData);
    }
}
