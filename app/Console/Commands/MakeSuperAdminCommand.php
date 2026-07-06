<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * FerXGo süper admin hesabı oluştur / şifresini sıfırla.
 *
 *   php artisan ferxgo:make-admin
 *   php artisan ferxgo:make-admin --email=ferdi@ferxgo.com.tr --password=Xyz --name="Ferdi Korkmaz"
 *   php artisan ferxgo:make-admin --email=ferdi@ferxgo.com.tr --reset-password  (var olan hesabın şifresini sıfırla)
 */
class MakeSuperAdminCommand extends Command
{
    protected $signature = 'ferxgo:make-admin
        {--email= : Admin e-postası}
        {--password= : Şifre (verilmezse rastgele üretilir)}
        {--name= : Ad Soyad}
        {--reset-password : Var olan hesabın şifresini sıfırla}';

    protected $description = 'FerXGo /admin paneline giriş yetkisi olan bir süper admin hesabı oluşturur veya şifreyi sıfırlar.';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Admin e-postası');
        $email = strtolower(trim($email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Geçersiz e-posta.');
            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        // Reset password mode
        if ($this->option('reset-password')) {
            if (! $existing) {
                $this->error('Bu e-posta ile hesap yok.');
                return self::FAILURE;
            }
            $password = $this->option('password') ?: \Illuminate\Support\Str::random(12);
            $existing->update([
                'password' => Hash::make($password),
                'type'     => 'admin',
                'status'   => 'active',
            ]);
            $this->info('  ✓ Şifre sıfırlandı.');
            $this->line('     E-posta:  ' . $email);
            $this->line('     Şifre:    ' . $password);
            return self::SUCCESS;
        }

        if ($existing) {
            $this->warn('  ⚠ Bu e-posta ile bir hesap zaten var (type=' . $existing->type . ').');
            if (! $this->confirm('  Var olan hesabı süper admin yap ve şifreyi güncelle?', true)) {
                return self::SUCCESS;
            }
        }

        $name = $this->option('name') ?: ($existing?->name ?? $this->ask('Ad Soyad', 'FerXGo Admin'));
        $password = $this->option('password') ?: \Illuminate\Support\Str::random(12);

        $data = [
            'name'     => $name,
            'password' => Hash::make($password),
            'type'     => 'admin',
            'status'   => 'active',
        ];

        if ($existing) {
            $existing->update($data);
        } else {
            User::create([...$data, 'email' => $email]);
        }

        $this->info('  ✓ Süper admin hazır. /admin ekranından giriş yapabilirsin.');
        $this->newLine();
        $this->line('     ┌─────────────────────────────────────────────');
        $this->line('     │  URL:      ' . config('app.url', 'https://appnew.randevumcepte.com.tr') . '/admin');
        $this->line('     │  E-posta:  ' . $email);
        $this->line('     │  Şifre:    ' . $password);
        $this->line('     └─────────────────────────────────────────────');
        $this->newLine();
        $this->warn('     ⚠ Şifreyi güvenli bir yere kaydet — bir daha görüntülenmez.');

        return self::SUCCESS;
    }
}
