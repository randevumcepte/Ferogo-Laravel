<?php

namespace App\Modules\Pricing\Services;

use App\Modules\Booking\Models\CustomerTrust;
use App\Modules\Pricing\Models\Extra;
use App\Modules\Pricing\Models\PricingRule;
use App\Modules\Vehicle\Models\VehicleClass;
use Carbon\Carbon;

/**
 * Fiyat motoru.
 *
 * Tarife önceliği:
 *   1. PricingRule (city × vehicle_class) — şehir-bazlı özel tarife varsa onu kullan
 *   2. VehicleClass defaults — fallback
 *
 * Hesap mantığı:
 *   subtotal = (base_fare + boarding_fee + distance_fare + time_fare) × multiplier
 *   subtotal = max(subtotal, minimum_fare)
 *   total    = subtotal + extras_total
 *
 * İndi-bindi (boarding_fee):
 *   Müşteri güven katmanına göre seçilir (trusted / standard / new / suspicious).
 *   Şehir kuralında override varsa onu, yoksa VehicleClass değerini kullanır.
 *
 * Multiplier:
 *   - Gece zammı (varsa) — night_multiplier
 *   - Yoğun saat (ilerde scheduler için) — peak_multiplier
 *   - Şimdilik sadece night uygulanır
 */
class FareCalculator
{
    /**
     * @param array<int,array{extra_id:int,quantity:int}> $extras
     * @param string|null $customerTrustTier 'trusted'|'standard'|'new'|'suspicious'
     *                                       null verilirse 'new' (en güvenli/yüksek) kabul edilir.
     * @return array{
     *   base_fare:float,
     *   boarding_fee:float,
     *   customer_trust_tier:string,
     *   distance_fare:float,
     *   time_fare:float,
     *   extras_total:float,
     *   extras:array,
     *   multiplier:float,
     *   subtotal:float,
     *   discount:float,
     *   total_fare:float,
     *   currency:string
     * }
     */
    public function calculate(
        int $cityId,
        int $vehicleClassId,
        float $distanceKm,
        int $durationMinutes,
        array $extras = [],
        ?Carbon $scheduledAt = null,
        ?string $customerTrustTier = null,
    ): array {
        $rule = PricingRule::where('city_id', $cityId)
            ->where('vehicle_class_id', $vehicleClassId)
            ->where('is_active', true)
            ->first();

        $class = VehicleClass::findOrFail($vehicleClassId);

        if (! $rule) {
            $rule = new PricingRule([
                'base_fare' => $class->base_fare,
                'per_km_fare' => $class->per_km_fare,
                'per_minute_fare' => $class->per_minute_fare,
                'minimum_fare' => $class->minimum_fare,
                'night_multiplier' => 1.50,
                'night_start' => '22:00:00',
                'night_end' => '06:00:00',
                'peak_multiplier' => 1.25,
            ]);
        }

        $tier = $this->normalizeTier($customerTrustTier);
        $boardingFee = $this->resolveBoardingFee($rule, $class, $tier);

        $baseFare = (float) $rule->base_fare;
        $distanceFare = $distanceKm * (float) $rule->per_km_fare;
        $timeFare = $durationMinutes * (float) $rule->per_minute_fare;

        // Ekstralar
        $extrasTotal = 0.0;
        $extraDetails = [];
        foreach ($extras as $extraData) {
            $extraId = $extraData['extra_id'] ?? null;
            if (! $extraId) {
                continue;
            }
            $extra = Extra::find($extraId);
            if (! $extra || ! $extra->is_active) {
                continue;
            }
            $qty = max(1, min((int) $extra->max_quantity, (int) ($extraData['quantity'] ?? 1)));
            $total = $extra->per_unit
                ? (float) $extra->price * $qty
                : (float) $extra->price;
            $extrasTotal += $total;
            $extraDetails[] = [
                'extra_id' => $extra->id,
                'name' => $extra->name,
                'quantity' => $qty,
                'unit_price' => (float) $extra->price,
                'total_price' => $total,
            ];
        }

        // Çarpan (gece zammı)
        $multiplier = 1.00;
        if ($scheduledAt) {
            $hour = $scheduledAt->format('H:i:s');
            $nightStart = (string) ($rule->night_start ?? '22:00:00');
            $nightEnd = (string) ($rule->night_end ?? '06:00:00');

            // Gece aralığı (gece yarısını geçebilir)
            $isNight = ($nightStart > $nightEnd)
                ? ($hour >= $nightStart || $hour < $nightEnd)
                : ($hour >= $nightStart && $hour < $nightEnd);

            if ($isNight) {
                $multiplier = (float) $rule->night_multiplier;
            }
        }

        $subtotal = ($baseFare + $boardingFee + $distanceFare + $timeFare) * $multiplier;
        $subtotal = max($subtotal, (float) $rule->minimum_fare);
        $total = $subtotal + $extrasTotal;

        return [
            'base_fare' => round($baseFare, 2),
            'boarding_fee' => round($boardingFee, 2),
            'customer_trust_tier' => $tier,
            'distance_fare' => round($distanceFare, 2),
            'time_fare' => round($timeFare, 2),
            'extras_total' => round($extrasTotal, 2),
            'extras' => $extraDetails,
            'multiplier' => $multiplier,
            'subtotal' => round($subtotal, 2),
            'discount' => 0.00,
            'total_fare' => round($total, 2),
            'currency' => 'TRY',
        ];
    }

    /**
     * Telefon numarasıyla müşteri güven katmanını çözer.
     * Hiç kaydı yoksa 'new' döner.
     */
    public function resolveTierForPhone(?string $phone): string
    {
        if (! $phone) {
            return CustomerTrust::defaultTierForNewPhone();
        }
        $trust = CustomerTrust::where('phone', $phone)->first();
        return $trust ? $trust->boardingFeeTier() : CustomerTrust::defaultTierForNewPhone();
    }

    /**
     * Geçersiz katman gelirse 'new' (yüksek tarife, güvenli varsayılan).
     */
    protected function normalizeTier(?string $tier): string
    {
        return in_array($tier, ['trusted', 'standard', 'new', 'suspicious'], true)
            ? $tier
            : 'new';
    }

    /**
     * Önce şehir kuralındaki override'a bakar (nullable),
     * yoksa VehicleClass varsayılanını kullanır.
     */
    protected function resolveBoardingFee(PricingRule $rule, VehicleClass $class, string $tier): float
    {
        $column = 'boarding_fee_' . $tier;
        $override = $rule->{$column} ?? null;
        if ($override !== null) {
            return (float) $override;
        }
        return (float) ($class->{$column} ?? 0);
    }
}
