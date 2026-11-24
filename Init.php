<?php
/**
 * josrafsmultienvio
 * Copyright (C) 2026 Josra
 */

namespace FacturaScripts\Plugins\josrafsmultienvio;

use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Plugins\josrafsmultienvio\Model\JosrafsCuentaEmail;
use FacturaScripts\Plugins\josrafsmultienvio\Model\JosrafsEnvioDoc;

/**
 * @author Josra
 */
class Init extends InitClass
{
    public function init(): void
    {
        $this->loadExtension(new Extension\Controller\ConfigEmail());
        $this->loadExtension(new Extension\Controller\SendMail());
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
        // crea o repara las tablas del plugin
        new JosrafsCuentaEmail();
        new JosrafsEnvioDoc();
    }
}
