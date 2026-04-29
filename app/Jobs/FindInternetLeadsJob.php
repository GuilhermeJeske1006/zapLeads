<?php

namespace App\Jobs;

use App\Models\Empresa;
use App\Models\ProspectingSearch;
use App\Services\Prospecting\ProspectingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FindInternetLeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 60;
    public int $timeout = 180;

    public function __construct(
        public readonly int $empresaId,
        public readonly string $descricaoEmpresa,
        public readonly string $tipoCliente,
        public readonly float $radiusKm,
        public readonly int $maxResults = 60,
    ) {}

    public function handle(ProspectingService $prospecting): void
    {
        $empresa = Empresa::find($this->empresaId);
        if (!$empresa) {
            return;
        }

        $search = $prospecting->run($empresa, $this->descricaoEmpresa, $this->tipoCliente, $this->radiusKm, $this->maxResults);
        // keep a reference for monitoring/logging if needed
        ProspectingSearch::whereKey($search->id)->exists();
    }
}
