<?php
/**
 * josrafsmultienvio
 * Copyright (C) 2026 Josra
 */

namespace FacturaScripts\Plugins\josrafsmultienvio\Extension\Controller;

use Closure;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\josrafsmultienvio\Lib\Josrafs\Secretos;
use FacturaScripts\Plugins\josrafsmultienvio\Model\JosrafsCuentaEmail;

/**
 * Añade a Panel de control > Email las pestañas del multienvío: las cuentas de salida
 * y sus opciones. No se crea ninguna entrada nueva en el menú.
 *
 * @author Josra
 */
class ConfigEmail
{
    public function createViews(): Closure
    {
        return function () {
            $this->addListView('ListJosrafsCuentaEmail', 'JosrafsCuentaEmail', 'jmv-cuentas', 'fa-solid fa-at')
                ->addOrderBy(['orden', 'nombre'], 'jmv-orden', 1)
                ->addOrderBy(['nombre'], 'name')
                ->addOrderBy(['email'], 'email')
                ->addSearchFields(['email', 'host', 'nombre', 'remitente'])
                ->addFilterCheckbox('activa', 'jmv-activa', 'activa');

            $this->addEditView('EditJosrafsOpciones', 'Settings', 'jmv-opciones', 'fa-solid fa-sliders')
                ->setSettings('btnNew', false)
                ->setSettings('btnDelete', false);
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName === 'ListJosrafsCuentaEmail') {
                $view->loadData();

                // si hay cuentas y no se pueden cifrar las contraseñas, lo decimos
                $motivo = Secretos::motivo();
                if ($view->count > 0 && false === empty($motivo)) {
                    Tools::log()->warning($motivo);
                }

                return null;
            }

            if ($viewName === 'EditJosrafsOpciones') {
                $view->loadData('josrafsmultienvio');
                $view->model->name = 'josrafsmultienvio';
                return null;
            }

            return null;
        };
    }
}
