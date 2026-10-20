<?php

namespace App\Services\Catalog\Transfer;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TransferApiSettings
{
    public function config(): array
    {
        $config = config('catalog_import.config.svetofor_1c', []);
        if (parse_url($config['base_url'] ?? '', PHP_URL_HOST) === 'api.svetofor-mebel.ru') {
            $config['base_url'] = 'https://api.svetofor-mebel.ru';
        }
        if (Schema::hasTable('catalog_transfer_settings')) {
            $encrypted = DB::table('catalog_transfer_settings')->where('key', 'api_token')->value('value');
            if ($encrypted !== null) {
                $config['api_key'] = Crypt::decryptString($encrypted);
            }
        }

        return $config;
    }

    public function saveToken(string $token): void
    {
        validator(['token' => $token], ['token' => 'required|string|min:24|max:4096'])->validate();
        DB::table('catalog_transfer_settings')->updateOrInsert(['key' => 'api_token'], ['value' => Crypt::encryptString($token), 'created_at' => now(), 'updated_at' => now()]);
    }
}
