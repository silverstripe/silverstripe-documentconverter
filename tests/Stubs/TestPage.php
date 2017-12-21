<?php

namespace SilverStripe\DocumentConverter\Tests\Stubs;

use SilverStripe\Dev\TestOnly;
use SilverStripe\DocumentConverter\PageExtension;
use Page;

class TestPage extends Page implements TestOnly
{
    private static $extensions = [PageExtension::class];
    private static $defaults = [
        'Content' => '<h1>Default TestPage</h1><p>With pre-import content.</p>'
    ];
}
