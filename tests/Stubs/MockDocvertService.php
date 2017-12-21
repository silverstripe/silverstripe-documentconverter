<?php

namespace SilverStripe\DocumentConverter\Tests\Stubs;

use SilverStripe\Dev\TestOnly;
use SilverStripe\DocumentConverter\ServiceConnector;

class MockDocvertService extends ServiceConnector implements TestOnly
{
    public function import()
    {
        return '<h1>Fake document</h1><p>For testing purposes.</p>';
    }
}