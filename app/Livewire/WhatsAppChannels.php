<?php

namespace App\Livewire;

use App\Models\Empresa;
use App\Models\WhatsAppChannel;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class WhatsAppChannels extends Component
{
    public int $empresaId;

    public string $nome   = '';
    public string $numero = '';
    public bool $isDefault = false;
    public bool $ativo = true;
    public ?int $editingId = null;

    protected function rules(): array
    {
        return [
            'nome'      => 'required|string|max:100',
            'numero'    => ['required', 'regex:/^whatsapp:\+[1-9]\d{7,14}$/'],
            'isDefault' => 'boolean',
            'ativo'     => 'boolean',
        ];
    }

    protected $messages = [
        'numero.regex' => 'Formato: whatsapp:+5511999990000',
    ];

    public function save(): void
    {
        $this->validate();

        $empresa = Empresa::findOrFail($this->empresaId);

        DB::transaction(function () use ($empresa) {
            if ($this->isDefault) {
                $empresa->whatsappChannels()->update(['is_default' => false]);
            }

            $data = [
                'empresa_id' => $this->empresaId,
                'nome'       => $this->nome,
                'numero'     => $this->numero,
                'is_default' => $this->isDefault,
                'ativo'      => $this->ativo,
            ];

            if ($this->editingId) {
                WhatsAppChannel::where('id', $this->editingId)
                    ->where('empresa_id', $this->empresaId)
                    ->update($data);
            } else {
                WhatsAppChannel::create($data);
            }
        });

        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $channel = WhatsAppChannel::where('id', $id)
            ->where('empresa_id', $this->empresaId)
            ->firstOrFail();

        $this->editingId = $id;
        $this->nome      = $channel->nome;
        $this->numero    = $channel->numero;
        $this->isDefault = $channel->is_default;
        $this->ativo     = $channel->ativo;
    }

    public function delete(int $id): void
    {
        WhatsAppChannel::where('id', $id)
            ->where('empresa_id', $this->empresaId)
            ->delete();
    }

    public function setDefault(int $id): void
    {
        DB::transaction(function () use ($id) {
            WhatsAppChannel::where('empresa_id', $this->empresaId)
                ->update(['is_default' => false]);

            WhatsAppChannel::where('id', $id)
                ->where('empresa_id', $this->empresaId)
                ->update(['is_default' => true]);
        });
    }

    public function toggleAtivo(int $id): void
    {
        $channel = WhatsAppChannel::where('id', $id)
            ->where('empresa_id', $this->empresaId)
            ->firstOrFail();

        $channel->update(['ativo' => !$channel->ativo]);
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->nome      = '';
        $this->numero    = '';
        $this->isDefault = false;
        $this->ativo     = true;
        $this->resetValidation();
    }

    public function render()
    {
        $channels = WhatsAppChannel::where('empresa_id', $this->empresaId)
            ->orderByDesc('is_default')
            ->orderBy('nome')
            ->get();

        return view('livewire.whatsapp-channels', compact('channels'));
    }
}
