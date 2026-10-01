<?php

namespace App\Console\Commands;

use App\Support\Phone;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NormalizePhones extends Command
{
    protected $signature = 'leads:normalize-phones';

    protected $description = 'Fill telefone_e164 on leads and conversations, merging conversations split by phone format';

    public function handle(): int
    {
        $regions = DB::table('empresas')->pluck('country', 'id');
        $canonical = fn (object $row) => Phone::canonical($row->telefone, $regions[$row->empresa_id] ?? 'BR');

        $merged = DB::transaction(function () use ($canonical) {
            foreach (DB::table('leads')->select('id', 'empresa_id', 'telefone')->lazyById() as $lead) {
                DB::table('leads')->where('id', $lead->id)->update(['telefone_e164' => $canonical($lead)]);
            }

            $merged = 0;

            // Cleared first so a re-run never collides with stale values on the unique index.
            DB::table('conversations')->update(['telefone_e164' => null]);

            DB::table('conversations')->orderBy('id')->get()
                ->map(fn (object $conversation) => [$conversation, $canonical($conversation)])
                ->groupBy(fn (array $pair) => $pair[1] === null ? "id:{$pair[0]->id}" : "{$pair[0]->empresa_id}|{$pair[1]}")
                ->each(function (Collection $pairs) use (&$merged) {
                    $merged += $pairs->count() - 1;
                    $this->mergeConversations($pairs->pluck(0), $pairs->first()[1]);
                });

            return $merged;
        });

        $this->info("Phones normalized. Duplicate conversations merged: {$merged}.");

        return self::SUCCESS;
    }

    /**
     * Keeps the oldest conversation of a contact and moves the others' messages into it.
     *
     * @param  Collection<int, object>  $conversations  same empresa and phone, oldest first
     */
    private function mergeConversations(Collection $conversations, ?string $e164): void
    {
        $keep = $conversations->first();
        $duplicateIds = $conversations->slice(1)->pluck('id');

        if ($duplicateIds->isEmpty()) {
            DB::table('conversations')->where('id', $keep->id)->update(['telefone_e164' => $e164]);
            return;
        }

        $latest = $conversations->sortByDesc('last_message_at')->first();

        DB::table('messages')->whereIn('conversation_id', $duplicateIds)->update(['conversation_id' => $keep->id]);
        DB::table('conversations')->whereIn('id', $duplicateIds)->delete();

        DB::table('conversations')->where('id', $keep->id)->update([
            'telefone_e164'       => $e164,
            'lead_id'             => $conversations->pluck('lead_id')->filter()->first(),
            'whatsapp_channel_id' => $conversations->pluck('whatsapp_channel_id')->filter()->first(),
            'campaign_id'         => $conversations->pluck('campaign_id')->filter()->first(),
            'nome_contato'        => $conversations->pluck('nome_contato')->filter()->first(),
            'last_message'        => $latest->last_message,
            'last_message_at'     => $latest->last_message_at,
            'unread_count'        => $conversations->sum('unread_count'),
            'status'              => $conversations->contains('status', 'blocked') ? 'blocked' : $keep->status,
        ]);
    }
}
