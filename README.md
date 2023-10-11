# Silverstripe Document Converter

[![CI](https://github.com/silverstripe/silverstripe-documentconverter/actions/workflows/ci.yml/badge.svg)](https://github.com/silverstripe/silverstripe-documentconverter/actions/workflows/ci.yml)
[![Silverstripe supported module](https://img.shields.io/badge/silverstripe-supported-0071C4.svg)](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/)

## Overview

The module adds functionality to import .docx files into Silverstripe pages and content.

## Installation

```sh
composer require silverstripe/documentconverter
```

## Configuration

### PHPOffice/PHPWord

By default this project will use the [PHPOffice/PHPWord](https://github.com/PHPOffice/PHPWord) library to convert uploaded word documents.

### docvert

**Note:** Using of of [docvert](https://github.com/holloway/docvert) to convert uploaded word documents was primarily designed for Common Web Platform (CWP) clients. It is no longer recommended to use docvert.

docvert support is deprecated and will be removed in the next major version

If you wish to use docvert instead of PHPOffice/PHPWord, then add the following configuration to your project:

```yaml
SilverStripe\DocumentConverter\ImportField:
  importer_class: SilverStripe\DocumentConverter\ServiceConnector
```

If you are using docver then you will need to set the following three environment variables:
- `DOCVERT_USERNAME`
- `DOCVERT_PASSWORD`
- `DOCVERT_URL`

If do not have the cwp/cwp-core module installed then enable docvert with the following configuration - note will be automatically applied if you also have the cwp/cwp-core module installed and the `DOCVERT_USERNAME` environment variable set.

```yaml
Page:
  extensions:
    - SilverStripe\DocumentConverter\PageExtension
```

## User Guide

For usage instructions see the [User guide](docs/en/userguide/index.md).

### Translations

Translations of the natural language strings are managed through a third party translation interface, transifex.com. Newly added strings will be periodically uploaded there for translation, and any new translations will be merged back to the project source code.

Please use [https://www.transifex.com/projects/p/silverstripe-documentconverter](https://www.transifex.com/projects/p/silverstripe-documentconverter) to contribute translations, rather than sending pull requests with YAML files.
