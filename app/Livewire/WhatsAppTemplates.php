<?php

namespace App\Livewire;

use App\Models\Empresa;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * Templates created in Twilio's Content Template Builder, registered by ContentSid. Status and
 * body come from Twilio; the user maps each placeholder to what fills it.
 */
class WhatsAppTemplates extends Component
{
    public Empresa $empresa;

    public string $contentSid = '';

    public function adicionar(): void
    {
        $this->contentSid = trim($this->contentSid);

        $this->validate(
            ['contentSid' => ['required', 'regex:/^HX[0-9a-fA-F]{32}$/']],
            ['contentSid.regex' => __('messages.template_sid_format')],
        );

        if ($this->empresa->whatsappTemplates()->where('content_sid', $this->contentSid)->exists()) {
            $this->addError('contentSid', __('messages.template_already_added'));
            return;
        }

        $template = new WhatsAppTemplate(['empresa_id' => $this->empresa->id, 'content_sid' => $this->contentSid]);
        if (!$this->sync($template)) {
            $this->addError('contentSid', __('messages.template_not_found'));
            return;
        }

        $this->contentSid = '';
        $this->dispatch('toast', type: 'success', message: __('messages.template_added'));
    }

    public function sincronizar(int $id): void
    {
        $synced = $this->sync($this->template($id));

        $this->dispatch('toast',
            type: $synced ? 'success' : 'error',
            message: __($synced ? 'messages.template_synced' : 'messages.template_not_found'),
        );
    }

    public function mapear(int $id, string $placeholder, string $field): void
    {
        $template = $this->template($id);
        $variaveis = $template->variaveis ?? [];

        if (!array_key_exists($placeholder, $variaveis)) {
            return;
        }

        $variaveis[$placeholder] = array_key_exists($field, WhatsAppTemplate::FIELDS) ? $field : null;
        $template->update(['variaveis' => $variaveis]);
    }

    public function alternarAtivo(int $id): void
    {
        $template = $this->template($id);
        $template->update(['ativo' => !$template->ativo]);
    }

    public function remover(int $id): void
    {
        $this->template($id)->delete();
    }

    /** Pulls name, body, placeholders and approval from Twilio; keeps the mappings already made. */
    private function sync(WhatsAppTemplate $template): bool
    {
        try {
            $remote = app(WhatsAppService::class)->fetchContentTemplate($template->content_sid);
        } catch (\Throwable $e) {
            Log::warning('Twilio template sync failed', ['content_sid' => $template->content_sid, 'error' => $e->getMessage()]);
            return false;
        }

        $current = $template->variaveis ?? [];

        $template->fill([
            'nome'          => $remote['nome'],
            'idioma'        => $remote['idioma'],
            'categoria'     => $remote['categoria'],
            'corpo_preview' => $remote['corpo'],
            'variaveis'     => collect($remote['variaveis'])->mapWithKeys(fn ($key) => [$key => $current[$key] ?? null])->all(),
            'status'        => $remote['status'],
            'synced_at'     => now(),
        ])->save();

        return true;
    }

    private function template(int $id): WhatsAppTemplate
    {
        return $this->empresa->whatsappTemplates()->findOrFail($id);
    }

    public function render()
    {
        return view('livewire.whatsapp-templates', [
            'templates' => $this->empresa->whatsappTemplates()->orderBy('id')->get(),
        ]);
    }
}
