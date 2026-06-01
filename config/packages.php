<?php

/**
 * Sürücü Paket Sistemi — Martı TAG modelinden esinlenilmiş kademeli abonelik.
 *
 * Komisyon YOK — sürücü paketi ödeyince yaptığı her işin %100'ü kendinin.
 * Paket bittiğinde dispatch otomatik kapanır (radar'a düşmez).
 */

return [
    'currency'   => 'TRY',
    'currency_symbol' => '₺',

    // Sürücüye gösterilen paket katalogu
    // duration_hours bittikten sonra paket expire olur.
    'types' => [
        'hourly_3' => [
            'label'           => '3 Saatlik',
            'subtitle'        => 'Kısa vardiya, dene-gör',
            'duration_hours'  => 3,
            'price'           => 199.00,
            'order'           => 1,
            'badge'           => null,
        ],
        'daily' => [
            'label'           => 'Tam Gün',
            'subtitle'        => '24 saat tam aktif',
            'duration_hours'  => 24,
            'price'           => 449.00,
            'order'           => 2,
            'badge'           => 'POPÜLER',
        ],
        'weekly' => [
            'label'           => 'Haftalık',
            'subtitle'        => 'Düzenli çalışan için',
            'duration_hours'  => 24 * 7,
            'price'           => 2499.00,
            'order'           => 3,
            'badge'           => '%21 İndirim',
        ],
        'monthly' => [
            'label'           => 'Aylık',
            'subtitle'        => 'Tam zamanlı sürücü',
            'duration_hours'  => 24 * 30,
            'price'           => 7999.00,
            'order'           => 4,
            'badge'           => '%41 İndirim',
        ],
    ],
];
