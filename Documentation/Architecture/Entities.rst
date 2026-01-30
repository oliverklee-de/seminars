:navigation-title: Entities

.. include:: /Includes.rst.txt

===========================
Entites (for all used ORMs)
===========================

..  note::

    There are 3 distinct subclasses of the abstract ``Event`` class. They are
    implemented using single-table inheritance (STI) on the ``object_type``
    field. This is a convenience structure that helps you avoid having to enter
    the same information over and over.

+--------------+--------------------------------------------------------+
| term         | description                                            |
+==============+========================================================+
| single event | an event that takes place only once                    |
+--------------+--------------------------------------------------------+
| event topic  | topic for an event that might be held multiple times,  |
|              | always repeated with the same contents                 |
+--------------+--------------------------------------------------------+
| event date   | a specific time and place when an event for a specific |
|              | topic takes place                                      |
+--------------+--------------------------------------------------------+

For example, you might want to hold the same workshops on TYPO3 extension
development multiple times. Then you'd create an **event topic**  with title,
description etc., and then create multiple **event dates** that reference that
topic. The event dates that would contain the specific dates and times, venues,
speakers etc.

People then would register for the individual **event dates**.

+-----------------------+---------------------+--------------------+-------------------------+---------------------------------+
| term                  | Extbase model       | legacy oelib model | legacy seminars model   | database table                  |
+=======================+=====================+====================+=========================+=================================+
| category              | ``Category``        | ``Category``       | `LegacyCategory`        | ``tx_seminars_categories``      |
+-----------------------+---------------------+--------------------+-------------------------+---------------------------------+
| event                 | ``Event``           | ``Event``          | ``LegacyEvent``         | ``tx_seminars_seminars``        |
+-----------------------+---------------------+--------------------+-------------------------+---------------------------------+
| event type            | ``EventType``       | ``EventType``      | —                       | ``tx_seminars_event_types``     |
+-----------------------+---------------------+--------------------+-------------------------+---------------------------------+
| food option           | ``FoodOption``      | —                  | —                       | ``tx_seminars_foods``           |
+-----------------------+---------------------+--------------------+-------------------------+---------------------------------+
| organizer             | ``Organizer``       | ``Organizer``      | ``LegacyOrganizer``     | ``tx_seminars_organizers``      |
+-----------------------+---------------------+--------------------+-------------------------+---------------------------------+
| payment method        | ``PaymentMethod``   | —                  | —                       | ``tx_seminars_payment_methods`` |
+-----------------------+---------------------+--------------------+-------------------------+---------------------------------+
| registration          | ``Registration``    | ``Registration``   | ``LegacyRegistration``  | ``tx_seminars_attendances``     |
+-----------------------+---------------------+--------------------+-------------------------+---------------------------------+
| registration checkbox | ``Registration``    | —                  | —                       | ``tx_seminars_checkboxes``      |
+-----------------------+---------------------+--------------------+-------------------------+---------------------------------+
| speaker               | ``Speaker``         | —                  | ``LegacySpeaker``       | ``tx_seminars_speakers``        |
+-----------------------+---------------------+--------------------+-------------------------+---------------------------------+
| time slot             | —                   | —                  | ``LegacyTimeSlot``      | ``tx_seminars_timeslot``        |
+-----------------------+---------------------+--------------------+-------------------------+---------------------------------+
| venue                 | ``Venue``           | ``Place``          | —                       | ``tx_seminars_sites``           |
+-----------------------+---------------------+--------------------+-------------------------+---------------------------------+
