.. include:: /Includes.rst.txt

==================
Extbase Controller
==================

Used Controller and their purposes
==================================

BackEnd
-------

+------------------------+-----------------------------------------------------+
| Controller             | Purpose                                             |
+========================+=====================================================+
| EmailController        |  For sending emails to the attendees of an event    |
+------------------------+-----------------------------------------------------+
| EventController        | Controller for the event list in the backend module |
|                        | (delete, hide something, etc.).                     |
+------------------------+-----------------------------------------------------+
| ModuleController       | Dashboard                                           |
+------------------------+-----------------------------------------------------+
| RegistrationController | For displaying events & CSV export                  |
+------------------------+-----------------------------------------------------+

Event (Frontend)
----------------

+----------------------------+--------------------------------------------------------------+
| Controller                 | Purpose                                                      |
+============================+==============================================================+
| EventController            | For showing events (upcoming, ongoing, past) in the frontend |
|                            | through a list & single view.                                |
+----------------------------+--------------------------------------------------------------+
| EventRegistrationController| For registering for events.                                  |
+----------------------------+--------------------------------------------------------------+
| FrontendEditorController   | For editing single events in the frontend.                   |
+----------------------------+--------------------------------------------------------------+
