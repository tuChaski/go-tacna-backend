<?php

namespace Database\Seeders;

use App\Models\AdministradorEmpresaPerfil;
use App\Models\ConductorPerfil;
use App\Models\Empresa;
use App\Models\Micro;
use App\Models\Plan;
use App\Models\PosicionGps;
use App\Models\Ruta;
use App\Models\Suscripcion;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración para desarrollo local.
 *
 * El seeder es idempotente: se puede ejecutar en cada arranque sin duplicar
 * registros ni fallar por restricciones únicas. En producción no se ejecuta
 * porque el entrypoint solo lo invoca cuando SEED_DEMO_DATA=true.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $empresaTacna = Empresa::updateOrCreate(
            ['ruc' => '20123456789'],
            [
                'razon_social' => 'Transportes Tacna S.A.C.',
                'telefono' => '052-123456',
                'direccion' => 'Av. Bolognesi 123, Tacna',
                'activo' => true,
            ]
        );

        $empresaSur = Empresa::updateOrCreate(
            ['ruc' => '20987654321'],
            [
                'razon_social' => 'Rutas del Sur E.I.R.L.',
                'telefono' => '052-654321',
                'direccion' => 'Av. Leguía 456, Tacna',
                'activo' => true,
            ]
        );

        Usuario::updateOrCreate(
            ['correo' => 'admin@gotacna.pe'],
            [
                'dni' => '70000001',
                'nombre' => 'Administrador',
                'apellido' => 'General',
                'telefono' => '999111222',
                'password_hash' => 'admin123',
                'rol' => 'admin_general',
                'activo' => true,
            ]
        );

        $adminEmpresa = Usuario::updateOrCreate(
            ['correo' => 'admin.empresa@gotacna.pe'],
            [
                'dni' => '70000002',
                'nombre' => 'Administrador',
                'apellido' => 'Empresa',
                'telefono' => '999222333',
                'password_hash' => 'empresa123',
                'rol' => 'admin_empresa',
                'activo' => true,
            ]
        );

        AdministradorEmpresaPerfil::updateOrCreate(
            [
                'usuario_id' => $adminEmpresa->id,
                'empresa_id' => $empresaTacna->id,
            ],
            ['cargo' => 'Gerente de operaciones']
        );

        $conductor1User = Usuario::updateOrCreate(
            ['correo' => 'conductor1@gotacna.pe'],
            [
                'dni' => '70000003',
                'nombre' => 'Juan',
                'apellido' => 'Pérez',
                'telefono' => '999333444',
                'password_hash' => 'conductor123',
                'rol' => 'conductor',
                'activo' => true,
            ]
        );

        $conductor1 = ConductorPerfil::updateOrCreate(
            ['usuario_id' => $conductor1User->id],
            [
                'empresa_id' => $empresaTacna->id,
                'licencia_conducir' => 'Q12345678',
                'estado_operativo' => 'disponible',
            ]
        );

        $conductor2User = Usuario::updateOrCreate(
            ['correo' => 'conductor2@gotacna.pe'],
            [
                'dni' => '70000004',
                'nombre' => 'Carlos',
                'apellido' => 'Mamani',
                'telefono' => '999444555',
                'password_hash' => 'conductor123',
                'rol' => 'conductor',
                'activo' => true,
            ]
        );

        $conductor2 = ConductorPerfil::updateOrCreate(
            ['usuario_id' => $conductor2User->id],
            [
                'empresa_id' => $empresaSur->id,
                'licencia_conducir' => 'Q87654321',
                'estado_operativo' => 'disponible',
            ]
        );

        $pasajero = Usuario::updateOrCreate(
            ['correo' => 'pasajero@gotacna.pe'],
            [
                'dni' => '70000005',
                'nombre' => 'María',
                'apellido' => 'Quispe',
                'telefono' => '999555666',
                'password_hash' => 'pasajero123',
                'rol' => 'pasajero',
                'activo' => true,
            ]
        );

        $planBasico = Plan::updateOrCreate(
            ['nombre_plan' => 'Plan Básico'],
            ['costo' => 5.00, 'activo' => true]
        );

        Plan::updateOrCreate(
            ['nombre_plan' => 'Plan Estudiante'],
            ['costo' => 3.50, 'activo' => true]
        );

        Plan::updateOrCreate(
            ['nombre_plan' => 'Plan Mensual'],
            ['costo' => 25.00, 'activo' => true]
        );

        $ruta1 = Ruta::updateOrCreate(
            [
                'empresa_id' => $empresaTacna->id,
                'nombre_ruta' => 'Ruta Centro - Mercado',
            ],
            ['activo' => true]
        );

        Ruta::guardarTrazado($ruta1->id, [
            'type' => 'LineString',
            'coordinates' => [
                [-70.246500, -18.014000],
                [-70.241000, -18.019500],
                [-70.235500, -18.025000],
                [-70.230000, -18.030500],
            ],
        ]);

        $ruta2 = Ruta::updateOrCreate(
            [
                'empresa_id' => $empresaSur->id,
                'nombre_ruta' => 'Ruta Terminal - Ciudad Nueva',
            ],
            ['activo' => true]
        );

        Ruta::guardarTrazado($ruta2->id, [
            'type' => 'LineString',
            'coordinates' => [
                [-70.252000, -18.010000],
                [-70.247000, -18.015000],
                [-70.242000, -18.020000],
            ],
        ]);

        $micro1 = Micro::updateOrCreate(
            ['placa' => 'V1A-101'],
            [
                'empresa_id' => $empresaTacna->id,
                'ruta_id' => $ruta1->id,
                'conductor_actual_id' => $conductor1->id,
                'capacidad_pasajeros' => 22,
                'estado' => 'disponible',
            ]
        );

        Micro::updateOrCreate(
            ['placa' => 'V2B-202'],
            [
                'empresa_id' => $empresaSur->id,
                'ruta_id' => $ruta2->id,
                'conductor_actual_id' => $conductor2->id,
                'capacidad_pasajeros' => 20,
                'estado' => 'disponible',
            ]
        );

        // Las posiciones son un histórico: solo se crean si el micro aún no
        // tiene ninguna, para no acumular filas en cada arranque.
        if (! PosicionGps::where('micro_id', $micro1->id)->exists()) {
            PosicionGps::create([
                'micro_id' => $micro1->id,
                'latitud' => -18.014000,
                'longitud' => -70.246500,
                'velocidad' => 0,
                'fecha_hora' => now()->subMinutes(5),
            ]);

            PosicionGps::create([
                'micro_id' => $micro1->id,
                'latitud' => -18.019500,
                'longitud' => -70.241000,
                'velocidad' => 28.5,
                'fecha_hora' => now(),
            ]);
        }

        Suscripcion::updateOrCreate(
            [
                'usuario_id' => $pasajero->id,
                'plan_id' => $planBasico->id,
                'estado' => 'activa',
            ],
            [
                'fecha_inicio' => now()->toDateString(),
                'fecha_vencimiento' => now()->addDays(30)->toDateString(),
            ]
        );

        $this->command?->info(
            'Datos de demostración sincronizados. '
            .'Admin general: admin@gotacna.pe / admin123.'
        );
    }
}
