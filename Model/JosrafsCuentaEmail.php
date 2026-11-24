<?php
/**
 * josrafsmultienvio
 * Copyright (C) 2026 Josra
 */

namespace FacturaScripts\Plugins\josrafsmultienvio\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Validator;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FormatoDocumento;
use FacturaScripts\Plugins\josrafsmultienvio\Lib\Josrafs\Secretos;

/**
 * Cuenta de correo de salida: su servidor SMTP y el formato de documento que usa.
 *
 * @author Josra
 */
class JosrafsCuentaEmail extends ModelClass
{
    use ModelTrait;

    /** @var bool */
    public $activa;

    /** @var string */
    public $authtype;

    /** @var string */
    public $email;

    /** @var string */
    public $emailbcc;

    /** @var string */
    public $emailcc;

    /** @var string */
    public $enc;

    /** @var string */
    public $firma;

    /** @var string */
    public $host;

    /** @var int */
    public $id;

    /** @var int */
    public $idempresa;

    /** @var int */
    public $idformato;

    /** @var bool */
    public $lowsecure;

    /** @var string */
    public $mailer;

    /** @var string */
    public $nombre;

    /** @var int */
    public $orden;

    /** @var string */
    public $password;

    /** @var bool */
    public $predeterminada;

    /** @var int */
    public $puerto;

    /** @var string */
    public $remitente;

    /** @var string */
    public $replyto;

    /** @var string */
    public $testfecha;

    /** @var bool */
    public $testok;

    /** @var string */
    public $usuario;

    /**
     * Devuelve las cuentas activas, la predeterminada primero.
     *
     * @return static[]
     */
    public static function activas(): array
    {
        return static::all(
            [Where::eq('activa', true)],
            ['predeterminada' => 'DESC', 'orden' => 'ASC', 'email' => 'ASC'],
            0,
            0
        );
    }

    public function clear(): void
    {
        parent::clear();
        $this->activa = true;
        $this->enc = 'ssl';
        $this->lowsecure = true;
        $this->mailer = 'smtp';
        $this->orden = 100;
        $this->predeterminada = false;
        $this->puerto = 465;
    }

    public function install(): string
    {
        // dependencias necesarias para las claves foráneas
        new Empresa();
        new FormatoDocumento();

        return parent::install();
    }

    /**
     * Devuelve la contraseña en claro, lista para dársela a PHPMailer.
     */
    public function passwordPlano(): string
    {
        return (string)Secretos::descifrar($this->password);
    }

    /**
     * Busca una cuenta activa por su dirección de correo.
     */
    public static function porEmail(?string $email): ?self
    {
        if (empty($email)) {
            return null;
        }

        foreach (static::activas() as $cuenta) {
            if (0 === strcasecmp((string)$cuenta->email, $email)) {
                return $cuenta;
            }
        }

        return null;
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'nombre';
    }

    public function save(): bool
    {
        if (false === parent::save()) {
            return false;
        }

        // solo puede haber una cuenta predeterminada
        if ($this->predeterminada) {
            foreach (static::all([Where::eq('predeterminada', true)], [], 0, 0) as $otra) {
                if ($otra->id != $this->id) {
                    $otra->predeterminada = false;
                    $otra->save();
                }
            }
        }

        return true;
    }

    public static function tableName(): string
    {
        return 'josrafsmultienvio_cuentas';
    }

    public function test(): bool
    {
        $this->authtype = Tools::noHtml($this->authtype);
        $this->email = strtolower(trim((string)Tools::noHtml($this->email)));
        $this->emailbcc = Tools::noHtml($this->emailbcc);
        $this->emailcc = Tools::noHtml($this->emailcc);
        $this->firma = Tools::noHtml($this->firma);
        $this->host = Tools::noHtml($this->host);
        $this->nombre = Tools::noHtml($this->nombre);
        $this->remitente = Tools::noHtml($this->remitente);
        $this->replyto = Tools::noHtml($this->replyto);
        $this->usuario = Tools::noHtml($this->usuario);

        if (empty($this->nombre)) {
            $this->nombre = $this->email;
        }

        if (false === Validator::email($this->email)) {
            Tools::log()->warning('jmv-email-no-valido', ['%email%' => $this->email]);
            return false;
        }

        if (empty($this->remitente)) {
            $this->remitente = $this->nombre;
        }

        if ($this->mailer === 'smtp' && empty($this->host)) {
            Tools::log()->warning('jmv-falta-host');
            return false;
        }

        if (false === empty($this->replyto) && false === Validator::email((string)$this->replyto)) {
            Tools::log()->warning('jmv-replyto-no-valido', ['%email%' => $this->replyto]);
            return false;
        }

        // dos cuentas activas con el mismo email dejarían el envío a suertes
        if ($this->activa) {
            foreach (static::all([Where::eq('email', $this->email), Where::eq('activa', true)], [], 0, 0) as $otra) {
                if ($otra->id != $this->id) {
                    Tools::log()->warning('jmv-email-repetido', ['%email%' => $this->email]);
                    return false;
                }
            }
        }

        $this->orden = empty($this->orden) ? 100 : (int)$this->orden;
        $this->puerto = empty($this->puerto) ? 0 : (int)$this->puerto;

        // guardamos la contraseña cifrada
        $this->password = Secretos::cifrar($this->password);

        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'ConfigEmail?activetab=List'): string
    {
        return parent::url($type, $list);
    }
}
