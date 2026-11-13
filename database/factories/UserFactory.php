<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Organisation\Models\Company;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'person_id' => null,
            'is_active' => true,
            'all_companies' => false,
            'must_change_password' => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * @param  array<int, Company|int>  $companies
     */
    public function forCompanies(array $companies): static
    {
        return $this->afterCreating(function (User $user) use ($companies) {
            $ids = collect($companies)->map(fn ($company) => $company instanceof Company ? $company->id : $company)->all();
            $user->companies()->sync($ids);
        });
    }

    public function allCompanies(): static
    {
        return $this->state(fn (array $attributes) => [
            'all_companies' => true,
        ]);
    }

    public function withRole(string $role): static
    {
        return $this->afterCreating(function (User $user) use ($role) {
            Role::findOrCreate($role);
            $user->assignRole($role);
        });
    }

    public function withPermissions(string ...$names): static
    {
        return $this->afterCreating(function (User $user) use ($names) {
            foreach ($names as $name) {
                Permission::findOrCreate($name);
            }
            $user->givePermissionTo($names);
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
