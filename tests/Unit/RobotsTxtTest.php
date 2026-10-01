<?php

namespace Tests\Unit;

use App\Support\Net\RobotsTxt;
use App\Support\Net\SafeHttp;
use PHPUnit\Framework\TestCase;

class RobotsTxtTest extends TestCase
{
    public function test_longest_rule_for_any_agent_decides(): void
    {
        $robots = RobotsTxt::parse("User-agent: *\nDisallow: /admin\nDisallow: /contato\nAllow: /contato/fale\n", 'ZapLeadsBot');

        $this->assertTrue($robots->allows('/'));
        $this->assertFalse($robots->allows('/admin/login'));
        $this->assertFalse($robots->allows('/contato'));
        $this->assertTrue($robots->allows('/contato/fale-conosco'));
    }

    public function test_own_group_wins_over_any_agent(): void
    {
        $robots = RobotsTxt::parse("User-agent: *\nDisallow:\n\nUser-agent: ZapLeadsBot\nDisallow: /\n", 'ZapLeadsBot');

        $this->assertFalse($robots->allows('/'));
        $this->assertTrue(RobotsTxt::parse("User-agent: *\nDisallow:\n", 'ZapLeadsBot')->allows('/sobre'));
        $this->assertTrue(RobotsTxt::parse('', 'ZapLeadsBot')->allows('/'));
    }

    public function test_resolves_redirect_locations(): void
    {
        $this->assertSame('https://b.com/x', SafeHttp::absolute('https://a.com/p/q', 'https://b.com/x'));
        $this->assertSame('https://b.com/x', SafeHttp::absolute('https://a.com/p/q', '//b.com/x'));
        $this->assertSame('https://a.com/x', SafeHttp::absolute('https://a.com/p/q', '/x'));
        $this->assertSame('https://a.com/p/x', SafeHttp::absolute('https://a.com/p/q', 'x'));
    }
}
