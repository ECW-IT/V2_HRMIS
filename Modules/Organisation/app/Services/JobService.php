<?php

namespace Modules\Organisation\Services;

use Illuminate\Support\Facades\DB;
use Modules\Organisation\Exceptions\DuplicateName;
use Modules\Organisation\Models\Job;

final class JobService
{
    /**
     * @param  array{code: string, name: string}  $data
     */
    public function create(array $data): Job
    {
        return DB::transaction(function () use ($data) {
            $nameKey = self::nameKey($data['name']);
            $this->assertUniqueNameKey($nameKey);

            return Job::query()->create([
                'code' => $data['code'],
                'name' => $data['name'],
                'name_key' => $nameKey,
                'is_active' => true,
            ]);
        });
    }

    /**
     * @param  array{code?: string, name?: string, is_active?: bool}  $data
     */
    public function update(Job $job, array $data): Job
    {
        return DB::transaction(function () use ($job, $data) {
            $name = $data['name'] ?? $job->name;
            $nameKey = self::nameKey($name);

            if ($nameKey !== $job->name_key) {
                $this->assertUniqueNameKey($nameKey, $job->id);
            }

            $job->update([
                'code' => $data['code'] ?? $job->code,
                'name' => $name,
                'name_key' => $nameKey,
                'is_active' => $data['is_active'] ?? $job->is_active,
            ]);

            return $job->fresh();
        });
    }

    public static function nameKey(string $name): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($name)) ?? '';

        return mb_strtolower($collapsed);
    }

    private function assertUniqueNameKey(string $nameKey, ?int $ignoreId = null): void
    {
        $exists = Job::query()
            ->where('name_key', $nameKey)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw new DuplicateName('A job with this name already exists.');
        }
    }
}
