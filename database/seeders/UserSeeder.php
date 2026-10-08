<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    private array $jobTitleToRole = [
        'Superadministrador' => 'superadmin',
        'Frontend Developer' => 'developer',
        'Backend Developer' => 'developer',
        'Fullstack Developer' => 'developer',
        'QA Engineer' => 'qa engineer',
        'Designer' => 'designer',
        'Manager' => 'manager',
        'Owner' => 'admin',
    ];

    public function run(): void
    {
        User::factory()
            ->create([
                'email' => 'superadmin@mail.com',
                'job_title' => 'Superadministrador',
                'area_id' => null,
            ])
            ->assignRole('superadmin');

        $area = Area::first() ?? Area::factory()->create(['name' => 'Área de Prueba']);

        $rolesExceptSuperadmin = array_filter(
            RoleSeeder::$roles,
            fn ($r) => $r !== 'superadmin'
        );

        foreach ($rolesExceptSuperadmin as $role) {
            $user = User::factory()
                ->create([
                    'email' => "$role@mail.com",
                    'job_title' => $this->getJobTitle($role),
                    'area_id' => $area->id,
                ]);
            $user->assignRole($role);
            $user->areas()->attach($area->id);
        }

        User::factory(20)
            ->create(['area_id' => $area->id])
            ->each(function (User $user) use ($area) {
                $user->assignRole($this->jobTitleToRole[$user->job_title]);
                $user->areas()->attach($area->id);
            });
    }

    private function getJobTitle(string $role): string
    {
        foreach ($this->jobTitleToRole as $title => $value) {
            if ($role === $value) {
                return $title;
            }
        }

        return 'Employee';
    }
}
