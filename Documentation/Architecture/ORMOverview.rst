.. include:: /Includes.rst.txt

============
Overview over the used ORMs
============

Extbase
=======

Concept
-------

Extbase separates persistence, data access and domain logic:

+---------------+------------------------------------------------------+---------------------------+
|               | What it does                                         | Where to find             |
+===============+======================================+===============+===========================+
| TCA           | How Data is shown & stored in the Backend            | Configuration/TCA         |
+---------------+------------------------------------------------------+---------------------------+
| Model         | The business logic, for what happens with the data   | Classes/Domain/Model      |
+---------------+------------------------------------------------------+---------------------------+
| Repository    | Access to the Data between Database and Domain Model | Classes/Domain/Repository |
+---------------+------------------------------------------------------+---------------------------+

Legacy oelib
============

Concept
-------

oelib ORM separates Models (business logic) from Mappers (persistence) and Collections (groups of models).

+------------+-----------------------------------------------------------+--------------------------+
|            | What it does                                              | Where to find            |
+============+===========================================================+==========================+
| Model      | Here is the Business-Logic                                | Classes/Model            |
+------------+-----------------------------------------------------------+--------------------------+
| Mapper     | Gives you Database-Access to the Models (including find,  | Classes/Mapper           |
|            | save, update and delete)                                  |                          |
+------------+-----------------------------------------------------------+--------------------------+
| Collection | A container for Models (like the Bag in legacy-seminars). | Classes/Collection       |
|            | You can use iteration and filtering.                      |                          |
+------------+-----------------------------------------------------------+--------------------------+

Legacy seminars
===============

Concept
-------

This one is already an old grandfather -lived happily before Extbase- and shouldn`t be expanded.
The Legacy-ORM is not able to do Workspaces or Translations.

+----------------+----------------------------------------------------------+--------------------+
|                | What it does                                             | Where to find      |
+================+==========================================================+====================+
| OldModel       | Every file represents a single database record and some  | Classes/OldModel   |
|                | business logic (for example getters)                     |                    |
+----------------+----------------------------------------------------------+--------------------+
| Bag            | A collection, to get a "bag" of models out of the        | Classes/Bag        |
|                | database. For example, SpeakerBag gives you the objects  |                    |
|                | for the speakers of events.                              |                    |
+----------------+----------------------------------------------------------+--------------------+
| BagBuilder     | The BagBuilder makes Bags for particular single models.  | Classes/BagBuilder |
|                | For example, OrganizerBagBuilder can get all events out. |                    |
+----------------+----------------------------------------------------------+--------------------+
