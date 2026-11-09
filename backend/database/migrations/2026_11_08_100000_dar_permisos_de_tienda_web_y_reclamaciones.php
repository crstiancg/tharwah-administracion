<?php

use App\Support\RutasProtegidas;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los permisos de la tienda web y del libro de reclamaciones para los roles
 * que ya existen. permisos:sync crea los permisos al desplegar pero no se los
 * da a nadie, y sin esto nadie vería las pantallas nuevas.
 *
 * Sólo suma: no toca otros roles, ni usuarios, ni los permisos que ya tenían.
 * En una base nueva no hay roles todavía y no hace nada (el PermissionSeeder
 * le da todo al Administrador).
 */
return new class extends Migration
{
    private const RECLAMACIONES = ['reclamaciones.index', 'reclamaciones.show', 'reclamaciones.responder'];

    private const TIENDA_WEB = [
        'contenido-web.index', 'contenido-web.show', 'contenido-web.update',
        'contenido-web.publicar', 'contenido-web.descartar', 'contenido-web.archivos',
        'productos-web.index', 'productos-web.show', 'productos-web.update',
        'productos-web.publicar', 'productos-web.descartar', 'productos-web.archivos',
    ];

    /** Rol => permisos que recibe. */
    private function asignaciones(): array
    {
        return [
            // El Administrador tiene todos los permisos del sistema.
            'Administrador' => [...self::TIENDA_WEB, ...self::RECLAMACIONES],
            // Atiende a los clientes: responde las hojas, no edita la web.
            'Jefa de Ventas' => self::RECLAMACIONES,
        ];
    }

    public function up(): void
    {
        $roles = Role::where('guard_name', 'api')->whereIn('name', array_keys($this->asignaciones()))->get();
        if ($roles->isEmpty()) {
            return;
        }

        $rutas = app(RutasProtegidas::class);
        foreach ($roles as $rol) {
            $permisos = collect($this->asignaciones()[$rol->name])->map(fn (string $nombre) => Permission::firstOrCreate(
                ['name' => $nombre, 'guard_name' => 'api'],
                ['description' => $rutas->describir($nombre)],
            ));
            $rol->givePermissionTo($permisos);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->asignaciones() as $nombre => $permisos) {
            $rol = Role::where('guard_name', 'api')->where('name', $nombre)->first();
            $existentes = Permission::where('guard_name', 'api')->whereIn('name', $permisos)->get();
            $rol?->revokePermissionTo($existentes);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
