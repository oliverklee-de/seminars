:navigation-title: ORMs

.. include:: /Includes.rst.txt

===========================
Overview over the used ORMs
===========================

The seminars extension makes use of three different object-relational mapping
frameworks (ORMs) for historical reasons.

Extbase
=======

Concept
-------

Extbase separates persistence, data access and domain logic:

+---------------------+-------------------------------------------------------+
|                     | What it does                                          |
+=====================+=======================================================+
| ``Model``           | The business logic, for what happens with the data    |
+---------------------+-------------------------------------------------------+
| ``Repository``      | Persisting the models to the database                 |
+---------------------+-------------------------------------------------------+

seminars will keep using Extbase for the foreseeable future.

Legacy oelib ORM
================

Concept
-------

oelib ORM separates models (business logic) from mappers (persistence) and
collections (groups of models).

The legacy oelib ORM is not able to do workspaces or translations.

It will be dropped soon and should not be used anymore.

+-------------------+------------------------------------------------------------+
|                   | What it does                                               |
+===================+============================================================+
| ``Model``         | Here is the business logic                                 |
+-------------------+------------------------------------------------------------+
| ``Mapper``        | Gives you database access to the models (including find,   |
|                   | save, update and delete)                                   |
+-------------------+------------------------------------------------------------+
| ``Collection``    | A container for models (like Extbase's ``ObjectStorage``). |
|                   | You can use iteration and filtering                        |
+-------------------+------------------------------------------------------------+

Legacy seminars ORM
===================

Concept
-------

This one is already an old grandfather - lived happily before Extbase - and
shouldn`t be used anymore.

The legacy seminars ORM is not able to do workspaces or translations.

It will be dropped soon and should not be used anymore.

+--------------------+----------------------------------------------------------+
|                    | What it does                                             |
+====================+==========================================================+
| ``OldModel``       | Every file represents a single database record and some  |
|                    | business logic (for example getters)                     |
+--------------------+----------------------------------------------------------+
| ``Bag``            | A collection, to get a "bag" of models out of the        |
|                    | database. For example, ``SpeakerBag`` can hold speakers. |
+--------------------+----------------------------------------------------------+
| ``BagBuilder``     | A ``BagBuilder`` builds ``Bag``s for particular models.  |
|                    |                                                          |
|                    | For example, ``OrganizerBagBuilder`` can read organizers |
+--------------------+----------------------------------------------------------+
