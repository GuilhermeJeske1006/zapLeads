<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Lead;
use App\Models\OutreachAttempt;
use App\Models\User;
use App\Services\AIService;
use App\Services\Prospecting\AngleExperiment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Lottery;
use Tests\TestCase;

class AngleExperimentTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Agenda Fácil']);
        $this->lead = Lead::create(['empresa_id' => $this->empresa->id, 'nome' => 'Studio Bella', 'telefone' => '+55 47 99999-8888']);
    }

    protected function tearDown(): void
    {
        Lottery::determineResultsNormally();

        parent::tearDown();
    }

    public function test_suggests_the_angle_sent_least_until_every_angle_has_enough_sends(): void
    {
        $experiment = app(AngleExperiment::class);

        $this->assertSame('observacao', $experiment->suggest($this->empresa->id, AIService::ANGULOS, hasHook: true));
        $this->assertSame('dor_do_segmento', $experiment->suggest($this->empresa->id, AIService::ANGULOS, hasHook: false));

        $this->attempts('observacao', 1);
        $this->attempts('dor_do_segmento', 1);
        $this->assertSame('roteamento', $experiment->suggest($this->empresa->id, AIService::ANGULOS, hasHook: true));

        // A clear leader is not suggested before the others reach the minimum.
        $this->attempts('observacao', 40, replies: 30);
        $this->attempts('dor_do_segmento', 40, replies: 2);
        $this->attempts('roteamento', AngleExperiment::MIN_SENDS - 1);
        $this->assertNull($experiment->winner($experiment->stats($this->empresa->id)));
        $this->assertSame('roteamento', $experiment->suggest($this->empresa->id, AIService::ANGULOS, hasHook: true));
    }

    public function test_after_the_minimum_the_best_reply_rate_wins_and_the_rest_still_get_explored(): void
    {
        $this->attempts('observacao', 30, replies: 3);
        $this->attempts('dor_do_segmento', 40, replies: 10);
        $this->attempts('roteamento', 30, replies: 6);
        // Replies to a follow-up don't count for the first message's angle.
        $this->attempts('roteamento', 30, replies: 30, etapa: 1);
        $experiment = app(AngleExperiment::class);

        $this->assertSame('dor_do_segmento', $experiment->winner($experiment->stats($this->empresa->id)));
        $this->assertSame(['envios' => 30, 'respostas' => 6, 'taxa' => 0.2], $experiment->stats($this->empresa->id)['roteamento']);

        Lottery::alwaysLose();
        $this->assertSame('dor_do_segmento', $experiment->suggest($this->empresa->id, AIService::ANGULOS, hasHook: true));

        // Exploration: the other angle sent least.
        Lottery::alwaysWin();
        $this->assertSame('observacao', $experiment->suggest($this->empresa->id, AIService::ANGULOS, hasHook: true));
        // Without a hook "observacao" is out: the only other angle goes.
        $this->assertSame('roteamento', $experiment->suggest($this->empresa->id, AIService::ANGULOS, hasHook: false));
    }

    public function test_only_the_angles_that_passed_review_compete(): void
    {
        $this->attempts('observacao', 30, replies: 20);
        $this->attempts('dor_do_segmento', 30, replies: 5);
        $this->attempts('roteamento', 30, replies: 1);
        Lottery::alwaysLose();

        $this->assertSame('dor_do_segmento', app(AngleExperiment::class)->suggest($this->empresa->id, ['dor_do_segmento', 'roteamento'], hasHook: true));
        $this->assertSame('roteamento', app(AngleExperiment::class)->suggest($this->empresa->id, ['roteamento'], hasHook: false));
    }

    public function test_each_empresa_runs_its_own_test(): void
    {
        $other = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Outra']);
        $lead = Lead::create(['empresa_id' => $other->id, 'nome' => 'X', 'telefone' => '+55 47 99999-0000']);
        foreach (range(1, 5) as $i) {
            OutreachAttempt::create(['empresa_id' => $other->id, 'lead_id' => $lead->id, 'canal' => 'api', 'mensagem' => 'Oi?', 'variante' => 'observacao', 'etapa' => 0]);
        }

        $this->assertSame(0, app(AngleExperiment::class)->stats($this->empresa->id)['observacao']['envios']);
        $this->assertSame('observacao', app(AngleExperiment::class)->suggest($this->empresa->id, AIService::ANGULOS, hasHook: true));
    }

    private function attempts(string $angle, int $count, int $replies = 0, int $etapa = 0): void
    {
        foreach (range(1, $count) as $i) {
            OutreachAttempt::create([
                'empresa_id'   => $this->empresa->id,
                'lead_id'      => $this->lead->id,
                'canal'        => 'assisted',
                'mensagem'     => 'Oi?',
                'variante'     => $angle,
                'etapa'        => $etapa,
                'responded_at' => $i <= $replies ? now() : null,
            ]);
        }
    }
}
