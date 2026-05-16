=============================
Setting up the Scheduler task
=============================

..  warning::
    The CSV export currently will be empty if  the `b13/bolt` package is
    installed.

This extension offers a Scheduler Task to trigger actions. It can be configured to
send reminders to the events' organizers

- if a confirmed event is about to begin, or

- if the speakers' cancelation deadline of a neither confirmed nor
  canceled event has just passed.

The reminders are emails with a localized text and the list of
registrations appended as CSV.

To setup the CLI, do the following:

#. Set up the Scheduler as described in the manual of the Scheduler extension.

#. Choose/create a FE page where to do some TS setup configuration for
   the Scheduler task and configure the following:

- Set the option “ *sendCancelationDeadlineReminder* ” to 1 to enable
  the cancellation deadline reminder.

- For the option “ *sendEventTakesPlaceReminderDaysBeforeBeginDate* ”,
  set the number of days before an upcoming event, when to send a
  reminder to the organizers. Setting zero will disable this reminder
  about an event taking place.

#. Add a seminars Scheduler task and provide UID of the page with the configuration.

** Daily digest of new registrations **
"""""""""""""""""""""""""""""""""""""""

The Scheduler task also can send a (usually daily) digest of new registration.
This functionality can be enabled and configured via TypoScript setup in the
namespace plugin.tx\_seminars.registrationDigestEmail.

The emails will use the language that has been set as default language for the
Scheduler back-end user.
