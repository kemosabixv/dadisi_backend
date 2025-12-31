<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

class QuickTest extends BaseTestCase
{
    public function test_simple_math()
    {
        $this->assertEquals(2, 1 + 1);
    }
}
