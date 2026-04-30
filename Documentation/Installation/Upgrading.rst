==================================
Upgrading from seminars 5.x to 6.x
==================================

The following steps are necessary to upgrade from seminars 5.x to 6.x:

#.  Upgrade to the latest version of seminars 5.x.
#.  Upgrade to the latest versions of feuserextrafields and oelib.
#.  Update the database schema and run all upgrade wizards.
#.  If you are using onetimaccount, switch the plugin to
    "One-time FE account creator without autologin".
#.  Upgrade to the latest version of onetimeaccount.
#.  If you are using the `allowRegistrationForStartedEvents` option, set a
    corresponding registration deadline for all future events for which you
    want to allow registration after the event has started. (This option
    has been removed in seminars 6.0.)
#.  Go through the venue records and make sure that the address field contains
    the full address (including ZIP code, city and country (if relevant)).
#.  If you have any events for which no registration should not be possible and
    which have no date yet, make sure that these events have the "needs
    registration" checkbox set to "no". (seminars 6.0 by default allows
    registration for all events that have no date yet as well.)
#.  If you have been using the `unregistrationDeadlineDaysBeforeBeginDate`
    configuration option, set a corresponding unregistration deadline for all
    future events. (This option has been removed in seminars 6.0.)
#.  If you do not want attendees to be able to cancel their registration
    themselves, set the unregistration deadline to a date in the past for all
    future events. (By default, attendees will be able to cancel their
    registration themselves until the event starts.)
#.  If you are using custom Fluid templates for the registration form, remove
    the `separateBillingAddress` field from all registration form templates.
#.  Upgrade to seminars 6.x.
#.  Update the database schema and run all upgrade wizards.
#.  If you are using the selector widget (the search form) in the frontend,
    open the corresponding content element in the backend and save it again.
