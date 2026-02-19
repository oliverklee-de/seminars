.. include:: /Includes.rst.txt

===========
Controllers
===========

Extbase Controllers
===================

Backend controllers
-------------------

+----------------------------+-----------------------------------------------------+
| ``Controller``             | Purpose                                             |
+============================+=====================================================+
| ``EmailController``        |  For sending emails to the attendees of an event    |
+----------------------------+-----------------------------------------------------+
| ``EventController``        | Controller for the event list in the backend module |
|                            | (delete, hide something, etc.).                     |
+----------------------------+-----------------------------------------------------+
| ``ModuleController``       | Dashboard                                           |
+----------------------------+-----------------------------------------------------+
| ``RegistrationController`` | For displaying events & CSV export                  |
+----------------------------+-----------------------------------------------------+

Frontend controllers
--------------------

+-----------------------------------+---------------------------------------------------------------+
| Controller                        | Purpose                                                       |
+===================================+===============================================================+
| ``EventController``               | For showing events (upcoming & ongoing, past) in the frontend |
|                                   | through a list & single view.                                 |
+-----------------------------------+---------------------------------------------------------------+
| ``EventRegistrationController``   | For registering for events                                    |
+-----------------------------------+---------------------------------------------------------------+
| ``EventUnregistrationController`` | For unregistering for events                                  |
+-----------------------------------+---------------------------------------------------------------+
| ``FrontendEditorController``      | For editing single events or event dates in the frontend      |
+-----------------------------------+---------------------------------------------------------------+
| ``MyRegistrationsController``     | For showing registrations of the currently logged-in FE user  |
+-----------------------------------+---------------------------------------------------------------+


Legacy ``DefaultController``
----------------------------

In ``FrontEnd/``, there are additional classes that are used by the ``DefaultController``.

+----------------------------+------------------------------------------------------------------+
| View (``whatToDisplay``)   | Description                                                      |
+============================+==================================================================+
| ``single_view``            | Single view for one event.                                       |
+----------------------------+------------------------------------------------------------------+
| ``seminar_list``           | List of all seminars (you don`t have to be logged-in).           |
+----------------------------+------------------------------------------------------------------+
| ``my_events``              | Shows the events the currently logged-in user is registered for. |
+----------------------------+------------------------------------------------------------------+
| ``my_vip_events``          | Shows events the logged-in user has management permissions for.  |
+----------------------------+------------------------------------------------------------------+
| ``list_registrations``     | List of all registrations for a specific event.                  |
|                            | Intended for normal frontend users viewing event registrations.  |
+----------------------------+------------------------------------------------------------------+
| ``list_vip_registrations`` | List of all registrations for a specific event.                  |
|                            | Intended for users with special management permissions.          |
+----------------------------+------------------------------------------------------------------+
| ``category_list``          | List of event categories.                                        |
+----------------------------+------------------------------------------------------------------+
| ``topic_list``             | List of event topics.                                            |
+----------------------------+------------------------------------------------------------------+
