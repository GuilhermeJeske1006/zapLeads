<?php

namespace App\Http\Controllers;

use App\Http\Requests\CaptureLeadRequest;
use App\Models\Loja;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PublicCatalogoController extends Controller
{
    public function __construct(
        private LeadService $leadService,
    ) {}

    public function show(string $slug): View
    {
        $loja = Loja::where('slug', $slug)->where('ativo', true)->firstOrFail();
        $produtos = $loja->produtos()->where('ativo', true)->orderBy('ordem')->get();

        return view('public.catalogo', compact('loja', 'produtos'));
    }

    public function capturarLead(CaptureLeadRequest $request, string $slug): JsonResponse
    {
        $loja = Loja::where('slug', $slug)->where('ativo', true)->firstOrFail();

        $lead = $this->leadService->capturar($loja, $request->validated());

        return response()->json([
            'success' => true,
            'whatsapp_url' => $this->buildWhatsAppUrl($loja, $lead->nome),
            'is_nearby' => $lead->is_nearby,
        ]);
    }

    private function buildWhatsAppUrl(Loja $loja, string $nome): string
    {
        $message = urlencode(__('messages.whatsapp_greeting', ['nome' => $nome, 'loja' => $loja->nome]));
        $phone = preg_replace('/\D/', '', $loja->whatsapp);
        return "https://wa.me/{$phone}?text={$message}";
    }
}
