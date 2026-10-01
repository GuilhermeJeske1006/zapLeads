<?php

namespace Tests\Unit;

use App\Support\Cnpj;
use PHPUnit\Framework\TestCase;

class CnpjTest extends TestCase
{
    public function test_validates_check_digits(): void
    {
        $this->assertSame('11222333000181', Cnpj::normalize('11.222.333/0001-81'));
        $this->assertNull(Cnpj::normalize('11.222.333/0001-82'));
        $this->assertNull(Cnpj::normalize('11.111.111/1111-11'));
        $this->assertNull(Cnpj::normalize('1122233300018'));
    }

    public function test_finds_valid_numbers_in_text(): void
    {
        $text = 'Studio Bella LTDA — CNPJ 11.222.333/0001-81 · fone 4733221100 · antigo 11.222.333/0001-82 · 11222333000181';

        $this->assertSame(['11222333000181'], Cnpj::findAll($text));
    }
}
