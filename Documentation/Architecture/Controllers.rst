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
