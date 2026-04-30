Setup for the Seminar Manager front-end plug-in in plugin.tx\_seminars\_pi1
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

You can configure the plug-in using flexforms of the front-end plug-in
(for most values) or your TS template setup in the form
plugin.tx\_seminars\_pi1. *property = value.*

If your want to set a value for all instances of the plug-in in one
place, use the TS template setup. If you use flexforms, make sure to
set the values at all relevant instances of the plug-in: It doesn't do
to specify the fields for the online registration in the seminar list
front-end plug-in—you need to set these fields in the online
registration front-end plug-in.

**Note: If you set any non-empty value in the flexforms, this will
override the corresponding value from TS Setup.**

.. ### BEGIN~OF~TABLE ###

.. container:: table-row

   Property
         Property:

   Data type
         Data type:

   Description
         Description:

   Default
         Default:


.. container:: table-row

   Property
         enableRegistration

   Data type
         boolean

   Description
         Set this to 0 if you don't use the registration feature for this site.

   Default
         1


.. container:: table-row

   Property
         what\_to\_display

   Data type
         string

   Description
         The kind of front-end plug-in to display. Allowed values are in:
         *seminar\_list, single\_view, topic\_list,*  *my\_events,
         my\_vip\_events, seminar\_registration, list\_registrations,
         list\_vip\_registrations, edit\_event,
         category\_list, event\_headline* This must be set using flexforms.

   Default
         seminar\_list


.. container:: table-row

   Property
         templateFile

   Data type
         string

   Description
         location of the HTML template for the FE plugin

   Default
         EXT:seminars/pi1/seminars\_pi1.tmpl


.. container:: table-row

   Property
         salutation

   Data type
         string

   Description
         Switch whether to use formal/informal language on the front
         end.Allowed values are:formal \| informal

   Default
         formal


.. container:: table-row

   Property
         showSingleEvent

   Data type
         integer

   Description
         The UID of an event record. If an event is selected, the plug-inalways
         shows the single view of this event and not the list.

         This must be set using flexforms.

   Default


.. container:: table-row

   Property
         timeframeInList

   Data type
         string

   Description
         the time-frame from which events should be displayed in the list view.
         Select one of these keywords:all, past, pastAndCurrent, current,
         currentAndUpcoming, upcoming, deadlineNotOver, today

   Default
         currentAndUpcoming


.. container:: table-row

   Property
         hideColumns

   Data type
         string

   Description
         comma-separated list of column names that shouldn't be displayed in
         the list view, e.g.  *organizers,price\_special*

         The order of the elements in this list has no influence on the
         output.Allowed values are in: category, title,subtitle,uid,
         event\_type, language, accreditation\_number, credit\_points, teaser,
         speakers, date, time, expiry, place, city, seats,
         price\_regular, price\_special, total\_price, organizers,
         target\_groups, attached\_files, vacancies, status\_registration,
         registration, list\_registrations, status, edit, imagePlease note that
         some columns will only be shown if a front-end user currently is
         logged in.

   Default
         Image,category,subtitle,event\_type,language,accreditation\_number,cre
         dit\_points,teaser,time,expiry,place,price\_special,speakers,t
         arget\_groups,attached\_files


.. container:: table-row

   Property
         hideFields

   Data type
         string

   Description
         comma-separated list of field names that shouldn't be displayed in the
         detail view, e.g.  *organizers,price\_special*

         The order of the elements in this list has no influence on the
         output.Allowed values are in: event\_type, title, subtitle,
         description, accreditation\_number, credit\_points, category, date,
         uid, time, place, room, expiry, speakers, partners, tutors, leaders, p
         rice\_regular,price\_special,additional\_information,
         target\_groups, attached\_files,
         paymentmethods, target\_groups, organizers, vacancies,
         deadline\_registration, otherdates, eventsnextday, registration, back,
         image, requirements, dependencies

   Default
         credit\_points,eventsnextday


.. container:: table-row

   Property
         hideSearchForm

   Data type
         boolean

   Description
         whether to show the search form in the list view

   Default
         0


.. container:: table-row

   Property
         displaySearchFormFields

   Data type
         string

   Description
         comma-separated list of search options which should be shown in the
         search widget. If no field is displayed the search widget will be
         hidden. Allowed values are in: event\_type, city,
         place, full\_text\_search, date, age, organizer, price, categories

   Default


.. container:: table-row

   Property
         limitListViewToCategories

   Data type
         string

   Description
         comma-separated list of category UIDs to filter the list view for,
         leave empty to have no such filter

   Default


.. container:: table-row

   Property
         limitListViewToPlaces

   Data type
         string

   Description
         comma-separated list of place UIDs to filter the list view for, leave
         empty to have no such filter

   Default


.. container:: table-row

   Property
         limitListViewToOrganizers

   Data type
         string

   Description
         comma-separated list of organizer UIDs to filter the list view for,
         leave empty to have no such filter

   Default


.. container:: table-row

   Property
         showOnlyEventsWithVacancies

   Data type
         boolean

   Description
         whether to show only events with vacancies on in the list view

   Default
         0


