<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NormalizePhonesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_fills_canonical_phones_and_merges_split_conversations(): void
    {
        $empresa = $this->empresa();
        $other = $this->empresa();

        // Legacy rows: same contact saved by an outbound send (with 9) and an inbound webhook (without 9).
        $outbound = $this->conversation($empresa, '5547992801006', ['last_message' => 'Oi!', 'last_message_at' => '2026-09-01 10:00:00']);
        $inbound = $this->conversation($empresa, '554792801006', [
            'last_message' => 'Pode me mandar?', 'last_message_at' => '2026-09-02 10:00:00', 'unread_count' => 2, 'nome_contato' => 'Carla',
        ]);
        $otherEmpresa = $this->conversation($other, '554792801006');
        $this->message($outbound, 'user');
        $this->message($inbound, 'lead');
        $this->message($inbound, 'lead');

        $leadId = DB::table('leads')->insertGetId([
            'empresa_id' => $empresa->id, 'nome' => 'Studio Bella', 'telefone' => '+55 47 99280-1006',
        ]);

        $this->artisan('leads:normalize-phones')->assertSuccessful();

        $this->assertDatabaseMissing('conversations', ['id' => $inbound]);
        $this->assertDatabaseHas('conversations', [
            'id'              => $outbound,
            'telefone_e164'   => '+5547992801006',
            'last_message'    => 'Pode me mandar?',
            'unread_count'    => 2,
            'nome_contato'    => 'Carla',
        ]);
        $this->assertSame(3, DB::table('messages')->where('conversation_id', $outbound)->count());
        $this->assertDatabaseHas('conversations', ['id' => $otherEmpresa, 'telefone_e164' => '+5547992801006']);
        $this->assertDatabaseHas('leads', ['id' => $leadId, 'telefone_e164' => '+5547992801006']);
    }

    public function test_can_run_again(): void
    {
        $empresa = $this->empresa();
        $this->conversation($empresa, '5547992801006');

        $this->artisan('leads:normalize-phones')->assertSuccessful();
        $this->artisan('leads:normalize-phones')->assertSuccessful();

        $this->assertDatabaseHas('conversations', ['empresa_id' => $empresa->id, 'telefone_e164' => '+5547992801006']);
    }

    private function conversation(Empresa $empresa, string $telefone, array $attributes = []): int
    {
        return DB::table('conversations')->insertGetId([
            'empresa_id' => $empresa->id,
            'telefone'   => $telefone,
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);
    }

    private function message(int $conversationId, string $sender): void
    {
        DB::table('messages')->insert([
            'conversation_id' => $conversationId,
            'sender'          => $sender,
            'message'         => 'texto',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    private function empresa(): Empresa
    {
        return Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Empresa']);
    }
}
