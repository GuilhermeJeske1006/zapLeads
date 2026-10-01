<?php

namespace Tests\Unit;

use App\Services\Prospecting\MessageParts;
use App\Services\Prospecting\OutreachMessageValidator;
use PHPUnit\Framework\TestCase;

class OutreachMessageValidatorTest extends TestCase
{
    private const SOURCES = '{"nome":"Studio Bella","nota_google":4.8,"avaliacoes":120,"cidade":"Blumenau","oferta_de_entrada":"Demo de 10 minutos","segmento":"CNPJ ATIVA"}';

    public function test_plan_example_passes_only_when_its_facts_are_in_the_data(): void
    {
        $message = 'Oi, Carla! Vi que o Studio Bella tem nota 4,8 no Google, mas duas avaliações recentes falam de dificuldade pra conseguir horário pelo WhatsApp. Seria absurdo eu te mostrar como outros salões aqui de Blumenau resolveram isso?';

        $this->assertSame([], OutreachMessageValidator::violations($message, self::SOURCES));
        $this->assertSame(['number:4.8'], OutreachMessageValidator::violations($message, '{"nome":"Studio Bella"}'));
    }

    public function test_flags_each_broken_rule(): void
    {
        $violations = OutreachMessageValidator::violations(
            'Olá, espero que esteja bem! Meu nome é Ana. PROMOÇÃO 😀😀 em www.agenda.com.br: 30% off pra fechar uma Parceria.',
            self::SOURCES,
        );

        $this->assertEqualsCanonicalizing([
            'phrase:espero que esteja bem', 'phrase:meu nome e', 'phrase:parceria',
            'caps', 'emojis', 'link', 'number:30', 'no_question',
        ], $violations);
    }

    public function test_long_messages_are_refused(): void
    {
        $this->assertContains('too_long', OutreachMessageValidator::violations(str_repeat('a', 291) . ' tudo bem?', ''));
    }

    public function test_one_emoji_and_names_in_capitals_from_the_data_are_fine(): void
    {
        $this->assertSame([], OutreachMessageValidator::violations('Vi que o CNPJ está ATIVA 🙂 Uma demo de 10 minutos faz sentido?', self::SOURCES));
    }

    public function test_goodbye_needs_no_question(): void
    {
        $this->assertSame([], OutreachMessageValidator::violations('Vou parar de te incomodar por aqui. Se um dia isso virar prioridade, é só me chamar.', '', needsQuestion: false));
    }

    public function test_splits_greeting_opening_and_question(): void
    {
        $this->assertSame(
            ['saudacao' => 'Oi, Carla!', 'abertura' => 'Vi que vocês têm nota 4,8.', 'pergunta' => 'Seria absurdo eu te mostrar como? Se não fizer sentido, me avisa.'],
            MessageParts::split("Oi, Carla! Vi que vocês têm nota 4,8.\nSeria absurdo eu te mostrar como? Se não fizer sentido, me avisa."),
        );
        $this->assertSame(
            ['saudacao' => 'Oi, tudo bem?', 'abertura' => '', 'pergunta' => 'É com você que falo sobre a agenda?'],
            MessageParts::split('Oi, tudo bem? É com você que falo sobre a agenda?'),
        );
        $this->assertSame(
            ['saudacao' => '', 'abertura' => 'Vou parar por aqui.', 'pergunta' => ''],
            MessageParts::split('Vou parar por aqui.'),
        );
        $this->assertSame('É com você que falo sobre a agenda?', MessageParts::withoutGreeting('Olá! É com você que falo sobre a agenda?'));
    }
}
