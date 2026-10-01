<?php

namespace App\Services\Prospecting;

use App\Models\Empresa;
use App\Models\Lead;
use App\Services\AIService;
use App\Services\Scoring\LeadScoringService;

/**
 * Writes prospect messages with the AI from the sales profile and the lead's dossier, and keeps
 * only what passes OutreachMessageValidator. What fails is rewritten once, with the reasons.
 */
class OutreachWriter
{
    public function __construct(
        private readonly AIService $ai,
    ) {}

    /**
     * The first message in the three angles that passed review. Null when none did.
     *
     * @return array{dor_hipotese: ?string, variantes: list<array{angulo: string, mensagem: string, gancho_usado: ?string}>}|null
     */
    public function firstMessage(Lead $lead): ?array
    {
        [$empresa, $leadData, $sources, $idioma] = $this->inputs($lead);

        $first = $this->ai->gerarAbordagem($empresa, $leadData, $idioma);
        $valid = $this->passing($first['variantes'] ?? [], $sources);
        $missing = array_diff(AIService::ANGULOS, array_column($valid, 'angulo'));

        if ($first !== null && $missing) {
            $retry = $this->ai->gerarAbordagem($empresa, $leadData, $idioma, $this->reasons($first['variantes'], $sources));
            foreach ($this->passing($retry['variantes'] ?? [], $sources) as $variante) {
                if (in_array($variante['angulo'], $missing, true)) {
                    $valid[] = $variante;
                }
            }
        }

        if ($valid === []) {
            return null;
        }

        usort($valid, fn (array $a, array $b) => array_search($a['angulo'], AIService::ANGULOS) <=> array_search($b['angulo'], AIService::ANGULOS));

        return [
            'dor_hipotese' => ($first['dor_hipotese'] ?? '') ?: (($retry['dor_hipotese'] ?? '') ?: null),
            'variantes'    => $valid,
        ];
    }

    /**
     * A follow-up of the cold cadence, or null when no version passed review.
     *
     * @param  list<string>  $historico  messages already sent to the lead, oldest first
     */
    public function followUp(Lead $lead, string $objetivo, array $historico, ?string $dorHipotese): ?string
    {
        [$empresa, $leadData, $sources, $idioma] = $this->inputs($lead, array_filter(['dor_hipotese' => $dorHipotese]));
        // What the user already said to the lead may be repeated.
        $sources .= ' ' . implode(' ', $historico);
        $needsQuestion = $objetivo === 'novo_angulo';

        $text = $this->ai->gerarFollowUp($empresa, $leadData, $objetivo, $historico, $idioma);
        if ($text === '') {
            return null;
        }

        $violations = OutreachMessageValidator::violations($text, $sources, $needsQuestion);
        if ($violations === []) {
            return $text;
        }

        $text = $this->ai->gerarFollowUp($empresa, $leadData, $objetivo, $historico, $idioma, array_map(self::describe(...), $violations));

        return $text !== '' && OutreachMessageValidator::violations($text, $sources, $needsQuestion) === [] ? $text : null;
    }

    /** What the seller offers, with only the filled fields. */
    public static function empresaData(Empresa $empresa): array
    {
        return array_filter([
            'nome'                 => $empresa->nome,
            'cidade'               => $empresa->cidade,
            'o_que_faz'            => $empresa->descricao_empresa,
            'oferta_principal'     => $empresa->oferta_principal,
            'problema_que_resolve' => $empresa->problema_que_resolve,
            'diferencial'          => $empresa->diferencial,
            'cliente_ideal'        => $empresa->tipo_cliente_alvo,
            'provas_sociais'       => $empresa->provas_sociais ?: null,
            'oferta_de_entrada'    => $empresa->oferta_de_entrada,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * What the message may use about the lead: the scoring facts (no ids or contact data), the
     * hook and pain the scoring found, and the decision maker's first name only when trusted.
     */
    public static function leadData(Lead $lead, array $extra = []): array
    {
        $facts = LeadScoringService::facts($lead);
        unset($facts['id'], $facts['tipos_google']);
        $insights = $lead->ai_insights ?? [];

        return array_filter([
            ...$facts,
            'aberta_em'             => $lead->data_abertura?->year,
            'gancho'                => $insights['gancho'] ?? null,
            'dor_provavel'          => $insights['dor_provavel'] ?? null,
            'decisor_primeiro_nome' => $lead->primeiroNomeDecisor(),
            ...$extra,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /** @return array{0: array, 1: array, 2: string, 3: string} empresa data, lead data, sources text, language */
    private function inputs(Lead $lead, array $extra = []): array
    {
        $empresa = self::empresaData($lead->empresa);
        $leadData = self::leadData($lead, $extra);

        return [$empresa, $leadData, json_encode([$empresa, $leadData], JSON_UNESCAPED_UNICODE), (string) ($lead->empresa->locale ?: 'pt_BR')];
    }

    /** @return list<array{angulo: string, mensagem: string, gancho_usado: ?string}> */
    private function passing(array $variantes, string $sources): array
    {
        return array_values(array_filter($variantes, fn (array $v) => OutreachMessageValidator::violations($v['mensagem'], $sources) === []));
    }

    /** @return list<string> */
    private function reasons(array $variantes, string $sources): array
    {
        $reasons = [];
        foreach ($variantes as $variante) {
            foreach (OutreachMessageValidator::violations($variante['mensagem'], $sources) as $violation) {
                $reasons[] = "variante {$variante['angulo']}: " . self::describe($violation);
            }
        }

        return $reasons;
    }

    /** A violation code in words the model acts on. */
    private static function describe(string $violation): string
    {
        [$code, $detail] = array_pad(explode(':', $violation, 2), 2, '');

        return match ($code) {
            'too_long'    => 'passou de ' . OutreachMessageValidator::MAX_LENGTH . ' caracteres',
            'link'        => 'tem link ou endereço de site',
            'emojis'      => 'tem mais de 1 emoji',
            'phrase'      => "usa a expressão proibida \"{$detail}\"",
            'caps'        => 'tem palavra em CAIXA ALTA',
            'number'      => "cita o número {$detail}, que não está nos dados",
            'no_question' => 'não tem pergunta',
            default       => $violation,
        };
    }
}
