# SilverStripe Document Converter

[![Build Status](http://img.shields.io/travis/silverstripe/silverstripe-documentconverter.svg?style=flat)](https://travis-ci.org/silverstripe/silverstripe-documentconverter)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/silverstripe/silverstripe-documentconverter/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/silverstripe/silverstripe-documentconverter/?branch=master)
[![codecov](https://codecov.io/gh/silverstripe/silverstripe-documentconverter/branch/master/graph/badge.svg)](https://codecov.io/gh/silverstripe/silverstripe-documentconverter)
[![SilverStripe supported module](https://img.shields.io/badge/silverstripe-supported-0071C4.svg)](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/)

## Overview

The module adds functionality to import OpenOffice-compatible files (doc, docx, etc) into SilverStripe pages and content.

## Requirements

 * SilverStripe CMS ^4

**Note:** For a SilverStripe 3.x compatible version, please use [the 1.x release line](https://github.com/silverstripe/silverstripe-documentconverter/tree/1.0).

## Installation

Install with [composer](https://getcomposer.org/) by running `composer require silverstripe/documentconverter` in the root of your SilverStripe project.

## Configuration

You will need to set the following three environment variables:
- `DOCVERT_USERNAME`
- `DOCVERT_PASSWORD`
- `DOCVERT_URL`

**Note:** This module is primarily designed for Common Web Platform (CWP) clients. There will be additional setup required to use this module as intended, if you are not using the CWP government edition.

## User Guide

For usage instructions see the [User guide](docs/en/userguide/index.md).

### Translations

Translations of the natural language strings are managed through a third party translation interface, transifex.com. Newly added strings will be periodically uploaded there for translation, and any new translations will be merged back to the project source code.

Please use [https://www.transifex.com/projects/p/silverstripe-documentconverter](https://www.transifex.com/projects/p/silverstripe-documentconverter) to contribute translations, rather than sending pull requests with YAML files.
