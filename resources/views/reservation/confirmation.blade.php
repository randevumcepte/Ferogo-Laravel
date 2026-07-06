@extends('layouts.public')

@section('title', 'Rezervasyonunuz Alındı · FerXGo')

@section('content')
<div class="gradient-radial pt-24 pb-16 min-h-screen">

    <section class="px-6 py-12">
        <div class="max-w-2xl mx-auto">

            {{-- Success header --}}
            <div class="text-center mb-10">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-500/15 border-2 border-green-500/40 mb-6">
                    <svg class="w-10 h-10 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h1 class="text-3xl md:text-4xl font-bold mb-3">Rezervasyonunuz Alındı 🎉</h1>
                <p class="text-zinc-400">En kısa sürede sizi arayacağız. Rezervasyon numaranız:</p>
                <div class="inline-block mt-4 px-6 py-2 bg-brand/10 border border-brand/30 rounded-full">
                    <code class="text-brand font-mono font-bold">#{{ strtoupper(substr($ride->public_id, -8)) }}</code>
                </div>
            </div>

            {{-- Reservation summary --}}
            <div class="bg-zinc-900/50 border border-white/5 rounded-2xl p-6 space-y-5">

                <div>
                    <div class="text-xs text-zinc-500 uppercase tracking-wider mb-1">Müşteri</div>
                    <div class="text-lg font-semibold">{{ $ride->customer_name }}</div>
                    <div class="text-sm text-zinc-400">{{ $ride->customer_phone }}</div>
                </div>

                <div class="border-t border-white/5 pt-5">
                    <div class="text-xs text-zinc-500 uppercase tracking-wider mb-1">Rota</div>
                    <div class="space-y-2">
                        <div class="flex items-start gap-3">
                            <span class="text-green-400 mt-1">●</span>
                            <div>
                                <div class="text-sm text-zinc-300">{{ $ride->pickup_address }}</div>
                                <div class="text-xs text-zinc-500">Alış</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="text-red-400 mt-1">●</span>
                            <div>
                                <div class="text-sm text-zinc-300">{{ $ride->dropoff_address }}</div>
                                <div class="text-xs text-zinc-500">Bırakış</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-white/5 pt-5 grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <div class="text-xs text-zinc-500 uppercase tracking-wider mb-1">Tarih & Saat</div>
                        <div class="text-zinc-200 font-medium">{{ $ride->scheduled_at?->format('d.m.Y H:i') ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500 uppercase tracking-wider mb-1">Araç Sınıfı</div>
                        <div class="text-zinc-200 font-medium">{{ $ride->vehicleClass->name }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500 uppercase tracking-wider mb-1">Yolcu</div>
                        <div class="text-zinc-200 font-medium">{{ $ride->passenger_count }} kişi</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500 uppercase tracking-wider mb-1">Şehir</div>
                        <div class="text-zinc-200 font-medium">{{ $ride->city->name }}</div>
                    </div>
                </div>

                {{-- Fare breakdown --}}
                <div class="border-t border-white/5 pt-5">
                    <div class="text-xs text-zinc-500 uppercase tracking-wider mb-3">Ücret Detayı</div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between text-zinc-400">
                            <span>Açılış ücreti</span>
                            <span>₺{{ number_format($ride->base_fare, 2, ',', '.') }}</span>
                        </div>
                        @if($ride->boarding_fee > 0)
                            @php
                                $tierLabels = ['trusted' => 'sadık müşteri', 'standard' => 'müşteri', 'new' => 'yeni müşteri', 'suspicious' => 'riskli'];
                                $tierColors = ['trusted' => 'text-emerald-400', 'standard' => 'text-zinc-400', 'new' => 'text-zinc-400', 'suspicious' => 'text-rose-400'];
                                $tier = $ride->customer_trust_tier ?? 'new';
                            @endphp
                            <div class="flex justify-between {{ $tierColors[$tier] ?? 'text-zinc-400' }}">
                                <span>İndi-bindi <span class="text-xs opacity-70">({{ $tierLabels[$tier] ?? 'standart' }})</span></span>
                                <span>₺{{ number_format($ride->boarding_fee, 2, ',', '.') }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-zinc-400">
                            <span>Mesafe ücreti</span>
                            <span>₺{{ number_format($ride->distance_fare, 2, ',', '.') }}</span>
                        </div>
                        @if($ride->time_fare > 0)
                            <div class="flex justify-between text-zinc-400">
                                <span>Süre ücreti</span>
                                <span>₺{{ number_format($ride->time_fare, 2, ',', '.') }}</span>
                            </div>
                        @endif
                        @if($ride->extras_total > 0)
                            <div class="flex justify-between text-zinc-400">
                                <span>Ekstralar</span>
                                <span>₺{{ number_format($ride->extras_total, 2, ',', '.') }}</span>
                            </div>
                        @endif
                        @if($ride->multiplier > 1)
                            <div class="flex justify-between text-yellow-400 text-xs">
                                <span>Gece zammı (×{{ $ride->multiplier }})</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-lg font-bold text-white pt-2 border-t border-white/5">
                            <span>Tahmini Toplam</span>
                            <span class="text-brand">₺{{ number_format($ride->total_fare, 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                @if($ride->extras->isNotEmpty())
                    <div class="border-t border-white/5 pt-5">
                        <div class="text-xs text-zinc-500 uppercase tracking-wider mb-2">Seçilen Ekstralar</div>
                        <ul class="text-sm text-zinc-300 space-y-1">
                            @foreach($ride->extras as $rideExtra)
                                <li class="flex justify-between">
                                    <span>{{ $rideExtra->extra->name }} × {{ $rideExtra->quantity }}</span>
                                    <span class="text-zinc-400">₺{{ number_format($rideExtra->total_price, 2, ',', '.') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

            </div>

            {{-- Next steps --}}
            <div class="mt-8 p-5 bg-blue-500/5 border border-blue-500/20 rounded-2xl">
                <div class="flex items-start gap-3">
                    <span class="text-2xl">📞</span>
                    <div>
                        <div class="font-semibold text-blue-300 mb-1">Sıradaki adım</div>
                        <div class="text-sm text-zinc-400 leading-relaxed">
                            Müşteri temsilcimiz size birazdan ulaşacak.
                            Acil durumda <a href="tel:+908503403039" class="text-blue-300 underline">0850 340 3039</a> arayabilirsiniz
                            veya <a href="https://wa.me/908503403039" class="text-green-400 underline">WhatsApp'tan yazabilirsiniz</a>.
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-8">
                <a href="{{ route('home') }}" class="text-zinc-500 hover:text-white transition text-sm">
                    ← Ana sayfaya dön
                </a>
            </div>

        </div>
    </section>

</div>
@endsection
