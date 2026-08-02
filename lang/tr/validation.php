<?php

/**
 * Türkçe doğrulama mesajları.
 *
 * APP_LOCALE=tr olduğu için Laravel bu dosyayı kullanır. Dosya yoksa framework'ün
 * gömülü İngilizce mesajlarına düşerdi (mobil login'de "The email field is required."
 * gibi uyarılar bu yüzden İngilizce çıkıyordu).
 */

return [

    'accepted'             => ':attribute alanı kabul edilmelidir.',
    'accepted_if'          => ':other :value olduğunda :attribute alanı kabul edilmelidir.',
    'active_url'           => ':attribute geçerli bir URL değil.',
    'after'                => ':attribute, :date tarihinden sonra olmalıdır.',
    'after_or_equal'       => ':attribute, :date tarihinden sonra veya aynı olmalıdır.',
    'alpha'                => ':attribute yalnızca harflerden oluşabilir.',
    'alpha_dash'           => ':attribute yalnızca harf, rakam, tire ve alt çizgi içerebilir.',
    'alpha_num'            => ':attribute yalnızca harf ve rakamlardan oluşabilir.',
    'array'                => ':attribute bir dizi olmalıdır.',
    'before'               => ':attribute, :date tarihinden önce olmalıdır.',
    'before_or_equal'      => ':attribute, :date tarihinden önce veya aynı olmalıdır.',
    'between'              => [
        'array'   => ':attribute :min - :max arasında öğe içermelidir.',
        'file'    => ':attribute :min - :max kilobayt arasında olmalıdır.',
        'numeric' => ':attribute :min - :max arasında olmalıdır.',
        'string'  => ':attribute :min - :max karakter arasında olmalıdır.',
    ],
    'boolean'              => ':attribute alanı yalnızca doğru veya yanlış olabilir.',
    'confirmed'            => ':attribute onayı eşleşmiyor.',
    'current_password'     => 'Şifre hatalı.',
    'date'                 => ':attribute geçerli bir tarih değil.',
    'date_equals'          => ':attribute, :date tarihine eşit olmalıdır.',
    'date_format'          => ':attribute, :format biçimine uymuyor.',
    'declined'             => ':attribute alanı reddedilmelidir.',
    'declined_if'          => ':other :value olduğunda :attribute alanı reddedilmelidir.',
    'different'            => ':attribute ile :other birbirinden farklı olmalıdır.',
    'digits'               => ':attribute :digits haneli olmalıdır.',
    'digits_between'       => ':attribute :min - :max hane arasında olmalıdır.',
    'dimensions'           => ':attribute geçersiz görsel boyutlarına sahip.',
    'distinct'             => ':attribute alanında tekrar eden bir değer var.',
    'doesnt_end_with'      => ':attribute şunlardan biriyle bitemez: :values.',
    'doesnt_start_with'    => ':attribute şunlardan biriyle başlayamaz: :values.',
    'email'                => ':attribute geçerli bir e-posta adresi olmalıdır.',
    'ends_with'            => ':attribute şunlardan biriyle bitmelidir: :values.',
    'enum'                 => 'Seçilen :attribute geçersiz.',
    'exists'               => 'Seçilen :attribute geçersiz.',
    'file'                 => ':attribute bir dosya olmalıdır.',
    'filled'               => ':attribute alanı doldurulmalıdır.',
    'gt'                   => [
        'array'   => ':attribute :value öğeden fazla içermelidir.',
        'file'    => ':attribute :value kilobayttan büyük olmalıdır.',
        'numeric' => ':attribute :value değerinden büyük olmalıdır.',
        'string'  => ':attribute :value karakterden uzun olmalıdır.',
    ],
    'gte'                  => [
        'array'   => ':attribute en az :value öğe içermelidir.',
        'file'    => ':attribute en az :value kilobayt olmalıdır.',
        'numeric' => ':attribute en az :value olmalıdır.',
        'string'  => ':attribute en az :value karakter olmalıdır.',
    ],
    'image'                => ':attribute bir görsel olmalıdır.',
    'in'                   => 'Seçilen :attribute geçersiz.',
    'in_array'             => ':attribute alanı :other içinde yok.',
    'integer'              => ':attribute bir tam sayı olmalıdır.',
    'ip'                   => ':attribute geçerli bir IP adresi olmalıdır.',
    'ipv4'                 => ':attribute geçerli bir IPv4 adresi olmalıdır.',
    'ipv6'                 => ':attribute geçerli bir IPv6 adresi olmalıdır.',
    'json'                 => ':attribute geçerli bir JSON metni olmalıdır.',
    'lt'                   => [
        'array'   => ':attribute :value öğeden az içermelidir.',
        'file'    => ':attribute :value kilobayttan küçük olmalıdır.',
        'numeric' => ':attribute :value değerinden küçük olmalıdır.',
        'string'  => ':attribute :value karakterden kısa olmalıdır.',
    ],
    'lte'                  => [
        'array'   => ':attribute en fazla :value öğe içermelidir.',
        'file'    => ':attribute en fazla :value kilobayt olmalıdır.',
        'numeric' => ':attribute en fazla :value olmalıdır.',
        'string'  => ':attribute en fazla :value karakter olmalıdır.',
    ],
    'mac_address'          => ':attribute geçerli bir MAC adresi olmalıdır.',
    'max'                  => [
        'array'   => ':attribute en fazla :max öğe içermelidir.',
        'file'    => ':attribute en fazla :max kilobayt olmalıdır.',
        'numeric' => ':attribute en fazla :max olmalıdır.',
        'string'  => ':attribute en fazla :max karakter olmalıdır.',
    ],
    'mimes'                => ':attribute şu türlerden biri olmalıdır: :values.',
    'mimetypes'            => ':attribute şu türlerden biri olmalıdır: :values.',
    'min'                  => [
        'array'   => ':attribute en az :min öğe içermelidir.',
        'file'    => ':attribute en az :min kilobayt olmalıdır.',
        'numeric' => ':attribute en az :min olmalıdır.',
        'string'  => ':attribute en az :min karakter olmalıdır.',
    ],
    'multiple_of'          => ':attribute, :value değerinin katı olmalıdır.',
    'not_in'               => 'Seçilen :attribute geçersiz.',
    'not_regex'            => ':attribute biçimi geçersiz.',
    'numeric'              => ':attribute bir sayı olmalıdır.',
    'password'             => [
        'letters'       => ':attribute en az bir harf içermelidir.',
        'mixed'         => ':attribute en az bir büyük ve bir küçük harf içermelidir.',
        'numbers'       => ':attribute en az bir rakam içermelidir.',
        'symbols'       => ':attribute en az bir sembol içermelidir.',
        'uncompromised' => 'Bu :attribute bir veri sızıntısında görüldü. Lütfen farklı bir :attribute seç.',
    ],
    'present'              => ':attribute alanı gönderilmelidir.',
    'prohibited'           => ':attribute alanı yasaktır.',
    'prohibited_if'        => ':other :value olduğunda :attribute alanı yasaktır.',
    'prohibited_unless'    => ':other :values içinde değilse :attribute alanı yasaktır.',
    'prohibits'            => ':attribute alanı :other alanının gönderilmesini engeller.',
    'regex'                => ':attribute biçimi geçersiz.',
    'required'             => ':attribute alanı zorunludur.',
    'required_array_keys'  => ':attribute alanı şu anahtarları içermelidir: :values.',
    'required_if'          => ':other :value olduğunda :attribute alanı zorunludur.',
    'required_if_accepted' => ':other kabul edildiğinde :attribute alanı zorunludur.',
    'required_unless'      => ':other :values içinde değilse :attribute alanı zorunludur.',
    'required_with'        => ':values gönderildiğinde :attribute alanı zorunludur.',
    'required_with_all'    => ':values gönderildiğinde :attribute alanı zorunludur.',
    'required_without'     => ':values gönderilmediğinde :attribute alanı zorunludur.',
    'required_without_all' => ':values alanlarından hiçbiri gönderilmediğinde :attribute alanı zorunludur.',
    'same'                 => ':attribute ile :other eşleşmelidir.',
    'size'                 => [
        'array'   => ':attribute :size öğe içermelidir.',
        'file'    => ':attribute :size kilobayt olmalıdır.',
        'numeric' => ':attribute :size olmalıdır.',
        'string'  => ':attribute :size karakter olmalıdır.',
    ],
    'starts_with'          => ':attribute şunlardan biriyle başlamalıdır: :values.',
    'string'               => ':attribute bir metin olmalıdır.',
    'timezone'             => ':attribute geçerli bir saat dilimi olmalıdır.',
    'unique'               => ':attribute daha önce alınmış.',
    'uploaded'             => ':attribute yüklenemedi.',
    'url'                  => ':attribute geçerli bir URL olmalıdır.',
    'uuid'                 => ':attribute geçerli bir UUID olmalıdır.',

    /*
    |--------------------------------------------------------------------------
    | Özel doğrulama mesajları
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'password' => [
            'min' => 'Şifre en az :min karakter olmalıdır.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Alan adları (mesajlarda :attribute yerine geçer)
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'email'        => 'e-posta',
        'password'     => 'şifre',
        'phone'        => 'telefon numarası',
        'code'         => 'doğrulama kodu',
        'device_id'    => 'cihaz kimliği',
        'name'         => 'ad',
        'platform'     => 'platform',
        'app_version'  => 'uygulama sürümü',
        'os_version'   => 'işletim sistemi sürümü',
        'device_model' => 'cihaz modeli',
        'locale'       => 'dil',
        'amount'       => 'tutar',
        'pickup_address'  => 'alınacak adres',
        'dropoff_address' => 'bırakılacak adres',
        'customer_name'   => 'ad',
        'customer_phone'  => 'telefon numarası',
        'kvkk_consent'    => 'KVKK onayı',
    ],

];
