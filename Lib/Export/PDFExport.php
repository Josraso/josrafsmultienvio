<?php
/**
 * josrafsmultienvio
 * Copyright (C) 2026 Josra
 */

namespace FacturaScripts\Plugins\josrafsmultienvio\Lib\Export;

use Exception;
use FacturaScripts\Core\Lib\Export\PDFExport as CorePDFExport;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\FormatoDocumento;
use FacturaScripts\Plugins\josrafsmultienvio\Model\JosrafsEnvioDoc;

/**
 * Cuando la opción "recordar el formato usado" está activada, los documentos que ya
 * se enviaron se imprimen con el mismo formato con el que salieron, para que la copia
 * que se imprime o se reenvía lleve el mismo logo que recibió el cliente.
 *
 * Si en el momento de imprimir se elige un formato a mano, manda el elegido: FacturaScripts
 * solo pregunta por el formato del documento cuando no se le ha dado ninguno.
 *
 * @author Josra
 */
class PDFExport extends CorePDFExport
{
    protected function getDocumentFormat($model)
    {
        try {
            $recordado = $this->formatoRecordadoJmv($model);
        } catch (Exception $exception) {
            Tools::log()->warning($exception->getMessage());
            $recordado = null;
        }
        if (null !== $recordado) {
            return $recordado;
        }

        return parent::getDocumentFormat($model);
    }

    private function formatoRecordadoJmv($model): ?FormatoDocumento
    {
        if (empty(Tools::settings('josrafsmultienvio', 'recordar'))) {
            return null;
        }

        if (false === is_object($model) || false === method_exists($model, 'modelClassName')
            || false === method_exists($model, 'primaryColumnValue')) {
            return null;
        }

        $codigo = (string)$model->primaryColumnValue();
        $envio = JosrafsEnvioDoc::buscarDoc($model->modelClassName(), $codigo);
        if (null === $envio || empty($envio->idformato)) {
            return null;
        }

        $formato = new FormatoDocumento();
        if (false === $formato->load($envio->idformato)) {
            return null;
        }

        return $formato;
    }
}
