<?php

namespace Modules\CoreHr\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Modules\CoreHr\Models\Bank;
use Modules\CoreHr\Models\BankBranch;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        $banksPath = database_path('seeders/data/banks.csv');
        $branchesPath = database_path('seeders/data/bank_branches.csv');

        if (! File::exists($banksPath) || ! File::exists($branchesPath)) {
            $this->command?->warn('Bank CSV files not found — skipping BankSeeder.');

            return;
        }

        $banks = fopen($banksPath, 'r');
        $header = fgetcsv($banks);
        while (($row = fgetcsv($banks)) !== false) {
            $data = array_combine($header, $row);
            Bank::query()->updateOrCreate(
                ['code' => $data['code']],
                ['name' => $data['name'], 'is_active' => true],
            );
        }
        fclose($banks);

        $branches = fopen($branchesPath, 'r');
        $header = fgetcsv($branches);
        while (($row = fgetcsv($branches)) !== false) {
            $data = array_combine($header, $row);
            $bank = Bank::query()->where('code', $data['bank_code'])->first();
            if ($bank === null) {
                continue;
            }
            BankBranch::query()->updateOrCreate(
                ['bank_id' => $bank->id, 'code' => $data['code']],
                ['name' => $data['name']],
            );
        }
        fclose($branches);
    }
}
