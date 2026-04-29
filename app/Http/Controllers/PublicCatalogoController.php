<?php

namespace App\Http\Controllers;

use App\Http\Requests\CaptureLeadRequest;
use App\Models\Empresa;
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
        $empresa = Empresa::where('slug', $slug)->where('ativo', true)->firstOrFail();
        $produtos = $empresa->produtos()->where('ativo', true)->orderBy('ordem')->get();

        return view('public.catalogo', ['loja' => $empresa, 'produtos' => $produtos]);
    }

    public function capturarLead(CaptureLeadRequest $request, string $slug): JsonResponse
    {
        $empresa = Empresa::where('slug', $slug)->where('ativo', true)->firstOrFail();

        $lead = $this->leadService->capturar($empresa, $request->validated());

        return response()->json([
            'success' => true,
            'whatsapp_url' => $this->buildWhatsAppUrl($empresa, $lead->nome),
            'is_nearby' => $lead->is_nearby,
        ]);
    }

    private function buildWhatsAppUrl(Empresa $empresa, string $nome): string
    {
        $message = urlencode(__('messages.whatsapp_greeting', ['nome' => $nome, 'loja' => $empresa->nome]));
        $phone = preg_replace('/\D/', '', $empresa->whatsapp);
        return "https://wa.me/{$phone}?text={$message}";
    }
}
