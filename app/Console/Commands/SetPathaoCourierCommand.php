<?php

namespace App\Console\Commands;

use App\Services\Courier\PathaoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetPathaoCourierCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'set:pathao-courier 
                            {--client-id= : Pathao Client ID from merchant developer panel} 
                            {--client-secret= : Pathao Client Secret} 
                            {--username= : Pathao merchant account login email} 
                            {--password= : Pathao merchant account login password}';

    /**
     * The console command description.
     */
    protected $description = 'Set up Pathao Courier credentials, verify with Hermès API, and issue OAuth Bearer token (PRD §7.2)';

    /**
     * Execute the console command.
     */
    public function handle(PathaoService $pathaoService): int
    {
        $this->info('====================================================');
        $this->info(' TASA POS — Pathao Courier Integration Setup Wizard ');
        $this->info('====================================================');
        $this->line('');
        $this->comment('Pathao uses OAuth 2.0 with your Merchant Portal credentials.');
        $this->comment('You need your Client ID & Client Secret from: https://merchant.pathao.com');
        $this->line('');

        $clientId = $this->option('client-id') 
            ?: env('PATHAO_CLIENT_ID') 
            ?: $this->ask('Enter Pathao Client ID');

        $clientSecret = $this->option('client-secret') 
            ?: env('PATHAO_CLIENT_SECRET') 
            ?: $this->ask('Enter Pathao Client Secret');

        $username = $this->option('username') 
            ?: env('PATHAO_USERNAME') 
            ?: $this->ask('Enter Pathao Merchant Email (Username)');

        $password = $this->option('password') 
            ?: env('PATHAO_PASSWORD') 
            ?: $this->secret('Enter Pathao Merchant Password');

        if (! $clientId || ! $clientSecret || ! $username || ! $password) {
            $this->error('All credentials (Client ID, Client Secret, Username, Password) are required.');
            return self::FAILURE;
        }

        // Temporarily configure services for test
        config([
            'services.pathao.client_id' => $clientId,
            'services.pathao.client_secret' => $clientSecret,
            'services.pathao.username' => $username,
            'services.pathao.password' => $password,
        ]);

        $this->line('');
        $this->info('Authenticating with Pathao Hermès Merchant API...');

        $token = $pathaoService->issueToken();

        if (! $token) {
            $lastError = $pathaoService->getLastError() ?: 'Unknown error';
            $this->line('');
            $this->error("❌ Could not obtain OAuth token from Pathao: {$lastError}");
            $this->line('');
            $this->warn('Troubleshooting Tips:');
            $this->line(' 1. Check your Merchant Email and Password: they must match your actual login on https://merchant.pathao.com.');
            $this->line(' 2. Check Client ID and Client Secret: they are created in Pathao Merchant Panel -> Settings -> Developer API.');
            $this->line(' 3. In Pathao, there is no static "Secret Token" — the system automatically exchanges your credentials for an OAuth Bearer token.');
            $this->line('');
            return self::FAILURE;
        }

        $this->line('');
        $this->info('✅ Successfully authenticated with Pathao!');
        $this->info('OAuth Bearer access token saved to database (valid for 30 days).');

        // Fetch stores to verify permissions
        $stores = $pathaoService->getStores();
        $this->line('');
        $this->info('Available Merchant Stores:');
        foreach ($stores as $store) {
            $this->line(" - Store #{$store['store_id']}: {$store['store_name']} ({$store['store_address']})");
        }

        // Offer to update .env
        $this->updateEnvironmentFile([
            'PATHAO_CLIENT_ID' => $clientId,
            'PATHAO_CLIENT_SECRET' => $clientSecret,
            'PATHAO_USERNAME' => $username,
            'PATHAO_PASSWORD' => $password,
            'PATHAO_BASE_URL' => 'https://api-hermes.pathao.com',
            'PATHAO_SANDBOX' => 'false',
        ]);

        $this->line('');
        $this->info('🎉 Pathao Courier integration is now fully active in Tasa POS.');
        return self::SUCCESS;
    }

    /**
     * Update environment variables in the .env file.
     */
    protected function updateEnvironmentFile(array $values): void
    {
        $envPath = base_path('.env');
        if (! File::exists($envPath)) {
            return;
        }

        $content = File::get($envPath);

        foreach ($values as $key => $val) {
            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$val}", $content);
            } else {
                $content .= "\n{$key}={$val}";
            }
        }

        File::put($envPath, $content);
        $this->info('.env credentials updated.');
    }
}
