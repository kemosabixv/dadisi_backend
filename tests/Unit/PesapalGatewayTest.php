<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PaymentGateway\PesapalGateway;
use App\Services\PaymentGateway\PaymentGatewayInterface;

class PesapalGatewayTest extends TestCase
{
    public function test_pesapal_gateway_implements_interface()
    {
        $config = [
            'consumer_key' => 'test_key',
            'consumer_secret' => 'test_secret',
            'environment' => 'sandbox',
            'api_base' => 'https://cybqa.pesapal.com/pesapalv3/api',
        ];
        $gateway = new PesapalGateway($config);
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
    }

    public function test_charge_returns_not_implemented_when_unconfigured()
    {
        // Gateway with no config should return 'not_implemented'
        $gateway = new PesapalGateway([]);
        $res = $gateway->charge('254701234567', 1000, ['test' => true]);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('success', $res);
        $this->assertEquals(false, $res['success']);
        $this->assertEquals('not_implemented', $res['status']);
    }
}
