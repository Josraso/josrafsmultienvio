<?php
/**
 * josrafsmultienvio
 * Copyright (C) 2026 Josra
 */

namespace FacturaScripts\Plugins\josrafsmultienvio\Controller;

use Exception;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Plugins\josrafsmultienvio\Lib\Josrafs\Secretos;
use FacturaScripts\Plugins\josrafsmultienvio\Model\JosrafsCuentaEmail;

/**
 * Ficha de una cuenta de correo de salida. No sale en el menú: se abre desde
 * Panel de control > Email > Cuentas de envío.
 *
 * @author Josra
 */
class EditJosrafsCuentaEmail extends EditController
{
    public function getModelClassName(): string
    {
        return 'JosrafsCuentaEmail';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'jmv-cuenta';
        $data['icon'] = 'fa-solid fa-at';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function createViews(): void
    {
        parent::createViews();

        $mvn = $this->getMainViewName();
        $this->setSettings($mvn, 'btnOptions', false);
        $this->setSettings($mvn, 'btnPrint', false);
    }

    /**
     * Crea una copia desactivada de la cuenta, para cambiarle el email y activarla.
     */
    protected function duplicarAction(): void
    {
        if (false === $this->validateFormToken()) {
            return;
        } elseif (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return;
        }

        $cuenta = $this->cuentaActual();
        if (null === $cuenta) {
            return;
        }

        $copia = new JosrafsCuentaEmail();
        foreach ($cuenta->getModelFields() as $campo => $datos) {
            if ($campo === 'id') {
                continue;
            }
            $copia->{$campo} = $cuenta->{$campo};
        }

        $copia->activa = false;
        $copia->predeterminada = false;
        $copia->nombre = Tools::textBreak((string)$cuenta->nombre . ' (' . Tools::trans('jmv-copia') . ')', 50);
        $copia->testfecha = null;
        $copia->testok = null;

        if (false === $copia->save()) {
            Tools::log()->warning('record-save-error');
            return;
        }

        Tools::log()->notice('jmv-duplicada');
        $this->redirect($copia->url());
    }

    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'jmv-duplicar':
                $this->duplicarAction();
                break;

            case 'jmv-test':
                $this->testAction();
                break;
        }

        return parent::execPreviousAction($action);
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $mvn = $this->getMainViewName();

        switch ($viewName) {
            case $mvn:
                parent::loadData($viewName, $view);

                // si solo hay una empresa, la columna no aporta nada
                if ($this->empresa->count() < 2) {
                    $view->disableColumn('company');
                }

                if ($view->model->exists()) {
                    $this->addButton($viewName, [
                        'action' => 'jmv-test',
                        'color' => 'info',
                        'icon' => 'fa-solid fa-envelope',
                        'label' => 'test'
                    ]);

                    $this->addButton($viewName, [
                        'action' => 'jmv-duplicar',
                        'color' => 'secondary',
                        'icon' => 'fa-solid fa-clone',
                        'label' => 'jmv-duplicar'
                    ]);
                }

                $motivo = Secretos::motivo();
                if (false === empty($motivo)) {
                    Tools::log()->warning($motivo);
                }
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    /**
     * Comprueba la conexión con el servidor de correo de esta cuenta y deja apuntado el resultado.
     */
    protected function testAction(): void
    {
        if (false === $this->validateFormToken()) {
            return;
        }

        $cuenta = $this->cuentaActual();
        if (null === $cuenta) {
            return;
        }

        if ($cuenta->mailer !== 'smtp') {
            Tools::log()->warning('test-' . $cuenta->mailer . '-not-implemented');
            return;
        }

        $correcto = false;
        try {
            $mail = new NewMail();
            $mail->setMailbox((string)$cuenta->email);
            $correcto = $mail->test();
        } catch (Exception $exception) {
            Tools::log()->error($exception->getMessage());
        }

        $cuenta->testfecha = Tools::dateTime();
        $cuenta->testok = $correcto;
        $cuenta->save();

        if ($correcto) {
            Tools::log()->notice('mail-test-ok');
            return;
        }

        Tools::log()->warning('mail-test-error');
    }

    private function cuentaActual(): ?JosrafsCuentaEmail
    {
        $codigo = (string)$this->request->queryOrInput('code', '');
        $cuenta = new JosrafsCuentaEmail();
        if (empty($codigo) || false === $cuenta->load($codigo)) {
            Tools::log()->warning('record-not-found');
            return null;
        }

        return $cuenta;
    }
}
