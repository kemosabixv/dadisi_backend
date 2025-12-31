<?php

namespace Tests\Unit\Models;

use App\Models\SystemSetting;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SystemSettingTest extends TestCase
{
    #[Test]
    public function it_casts_boolean_values_correctly()
    {
        $setting = new SystemSetting(['type' => 'boolean']);
        
        $setting->value = 'true';
        $this->assertTrue($setting->value);
        
        $setting->value = '1';
        $this->assertTrue($setting->value);

        $setting->value = 'false';
        $this->assertFalse($setting->value);
        
        $setting->value = '0';
        $this->assertFalse($setting->value);
    }

    #[Test]
    public function it_casts_integer_values_correctly()
    {
        $setting = new SystemSetting(['type' => 'integer']);
        $setting->value = '123';
        $this->assertIsInt($setting->value);
        $this->assertEquals(123, $setting->value);
    }

    #[Test]
    public function it_casts_float_values_correctly()
    {
        $setting = new SystemSetting(['type' => 'float']);
        $setting->value = '123.45';
        $this->assertIsFloat($setting->value);
        $this->assertEquals(123.45, $setting->value);
    }

    #[Test]
    public function it_casts_json_values_correctly()
    {
        $setting = new SystemSetting(['type' => 'json']);
        $data = ['foo' => 'bar'];
        
        $setting->value = $data; // Setter should json_encode
        $this->assertIsString($setting->getAttributes()['value']);
        
        $this->assertEquals($data, $setting->value); // Accessor should json_decode
    }
}
