<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder ensures all existing users have wallets.
     * New users will automatically get wallets via User::getOrCreateWallet()
     */
    public function run(): void
    {
        $this->command->info('Creating wallets for users...');

        // Get all users without wallets
        $usersWithoutWallets = User::doesntHave('wallet')->get();

        $count = 0;
        foreach ($usersWithoutWallets as $user) {
            $user->getOrCreateWallet();
            $count++;
        }

        $this->command->info("Created {$count} wallets for existing users.");
    }
}
