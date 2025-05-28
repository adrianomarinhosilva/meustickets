<?php
/**
 * Hooks do plugin Meus Tickets
 */

/**
 * Hook para modificar o menu do helpdesk
 */
function plugin_meustickets_menu_helpdesk() {
    $menu = array();
    
    if (Session::haveRight('ticket', READ)) {
        $menu['meustickets'] = array(
            'title' => __('Meus Tickets', 'meustickets'),
            'page'  => '/plugins/meustickets/front/meustickets.php',
            'icon'  => 'fas fa-user-check',
            'links' => array(
                'search' => '/plugins/meustickets/front/meustickets.php'
            )
        );
    }
    
    return $menu;
}

/**
 * Hook para inserir JavaScript personalizado
 */
function plugin_meustickets_giveItem($type, $ID, $data, $num) {
    return "";
}

/**
 * Hook para modificar a ordem do menu
 */
function plugin_meustickets_MassiveActions($type) {
    return array();
}