<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Domain\ValueObjects\Money;

class MoneyTest extends TestCase
{
    public function testMoneyCreation()
    {
        $money = new Money(10.50, 'USD');
        $this->assertEquals(10.50, $money->getAmount());
        $this->assertEquals('USD', $money->getCurrency());
    }

    public function testMoneyAddition()
    {
        $money1 = new Money(10.00, 'USD');
        $money2 = new Money(5.50, 'USD');
        $result = $money1->add($money2);
        
        $this->assertEquals(15.50, $result->getAmount());
    }

    public function testMoneySubtraction()
    {
        $money1 = new Money(10.00, 'USD');
        $money2 = new Money(3.50, 'USD');
        $result = $money1->subtract($money2);
        
        $this->assertEquals(6.50, $result->getAmount());
    }

    public function testMoneyMultiplication()
    {
        $money = new Money(10.00, 'USD');
        $result = $money->multiply(2.5);
        
        $this->assertEquals(25.00, $result->getAmount());
    }

    public function testMoneyComparison()
    {
        $money1 = new Money(10.00, 'USD');
        $money2 = new Money(5.00, 'USD');
        
        $this->assertTrue($money1->isGreaterThan($money2));
        $this->assertFalse($money1->isLessThan($money2));
    }

    public function testMoneyEquality()
    {
        $money1 = new Money(10.00, 'USD');
        $money2 = new Money(10.00, 'USD');
        
        $this->assertTrue($money1->equals($money2));
    }

    public function testDifferentCurrenciesThrowException()
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $money1 = new Money(10.00, 'USD');
        $money2 = new Money(5.00, 'EUR');
        $money1->add($money2);
    }

    public function testMoneyFormatting()
    {
        $money = new Money(1234.56, 'USD');
        $this->assertEquals('1234.56', $money->format());
    }

    public function testMoneyRounding()
    {
        $money = new Money(10.999, 'USD');
        $this->assertEquals(11.00, $money->getAmount());
    }
}
