<?php

namespace App\Livewire\Store;

use App\Models\Loja;
use App\Models\Produto;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ProdutoManager extends Component
{
    use WithFileUploads, WithPagination;

    public Loja $loja;
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $nome = '';
    public string $preco = '';
    public string $descricao = '';
    public $imagem = null;
    public bool $ativo = true;

    protected function rules(): array
    {
        return [
            'nome' => 'required|string|max:255',
            'preco' => 'required|numeric|min:0',
            'descricao' => 'nullable|string|max:2000',
            'imagem' => 'nullable|image|mimes:jpeg,png,webp|max:2048',
            'ativo' => 'boolean',
        ];
    }

    public function openCreate(): void
    {
        $this->reset('nome', 'preco', 'descricao', 'imagem', 'ativo', 'editingId');
        $this->ativo = true;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $produto = Produto::findOrFail($id);
        $this->editingId = $id;
        $this->nome = $produto->nome;
        $this->preco = $produto->preco;
        $this->descricao = $produto->descricao ?? '';
        $this->ativo = $produto->ativo;
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate();
        $data['loja_id'] = $this->loja->id;

        if ($this->imagem) {
            $data['imagem'] = $this->imagem->store('products', 'public');
        } else {
            unset($data['imagem']);
        }

        if ($this->editingId) {
            Produto::findOrFail($this->editingId)->update($data);
            $this->dispatch('toast', type: 'success', message: __('messages.produto_updated'));
        } else {
            Produto::create($data);
            $this->dispatch('toast', type: 'success', message: __('messages.produto_created'));
        }

        $this->showModal = false;
        $this->resetPage();
    }

    public function toggleAtivo(int $id): void
    {
        $produto = Produto::findOrFail($id);
        $produto->update(['ativo' => !$produto->ativo]);
    }

    public function delete(int $id): void
    {
        Produto::findOrFail($id)->delete();
        $this->dispatch('toast', type: 'success', message: __('messages.produto_deleted'));
    }

    public function render()
    {
        $produtos = $this->loja->produtos()->orderBy('ordem')->paginate(12);
        return view('livewire.store.produto-manager', compact('produtos'));
    }
}
