<?php
/**
 * josrafsmultienvio
 * Copyright (C) 2026 Josra
 */

namespace FacturaScripts\Plugins\josrafsmultienvio\Lib\Email;

use Exception;
use FacturaScripts\Core\Lib\Email\NewMail as CoreNewMail;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\josrafsmultienvio\Model\JosrafsCuentaEmail;

/**
 * Añade al correo de FacturaScripts las cuentas de salida del multienvío:
 * las ofrece en el desplegable "Desde" y, cuando se elige una, manda el correo
 * con su servidor, su remitente, su firma, sus copias y su dirección de respuesta.
 *
 * @author Josra
 */
class NewMail extends CoreNewMail
{
    /** @var JosrafsCuentaEmail|null */
    protected $cuentaJmv = null;

    /**
     * Cuenta del multienvío que se está usando, o null si se envía con la cuenta de la instalación.
     */
    public function cuentaJosrafs(): ?JosrafsCuentaEmail
    {
        return $this->cuentaJmv;
    }

    public function getAvailableMailboxes(): array
    {
        $lista = parent::getAvailableMailboxes();

        $predeterminada = '';
        try {
            foreach (JosrafsCuentaEmail::activas() as $cuenta) {
                if (false === in_array($cuenta->email, $lista, true)) {
                    $lista[] = $cuenta->email;
                }
                if ($cuenta->predeterminada && empty($predeterminada)) {
                    $predeterminada = $cuenta->email;
                }
            }
        } catch (Exception $exception) {
            // si la tabla de cuentas no está lista, el correo de la instalación sigue funcionando
            Tools::log()->warning($exception->getMessage());
            return $lista;
        }

        // la cuenta marcada como predeterminada sale primera, para que venga ya elegida
        if (false === empty($predeterminada)) {
            $lista = array_values(array_diff($lista, [$predeterminada]));
            array_unshift($lista, $predeterminada);
        }

        return $lista;
    }

    public function setMailbox(string $emailFrom): CoreNewMail
    {
        parent::setMailbox($emailFrom);

        try {
            $cuenta = JosrafsCuentaEmail::porEmail($emailFrom);
        } catch (Exception $exception) {
            Tools::log()->warning($exception->getMessage());
            $this->cuentaJmv = null;
            return $this;
        }

        if (null === $cuenta) {
            // es la cuenta de la propia instalación: no tocamos nada
            $this->cuentaJmv = null;
            return $this;
        }

        // si ya habíamos aplicado esta misma cuenta, no repetimos copias ni respuestas
        if ($this->cuentaJmv && (int)$this->cuentaJmv->id === (int)$cuenta->id) {
            return $this;
        }

        $this->cuentaJmv = $cuenta;

        if (false === empty($cuenta->remitente)) {
            $this->fromName = $cuenta->remitente;
        }

        $this->mail->Mailer = empty($cuenta->mailer) ? 'smtp' : $cuenta->mailer;
        $this->mail->SMTPSecure = (string)$cuenta->enc;
        if ($this->mail->SMTPSecure) {
            $this->mail->SMTPAuth = true;
            $this->mail->AuthType = (string)$cuenta->authtype;
        }
        $this->mail->Host = (string)$cuenta->host;
        $this->mail->Port = (int)$cuenta->puerto;
        $this->mail->Username = empty($cuenta->usuario) ? (string)$cuenta->email : (string)$cuenta->usuario;
        $this->mail->Password = $cuenta->passwordPlano();
        $this->lowsecure = (bool)$cuenta->lowsecure;

        if (false === empty($cuenta->firma)) {
            $this->signature = $cuenta->firma;
        }

        if (false === empty($cuenta->replyto)) {
            $this->replyTo((string)$cuenta->replyto);
        }

        foreach (static::splitEmails((string)$cuenta->emailcc) as $email) {
            $this->cc($email);
        }

        foreach (static::splitEmails((string)$cuenta->emailbcc) as $email) {
            $this->bcc($email);
        }

        return $this;
    }
}
