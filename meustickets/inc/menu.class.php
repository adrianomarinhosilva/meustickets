<?php
/**
 * Classe do menu Meus Tickets
 */

class PluginMeusticketsMenu extends CommonGLPI {
    
    static $rightname = 'ticket';
    
    /**
     * Obter nome do menu
     */
    static function getMenuName() {
        return __('Meus Chamados', 'meustickets');
    }
    
    /**
     * Obter conteúdo do menu
     */
    static function getMenuContent() {
        global $CFG_GLPI;
        
        $menu = array();
        $menu['title'] = self::getMenuName();
        $menu['page'] = '/plugins/meustickets/front/meustickets.php';
        $menu['icon'] = 'fas fa-user-circle';
        $menu['options'] = array(
            'meustickets' => array(
                'title' => self::getMenuName(),
                'page' => '/plugins/meustickets/front/meustickets.php',
                'links' => array(
                    'search' => '/plugins/meustickets/front/meustickets.php'
                )
            )
        );
        
        return $menu;
    }
    
    /**
     * Verificar se o usuário pode ver o menu
     */
    static function canView() {
        // Verificar se o usuário tem permissão para pelo menos um tipo de item
        return Session::haveRight('ticket', READ) || 
               Session::haveRight('problem', READ) || 
               Session::haveRight('change', READ) || 
               Session::haveRight('project', READ);
    }
}
?>