<?php

namespace App\Modules\Booking\Support;

use App\Modules\Booking\Models\RideRequest;
use App\Modules\Booking\Services\RideRequestService;

/**
 * Pazarlık bloğunu API payload'larına ekleyen ortak yardımcı.
 * Hem müşteri (statusPayload) hem sürücü (offerPayload) tarafı aynı şekli kullanır.
 */
trait NegotiationPayload
{
    /**
     * Talebin fiyat pazarlığı durumunu döner. Pazarlıksız eski talepte null.
     */
    protected function negotiationPayload(RideRequest $req): ?array
    {
        // Hiç fiyat bilgisi yoksa (eski talep) pazarlık bloğu yok
        if ($req->suggested_fare === null
            && $req->customer_offer_fare === null
            && $req->agreed_fare === null) {
            return null;
        }

        $suggested = $req->suggested_fare !== null ? (float) $req->suggested_fare : null;

        $min = $max = null;
        if ($suggested !== null && $suggested > 0) {
            $band = RideRequestService::PRICE_BAND;
            $min  = round($suggested * (1 - $band), 2);
            $max  = round($suggested * (1 + $band), 2);
        }

        $round = (int) $req->negotiation_round;

        return [
            'state'               => $req->negotiation_state,
            'round'               => $round,
            'max_rounds'          => RideRequestService::MAX_NEGOTIATION_ROUNDS,
            'rounds_left'         => max(0, RideRequestService::MAX_NEGOTIATION_ROUNDS - $round),
            'suggested_fare'      => $suggested,
            'customer_offer_fare' => $req->customer_offer_fare !== null ? (float) $req->customer_offer_fare : null,
            'driver_counter_fare' => $req->driver_counter_fare !== null ? (float) $req->driver_counter_fare : null,
            'agreed_fare'         => $req->agreed_fare !== null ? (float) $req->agreed_fare : null,
            'current_price'       => $req->currentPrice(),
            'min_fare'            => $min,
            'max_fare'            => $max,
            // Sıra kimde: driver_countered → yolcu, customer_offered → sürücü
            'awaiting'            => $req->negotiation_state === 'driver_countered'
                ? 'customer'
                : ($req->negotiation_state === 'customer_offered' ? 'driver' : null),
            'currency'            => 'TRY',
        ];
    }
}
