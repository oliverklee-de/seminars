.. include:: /Includes.rst.txt

=============
About testing
=============

Used testing frameworks
=======================

* `TYPO3 Testing Distribution <https://github.com/oliverklee-de/TYPO3-testing-distribution>`_
  Distribution for testing TYPO3 extensions from Oli.

* `TYPO3 Testing Framework <https://github.com/TYPO3/testing-framework>`_
  Official testing framework for TYPO3 extensions.

Projects own directories for tests
==================================

+------------+-------------------+---------------------------------+
| Type       | Directory         | Invoke all tests                |
+============+===================+=================================+
| Functional | Tests/Functional  | composer check:tests:functional |
+------------+-------------------+---------------------------------+
| Unit       | Tests/Unit        | composer check:tests:unit       |
+------------+-------------------+---------------------------------+
