<?php
/**
 * josrafsmultienvio
 * Copyright (C) 2026 Josra
 */

namespace FacturaScripts\Plugins\josrafsmultienvio\Extension\Controller;

use Closure;
use FacturaScripts\Plugins\josrafsmultienvio\Lib\Josrafs\Multienvio;

/**
 * Al enviar un documento, si la cuenta elegida tiene formato asignado, el PDF adjunto
 * se vuelve a generar con ese formato (logo, título y texto de pie de esa cuenta).
 *
 * Ojo: todos los métodos de una extensión tienen que devolver un Closure, así que el
 * trabajo de verdad está en la clase Lib\Josrafs\Multienvio.
 *
 * @author Josra
 */
class SendMail
{
    /**
     * Se ejecuta con la cuenta ya elegida, justo antes de mandar el correo.
     */
    public function send(): Closure
    {
        return function () {
            $cuenta = method_exists($this->newMail, 'cuentaJosrafs') ? $this->newMail->cuentaJosrafs() : null;
            if (null === $cuenta || empty($cuenta->idformato)) {
                return null;
            }

            $documentos = Multienvio::cargarDocumentos(
                (string)$this->request->queryOrInput('modelClassName', ''),
                (string)$this->request->queryOrInput('modelCode', ''),
                (string)$this->request->queryOrInput('modelCodes', '')
            );
            if (empty($documentos)) {
                return null;
            }

            Multienvio::regenerarAdjunto(
                $documentos,
                (string)$this->request->queryOrInput('fileName', ''),
                (int)$cuenta->idformato
            );

            return null;
        };
    }

    /**
     * Se ejecuta cuando el correo ya ha salido: apuntamos con qué formato se envió.
     */
    public function updateFemail(): Closure
    {
        return function () {
            $cuenta = method_exists($this->newMail, 'cuentaJosrafs') ? $this->newMail->cuentaJosrafs() : null;
            if (null === $cuenta || empty($cuenta->idformato)) {
                return null;
            }

            $documentos = Multienvio::cargarDocumentos(
                (string)$this->request->queryOrInput('modelClassName', ''),
                (string)$this->request->queryOrInput('modelCode', ''),
                (string)$this->request->queryOrInput('modelCodes', '')
            );
            if (empty($documentos)) {
                return null;
            }

            Multienvio::recordarEnvio($documentos, (int)$cuenta->idformato, (int)$cuenta->id, (string)$cuenta->email);

            return null;
        };
    }
}
