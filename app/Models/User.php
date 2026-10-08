<?php

namespace App\Models;

use App\Models\Concerns\HasArchivedBy;
use App\Services\PermissionService;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Lacodix\LaravelModelFilter\Traits\IsSearchable;
use Lacodix\LaravelModelFilter\Traits\IsSortable;
use Laravel\Sanctum\HasApiTokens;
use LaravelArchivable\Archivable;
use Overtrue\LaravelFavorite\Traits\Favoriter;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Traits\HasRoles;

/**
 * User
 *
 * Usuario de la aplicación. Traits: archivable, auditable, roles/permisos (Spatie),
 * searchable, sortable, favoriter (favoritos), API tokens, password reset
 */
class User extends Authenticatable implements AuditableContract, CanResetPasswordContract
{
    use Archivable, Auditable, CanResetPassword, Favoriter, HasApiTokens, HasArchivedBy, HasFactory, HasRoles, IsSearchable, IsSortable, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'job_title',
        'avatar',
        'phone',
        'google_id',
        'area_id',
        'archived_by_id',
    ];

    protected $searchable = [
        'name',
        'email',
        'job_title',
    ];

    protected $sortable = [
        'name' => 'asc',
        'email',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    /**
     * Área "principal" (columna users.area_id).
     * Se mantiene por compatibilidad; el acceso real se decide con areas().
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Todas las áreas a las que pertenece el usuario (tabla pivote area_user).
     */
    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class)->withTimestamps();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user_access');
    }

    public function subscribedToTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'subscribe_task');
    }

    // ─── Helpers de rol ───────────────────────────────────────────────────────

    public function getFirstName(): string
    {
        return Str::beforeLast($this->name, ' ');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isNotAdmin(): bool
    {
        return ! $this->isAdmin() && ! $this->isSuperAdmin();
    }

    /**
     * Devuelve true si el usuario puede operar dentro del área dada.
     * El superadmin puede operar en cualquier área.
     */
    public function belongsToArea(?int $areaId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $areaId !== null && in_array($areaId, $this->areaIds(), true);
    }

    // ─── Áreas ────────────────────────────────────────────────────────────────

    /** Cache por instancia: se consulta muchas veces por request en las policies. */
    protected ?array $cachedAreaIds = null;

    /**
     * IDs (int) de todas las áreas del usuario.
     *
     * @return int[]
     */
    public function areaIds(): array
    {
        if ($this->cachedAreaIds !== null) {
            return $this->cachedAreaIds;
        }

        $ids = $this->relationLoaded('areas')
            ? $this->areas->pluck('id')
            : $this->areas()->pluck('areas.id');

        return $this->cachedAreaIds = $ids->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * True si ambos usuarios comparten al menos un área.
     */
    public function sharesAreaWith(User $other): bool
    {
        return ! empty(array_intersect($this->areaIds(), $other->areaIds()));
    }

    /**
     * Reemplaza las áreas del usuario y mantiene users.area_id (área principal)
     * apuntando a una de ellas, para compatibilidad con el código existente.
     *
     * @param  array<int|string>  $areaIds
     */
    public function syncAreas(array $areaIds): void
    {
        $areaIds = collect($areaIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        $this->areas()->sync($areaIds);

        $current = $this->area_id === null ? null : (int) $this->area_id;
        $primary = in_array($current, $areaIds, true) ? $current : ($areaIds[0] ?? null);

        if ($current !== $primary) {
            $this->update(['area_id' => $primary]);
        }

        $this->cachedAreaIds = null;
        $this->unsetRelation('areas');
    }

    // ─── Acceso a proyectos ───────────────────────────────────────────────────
    // Usa PermissionService para obtener usuarios con acceso (superadmin + area admin + explícito)
    public function hasProjectAccess(Project $project): bool
    {
        $users = PermissionService::usersWithAccessToProject($project);

        return $users->pluck('id')->contains($this->id);
    }

    // ─── Dropdown helpers ─────────────────────────────────────────────────────

    /**
     * Lista de usuarios para dropdowns.
     * El superadmin ve todos (null); los demás solo ven usuarios de sus áreas.
     *
     * @param  int|int[]|null  $areaIds
     */
    public static function userDropdownValues(int|array|null $areaIds = null): array
    {
        $areaIds = $areaIds === null ? null : (array) $areaIds;

        return self::orderBy('name')
            ->when($areaIds !== null, fn ($q) => $q->whereHas('areas', fn ($a) => $a->whereIn('areas.id', $areaIds)))
            ->get(['id', 'name'])
            ->map(fn ($i) => ['value' => (string) $i->id, 'label' => $i->name])
            ->toArray();
    }
}