.. container:: table-row

   Property
         seminarImageListViewHeight

   Data type
         integer

   Description
         the maximum height of the image of a seminar in the list view

   Default
         43


.. container:: table-row

   Property
         seminarImageListViewWidth

   Data type
         integer

   Description
         the maximum width of the image of a seminar in the list view

   Default
         70


.. container:: table-row

   Property
         hidePageBrowser

   Data type
         boolean

   Description
         whether to show the page browser in the list view

   Default
         0


.. container:: table-row

   Property
         hideCanceledEvents

   Data type
         boolean

   Description
         whether to show canceled events in the list view

   Default
         0


.. container:: table-row

   Property
         sortListViewByCategory

   Data type
         boolean

   Description
         whether the list view should always be sorted by category (before
         applying the normal sorting)

   Default
         0


.. container:: table-row

   Property
         generalPriceInList

   Data type
         boolean

   Description
         whether to use the label “Price” as column header for the standard
         price (instead of “Standard price”)

   Default
         0


.. container:: table-row

   Property
         generalPriceInSingle

   Data type
         boolean

   Description
         whether to use the label “Price” as heading for the standard price
         (instead of “Standard price”) in the detailed view and on the
         registration page

   Default
         0


.. container:: table-row

   Property
         accessToFrontEndRegistrationLists

   Data type
         string

   Description
         who is allowed to view the list of registrations on the front end;
         allowed values are: attendees\_and\_managers, login

   Default
         attendees\_and\_managers


.. container:: table-row

   Property
         showSpeakerDetails

   Data type
         boolean

   Description
         whether to show detailed information of the speakers in the single view;
         if disabled, only the names will be shown

   Default
         1


.. container:: table-row

   Property
         showSiteDetails

   Data type
         boolean

   Description
         whether to show detailed information of the locations in the single
         viewif disabled, only the name of the locations will be shown

   Default
         1


.. container:: table-row

   Property
         limitFileDownloadToAttendees

   Data type
         boolean

   Description
         whether file downloads are limited to attendees only

   Default
         1


.. container:: table-row

   Property
         showFeUserFieldsInRegistrationsList

   Data type
         string

   Description
         comma-separated list of FEuser fields to show in the list of
         registrations for an event

   Default
         name


.. container:: table-row

   Property
         showRegistrationFieldsInRegistrationList

   Data type
         string

   Description
         comma-separated list of registration fields to show in the list of
         registrations for an event

   Default
         None


.. container:: table-row

   Property
         enableSortingLinksInListView

   Data type
         boolean

   Description
        whether to add sorting links to the headers in the list view

   Default
         1


.. container:: table-row

   Property
         linkToSingleView

   Data type
         string

   Description
        when to link to the single view: always, never, onlyForNonEmptyDescription

   Default
         always


.. container:: table-row

   Property
         speakerImageWidth

   Data type
         integer

   Description
         width of the speaker image in the event single view

   Default
         150


.. container:: table-row

   Property
         speakerImageHeight

   Data type
         integer

   Description
         height of the speaker image in the event single view

   Default
         150


.. container:: table-row

   Property
         pages

   Data type
         integer

   Description
         PID of the sysfolder that contains all the event records (e.g. the
         starting point)

   Default
         None


.. container:: table-row

   Property
         recursive

   Data type
         integer

   Description
         level of recursion that should be used when accessing the
         startingpoint

   Default
         None


.. container:: table-row

   Property
         listPID

   Data type
         page\_id

   Description
         PID of the FE page that contains the event list

   Default
         None


.. container:: table-row

   Property
         detailPID

   Data type
         page\_id

   Description
         PID of the FE page that contains the single view

   Default
         None


.. container:: table-row

   Property
         myEventsPID

   Data type
         page\_id

   Description
         PID of the FE page that contains the "my events" list

   Default
         None


.. container:: table-row

   Property
         registerPID

   Data type
         page\_id

   Description
         PID of the FE page that contains the seminar registration plug-in

   Default
         None


.. container:: table-row

   Property
         loginPID

   Data type
         page\_id

   Description
         PID of the FE page that contains the login form or onetimeaccount

   Default
         None


.. container:: table-row

   Property
         registrationsListPID

   Data type
         page\_id

   Description
         PID of the page that contains the registrations list for participants

   Default
         None


.. container:: table-row

   Property
         registrationsVipListPID

   Data type
         page\_id

   Description
         PID of the page that contains the registrations list for editors

   Default
         None


.. container:: table-row

   Property
         defaultEventVipsFeGroupID

   Data type
         integer

   Description
         UID of the FE user group that is allowed to see the registrations of
         all events

   Default
         None


.. container:: table-row

   Property
         seminarImageSingleViewWidth

   Data type
         integer

   Description
         the maximum width of the image of a seminar in the single view

   Default
         260


.. container:: table-row

   Property
         seminarImageSingleViewHeight

   Data type
         integer

   Description
         the maximum height of the image of a seminar in the single view

   Default
         160


.. ###### END~OF~TABLE ######

[tsref:plugin.tx\_seminars\_pi1]
