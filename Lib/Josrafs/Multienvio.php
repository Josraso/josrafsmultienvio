<?php
/**
 * josrafsmultienvio
 * Copyright (C) 2026 Josra
 */

namespace FacturaScripts\Plugins\josrafsmultienvio\Lib\Josrafs;

use Exception;
use FacturaScripts\Core\Lib\Email\NewMail as CoreNewMail;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Model\FormatoDocumento;
use FacturaScripts\Plugins\josrafsmultienvio\Model\JosrafsEnvioDoc;

/**
 * Trabajo común de las extensiones del multienvío.
 *
 * @author Josra
 */
class Multienvio
{
    const MODELOS = '\\FacturaScripts\\Dinamic\\Model\\';

    /**
     * Carga los documentos sobre los que se está enviando el correo.
     *
     * @return array
     */
    public static function cargarDocumentos(string $modelClassName, string $modelCode, string $modelCodes = ''): array
    {
        if (empty($modelClassName)) {
            return [];
        }

        $className = self::MODELOS . $modelClassName;
        if (false === class_exists($className)) {
            return [];
        }

        $codigos = [];
        if (false === empty($modelCode)) {
            $codigos[] = $modelCode;
        }
        foreach (explode(',', $modelCodes) as $codigo) {
            if (false === empty($codigo)) {
                $codigos[] = $codigo;
            }
        }

        $documentos = [];
        foreach (array_unique($codigos) as $codigo) {
            $documento = new $className();
            if ($documento->load($codigo)) {
                $documentos[] = $documento;
            }
        }

        return $documentos;
    }

    /**
     * Vuelve a generar el PDF que se va a adjuntar, con el formato de la cuenta elegida.
     * Se escribe en el mismo archivo temporal, así que el adjunto que ya estaba puesto
     * sale con el logo nuevo y no se toca ningún otro archivo del correo.
     */
    public static function regenerarAdjunto(array $documentos, string $fileName, int $idformato): bool
    {
        if (empty($documentos) || empty($idformato)) {
            return false;
        }

        $ruta = self::rutaAdjunto($fileName);
        if (null === $ruta) {
            return false;
        }

        // comprobamos que el formato existe antes de rehacer nada
        $formato = new FormatoDocumento();
        if (false === $formato->load($idformato)) {
            Tools::log()->warning('jmv-formato-no-encontrado', ['%id%' => $idformato]);
            return false;
        }

        $titulo = (string)($documentos[0]->codigo ?? '');

        try {
            $exportManager = new ExportManager();
            $exportManager->newDoc('PDF', $titulo, $idformato, '');
            foreach ($documentos as $documento) {
                if (false === $exportManager->addBusinessDocPage($documento)) {
                    Tools::log()->warning('jmv-adjunto-no-regenerado');
                    return false;
                }
            }

            $pdf = $exportManager->getDoc();
        } catch (Exception $exception) {
            Tools::log()->warning('jmv-adjunto-no-regenerado');
            Tools::log()->warning($exception->getMessage());
            return false;
        }
        if (empty($pdf)) {
            Tools::log()->warning('jmv-adjunto-no-regenerado');
            return false;
        }

        if (false === file_put_contents($ruta, $pdf)) {
            Tools::log()->warning('jmv-adjunto-no-escrito', ['%file%' => basename($ruta)]);
            return false;
        }

        return true;
    }

    /**
     * Apunta con qué formato ha salido cada documento.
     */
    public static function recordarEnvio(array $documentos, ?int $idformato, ?int $idcuenta, ?string $email): void
    {
        if (empty($idformato) || empty(Tools::settings('josrafsmultienvio', 'recordar'))) {
            return;
        }

        try {
            foreach ($documentos as $documento) {
                JosrafsEnvioDoc::recordar(
                    $documento->modelClassName(),
                    (string)$documento->primaryColumnValue(),
                    $idformato,
                    $idcuenta,
                    $email
                );
            }
        } catch (Exception $exception) {
            // el correo ya ha salido: que no se pueda apuntar el formato no es motivo para fallar
            Tools::log()->warning($exception->getMessage());
        }
    }

    /**
     * Devuelve la ruta del PDF del documento dentro de la carpeta temporal de correo,
     * o null si el nombre no es uno de esos archivos. Nunca toca adjuntos subidos a mano.
     */
    private static function rutaAdjunto(string $fileName): ?string
    {
        $nombre = basename($fileName);
        if (empty($nombre) || false === strpos($nombre, '_mail_')
            || '.pdf' !== strtolower(substr($nombre, -4))) {
            return null;
        }

        $carpeta = realpath(FS_FOLDER . DIRECTORY_SEPARATOR . CoreNewMail::ATTACHMENTS_TMP_PATH);
        if (false === $carpeta) {
            return null;
        }

        $ruta = $carpeta . DIRECTORY_SEPARATOR . $nombre;
        if (false === file_exists($ruta) || false === is_writable($ruta)) {
            return null;
        }

        return $ruta;
    }
}
