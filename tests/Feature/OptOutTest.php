<?php

namespace Tests\Feature;

use App\Services\AIService;
use App\Services\OptOutDetector;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OptOutTest extends TestCase
{
    public function test_request_to_be_removed_from_the_list_is_an_opt_out(): void
    {
        $this->classifierAnswers('opt_out', times: 1);

        $this->assertTrue($this->detector()->isOptOut('Por favor me remove dessa lista'));
    }

    public function test_interest_is_not_an_opt_out(): void
    {
        $this->classifierAnswers(null, times: 0);

        $this->assertFalse($this->detector()->isOptOut('Parece interessante, me manda mais'));
    }

    #[DataProvider('bareCommands')]
    public function test_bare_command_needs_no_classifier(string $text): void
    {
        $this->classifierAnswers(null, times: 0);

        $this->assertTrue($this->detector()->isOptOut($text));
    }

    public static function bareCommands(): array
    {
        return [['Sair'], ['PARE!'], ['Não quero'], ['pode parar'], ['STOP']];
    }

    #[DataProvider('wordsContainingTerms')]
    public function test_terms_only_match_whole_words(string $text): void
    {
        $this->classifierAnswers(null, times: 0);

        $this->assertFalse($this->detector()->isOptOut($text));
    }

    public static function wordsContainingTerms(): array
    {
        return [['Vou preparar o orçamento e te falo'], ['Eu sairia mais cedo hoje'], ['Parece bom']];
    }

    public function test_classifier_decides_longer_messages(): void
    {
        $this->classifierAnswers('interest', times: 1);

        $this->assertFalse($this->detector()->isOptOut('Não quero perder essa promoção, me conta mais'));
    }

    public function test_opt_out_is_honoured_when_the_classifier_fails(): void
    {
        $this->classifierAnswers(null, times: 1);

        $this->assertTrue($this->detector()->isOptOut('Pare de me mandar mensagem por favor'));
    }

    private function classifierAnswers(?string $intent, int $times): void
    {
        $this->mock(AIService::class, fn (MockInterface $ai) => $ai
            ->shouldReceive('classificarRespostaProspeccao')
            ->times($times)
            ->andReturn($intent));
    }

    private function detector(): OptOutDetector
    {
        return app(OptOutDetector::class);
    }
}
