# Silverstripe Document Converter

[![CI](https://github.com/silverstripe/silverstripe-documentconverter/actions/workflows/ci.yml/badge.svg)](https://github.com/silverstripe/silverstripe-documentconverter/actions/workflows/ci.yml)
[![Silverstripe supported module](https://img.shields.io/badge/silverstripe-supported-0071C4.svg)](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/)

## Overview

The module adds functionality to import OpenOffice-compatible files (doc, docx, etc) into Silverstripe pages and content.

## Installation

```sh
composer require silverstripe/documentconverter
```

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
