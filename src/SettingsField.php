<?php

namespace SilverStripe\DocumentConverter;

use InvalidArgumentException;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;

class SettingsField extends CompositeField
{
    /**
     * Reference to the inner upload field (ImportField).
     */
    private $innerField = null;

    /**
     * Augments a simple CompositeField with uploader and import options.
     *
     * @param $children FieldSet/array Any additional children.
     */
    public function __construct($children = null)
    {
        if ($children) {
            $class = get_class();
            throw new InvalidArgumentException(
                "{$class}::__construct does not accept extra parameters."
            );
        }

        $fields = FieldList::create([
            LiteralField::create(
                'FileWarningHeader',
                '<div class="alert alert-warning">' . _t(
                    __CLASS__ . '.FileWarningHeader',
                    'Warning: import will remove all content and subpages of this page.'
                ) . '</div>',
                4
            ),
            $this->innerField = ImportField::create(
                'ImportedFromFile',
                _t(__CLASS__ . '.ImportedFromFile', 'Import content from a word document')
            ),
            LiteralField::create(
                'DoNotSaveWarning',
                '<div class="alert alert-warning">' . _t(
                    __CLASS__ . '.ExtraStuff',
                    'Note: Only .docx files are supported.'
                    . '<br><br>Warning: Page content will be updated as soon as you select a file.'
                    . '<br><br>Do not click the Save or Publish buttons as this will revert the uploaded content.'
                    . '<br><br>Refresh to page to view the uploaded content.'
                ) . '</div>'
            ),
        ]);

        return parent::__construct($fields);
    }

    public function getInnerField()
    {
        return $this->innerField;
    }
}
