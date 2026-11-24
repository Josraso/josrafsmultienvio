<?php
/**
 * josrafsmultienvio
 * Copyright (C) 2026 Josra
 */

namespace FacturaScripts\Plugins\josrafsmultienvio\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\FormatoDocumento;

/**
 * Deja apuntado con qué formato se envió cada documento, para que las copias
 * que se impriman o se reenvíen después salgan con el mismo logo.
 *
 * @author Josra
 */
class JosrafsEnvioDoc extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $codigo;

    /** @var string */
    public $email;

    /** @var string */
    public $fecha;

    /** @var int */
    public $id;

    /** @var int */
    public $idcuenta;

    /** @var int */
    public $idformato;

    /** @var string */
    public $modelo;

    /**
     * Devuelve el registro del documento, o null si ese documento no se ha enviado todavía.
     */
    public static function buscarDoc(string $modelo, string $codigo): ?self
    {
        if (empty($modelo) || empty($codigo)) {
            return null;
        }

        $registro = new static();
        if ($registro->loadWhere([Where::eq('modelo', $modelo), Where::eq('codigo', $codigo)])) {
            return $registro;
        }

        return null;
    }

    public function clear(): void
    {
        parent::clear();
        $this->fecha = Tools::dateTime();
    }

    public function install(): string
    {
        // dependencia necesaria para la clave foránea
        new FormatoDocumento();

        return parent::install();
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    /**
     * Apunta (o actualiza) el formato con el que se ha enviado un documento.
     */
    public static function recordar(string $modelo, string $codigo, ?int $idformato, ?int $idcuenta, ?string $email): bool
    {
        if (empty($modelo) || empty($codigo) || empty($idformato)) {
            return false;
        }

        $registro = static::buscarDoc($modelo, $codigo);
        if (null === $registro) {
            $registro = new static();
            $registro->modelo = $modelo;
            $registro->codigo = $codigo;
        }

        $registro->idformato = $idformato;
        $registro->idcuenta = $idcuenta;
        $registro->email = $email;
        $registro->fecha = Tools::dateTime();

        return $registro->save();
    }

    public static function tableName(): string
    {
        return 'josrafsmultienvio_envios';
    }

    public function test(): bool
    {
        $this->codigo = Tools::noHtml($this->codigo);
        $this->email = Tools::noHtml($this->email);
        $this->modelo = Tools::noHtml($this->modelo);

        return parent::test();
    }
}
