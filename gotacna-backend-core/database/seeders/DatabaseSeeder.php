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

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $empresaTacna = Empresa::create([
            'ruc' => '20123456789',
            'razon_social' => 'Transportes Tacna S.A.C.',
            'telefono' => '052-123456',
            'direccion' => 'Av. Bolognesi 123, Tacna',
            'activo' => true,
        ]);

        $empresaSur = Empresa::create([
            'ruc' => '20987654321',
            'razon_social' => 'Rutas del Sur E.I.R.L.',
            'telefono' => '052-654321',
            'direccion' => 'Av. Leguía 456, Tacna',
            'activo' => true,
        ]);

        $adminGeneral = Usuario::create([
            'dni' => '70000001',
            'nombre' => 'Administrador',
            'apellido' => 'General',
            'correo' => 'admin@gotacna.pe',
            'telefono' => '999111222',
            'password_hash' => 'admin123',
            'rol' => 'admin_general',
            'activo' => true,
        ]);

        $adminEmpresa = Usuario::create([
            'dni' => '70000002',
            'nombre' => 'Administrador',
            'apellido' => 'Empresa',
            'correo' => 'admin.empresa@gotacna.pe',
            'telefono' => '999222333',
            'password_hash' => 'empresa123',
            'rol' => 'admin_empresa',
            'activo' => true,
        ]);

        AdministradorEmpresaPerfil::create([
            'usuario_id' => $adminEmpresa->id,
            'empresa_id' => $empresaTacna->id,
            'cargo' => 'Gerente de operaciones',
        ]);

        $conductor1User = Usuario::create([
            'dni' => '70000003',
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
            'correo' => 'conductor1@gotacna.pe',
            'telefono' => '999333444',
            'password_hash' => 'conductor123',
            'rol' => 'conductor',
            'activo' => true,
        ]);

        $conductor1 = ConductorPerfil::create([
            'usuario_id' => $conductor1User->id,
            'empresa_id' => $empresaTacna->id,
            'licencia_conducir' => 'Q12345678',
            'estado_operativo' => 'disponible',
        ]);

        $conductor2User = Usuario::create([
            'dni' => '70000004',
            'nombre' => 'Carlos',
            'apellido' => 'Mamani',
            'correo' => 'conductor2@gotacna.pe',
            'telefono' => '999444555',
            'password_hash' => 'conductor123',
            'rol' => 'conductor',
            'activo' => true,
        ]);

        $conductor2 = ConductorPerfil::create([
            'usuario_id' => $conductor2User->id,
            'empresa_id' => $empresaSur->id,
            'licencia_conducir' => 'Q87654321',
            'estado_operativo' => 'disponible',
        ]);

        $pasajero = Usuario::create([
            'dni' => '70000005',
            'nombre' => 'María',
            'apellido' => 'Quispe',
            'correo' => 'pasajero@gotacna.pe',
            'telefono' => '999555666',
            'password_hash' => 'pasajero123',
            'rol' => 'pasajero',
            'activo' => true,
        ]);

        $planBasico = Plan::create([
            'nombre_plan' => 'Plan Básico',
            'costo' => 5.00,
            'activo' => true,
        ]);

        Plan::create([
            'nombre_plan' => 'Plan Estudiante',
            'costo' => 3.50,
            'activo' => true,
        ]);

        Plan::create([
            'nombre_plan' => 'Plan Mensual',
            'costo' => 25.00,
            'activo' => true,
        ]);

        $ruta1 = Ruta::create([
            'empresa_id' => $empresaTacna->id,
            'nombre_ruta' => 'Ruta Centro - Mercado',
            'activo' => true,
        ]);

        Ruta::guardarTrazado($ruta1->id, [
            'type' => 'LineString',
            'coordinates' => [
                [-70.246500, -18.014000],
                [-70.241000, -18.019500],
                [-70.235500, -18.025000],
                [-70.230000, -18.030500],
            ],
        ]);

        $ruta2 = Ruta::create([
            'empresa_id' => $empresaSur->id,
            'nombre_ruta' => 'Ruta Terminal - Ciudad Nueva',
            'activo' => true,
        ]);

        Ruta::guardarTrazado($ruta2->id, [
            'type' => 'LineString',
            'coordinates' => [
                [-70.252000, -18.010000],
                [-70.247000, -18.015000],
                [-70.242000, -18.020000],
            ],
        ]);

        $micro1 = Micro::create([
            'empresa_id' => $empresaTacna->id,
            'ruta_id' => $ruta1->id,
            'conductor_actual_id' => $conductor1->id,
            'placa' => 'V1A-101',
            'capacidad_pasajeros' => 22,
            'estado' => 'disponible',
        ]);

        Micro::create([
            'empresa_id' => $empresaSur->id,
            'ruta_id' => $ruta2->id,
            'conductor_actual_id' => $conductor2->id,
            'placa' => 'V2B-202',
            'capacidad_pasajeros' => 20,
            'estado' => 'disponible',
        ]);

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

        Suscripcion::create([
            'usuario_id' => $pasajero->id,
            'plan_id' => $planBasico->id,
            'fecha_inicio' => now()->toDateString(),
            'fecha_vencimiento' => now()->addDays(30)->toDateString(),
            'estado' => 'activa',
        ]);

        $this->command?->info('Datos de prueba creados. Admin: admin@gotacna.pe / admin123');
    }
}
