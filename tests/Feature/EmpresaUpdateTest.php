<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The catalog URL (/loja/{slug}) must not change unless the user edits the slug. */
class EmpresaUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['onboarding_completed_at' => now()]);
        Empresa::create([
            'user_id' => $this->user->id, 'nome' => 'Agenda Fácil', 'slug' => 'agenda-facil',
            'whatsapp' => '+55 47 99999-8888', 'endereco' => 'Rua XV, 100', 'cidade' => 'Blumenau',
        ]);
    }

    public function test_saving_the_ai_persona_keeps_the_slug(): void
    {
        $this->actingAs($this->user)->put(route('empresa.update'), [
            'whatsapp' => '+55 47 99999-8888', 'endereco' => 'Rua XV, 100', 'cidade' => 'Blumenau',
            'ai_persona' => 'Você é a Ana.',
        ])->assertRedirect(route('empresa.edit'))->assertSessionHasNoErrors();

        $empresa = $this->user->empresa()->first();
        $this->assertSame(['agenda-facil', 'Agenda Fácil', 'Você é a Ana.'], [$empresa->slug, $empresa->nome, $empresa->ai_persona]);
    }

    public function test_main_form_can_be_saved_with_its_own_slug(): void
    {
        $this->actingAs($this->user)->put(route('empresa.update'), [
            'nome' => 'Agenda Fácil Pro', 'slug' => 'agenda-facil',
            'whatsapp' => '+55 47 99999-8888', 'endereco' => 'Rua XV, 100', 'cidade' => 'Blumenau',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Agenda Fácil Pro', $this->user->empresa()->first()->nome);
    }

    public function test_slug_of_another_empresa_is_refused(): void
    {
        Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Outra', 'slug' => 'outra']);

        $this->actingAs($this->user)->put(route('empresa.update'), [
            'slug' => 'outra', 'whatsapp' => '+55 47 99999-8888', 'endereco' => 'Rua XV, 100', 'cidade' => 'Blumenau',
        ])->assertSessionHasErrors('slug');
    }
}
