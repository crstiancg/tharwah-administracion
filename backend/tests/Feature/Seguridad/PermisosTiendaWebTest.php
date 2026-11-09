<?php

namespace Tests\Feature\Seguridad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * La migración que reparte los permisos de la tienda web y del libro de
 * reclamaciones a los roles de una base que ya está en uso.
 */
class PermisosTiendaWebTest extends TestCase
{
    use RefreshDatabase;

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_08_100000_dar_permisos_de_tienda_web_y_reclamaciones.php');
    }

    private function permisosDe(string $rol): array
    {
        return Role::findByName($rol, 'api')->permissions()->pluck('name')->sort()->values()->all();
    }

    public function test_suma_los_permisos_nuevos_sin_tocar_los_que_ya_habia(): void
    {
        $previo = Permission::create(['name' => 'pedidos.index', 'guard_name' => 'api']);
        Role::create(['name' => 'Administrador', 'guard_name' => 'api'])->givePermissionTo($previo);
        Role::create(['name' => 'Jefa de Ventas', 'guard_name' => 'api'])->givePermissionTo($previo);
        Role::create(['name' => 'Vendedor', 'guard_name' => 'api'])->givePermissionTo($previo);

        $this->migracion()->up();
        // Correrla de nuevo no duplica ni falla.
        $this->migracion()->up();

        $admin = $this->permisosDe('Administrador');
        $this->assertCount(16, $admin);
        $this->assertContains('pedidos.index', $admin);
        $this->assertContains('contenido-web.publicar', $admin);
        $this->assertContains('reclamaciones.responder', $admin);

        $this->assertSame(
            ['pedidos.index', 'reclamaciones.index', 'reclamaciones.responder', 'reclamaciones.show'],
            $this->permisosDe('Jefa de Ventas'),
        );
        $this->assertSame(['pedidos.index'], $this->permisosDe('Vendedor'));
        $this->assertSame('Libro de reclamaciones · Responder', Permission::findByName('reclamaciones.responder', 'api')->description);

        $this->migracion()->down();

        $this->assertSame(['pedidos.index'], $this->permisosDe('Administrador'));
        $this->assertSame(['pedidos.index'], $this->permisosDe('Jefa de Ventas'));
    }

    public function test_en_una_base_nueva_no_hace_nada(): void
    {
        $this->migracion()->up();

        $this->assertSame(0, Permission::count());
    }
}
