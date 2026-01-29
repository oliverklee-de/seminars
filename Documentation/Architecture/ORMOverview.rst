.. include:: /Includes.rst.txt

===========================
Overview over the used ORMs
===========================

Extbase
=======

Concept
-------

Extbase separates persistence, data access and domain logic:

+---------------------+-------------------------------------------------------+
|                     | What it does                                          |
+=====================+======================================+================+
| ``Model``           | The business logic, for what happens with the data    |
+---------------------+-------------------------------------------------------+
| ``Repository``      | Access to the Data between database and domain model  |
+---------------------+-------------------------------------------------------+

Legacy oelib
============

Concept
-------

oelib ORM separates models (business logic) from mappers (persistence) and
collections (groups of models).

The legacy oelib ORM will be dropped soon and should not be used anymore.

+-------------------+-----------------------------------------------------------+
|                   | What it does                                              |
+===================+===========================================================+
| ``Model``         | Here is the business logic                                |
+-------------------+-----------------------------------------------------------+
| ``Mapper``        | Gives you Database-Access to the Models (including find,  |
|                   | save, update and delete)                                  |
+-------------------+-----------------------------------------------------------+
| ``Collection``    | A container for Models (like Extbase's ObjectStorage).    |
|                   | You can use iteration and filtering                       |
+-------------------+-----------------------------------------------------------+

Legacy seminars
===============

Concept
-------

This one is already an old grandfather - lived happily before Extbase - and shouldn`t be used anymore.
The legacy ORM is not able to do workspaces or translations.

The legacy seminars ORM will be dropped soon.

+---------------------+----------------------------------------------------------+
|                     | What it does                                             |
+=====================+==========================================================+
|  ``OldModel``       | Every file represents a single database record and some  |
|                     | business logic (for example getters)                     |
+---------------------+----------------------------------------------------------+
|  ``Bag``            | A collection, to get a "bag" of models out of the        |
|                     | database. For example, SpeakerBag gives you the objects  |
|                     | for the speakers of events                               |
+---------------------+----------------------------------------------------------+
|  ``BagBuilder``     | The BagBuilder makes Bags for particular single models.  |
|                     | For example, OrganizerBagBuilder can read organizers     |
+---------------------+----------------------------------------------------------+

Entities
========

**Hinweis**:

* Event is the main Object: represents one Seminar / Event
* EventTopic represents one subtopic / agenda item of the seminar. 3 are possible.
* EventDate represents one specific date (but can be for example different weekends).

+-------------------+---------------------+--------------------+-------------------------+--------------------------------+
| term              | Extbase model       | legacy oelib model | legacy seminars model   | TCA table                      |
+===================+=====================+====================+=========================+================================+
| event             | ``Event``           | ``Event``          | ``LegacyEvent``         | ``tx_seminars_seminars``       |
+-------------------+---------------------+--------------------+-------------------------+--------------------------------+
| category          | ``Category``        | ``Category``       | —                       | ``tx_seminars_categories``     |
+-------------------+---------------------+--------------------+-------------------------+--------------------------------+
| organizer         | ``Organizer``       | ``Organizer``      | ``LegacyOrganizer``     | ``tx_seminars_organizers``     |
+-------------------+---------------------+--------------------+-------------------------+--------------------------------+
| speaker           | ``Speaker``         | —                  | ``LegacySpeaker``       | ``tx_seminars_speakers``       |
+-------------------+---------------------+--------------------+-------------------------+--------------------------------+
| venue             | ``Venue``           | ``Place``          | —                       | ``tx_seminars_sites``          |
+-------------------+---------------------+--------------------+-------------------------+--------------------------------+
| payment method    | ``PaymentMethod``   | —                  | —                       | ``tx_seminars_payment_methods``|
+-------------------+---------------------+--------------------+-------------------------+--------------------------------+
| registration      | ``Registration``    | ``Registration``   | ``LegacyRegistration``  | ``tx_seminars_attendances``    |
+-------------------+---------------------+--------------------+-------------------------+--------------------------------+
| time slot         | —                   | —                  | ``LegacyTimeSlot``      | ``tx_seminars_timeslot``       |
+-------------------+---------------------+--------------------+-------------------------+--------------------------------+
