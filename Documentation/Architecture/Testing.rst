.. include:: /Includes.rst.txt

=============
About testing
=============

Used testing frameworks
=======================

* `TYPO3 Testing Framework <https://github.com/TYPO3/testing-framework>`_
  Official testing framework for TYPO3 extensions.

* `oelib testing framework <https://github.com/oliverklee-de/oelib/blob/main/Classes/Testing/TestingFramework.php>`_
  Legacy testing framework, and should not be used for new code.

Projects own directories for tests
==================================

+-------------------+-----------------------------+--------------------------------------------+-------------------------------+
| Type              | Directory                   | Invoke all tests                           | PHPUnit configuration file    |
+===================+=============================+============================================+===============================+
| Functional        | ``Tests/Functional/``       | ``composer check:tests:functional``        | ``UnitTests.xml``             |
+-------------------+-----------------------------+--------------------------------------------+-------------------------------+
| Unit              | ``Tests/Unit/``             | ``composer check:tests:unit``              | ``FunctionalTests.xml``       |
+-------------------+-----------------------------+--------------------------------------------+-------------------------------+
| Legacy Functional | ``Tests/LegacyFunctional/`` | ``composer check:tests:legacy-functional`` | ``FunctionalTests.xml``       |
+-------------------+-----------------------------+--------------------------------------------+-------------------------------+
