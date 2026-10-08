<?php

namespace App\Support;

/**
 * Códigos EAN-13. El de cada presentación lo registra el usuario (el de
 * fábrica, el que trae el envase); paraVariante() queda para los datos de
 * demostración y los productos sin código de fábrica.
 *
 * Formato: prefijo "20" + id de la variante en 10 dígitos + verificador.
 * GS1 reserva los prefijos 20-29 para uso interno: nunca chocan con el código
 * de fábrica de un producto. Sale del id y no del SKU porque el SKU cambia al
 * renombrar el producto, y una etiqueta pegada en la ropa no se puede cambiar.
 */
final class Ean13
{
    public const PREFIJO = '20';

    public static function paraVariante(int $id): string
    {
        $base = self::PREFIJO.str_pad((string) $id, 10, '0', STR_PAD_LEFT);

        return $base.self::digitoVerificador($base);
    }

    /**
     * Pesos 1 y 3 alternados desde la izquierda sobre los 12 primeros dígitos.
     */
    public static function digitoVerificador(string $doceDigitos): int
    {
        $suma = 0;
        foreach (str_split($doceDigitos) as $i => $digito) {
            $suma += (int) $digito * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $suma % 10) % 10;
    }

    /**
     * Lo que escribe o escanea el usuario, listo para guardar o buscar: sin
     * espacios ni guiones, y un UPC-A (12 dígitos, productos importados)
     * como EAN-13 con un 0 adelante (es el mismo código para el lector).
     */
    public static function normalizar(?string $codigo): ?string
    {
        if ($codigo === null) {
            return null;
        }

        $limpio = preg_replace('/[\s-]+/', '', $codigo);
        if ($limpio === '') {
            return null;
        }

        return preg_match('/^\d{12}$/', $limpio) ? '0'.$limpio : $limpio;
    }

    public static function esValido(string $codigo): bool
    {
        return preg_match('/^\d{13}$/', $codigo) === 1
            && self::digitoVerificador(substr($codigo, 0, 12)) === (int) $codigo[12];
    }
}
