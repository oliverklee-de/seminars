# Seminars TYPO3 extension

[![TYPO3 V10](https://img.shields.io/badge/TYPO3-10-orange.svg)](https://get.typo3.org/version/10)
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
composer ci:tests:unit
```

To run all unit tests in a directory or file (using the directory
`Tests/Unit/Model/` as an example):

```bash
.Build/vendor/bin/phpunit -c Configuration/UnitTests.xml Tests/Unit/Model/
```

#### In PhpStorm

First, you need to configure the path to PHPUnit in the settings:

Languages & Frameworks > PHP > Test Frameworks

In this section, configure PhpStorm to use the Composer autoload and
the script path `.Build/vendor/autoload.php` within your project.

In the Run/Debug configurations for PHPUnit, use an alternative configuration
file:

`Configuration/UnitTests.xml`

### Running the functional tests

You will need a local MySQL user that has the permissions to create new
databases.

In the examples, the following credentials are used:

- user name: `typo3`
- password: `typo3pass`
- DB name prefix: `typo3_test` (optional)
- DB host: `localhost` (omitted as this is the default)

You will need to provide those credentials as environment variables when
running the functional tests:

- `typo3DatabaseUsername`
- `typo3DatabasePassword`
- `typo3DatabaseName`

#### On the command line

To run all functional tests on the command line:

```bash
typo3DatabaseUsername=typo3 typo3DatabasePassword=typo3pass typo3DatabaseName=typo3_test composer ci:tests:functional
```

To run all functional tests in a directory or file (using the directory
`Tests/Functional/Authentication/` as an example):

```bash
typo3DatabaseUsername=typo3 typo3DatabasePassword=typo3pass typo3DatabaseName=typo3_test .Build/vendor/bin/phpunit -c Configuration/FunctionalTests.xml Tests/Functional/Authentication/
```

#### In PhpStorm

First, you need to configure the path to PHPUnit in the settings:

Languages & Frameworks > PHP > Test Frameworks

In this section, configure PhpStorm to use the Composer autoload and
the script path `.Build/vendor/autoload.php` within your project.

In the Run/Debug configurations for PHPUnit, use an alternative configuration
file:

`Configuration/FunctionalTests.xml`

Also set the following environment variables in your runner configuration:

- `typo3DatabaseUsername`
- `typo3DatabasePassword`
- `typo3DatabaseName`

### Running the legacy functional tests

Running the legacy tests works exactly the same as running the functional tests,
except that you run the tests in `Tests/LegacyFunctional/` instead
of `Tests/Functional/`. You'll still need to use the configuration file
`Configuration/FunctionalTests.xml`, though.
