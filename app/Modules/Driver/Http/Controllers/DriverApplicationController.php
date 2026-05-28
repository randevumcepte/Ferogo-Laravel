<?php

namespace App\Modules\Driver\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Driver\Models\DriverApplication;
use App\Modules\Shared\Models\City;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DriverApplicationController extends Controller
{
    public function show()
    {
        return view('driver.apply', [
            'cities' => City::where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $cityIds = City::where('is_active', true)->pluck('id')->toArray();

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'city_id' => ['nullable', Rule::in($cityIds)],
            'birth_year' => ['nullable', 'integer', 'min:1940', 'max:' . (date('Y') - 18)],
            'license_class' => ['required', Rule::in(['B', 'D', 'D1', 'E'])],
            'experience_band' => ['required', Rule::in(['under_1', '1_to_3', '3_to_5', '5_plus'])],
            'has_src' => ['nullable', 'boolean'],
            'vehicle_info' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'kvkk' => ['accepted'],
        ]);

        DriverApplication::create([
            ...$validated,
            'has_src' => (bool) ($validated['has_src'] ?? false),
            'has_vehicle' => true,
            'status' => 'pending',
            'source' => 'web',
            'ip_address' => $request->ip(),
        ]);

        return redirect()
            ->route('driver.apply')
            ->with('application_success', true);
    }
}
