# Silverstripe Document Converter

[![CI](https://github.com/silverstripe/silverstripe-documentconverter/actions/workflows/ci.yml/badge.svg)](https://github.com/silverstripe/silverstripe-documentconverter/actions/workflows/ci.yml)
[![Silverstripe supported module](https://img.shields.io/badge/silverstripe-supported-0071C4.svg)](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/)

## Overview

The module adds functionality to import OpenOffice-compatible files (doc, docx, etc) into Silverstripe pages and content.

## Requirements

 * Silverstripe CMS ^4

**Note:** For a Silverstripe 3.x compatible version, please use [the 1.x release line](https://github.com/silverstripe/silverstripe-documentconverter/tree/1.0).

## Installation

Install with [composer](https://getcomposer.org/) by running `composer require silverstripe/documentconverter` in the root of your Silverstripe project.

## Configuration

**Note:** Using of of docvert is primarily designed for Common Web Platform (CWP) clients

If you are using docver then you will need to set the following three environment variables:
- `DOCVERT_USERNAME`
- `DOCVERT_PASSWORD`
- `DOCVERT_URL`

If do not have the cwp/cwp-core module installed then enable document converter with the following configuration - note will be automatically applied if you also have the cwp/cwp-core module installed and the `DOCVERT_USERNAME` environment variable set.

```yaml
Page:
  extensions:
    - SilverStripe\DocumentConverter\PageExtension
```

By default this module will use docvert, though it's highly recommend you instead use the phpoffice/phpword module instead. Enable this with the following configuration:

```yaml
SilverStripe\DocumentConverter\ImportField:
  importer_class: SilverStripe\DocumentConverter\PHPWordImporter
```

docvert support is now deprecated and will be removed in the next major version

## User Guide

For usage instructions see the [User guide](docs/en/userguide/index.md).

### Translations

Translations of the natural language strings are managed through a third party translation interface, transifex.com. Newly added strings will be periodically uploaded there for translation, and any new translations will be merged back to the project source code.

Please use [https://www.transifex.com/projects/p/silverstripe-documentconverter](https://www.transifex.com/projects/p/silverstripe-documentconverter) to contribute translations, rather than sending pull requests with YAML files.
