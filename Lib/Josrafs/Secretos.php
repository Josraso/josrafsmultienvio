<?php
/**
 * josrafsmultienvio
 * Copyright (C) 2026 Josra
 */

namespace FacturaScripts\Plugins\josrafsmultienvio\Lib\Josrafs;

use FacturaScripts\Core\Tools;

/**
 * Cifra y descifra las contraseñas de las cuentas de correo.
 *
 * La clave de cifrado la gestiona el plugin solo, sin pasos manuales:
 *   1. Si el config.php ya define JOSRAFS_MULTIENVIO_KEY, se usa esa.
 *   2. Si no, se genera una y se añade al config.php (igual que hace FacturaScripts
 *      con su propia clave): se escribe en un temporal y se renombra, para no dejar
 *      nunca un config.php a medias.
 *   3. Si el config.php no se puede escribir, se guarda en MyFiles/josrafsmultienvio.key.
 *   4. Si no hay openssl en el servidor, no se cifra y se avisa en pantalla.
 *
 * @author Josra
 */
class Secretos
{
    const PREFIJO = 'jmv1:';
    const CONSTANTE = 'JOSRAFS_MULTIENVIO_KEY';
    const ARCHIVO_CLAVE = 'MyFiles/josrafsmultienvio.key';
    const BYTES_CLAVE = 32;

    /** @var string|null */
    private static $clave = null;

    /** @var string */
    private static $motivo = '';

    /**
     * Cifra un texto. Si no se puede cifrar lo devuelve tal cual, para no perder el dato.
     */
    public static function cifrar(?string $texto): ?string
    {
        if (null === $texto || '' === $texto) {
            return $texto;
        }

        // ya está cifrado, no lo volvemos a cifrar
        if (self::estaCifrado($texto)) {
            return $texto;
        }

        if (false === self::sePuedeCifrar()) {
            return $texto;
        }

        $clave = self::clave();
        $iv = openssl_random_pseudo_bytes(12);
        $tag = '';
        $cifrado = openssl_encrypt($texto, 'aes-256-gcm', hex2bin($clave), OPENSSL_RAW_DATA, $iv, $tag);
        if (false === $cifrado) {
            return $texto;
        }

        return self::PREFIJO . base64_encode($iv . $tag . $cifrado);
    }

    /**
     * Descifra un texto cifrado por este plugin. Si no está cifrado lo devuelve tal cual.
     */
    public static function descifrar(?string $texto): ?string
    {
        if (null === $texto || '' === $texto || false === self::estaCifrado($texto)) {
            return $texto;
        }

        if (false === extension_loaded('openssl')) {
            return '';
        }

        $clave = self::clave();
        if (empty($clave)) {
            return '';
        }

        $datos = base64_decode(substr($texto, strlen(self::PREFIJO)), true);
        if (false === $datos || strlen($datos) < 29) {
            return '';
        }

        $iv = substr($datos, 0, 12);
        $tag = substr($datos, 12, 16);
        $cifrado = substr($datos, 28);
        $plano = openssl_decrypt($cifrado, 'aes-256-gcm', hex2bin($clave), OPENSSL_RAW_DATA, $iv, $tag);

        return false === $plano ? '' : $plano;
    }

    public static function estaCifrado(string $texto): bool
    {
        return 0 === strpos($texto, self::PREFIJO);
    }

    /**
     * Clave de traducción del motivo por el que no se puede cifrar, para enseñarlo
     * en pantalla. Cadena vacía si todo va bien.
     */
    public static function motivo(): string
    {
        self::sePuedeCifrar();

        return self::$motivo;
    }

    public static function sePuedeCifrar(): bool
    {
        if (false === extension_loaded('openssl')) {
            self::$motivo = 'jmv-sin-openssl';
            return false;
        }

        if (empty(self::clave())) {
            self::$motivo = 'jmv-sin-clave';
            return false;
        }

        self::$motivo = '';
        return true;
    }

    /**
     * Devuelve la clave de cifrado en hexadecimal, creándola si hace falta.
     */
    private static function clave(): string
    {
        if (null !== self::$clave) {
            return self::$clave;
        }

        self::$clave = '';

        // 1. la constante del config.php
        if (defined(self::CONSTANTE)) {
            $valor = (string)constant(self::CONSTANTE);
            if (self::claveValida($valor)) {
                self::$clave = $valor;
                return self::$clave;
            }
        }

        // 2. el archivo de clave, si ya existe
        $archivo = FS_FOLDER . DIRECTORY_SEPARATOR . self::ARCHIVO_CLAVE;
        if (file_exists($archivo)) {
            $valor = trim((string)file_get_contents($archivo));
            if (self::claveValida($valor)) {
                self::$clave = $valor;
                return self::$clave;
            }
        }

        // 3. generamos una nueva y la guardamos donde se pueda
        $nueva = bin2hex(random_bytes(self::BYTES_CLAVE));
        if (self::guardarEnConfig($nueva) || self::guardarEnArchivo($nueva)) {
            self::$clave = $nueva;
        }

        return self::$clave;
    }

    private static function claveValida(string $valor): bool
    {
        return strlen($valor) === self::BYTES_CLAVE * 2 && ctype_xdigit($valor);
    }

    /**
     * Añade la constante al config.php. Devuelve false si no se puede tocar ese archivo.
     */
    private static function guardarEnConfig(string $clave): bool
    {
        $archivo = FS_FOLDER . DIRECTORY_SEPARATOR . 'config.php';
        if (false === file_exists($archivo) || false === is_writable($archivo)
            || false === is_writable(dirname($archivo)) || is_link($archivo)) {
            return false;
        }

        $contenido = file_get_contents($archivo);
        if (false === is_string($contenido) || 0 !== strpos($contenido, '<?php')) {
            return false;
        }
        if (false !== strpos($contenido, self::CONSTANTE)) {
            // ya está definida pero no era válida: no la tocamos
            return false;
        }

        $linea = "\ndefine('" . self::CONSTANTE . "', '" . $clave . "');";
        $nuevo = '<?php' . $linea . substr($contenido, strlen('<?php'));

        $temporal = $archivo . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (false === file_put_contents($temporal, $nuevo)) {
            @unlink($temporal);
            return false;
        }

        @chmod($temporal, fileperms($archivo) & 0777);
        if (false === @rename($temporal, $archivo)) {
            @unlink($temporal);
            return false;
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($archivo, true);
        }

        // la constante no está definida en esta petición, pero el valor ya lo tenemos en memoria
        return true;
    }

    private static function guardarEnArchivo(string $clave): bool
    {
        Tools::folderCheckOrCreate(FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles');
        $archivo = FS_FOLDER . DIRECTORY_SEPARATOR . self::ARCHIVO_CLAVE;
        if (false === file_put_contents($archivo, $clave)) {
            return false;
        }

        @chmod($archivo, 0600);
        return true;
    }
}
