<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    #[DataProvider('numbers')]
    public function test_normalizes_to_e164(string $raw, string $region, ?string $expected): void
    {
        $this->assertSame($expected, Phone::normalize($raw, $region));
    }

    public static function numbers(): array
    {
        return [
            'BR landline without country code'     => ['4733221100', 'BR', '+554733221100'],
            'BR mobile without country code'       => ['47991234567', 'BR', '+5547991234567'],
            'DDD 54 is not Argentina'              => ['54991234567', 'BR', '+5554991234567'],
            'DDD 55 is not the country code'       => ['55991234567', 'BR', '+5555991234567'],
            'Google formatted mobile'              => ['+55 47 99691-8841', 'BR', '+5547996918841'],
            'Google formatted landline'            => ['+55 47 3044-1996', 'BR', '+554730441996'],
            'national format with trunk prefix'    => ['(047) 3322-1100', 'BR', '+554733221100'],
            'digits with country code, no plus'    => ['5547992801006', 'BR', '+5547992801006'],
            'WhatsApp id without ninth digit'      => ['whatsapp:+554792801006', 'BR', '+5547992801006'],
            'old digits without ninth digit'       => ['554792801006', 'BR', '+5547992801006'],
            'valid 8-digit mobile keeps its form'  => ['+55 11 7012-3456', 'BR', '+551170123456'],
            'Argentina mobile preserved'           => ['+5491123456789', 'BR', '+5491123456789'],
            'Argentina national for AR empresa'    => ['011 2345-6789', 'AR', '+541123456789'],
            'US number with plus'                  => ['+14155238886', 'BR', '+14155238886'],
            'US number saved without plus'         => ['14155238886', 'BR', '+14155238886'],
            'too short'                            => ['123', 'BR', null],
            'no digits'                            => ['—', 'BR', null],
        ];
    }

    public function test_canonical_keeps_unvalidated_international_number(): void
    {
        $this->assertSame('+999123', Phone::canonical('whatsapp:+999123'));
        $this->assertNull(Phone::canonical('999123'));
        $this->assertSame('+5547992801006', Phone::canonical('554792801006'));
    }

    public function test_line_type(): void
    {
        $this->assertSame('mobile', Phone::lineType('+5547991234567'));
        $this->assertSame('fixed', Phone::lineType('+554733221100'));
        $this->assertSame('fixed_or_mobile', Phone::lineType('+14155238886'));
        $this->assertSame('unknown', Phone::lineType('+55123'));

        $this->assertTrue(Phone::isLikelyWhatsApp('+5547991234567'));
        $this->assertFalse(Phone::isLikelyWhatsApp('+554733221100'));
    }

    public function test_wa_me_link(): void
    {
        $this->assertSame('https://wa.me/5547996918841', Phone::waMeLink('+55 47 99691-8841'));
        $this->assertSame('https://wa.me/554733221100', Phone::waMeLink('4733221100'));
        $this->assertNull(Phone::waMeLink(''));
        $this->assertSame(
            'https://wa.me/5547996918841?text=Oi%2C%20tudo%20bem%3F%0AVoc%C3%AAs%20atendem%20s%C3%A1bado%3F',
            Phone::waMeLink('(47) 99691-8841', 'BR', "Oi, tudo bem?\nVocês atendem sábado?"),
        );
    }
}
