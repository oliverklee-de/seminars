# TYPO3 extension `seminars`

[![TYPO3 V11](https://img.shields.io/badge/TYPO3-11-orange.svg)](https://get.typo3.org/version/11)
[![License](https://img.shields.io/github/license/oliverklee-de/seminars)](https://packagist.org/packages/oliverklee/seminars)
[![GitHub CI Status](https://github.com/oliverklee-de/seminars/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/oliverklee-de/seminars/actions)
[![Coverage Status](https://coveralls.io/repos/github/oliverklee-de/seminars/badge.svg?branch=main)](https://coveralls.io/github/oliverklee-de/seminars?branch=main)

This TYPO3 extension allows you to create and manage a list of seminars,
workshops, lectures, theater performances and other events, allowing front-end
users to sign up. FE users also can create and edit events.

Most of the documentation is in ReST format
[in the Documentation/ folder](Documentation/) and is rendered
[as part of the TYPO3 documentation](https://docs.typo3.org/typo3cms/extensions/seminars/).

## Compatibility with TYPO3 12LTS/12.4

A TYPO3-12LTS-compatible version of this extension is available via an
[early-acces program](https://github.com/oliverklee-de/seminars/wiki/Early-access-program-for-newer-TYPO3-versions).

## Give it a try!

If you would like to test the extension yourself, there is a
[DDEV-based TYPO3 distribution](https://github.com/oliverklee-de/TYPO3-testing-distribution)
with this extension installed and some test records ready to go.

## Staying informed about the extension

If you would like to stay informed about this extension (including compatibility
with newer TYPO3 versions), you can subscribe to the
[author's newsletter](https://www.oliverklee.de/newsletter/).

## Running the tests locally

You will need to have a Git clone of the extension for this
with the Composer dependencies installed.

### Running the unit tests

#### On the command line

To run all unit tests on the command line:

```bash
composer check:tests:unit
```

To run all unit tests in a directory or file (using the directory
`Tests/Unit/Model/` as an example):

```bash
.Build/vendor/bin/phpunit -c Build/phpunit/UnitTests.xml Tests/Unit/Model/
```

#### In PhpStorm

First, you need to configure the path to PHPUnit in the settings:

Languages & Frameworks > PHP > Test Frameworks

In this section, configure PhpStorm to use the Composer autoload and
the script path `.Build/vendor/autoload.php` within your project.

In the Run/Debug configurations for PHPUnit, use an alternative configuration
file:

`Build/phpunit/UnitTests.xml`
