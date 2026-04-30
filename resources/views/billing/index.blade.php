<x-app-layout>
    <x-slot name="title">Assinatura</x-slot>

    @php
        $subStatus = $subscription['stripe_status'] ?? null;
        $subActive = in_array($subStatus, ['active', 'trialing'], true);
        $trialEnds = $user->trial_ends_at;
        $trialActive = $trialEnds && \Carbon\Carbon::parse($trialEnds)->isFuture();

        $badge = $user->is_master_admin
            ? 'bg-violet-500/15 text-violet-300 border-violet-500/30'
            : ($subActive ? 'bg-green-500/15 text-green-300 border-green-500/30' : ($trialActive ? 'bg-yellow-500/15 text-yellow-300 border-yellow-500/30' : 'bg-red-500/15 text-red-300 border-red-500/30'));
        $label = $user->is_master_admin
            ? 'MASTER'
            : ($subActive ? strtoupper((string) $subStatus) : ($trialActive ? 'TRIAL' : 'SEM ASSINATURA'));
    @endphp

    <div class="space-y-6">

        {{-- Plano atual --}}
        <div class="bg-gray-900/40 border border-gray-800 rounded-2xl p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">{{ $plan['name'] ?? 'Plano ZapLeads' }}</h2>
                    <p class="text-sm text-gray-400 mt-1">
                        R$ {{ number_format(($plan['price_brl'] ?? 0) / 100, 2, ',', '.') }}/mês
                    </p>
                    @if ($trialActive && !$subActive)
                        <p class="text-xs text-yellow-400 mt-2">
                            Trial ativo até {{ \Carbon\Carbon::parse($trialEnds)->format('d/m/Y') }}
                        </p>
                    @elseif (!empty($subscription['ends_at']))
                        <p class="text-xs text-red-400 mt-2">
                            Cancelamento agendado para {{ \Carbon\Carbon::parse($subscription['ends_at'])->format('d/m/Y') }}
                        </p>
                    @endif
                </div>

                <span class="inline-flex items-center px-2 py-1 text-xs rounded-lg border {{ $badge }}">
                    {{ $label }}
                </span>
            </div>

            @if (!($user->is_master_admin ?? false) && $subActive && empty($subscription['ends_at']))
                <div class="mt-5 pt-4 border-t border-gray-800">
                    <form method="POST" action="{{ route('billing.cancel') }}">
                        @csrf
                        <button type="submit"
                                onclick="return confirm('{{ __('messages.cancel_subscription_confirm') }}')"
                                class="px-4 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-300 border border-red-500/30 rounded-xl text-sm font-semibold transition-colors">
                            {{ __('messages.cancel_subscription') }}
                        </button>
                    </form>
                    <p class="text-xs text-gray-500 mt-2">
                        Ao cancelar, sua assinatura permanece ativa até o fim do período pago.
                    </p>
                </div>
            @endif

            @if (!$subActive && !$trialActive && !$user->is_master_admin)
                <div class="mt-4 pt-4 border-t border-gray-800">
                    <p class="text-sm text-gray-400">
                        Nenhuma assinatura ativa. Para reativar, acesse <a href="{{ route('onboarding.plano') }}" class="text-blue-400 hover:underline">Plano → Pagamento</a>.
                    </p>
                </div>
            @endif
        </div>

        {{-- Pagamentos --}}
        <div class="bg-gray-900/40 border border-gray-800 rounded-2xl p-6">
            <h3 class="text-base font-semibold text-white mb-4">Pagamentos</h3>

            @if (!empty($invoices))
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs text-gray-400 uppercase tracking-wide border-b border-gray-800">
                            <tr>
                                <th class="py-2 pr-3 text-left">Data</th>
                                <th class="py-2 pr-3 text-left">Total</th>
                                <th class="py-2 pr-3 text-left">Status</th>
                                <th class="py-2 pr-3 text-left">Links</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/50">
                            @foreach ($invoices as $inv)
                                <tr class="hover:bg-gray-800/20 transition-colors">
                                    <td class="py-2 pr-3 text-gray-200">
                                        {{ method_exists($inv, 'date') ? $inv->date()->format('d/m/Y') : '—' }}
                                    </td>
                                    <td class="py-2 pr-3 text-gray-200">
                                        {{ $inv->amountDue() }}
                                    </td>
                                    <td class="py-2 pr-3 text-gray-400">
                                        {{ $inv->status ?? '—' }}
                                    </td>
                                    <td class="py-2 pr-3">
                                        @php $stripeInv = $inv->asStripeInvoice(); @endphp
                                        <div class="flex items-center gap-3">
                                            @if (!empty($stripeInv->hosted_invoice_url))
                                                <a class="text-blue-400 hover:text-blue-300 text-xs" target="_blank" rel="noopener noreferrer" href="{{ $stripeInv->hosted_invoice_url }}">Ver</a>
                                            @endif
                                            @if (!empty($stripeInv->invoice_pdf))
                                                <a class="text-blue-400 hover:text-blue-300 text-xs" target="_blank" rel="noopener noreferrer" href="{{ $stripeInv->invoice_pdf }}">PDF</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-400">{{ __('messages.no_payments_found') }}</p>
            @endif
        </div>

        {{-- Conta --}}
        <div class="bg-gray-900/40 border border-red-500/20 rounded-2xl p-6">
            <h3 class="text-base font-semibold text-white mb-1">{{ __('messages.delete_account') }}</h3>
            <p class="text-sm text-gray-400 mb-4">{{ __('messages.permanent_action_warning') }}</p>

            <form method="POST" action="{{ route('profile.destroy') }}">
                @csrf
                @method('DELETE')
                <button type="submit"
                        onclick="return confirm('{{ __('messages.delete_account_confirm') }}')"
                        class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-xl text-sm font-semibold transition-colors">
                    {{ __('messages.delete_my_account') }}
                </button>
            </form>
        </div>

    </div>
</x-app-layout>
