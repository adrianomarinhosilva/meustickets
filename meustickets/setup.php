<?php
/**
 * Plugin Meus Tickets
 * 
 * @author Seu Nome
 * @version 1.0.0
 */

define('PLUGIN_MEUSTICKETS_VERSION', '1.0.0');

/**
 * Inicialização do plugin
 */
function plugin_init_meustickets() {
    global $PLUGIN_HOOKS;
    
    $PLUGIN_HOOKS['csrf_compliant']['meustickets'] = true;
    
    // Registrar o hook para modificar o menu
    $PLUGIN_HOOKS['menu_toadd']['meustickets'] = array('helpdesk' => 'PluginMeusticketsMenu');
}

/**
 * Obter nome do plugin
 */
function plugin_version_meustickets() {
    return array(
        'name'         => 'Meus Tickets',
        'version'      => PLUGIN_MEUSTICKETS_VERSION,
        'author'       => 'Seu Nome',
        'license'      => 'GPLv2+',
        'homepage'     => '',
        'requirements' => array(
            'glpi' => array(
                'min' => '9.5',
                'max' => '10.1'
            )
        )
    );
}

/**
 * Verificar pré-requisitos antes da instalação
 */
function plugin_meustickets_check_prerequisites() {
    return true;
}

/**
 * Verificar configuração do plugin
 */
function plugin_meustickets_check_config() {
    return true;
}

/**
 * Instalar plugin
 */
function plugin_meustickets_install() {
    return true;
}

/**
 * Desinstalar plugin
 */
function plugin_meustickets_uninstall() {
    return true;
}
?>