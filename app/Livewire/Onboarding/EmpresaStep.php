<?php

namespace App\Livewire\Onboarding;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.onboarding-layout', ['step' => 1])]
class EmpresaStep extends Component
{
    public string $nome = '';
    public string $whatsapp = '';
    public string $cidade = '';
    public string $endereco = '';

    public function mount(): void
    {
        $empresa = auth()->user()->empresa;
        if ($empresa) {
            $this->nome = $empresa->nome ?? '';
            $this->whatsapp = $empresa->whatsapp ?? '';
            $this->cidade = $empresa->cidade ?? '';
            $this->endereco = $empresa->endereco ?? '';
        }
    }

    public function salvar(): void
    {
        $this->validate([
            'nome' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:30'],
            'cidade' => ['nullable', 'string', 'max:100'],
            'endereco' => ['nullable', 'string', 'max:255'],
        ]);

        $user = auth()->user();
        $empresa = $user->empresa()->firstOrCreate([]);
        $empresa->update([
            'nome' => $this->nome,
            'whatsapp' => $this->whatsapp,
            'cidade' => $this->cidade,
            'endereco' => $this->endereco,
        ]);

        $this->redirect(route('onboarding.plano'), navigate: true);
    }

    public function render()
    {
        return view('livewire.onboarding.empresa-step');
    }
}
