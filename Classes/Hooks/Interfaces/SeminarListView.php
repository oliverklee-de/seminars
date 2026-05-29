<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Hooks\Interfaces;

use OliverKlee\Seminars\BagBuilder\EventBagBuilder;
use OliverKlee\Seminars\BagBuilder\RegistrationBagBuilder;
use OliverKlee\Seminars\FrontEnd\DefaultController;

/**
 * Use this interface for hooks concerning the seminar list views.
 *
 * It supersedes the deprecated `EventListView` interface.
 */
interface SeminarListView extends Hook
{
    /**
     * Modifies the list view seminar bag builder (the item collection for a seminar list).
     *
     * Add or alter limitations for the selection of seminars to be shown in the list.
     *
     * This function will be called for these types of seminar lists: "topics", "seminars",
     * "events next day", "other dates".
     *
     * @param DefaultController $controller the calling controller
     * @param string $whatToDisplay the flavor of list view: 'seminar_list', 'topic_list',
     *        'events_next_day' or 'other_dates'
     *
     * @see AbstractBagBuilder::getWhereClausePart()
     * @see AbstractBagBuilder::setWhereClausePart()
     */
    public function modifyEventBagBuilder(
        DefaultController $controller,
        EventBagBuilder $builder,
        string $whatToDisplay
    ): void;

    /**
     * Modifies the list view registration bag builder (the item collection for a "my events" list).
     *
     * Add or alter limitations for the selection of seminars to be shown in the
     * list.
     *
     * This function will be called for "my events" lists only.
     *
     * @param DefaultController $controller the calling controller
     * @param string $whatToDisplay the flavor of list view ('my_events' only?)
     *
     * @see AbstractBagBuilder::getWhereClausePart()
     * @see AbstractBagBuilder::setWhereClausePart()
     */
    public function modifyRegistrationBagBuilder(
        DefaultController $controller,
        RegistrationBagBuilder $builder,
        string $whatToDisplay
    ): void;

    /**
     * Modifies the list view header row in a seminar list.
     *
     * This function will be called for all types of seminar lists ("topics",
     * "seminars", "my events",
     * "events next day", "other dates").
     *
     * @param DefaultController $controller the calling controller
     */
    public function modifyListHeader(DefaultController $controller): void;

    /**
     * Modifies a list row in a seminar list.
     *
     * This function will be called for all types of seminar lists ("topics",
     * "seminars", "my events",
     * "events next day", "other dates").
     *
     * @param DefaultController $controller the calling controller
     */
    public function modifyListRow(DefaultController $controller): void;

    /**
     * Modifies a list view row in a "my seminars" list.
     *
     * This function will be called for "my events" lists only.
     *
     * @param DefaultController $controller the calling controller
     */
    public function modifyMyEventsListRow(DefaultController $controller): void;

    /**
     * Modifies the list view footer in a seminars list.
     *
     * This function will be called for all types of seminar lists ("topics",
     * "seminars", "my events",
     * "events next day", "other dates").
     *
     * @param DefaultController $controller the calling controller
     */
    public function modifyListFooter(DefaultController $controller): void;
}
