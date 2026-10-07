<?php

namespace App\Tests\Unit;

use App\Twig\AppExtension;
use PHPUnit\Framework\TestCase;

class AppExtensionTest extends TestCase
{
    public function testEuros(): void
    {
        $this->assertSame("4,50\u{00A0}€", AppExtension::euros(4.5));
        $this->assertSame("1\u{202F}234,00\u{00A0}€", AppExtension::euros('1234'));
        $this->assertSame("0,00\u{00A0}€", AppExtension::euros(null));
    }

    public function testKg(): void
    {
        $this->assertSame("0,75\u{00A0}kg", AppExtension::kg(0.75));
        $this->assertSame("29,5\u{00A0}kg", AppExtension::kg(29.5));
        $this->assertSame("1\u{00A0}kg", AppExtension::kg(1));
        $this->assertSame("0\u{00A0}kg", AppExtension::kg(0));
        $this->assertSame("0\u{00A0}kg", AppExtension::kg(-0.001));
    }

    public function testCouleurHex(): void
    {
        $this->assertSame('#28a745', AppExtension::couleurHex('green'));
        $this->assertSame('#fd7e14', AppExtension::couleurHex('inconnue'));
        $this->assertSame('#fd7e14', AppExtension::couleurHex(null));
    }
}
