<?php

namespace App\Livewire;

use App\Jobs\CheckSenderRegistrationStatusJob;
use App\Models\WhatsAppSenderRegistration;
use App\Services\TwilioSenderService;
use Livewire\Attributes\Locked;
use Livewire\Component;

class WhatsAppSenderWizard extends Component
{
    #[Locked]
    public int $empresaId;

    public int $step = 1;

    // Step 1 fields
    public string $numero            = '';
    public string $wabaId            = '';
    public string $verificationMethod = 'SMS';
    public string $profileName       = '';
    public string $profileVertical   = 'Other';
    public string $profileAbout      = '';

    // Step 2 fields
    public string $otpCode = '';

    // Current registration ID (persists across steps)
    public ?int $registrationId = null;

    public ?string $errorMessage   = null;
    public ?string $successMessage = null;

    protected function rulesStep1(): array
    {
        return [
            'numero'             => ['required', 'regex:/^whatsapp:\+[1-9]\d{7,14}$/'],
            'wabaId'             => 'required|string|max:50',
            'verificationMethod' => 'required|in:SMS,VOICE',
            'profileName'        => 'required|string|max:100',
            'profileVertical'    => 'required|string',
            'profileAbout'       => 'nullable|string|max:256',
        ];
    }

    protected function rulesStep2(): array
    {
        return [
            'otpCode' => 'required|string|min:4|max:8',
        ];
    }

    protected $messages = [
        'numero.regex'  => 'Formato: whatsapp:+5511999990000',
        'wabaId.required' => 'WABA ID obrigatório. Encontre no Meta Business Manager.',
    ];

    public function submitStep1(): void
    {
        $this->errorMessage   = null;
        $this->successMessage = null;

        $this->validate($this->rulesStep1());

        $reg = WhatsAppSenderRegistration::create([
            'empresa_id'          => $this->empresaId,
            'numero'              => $this->numero,
            'waba_id'             => $this->wabaId,
            'verification_method' => $this->verificationMethod,
            'status'              => WhatsAppSenderRegistration::STATUS_PENDING_OTP,
            'profile_data'        => [
                'name'     => $this->profileName,
                'vertical' => $this->profileVertical,
                'about'    => $this->profileAbout ?: null,
            ],
        ]);

        $result = app(TwilioSenderService::class)->initiate($reg);

        if (!$result['success']) {
            $reg->delete();
            $this->errorMessage = $result['error'];
            return;
        }

        $this->registrationId = $reg->id;
        $this->step = 2;
        $this->successMessage = "Código enviado para {$this->numero} via {$this->verificationMethod}.";
    }

    public function resendOtp(): void
    {
        $this->errorMessage   = null;
        $this->successMessage = null;

        $reg = $this->getRegistration();
        if (!$reg) {
            $this->errorMessage = 'Registro não encontrado. Recomece o processo.';
            return;
        }

        $result = app(TwilioSenderService::class)->initiate($reg);

        if (!$result['success']) {
            $this->errorMessage = $result['error'];
            return;
        }

        $this->successMessage = "Novo código enviado para {$this->numero}.";
    }

    public function submitStep2(): void
    {
        $this->errorMessage   = null;
        $this->successMessage = null;

        $this->validate($this->rulesStep2());

        $reg = $this->getRegistration();
        if (!$reg) {
            $this->errorMessage = 'Registro não encontrado. Recomece o processo.';
            $this->step = 1;
            return;
        }

        $result = app(TwilioSenderService::class)->confirmOtp($reg, $this->otpCode);

        if (!$result['success']) {
            $this->errorMessage = $result['error'];
            return;
        }

        CheckSenderRegistrationStatusJob::dispatch($reg)->delay(now()->addMinutes(15));

        $this->step = 3;
    }

    public function refreshStatus(): void
    {
        $this->errorMessage = null;

        $reg = $this->getRegistration();
        if (!$reg) {
            return;
        }

        app(TwilioSenderService::class)->syncStatus($reg);
    }

    public function getRegistration(): ?WhatsAppSenderRegistration
    {
        if (!$this->registrationId) {
            return null;
        }

        return WhatsAppSenderRegistration::where('id', $this->registrationId)
            ->where('empresa_id', $this->empresaId)
            ->first();
    }

    public function resetWizard(): void
    {
        $this->step              = 1;
        $this->numero            = '';
        $this->wabaId            = '';
        $this->verificationMethod = 'SMS';
        $this->profileName       = '';
        $this->profileVertical   = 'Other';
        $this->profileAbout      = '';
        $this->otpCode           = '';
        $this->registrationId    = null;
        $this->errorMessage      = null;
        $this->successMessage    = null;
        $this->resetValidation();
    }

    public function render()
    {
        $registration = $this->getRegistration();

        return view('livewire.whatsapp-sender-wizard', compact('registration'));
    }
}
