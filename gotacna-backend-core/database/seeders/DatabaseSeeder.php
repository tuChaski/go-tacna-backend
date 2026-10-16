<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Empresa;
use App\Models\Ruta;
use App\Models\Paradero;
use App\Models\Micro;
use App\Models\Conductor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear Usuarios de prueba (Admin, Conductores, Pasajero)
        $admin = User::create([
            'name'     => 'Administrador Go-Tacna',
            'email'    => 'admin@gotacna.pe',
            'password' => Hash::make('admin123'),
            'role'     => 'admin',
            'activo'   => true,
        ]);

        $conductorUser1 = User::create([
            'name'     => 'Juan Pérez (Conductor 101)',
            'email'    => 'conductor1@gotacna.pe',
            'password' => Hash::make('driver123'),
            'role'     => 'conductor',
            'activo'   => true,
        ]);

        $conductorUser2 = User::create([
            'name'     => 'Carlos Mamani (Conductor 10B)',
            'email'    => 'conductor2@gotacna.pe',
            'password' => Hash::make('driver123'),
            'role'     => 'conductor',
            'activo'   => true,
        ]);

        $pasajero = User::create([
            'name'     => 'Pasajero Tacneño',
            'email'    => 'pasajero@gotacna.pe',
            'password' => Hash::make('user123'),
            'role'     => 'pasajero',
            'activo'   => true,
        ]);

        // 2. Crear Empresas de Transporte
        $empresa101 = Empresa::create([
            'nombre' => 'Empresa de Transportes Ruta 101 S.A.',
            'activo' => true,
        ]);

        $empresa10B = Empresa::create([
            'nombre' => 'Empresa de Transportes 10B Tacna',
            'activo' => true,
        ]);

        // 3. Crear Rutas
        $ruta101 = Ruta::create([
            'empresa_id'  => $empresa101->id,
            'nombre'      => 'Línea 101 (Gregorio Albarracín - Centro - Pocollay)',
            'descripcion' => 'Recorrido desde Viñani pasa por Bolognesi hasta Pocollay',
            'trazado'     => json_encode([
                ['lat' => -18.0500, 'lng' => -70.2500],
                ['lat' => -18.0139, 'lng' => -70.2511],
                ['lat' => -18.0050, 'lng' => -70.2350],
            ]),
        ]);

        $ruta10B = Ruta::create([
            'empresa_id'  => $empresa10B->id,
            'nombre'      => 'Línea 10B (Cono Sur - UNJBG - Terminal)',
            'descripcion' => 'Pasa por el campus universitario UNJBG y Terminal Terrestre',
            'trazado'     => json_encode([
                ['lat' => -18.0300, 'lng' => -70.2600],
                ['lat' => -18.0250, 'lng' => -70.2480],
            ]),
        ]);

        // 4. Crear Paraderos Clave de Tacna
        $paraderoUnjbg = Paradero::create([
            'nombre'   => 'Paradero UNJBG (Puerta Principal)',
            'latitud'  => -18.02500000,
            'longitud' => -70.24800000,
        ]);

        $paraderoCentro = Paradero::create([
            'nombre'   => 'Paradero Av. Bolognesi (Plaza Zela)',
            'latitud'  => -18.01390000,
            'longitud' => -70.25110000,
        ]);

        $paraderoTerminal = Paradero::create([
            'nombre'   => 'Paradero Terminal Terrestre',
            'latitud'  => -18.00800000,
            'longitud' => -70.25500000,
        ]);

        // Vinculación de Paraderos a la Ruta 101 con su Orden
        $ruta101->paraderos()->attach([
            $paraderoUnjbg->id    => ['orden' => 1],
            $paraderoCentro->id   => ['orden' => 2],
            $paraderoTerminal->id => ['orden' => 3],
        ]);

        // 5. Crear Micros y Asignación de Conductores
        $micro1 = Micro::create([
            'empresa_id' => $empresa101->id,
            'placa'      => 'Z1A-780',
            'activo'     => true,
        ]);

        $micro2 = Micro::create([
            'empresa_id' => $empresa10B->id,
            'placa'      => 'Z2B-450',
            'activo'     => true,
        ]);

        Conductor::create([
            'usuario_id' => $conductorUser1->id,
            'micro_id'   => $micro1->id,
            'ruta_id'    => $ruta101->id,
        ]);

        Conductor::create([
            'usuario_id' => $conductorUser2->id,
            'micro_id'   => $micro2->id,
            'ruta_id'    => $ruta10B->id,
        ]);
    }
}