<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Department;
use App\Enums\RoleType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'department', 'is_active', 'job_title'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'department' => Department::class,
        ];
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(RoleType|string $role): bool
    {
        $slug = $role instanceof RoleType ? $role->value : $role;

        return $this->roles->contains('slug', $slug);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(RoleType::Admin);
    }

    public function isEditor(): bool
    {
        return $this->hasRole(RoleType::Editor);
    }

    public function isContributor(): bool
    {
        return $this->hasRole(RoleType::Contributor);
    }

    public function isViewer(): bool
    {
        return $this->hasRole(RoleType::Viewer);
    }

    /** Admins and editors may edit every section of any report. */
    public function canEditAllSections(): bool
    {
        return $this->isAdmin() || $this->isEditor();
    }

    /** Only admins may approve & lock a report. */
    public function canApprove(): bool
    {
        return $this->isAdmin();
    }
}
