<?php

namespace App\Repositories;

use App\Models\Lead;
use App\Models\Loja;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LeadRepository
{
    public function getAllForLoja(Loja $loja, array $filters = []): LengthAwarePaginator
    {
        return Lead::query()
            ->where('loja_id', $loja->id)
            ->when($filters['is_nearby'] ?? false, fn ($q) => $q->where('is_nearby', true))
            ->when($filters['min_score'] ?? null, fn ($q, $v) => $q->where('lead_score', '>=', $v))
            ->when($filters['cidade'] ?? null, fn ($q, $v) => $q->where('cidade', $v))
            ->orderByDesc('lead_score')
            ->paginate(20);
    }

    public function getNearbyLeads(Loja $loja): Collection
    {
        return Lead::where('loja_id', $loja->id)
            ->where('is_nearby', true)
            ->orderByDesc('lead_score')
            ->get();
    }

    public function getStats(Loja $loja): array
    {
        $leads = Lead::where('loja_id', $loja->id);

        return [
            'total' => (clone $leads)->count(),
            'nearby' => (clone $leads)->where('is_nearby', true)->count(),
            'hot' => (clone $leads)->where('lead_score', '>=', 80)->count(),
            'avg_score' => (clone $leads)->avg('lead_score'),
        ];
    }
}
