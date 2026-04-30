<x-app-layout>
    <x-slot name="title">Admin — Assinaturas</x-slot>

    <div class="space-y-6">
        <div class="bg-gray-900/40 border border-gray-800 rounded-2xl p-6">
            <div class="flex flex-col md:flex-row md:items-end gap-3">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Buscar</label>
                    <form method="GET" action="{{ route('admin.subscriptions.index') }}">
                        <div class="flex gap-2">
                            <input name="q" value="{{ $q }}" placeholder="nome ou email"
                                   class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors" />
                            <select name="status"
                                    class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
                                <option value="" {{ $status === '' ? 'selected' : '' }}>Todos</option>
                                <option value="subscribed" {{ $status === 'subscribed' ? 'selected' : '' }}>Ativos/Trial</option>
                                <option value="trial" {{ $status === 'trial' ? 'selected' : '' }}>Trial (user)</option>
                                <option value="unpaid" {{ $status === 'unpaid' ? 'selected' : '' }}>Sem pagamento</option>
                                <option value="master" {{ $status === 'master' ? 'selected' : '' }}>Master admin</option>
                            </select>
                            <button class="px-4 py-2 bg-green-600 hover:bg-green-500 text-white text-sm font-semibold rounded-xl transition-colors">
                                Filtrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="bg-gray-900/40 border border-gray-800 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-900 border-b border-gray-800">
                    <tr class="text-xs text-gray-400 uppercase tracking-wide">
                        <th class="px-4 py-3 text-left">{{ __('messages.name') }}</th>
                        <th class="px-4 py-3 text-left">Acesso</th>
                        <th class="px-4 py-3 text-left">Stripe</th>
                        <th class="px-4 py-3 text-left">Assinatura (default)</th>
                        <th class="px-4 py-3 text-left">Trial (user)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50">
                    @forelse ($users as $u)
                        @php
                            $sub = $u->subscriptions->firstWhere('type', 'default');
                            $subStatus = $sub->stripe_status ?? null;
                            $trialUserEnds = $u->trial_ends_at;
                            $trialActive = $trialUserEnds && \Carbon\Carbon::parse($trialUserEnds)->isFuture();
                            $subActive = in_array($subStatus, ['active', 'trialing'], true);
                            $access = $u->is_master_admin ? 'master' : ($u->onboarding_completed_at || $subActive ? 'ok' : ($trialActive ? 'trial' : 'blocked'));

                            $badge = match ($access) {
                                'master' => 'bg-violet-500/15 text-violet-300 border-violet-500/30',
                                'ok' => 'bg-green-500/15 text-green-300 border-green-500/30',
                                'trial' => 'bg-yellow-500/15 text-yellow-300 border-yellow-500/30',
                                default => 'bg-red-500/15 text-red-300 border-red-500/30',
                            };
                        @endphp

                        <tr class="hover:bg-gray-800/20 transition-colors">
                            <td class="px-4 py-3">
                                <div class="font-medium text-white">{{ $u->name }}</div>
                                <div class="text-xs text-gray-400">{{ $u->email }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-1 text-xs rounded-lg border {{ $badge }}">
                                    {{ strtoupper($access) }}
                                </span>
                                <div class="text-xs text-gray-500 mt-1">
                                    onboard: {{ $u->onboarding_completed_at ? \Carbon\Carbon::parse($u->onboarding_completed_at)->format('d/m/Y H:i') : '—' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-xs text-gray-300">customer: {{ $u->stripe_id ?: '—' }}</div>
                                <div class="text-xs text-gray-500">pm: {{ $u->pm_type ? ($u->pm_type . ' •••• ' . $u->pm_last_four) : '—' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($sub)
                                    <div class="text-xs text-gray-300">status: {{ $sub->stripe_status }}</div>
                                    <div class="text-xs text-gray-500">price: {{ $sub->stripe_price ?: '—' }}</div>
                                    <div class="text-xs text-gray-500">trial: {{ $sub->trial_ends_at ? \Carbon\Carbon::parse($sub->trial_ends_at)->format('d/m/Y H:i') : '—' }}</div>
                                    <div class="text-xs text-gray-500">ends: {{ $sub->ends_at ? \Carbon\Carbon::parse($sub->ends_at)->format('d/m/Y H:i') : '—' }}</div>
                                @else
                                    <div class="text-xs text-gray-500">—</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-xs text-gray-300">
                                    {{ $u->trial_ends_at ? \Carbon\Carbon::parse($u->trial_ends_at)->format('d/m/Y H:i') : '—' }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-gray-500">{{ __('messages.no_users') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="px-4 py-4 border-t border-gray-800">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</x-app-layout>

