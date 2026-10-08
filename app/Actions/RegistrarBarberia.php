<?php

namespace App\Actions;

use App\Enums\Rol;
use App\Models\Barberia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alta de una barbería nueva junto con su usuario dueño.
 * Se hace en una sola transacción para no dejar cuentas a medias.
 */
class RegistrarBarberia
{
    public const DIAS_DE_PRUEBA = 60;

    /**
     * @param  array{barberia: string, name: string, email: string, password: string}  $datos
     */
    public function __invoke(array $datos): User
    {
        return DB::transaction(function () use ($datos) {
            $barberia = Barberia::create([
                'nombre' => $datos['barberia'],
                'slug' => $this->slugUnico($datos['barberia']),
                'prueba_hasta' => now()->addDays(self::DIAS_DE_PRUEBA),
            ]);

            $usuario = new User([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'password' => $datos['password'],
            ]);
            // Fuera de fillable a propósito: solo el sistema decide a qué barbería pertenece y con qué rol.
            $usuario->barberia_id = $barberia->id;
            $usuario->rol = Rol::Dueno;
            $usuario->save();

            return $usuario;
        });
    }

    private function slugUnico(string $nombre): string
    {
        $base = Str::slug($nombre) ?: 'barberia';
        $slug = $base;

        while (Barberia::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
