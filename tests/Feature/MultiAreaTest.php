<?php

use App\Actions\User\UpdateUser;
use App\Http\Requests\User\StoreUserRequest;
use App\Models\Area;
use App\Models\Project;
use App\Models\User;
use App\Services\PermissionService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function resetPermissionCaches(): void
{
    foreach (['usersWithAccessToProject' => [], 'projectsThatUserCanAccess' => null] as $prop => $value) {
        $r = new ReflectionProperty(PermissionService::class, $prop);
        $r->setAccessible(true);
        $r->setValue(null, $value);
    }
}

function makeUser(string $role, array $areas): User
{
    $user = User::factory()->create(['area_id' => $areas[0]->id ?? null]);
    $user->assignRole($role);
    $user->syncAreas(collect($areas)->pluck('id')->all());

    return $user->fresh();
}

function makeProject(string $name, Area $area): Project
{
    $id = DB::table('projects')->insertGetId([
        'name' => $name, 'area_id' => $area->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return Project::find($id);
}

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
    resetPermissionCaches();

    $this->a = Area::create(['name' => 'A']);
    $this->b = Area::create(['name' => 'B']);
    $this->c = Area::create(['name' => 'C']);

    $this->pa = makeProject('PA', $this->a);
    $this->pb = makeProject('PB', $this->b);
    $this->pc = makeProject('PC', $this->c);
});

it('el admin con dos áreas ve los proyectos de ambas y no los de otras', function () {
    $admin = makeUser('admin', [$this->a, $this->b]);

    $names = PermissionService::projectsThatUserCanAccess($admin)->pluck('name')->sort()->values()->all();
    expect($names)->toBe(['PA', 'PB']);

    expect($admin->belongsToArea($this->a->id))->toBeTrue()
        ->and($admin->belongsToArea($this->b->id))->toBeTrue()
        ->and($admin->belongsToArea($this->c->id))->toBeFalse();

    expect($admin->hasProjectAccess($this->pb))->toBeTrue();
    resetPermissionCaches();
    expect($admin->hasProjectAccess($this->pc))->toBeFalse();
    expect($admin->can('view', $this->pb))->toBeTrue()
        ->and($admin->can('view', $this->pc))->toBeFalse();
});

it('un usuario común con dos áreas solo ve proyectos asignados explícitamente', function () {
    $dev = makeUser('developer', [$this->a, $this->b]);
    $this->pb->users()->attach($dev->id);

    expect($dev->can('view', $this->pb))->toBeTrue()
        ->and($dev->can('view', $this->pa))->toBeFalse();
});

it('la migración no rompe a un usuario con una sola área (comportamiento anterior)', function () {
    $admin = makeUser('admin', [$this->a]);

    expect(PermissionService::projectsThatUserCanAccess($admin)->pluck('name')->all())->toBe(['PA'])
        ->and($admin->area_id)->toBe($this->a->id);
});

it('UserPolicy: un admin gestiona usuarios con los que comparte al menos un área', function () {
    $adminA = makeUser('admin', [$this->a]);
    $userAB = makeUser('developer', [$this->a, $this->b]);
    $userB = makeUser('developer', [$this->b]);

    expect($adminA->can('update', $userAB))->toBeTrue()
        ->and($adminA->can('update', $userB))->toBeFalse();
});

it('al editar, un admin no puede quitar áreas que no administra', function () {
    $adminA = makeUser('admin', [$this->a]);
    $userAB = makeUser('developer', [$this->a, $this->b]);
    $this->actingAs($adminA);

    $result = UpdateUser::resolveAreaIds($userAB, [(string) $this->a->id]);
    sort($result);

    expect($result)->toBe([$this->a->id, $this->b->id]);
});

it('el superadmin reemplaza las áreas libremente', function () {
    $super = makeUser('superadmin', []);
    $userAB = makeUser('developer', [$this->a, $this->b]);
    $this->actingAs($super);

    expect(UpdateUser::resolveAreaIds($userAB, [$this->c->id]))->toBe([$this->c->id]);
});

it('validación: un admin no puede asignar un área que no tiene', function () {
    $adminA = makeUser('admin', [$this->a]);
    $this->actingAs($adminA);

    $rules = array_intersect_key((new StoreUserRequest)->rules(), array_flip(['area_ids', 'area_ids.*']));

    expect(Validator::make(['area_ids' => [(string) $this->a->id]], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['area_ids' => [(string) $this->b->id]], $rules)->passes())->toBeFalse()
        ->and(Validator::make(['area_ids' => []], $rules)->passes())->toBeFalse();
});

it('syncAreas mantiene users.area_id como área principal válida', function () {
    $user = makeUser('developer', [$this->a, $this->b]);
    expect($user->area_id)->toBe($this->a->id);

    $user->syncAreas([$this->b->id, $this->c->id]);
    expect($user->fresh()->area_id)->toBe($this->b->id)
        ->and($user->fresh()->areaIds())->toEqualCanonicalizing([$this->b->id, $this->c->id]);
});

it('dropdown de usuarios filtra por varias áreas', function () {
    makeUser('developer', [$this->a]);
    makeUser('developer', [$this->b]);
    makeUser('developer', [$this->c]);

    expect(count(User::userDropdownValues([$this->a->id, $this->b->id])))->toBe(2)
        ->and(count(User::userDropdownValues(null)))->toBe(3);
});

it('un área con usuarios (aunque estén archivados) no se puede borrar permanentemente', function () {
    $user = makeUser('developer', [$this->a]);
    $user->archive();

    expect($this->a->users()->withArchived()->exists())->toBeTrue()
        ->and($this->c->users()->withArchived()->exists())->toBeFalse();
});

it('las pantallas de usuarios, áreas y proyectos cargan para un admin con dos áreas', function () {
    $admin = makeUser('admin', [$this->a, $this->b]);
    $other = makeUser('developer', [$this->a, $this->c]);
    $this->actingAs($admin);

    $this->get(route('users.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.user.area_ids', [$this->a->id, $this->b->id])
            ->has('shared.areas', 2));
    $this->get(route('users.edit', $other))->assertOk()
        ->assertInertia(fn ($page) => $page->has('item.areas', 2));
    $this->get(route('areas.index'))->assertOk();
    $this->get(route('projects.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('items', 2));
    $this->get(route('projects.create'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('dropdowns.areas', 2));
});
