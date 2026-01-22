.. include:: /Includes.rst.txt

=========================
Packages and dependencies
=========================

Packages
========

seminars
--------

This is the main extension to which this manual belongs.

`View on GitHub <https://github.com/oliverklee-de/seminars>`__

seminars-premium
----------------

Provides advanced features for the seminars extension. It allows for
automatic generation of seminar-related documents and sending
documents automatically via email. These features streamline seminar
management and reduce manual work.

This extension is paid add-on.

Dependencies
============

oelib
-----

Provides general library functions for TYPO3 extensions like helper
functions for unit testing, templating and automatic configuration
checks.

`View on GitHub <https://github.com/oliverklee-de/oelib>`__

feuserextrafields
-----------------

Adds extra fields to frontend users, like:

- full salutation, e.g., "Hello Mr. Klee" (to work around the problem of trying to automatically generate gender-specific salutations)
- gender (with the mappings from sr_feuser_register)
- date_of_birth
- zone (state/province)
- privacy (privacy agreement accepted)
- status (job status)
- comments

`View on GitHub <https://github.com/oliverklee-de/feuserextrafields>`__

Emogrifier
----------

Converts HTML/CSS into inline styles for email templates, in other
words: It allows prettier e-Mails.

`View on GitHub <https://github.com/MyIntervals/emogrifier>`__

PHP-CSS-Parser
--------------

Parses CSS files in PHP. Allows extraction of CSS files into a data
structure, manipulation of said structure and output as (optimized)
CSS.

`View on GitHub <https://github.com/sabberworm/PHP-CSS-Parser>`__

static-info-tables
------------------

Adds static info tables like languages, currencies and countries.

`View Extension on TYPO3.org <https://extensions.typo3.org/extension/static_info_tables>`__
