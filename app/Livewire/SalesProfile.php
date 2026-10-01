<?php

namespace App\Livewire;

use App\Models\Empresa;
use Livewire\Component;

/**
 * "Minha Empresa → Vendas": what the empresa sells and to whom. The AI scores leads against it and
 * writes outreach from it, so it must hold only true facts.
 */
class SalesProfile extends Component
{
    private const MAX_PROVAS = 10;

    public Empresa $empresa;

    public string $descricaoEmpresa = '';
    public string $tipoClienteAlvo = '';
    public string $ofertaPrincipal = '';
    public string $problemaQueResolve = '';
    public string $diferencial = '';
    /** One proof per line. */
    public string $provasSociais = '';
    public string $ofertaDeEntrada = '';
    public string $segmentosExcluidos = '';

    public function mount(): void
    {
        $this->descricaoEmpresa = (string) $this->empresa->descricao_empresa;
        $this->tipoClienteAlvo = (string) $this->empresa->tipo_cliente_alvo;
        $this->ofertaPrincipal = (string) $this->empresa->oferta_principal;
        $this->problemaQueResolve = (string) $this->empresa->problema_que_resolve;
        $this->diferencial = (string) $this->empresa->diferencial;
        $this->provasSociais = implode("\n", $this->empresa->provas_sociais ?? []);
        $this->ofertaDeEntrada = (string) $this->empresa->oferta_de_entrada;
        $this->segmentosExcluidos = (string) $this->empresa->segmentos_excluidos;
    }

    public function salvar(): void
    {
        $this->validate([
            'descricaoEmpresa'   => 'nullable|string|max:1000',
            'tipoClienteAlvo'    => 'nullable|string|max:500',
            'ofertaPrincipal'    => 'nullable|string|max:255',
            'problemaQueResolve' => 'nullable|string|max:1000',
            'diferencial'        => 'nullable|string|max:1000',
            'provasSociais'      => 'nullable|string|max:3000',
            'ofertaDeEntrada'    => 'nullable|string|max:255',
            'segmentosExcluidos' => 'nullable|string|max:500',
        ]);

        $provas = array_values(array_filter(array_map(
            fn (string $line) => mb_substr(trim($line), 0, 300),
            preg_split('/\R/', $this->provasSociais) ?: [],
        )));

        if (count($provas) > self::MAX_PROVAS) {
            $this->addError('provasSociais', __('messages.sales_provas_max', ['max' => self::MAX_PROVAS]));
            return;
        }

        $this->empresa->update([
            'descricao_empresa'    => self::nullable($this->descricaoEmpresa),
            'tipo_cliente_alvo'    => self::nullable($this->tipoClienteAlvo),
            'oferta_principal'     => self::nullable($this->ofertaPrincipal),
            'problema_que_resolve' => self::nullable($this->problemaQueResolve),
            'diferencial'          => self::nullable($this->diferencial),
            'provas_sociais'       => $provas ?: null,
            'oferta_de_entrada'    => self::nullable($this->ofertaDeEntrada),
            'segmentos_excluidos'  => self::nullable($this->segmentosExcluidos),
        ]);

        $this->provasSociais = implode("\n", $provas);
        $this->dispatch('toast', type: 'success', message: __('messages.sales_saved'));
    }

    private static function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    public function render()
    {
        return view('livewire.sales-profile');
    }
}
