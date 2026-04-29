<?php

namespace App\Livewire\Leads;

use App\Models\Empresa;
use App\Services\AIService;
use Livewire\Component;

class LeadFinder extends Component
{
    public Empresa $empresa;

    public string $descricaoEmpresa = '';
    public string $tipoCliente = '';
    public array $resultados = [];
    public bool $buscando = false;
    public bool $buscaFeita = false;

    public function mount(): void
    {
        $this->descricaoEmpresa = $this->empresa->descricao_empresa ?? '';
        $this->tipoCliente      = $this->empresa->tipo_cliente_alvo ?? '';
    }

    public function buscar(AIService $ai): void
    {
        $this->validate([
            'descricaoEmpresa' => 'required|min:10',
            'tipoCliente'      => 'required|min:5',
        ], [
            'descricaoEmpresa.required' => 'Descreva sua empresa.',
            'descricaoEmpresa.min'      => 'Descreva com ao menos 10 caracteres.',
            'tipoCliente.required'      => 'Informe o tipo de cliente desejado.',
            'tipoCliente.min'           => 'Descreva com ao menos 5 caracteres.',
        ]);

        $this->empresa->update([
            'descricao_empresa'  => $this->descricaoEmpresa,
            'tipo_cliente_alvo'  => $this->tipoCliente,
        ]);

        $this->buscando = true;
        $this->buscaFeita = false;
        $this->resultados = [];

        try {
            $leads = $this->empresa->leads()->get()->toArray();
            $this->resultados = $ai->buscarLeadsPorPerfil(
                $this->descricaoEmpresa,
                $this->tipoCliente,
                $leads
            );
            $this->buscaFeita = true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('LeadFinder buscar failed', ['error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', message: 'Erro ao buscar leads. Tente novamente.');
        } finally {
            $this->buscando = false;
        }
    }

    public function render()
    {
        return view('livewire.leads.lead-finder');
    }
}
