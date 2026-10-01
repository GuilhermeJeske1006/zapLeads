<?php

namespace App\Services\Prospecting;

use App\Models\OutreachAttempt;
use App\Services\AIService;
use Carbon\CarbonInterface;
use Illuminate\Support\Lottery;

/**
 * A/B test of the first message's angle, per empresa. A reply counts for the first message when it
 * came before any follow-up (OutreachService::markReplied credits the latest attempt).
 *
 * Until every candidate angle has MIN_SENDS first messages, the one sent least is suggested, so the
 * samples stay even. After that the angle with the best reply rate is suggested, except EXPLORATION %
 * of the time, when another angle goes, so a slow starter can still catch up.
 */
class AngleExperiment
{
    public const MIN_SENDS = 30;

    /** Percent of suggestions that try an angle other than the winner. */
    public const EXPLORATION = 20;

    /**
     * Sends and replies of the first message by angle (every angle, even unsent).
     *
     * @return array<string, array{envios: int, respostas: int, taxa: ?float}>
     */
    public function stats(int $empresaId, ?CarbonInterface $since = null): array
    {
        $rows = OutreachAttempt::where('empresa_id', $empresaId)
            ->where('etapa', 0)
            ->whereIn('variante', AIService::ANGULOS)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->selectRaw('variante, count(*) as envios, count(responded_at) as respostas')
            ->groupBy('variante')
            ->get()
            ->keyBy('variante');

        $stats = [];
        foreach (AIService::ANGULOS as $angle) {
            $envios = (int) ($rows[$angle]->envios ?? 0);
            $respostas = (int) ($rows[$angle]->respostas ?? 0);
            $stats[$angle] = ['envios' => $envios, 'respostas' => $respostas, 'taxa' => $envios > 0 ? (float) $respostas / $envios : null];
        }

        return $stats;
    }

    /**
     * The angle with the best reply rate, once each of $angles has MIN_SENDS sends. Ties go to the
     * one with more sends, then to the order of AIService::ANGULOS.
     *
     * @param  array<string, array{envios: int, respostas: int, taxa: ?float}>  $stats
     * @param  list<string>  $angles
     */
    public function winner(array $stats, array $angles = AIService::ANGULOS): ?string
    {
        if (count($angles) < 2) {
            return null;
        }

        foreach ($angles as $angle) {
            if (($stats[$angle]['envios'] ?? 0) < self::MIN_SENDS) {
                return null;
            }
        }

        usort($angles, fn (string $a, string $b) => [-$stats[$a]['taxa'], -$stats[$a]['envios'], array_search($a, AIService::ANGULOS)]
            <=> [-$stats[$b]['taxa'], -$stats[$b]['envios'], array_search($b, AIService::ANGULOS)]);

        return $angles[0];
    }

    /**
     * The angle to put first in the review card, among those that passed review. Without a hook,
     * "observacao" has nothing concrete to say and only goes when it's the only one.
     *
     * @param  list<string>  $angles
     */
    public function suggest(int $empresaId, array $angles, bool $hasHook): string
    {
        if (!$hasHook && count($angles) > 1) {
            $angles = array_values(array_diff($angles, ['observacao']));
        }

        $stats = $this->stats($empresaId);
        $winner = $this->winner($stats, $angles);

        if ($winner === null) {
            return $this->leastSent($stats, $angles);
        }

        // Lottery "wins" are the exploration draws (tests fix them with Lottery::alwaysWin/alwaysLose).
        return Lottery::odds(self::EXPLORATION, 100)
            ->winner(fn () => $this->leastSent($stats, array_values(array_diff($angles, [$winner]))))
            ->loser(fn () => $winner)
            ->choose();
    }

    /** @param list<string> $angles */
    private function leastSent(array $stats, array $angles): string
    {
        usort($angles, fn (string $a, string $b) => [$stats[$a]['envios'] ?? 0, array_search($a, AIService::ANGULOS)]
            <=> [$stats[$b]['envios'] ?? 0, array_search($b, AIService::ANGULOS)]);

        return $angles[0];
    }
}
