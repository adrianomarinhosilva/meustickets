<?php
/**
 * Página principal do plugin Meus Tickets
 * Exibe tickets, problemas, mudanças e projetos do usuário
 */

include ('../../../inc/includes.php');

// Verificar autenticação
Session::checkLoginUser();

// Verificar permissões
if (!Session::haveRight('ticket', READ)) {
    Html::displayRightError();
}

// Iniciar HTML
Html::header(__('Meus Tickets', 'meustickets'), $_SERVER['PHP_SELF'], "helpdesk", "meustickets");

// Obter ID do usuário atual
$user_id = Session::getLoginUserID();

echo "<div class='center'>";

// Função para buscar itens
function buscarItens($itemtype, $user_id) {
    global $DB;
    
    $table = getTableForItemType($itemtype);
    $items = array();
    
    // Campos base para a busca
    $campos_usuario = array();
    
    switch($itemtype) {
        case 'Ticket':
            $campos_usuario = array(
                'users_id_requester' => 'Requerente',
                'users_id_assign' => 'Atribuído',
                'users_id_observer' => 'Observador'
            );
            break;
            
        case 'Problem':
            $campos_usuario = array(
                'users_id_requester' => 'Requerente',
                'users_id_assign' => 'Atribuído',
                'users_id_observer' => 'Observador'
            );
            break;
            
        case 'Change':
            $campos_usuario = array(
                'users_id_requester' => 'Requerente',
                'users_id_assign' => 'Atribuído',
                'users_id_observer' => 'Observador'
            );
            break;
            
        case 'Project':
            $campos_usuario = array(
                'users_id' => 'Gerente',
                'users_id_requester' => 'Requerente'
            );
            break;
    }
    
    // Construir query para cada tipo
    foreach($campos_usuario as $campo => $papel) {
        // Busca direta na tabela principal
        $query = "SELECT DISTINCT t.*, '$papel' as papel_usuario, '$itemtype' as tipo_item
                  FROM `$table` t 
                  WHERE t.`$campo` = '$user_id' 
                  AND t.`is_deleted` = 0";
        
        if ($itemtype != 'Project') {
            $query .= " AND t.`status` NOT IN (5, 6)"; // Não incluir resolvidos/fechados
        }
        
        $result = $DB->query($query);
        if ($result) {
            while ($row = $DB->fetchAssoc($result)) {
                $items[] = $row;
            }
        }
        
        // Busca na tabela de relacionamentos (users_id)
        $relation_table = $table . '_users';
        if ($DB->tableExists($relation_table)) {
            $query = "SELECT DISTINCT t.*, 
                             CASE tu.type 
                                WHEN 1 THEN 'Requerente'
                                WHEN 2 THEN 'Atribuído' 
                                WHEN 3 THEN 'Observador'
                                ELSE 'Relacionado'
                             END as papel_usuario,
                             '$itemtype' as tipo_item
                      FROM `$table` t 
                      INNER JOIN `$relation_table` tu ON tu.`" . strtolower($itemtype) . "s_id` = t.`id`
                      WHERE tu.`users_id` = '$user_id' 
                      AND t.`is_deleted` = 0";
            
            if ($itemtype != 'Project') {
                $query .= " AND t.`status` NOT IN (5, 6)";
            }
            
            $result = $DB->query($query);
            if ($result) {
                while ($row = $DB->fetchAssoc($result)) {
                    // Evitar duplicatas
                    $existe = false;
                    foreach($items as $item_existente) {
                        if ($item_existente['id'] == $row['id'] && $item_existente['tipo_item'] == $row['tipo_item']) {
                            $existe = true;
                            break;
                        }
                    }
                    if (!$existe) {
                        $items[] = $row;
                    }
                }
            }
        }
    }
    
    return $items;
}

// FUNÇÃO CORRIGIDA: Buscar tickets sem NENHUM usuário atribuído
function buscarTicketsSemUsuarioAtribuido() {
    global $DB;
    
    $items = array();
    
    // Query mais detalhada verificando todos os campos relacionados a usuários
    $query = "SELECT DISTINCT t.*, 'Sem Atribuição' as papel_usuario, 'Ticket' as tipo_item
              FROM `glpi_tickets` t 
              WHERE t.`is_deleted` = 0
              AND t.`status` NOT IN (5, 6)
              AND (
                  t.`users_id_recipient` IS NULL OR t.`users_id_recipient` = 0 OR t.`users_id_recipient` = ''
              )
              AND NOT EXISTS (
                  SELECT 1 FROM `glpi_tickets_users` tu 
                  WHERE tu.`tickets_id` = t.`id` 
                  AND tu.`type` = 2
              )";
    
    $result = $DB->query($query);
    if ($result) {
        while ($row = $DB->fetchAssoc($result)) {
            $items[] = $row;
        }
    }
    
    // Se não encontrou nada com a query restritiva, tentar uma versão mais ampla
    if (empty($items)) {
        $query = "SELECT DISTINCT t.*, 'Sem Atribuição' as papel_usuario, 'Ticket' as tipo_item
                  FROM `glpi_tickets` t 
                  WHERE t.`is_deleted` = 0
                  AND t.`status` NOT IN (5, 6)
                  AND NOT EXISTS (
                      SELECT 1 FROM `glpi_tickets_users` tu 
                      WHERE tu.`tickets_id` = t.`id` 
                      AND tu.`type` = 2
                  )";
        
        $result = $DB->query($query);
        if ($result) {
            while ($row = $DB->fetchAssoc($result)) {
                $items[] = $row;
            }
        }
    }
    
    // Debug - adicionar logging temporário para verificar
    error_log("Tickets sem atribuição encontrados: " . count($items));
    if (!empty($items)) {
        error_log("Primeiro ticket: ID " . $items[0]['id'] . " - " . $items[0]['name']);
    }
    
    return $items;
}

// Função para gerar o link correto baseado no tipo
function gerarLink($tipo, $id) {
    global $CFG_GLPI;
    
    switch($tipo) {
        case 'Ticket':
            return $CFG_GLPI['root_doc'] . '/front/ticket.form.php?id=' . $id;
        case 'Problem':
            return $CFG_GLPI['root_doc'] . '/front/problem.form.php?id=' . $id;
        case 'Change':
            return $CFG_GLPI['root_doc'] . '/front/change.form.php?id=' . $id;
        case 'Project':
            return $CFG_GLPI['root_doc'] . '/front/project.form.php?id=' . $id;
        default:
            return '#';
    }
}

// Função para obter nome da entidade
function obterNomeEntidade($entities_id) {
    if (empty($entities_id)) {
        return '-';
    }
    
    $entity = new Entity();
    if ($entity->getFromDB($entities_id)) {
        return $entity->getField('name');
    }
    
    return '-';
}

// Função para criar links de ordenação
function criarLinkOrdenacao($campo, $titulo, $campo_atual, $ordem_atual, $filtro) {
    // Definir campos válidos para cada tipo de item para evitar erros
    $campos_validos = array(
        'tipo' => true,
        'id' => true, 
        'name' => true,
        'date_creation' => true,
        'date_mod' => true,
        'papel' => true,
        'entities_id' => true
    );
    
    // Campos que podem não existir em todos os tipos
    $campos_condicionais = array(
        'status' => array('Ticket', 'Problem', 'Change'), // Projects não têm status
        'priority' => array('Ticket', 'Problem', 'Change') // Projects não têm prioridade
    );
    
    // Verificar se o campo é válido
    $campo_valido = isset($campos_validos[$campo]) || isset($campos_condicionais[$campo]);
    
    if (!$campo_valido) {
        // Se o campo não é válido, retornar apenas o título sem link
        return $titulo;
    }
    
    $nova_ordem = ($campo_atual == $campo && $ordem_atual == 'ASC') ? 'DESC' : 'ASC';
    $seta = '';
    if ($campo_atual == $campo) {
        $seta = ($ordem_atual == 'ASC') ? ' ↑' : ' ↓';
    }
    $filtro_param = (!empty($filtro) && $filtro != 'todos') ? '&filtro=' . $filtro : '';
    return "<a href='" . $_SERVER['PHP_SELF'] . "?sort=$campo&order=$nova_ordem$filtro_param' style='color: #333; text-decoration: none; font-weight: bold;'>" . $titulo . $seta . "</a>";
}

// Buscar todos os itens
$todos_itens = array();

// Obter parâmetros de filtro
$filtro_ativo = isset($_GET['filtro']) ? $_GET['filtro'] : '';

// Se o filtro for "tickets_sem_atribuicao", buscar apenas esses tickets
if ($filtro_ativo == 'tickets_sem_atribuicao') {
    $todos_itens = buscarTicketsSemUsuarioAtribuido();
} else {
    // Buscar Tickets normalmente
    if (Session::haveRight('ticket', READ)) {
        $tickets = buscarItens('Ticket', $user_id);
        $todos_itens = array_merge($todos_itens, $tickets);
    }

    // Buscar Problemas
    if (Session::haveRight('problem', READ)) {
        $problemas = buscarItens('Problem', $user_id);
        $todos_itens = array_merge($todos_itens, $problemas);
    }

    // Buscar Mudanças
    if (Session::haveRight('change', READ)) {
        $mudancas = buscarItens('Change', $user_id);
        $todos_itens = array_merge($todos_itens, $mudancas);
    }

    // Buscar Projetos
    if (Session::haveRight('project', READ)) {
        $projetos = buscarItens('Project', $user_id);
        $todos_itens = array_merge($todos_itens, $projetos);
    }
}

// Obter parâmetros de ordenação
$campo_ordenacao = isset($_GET['sort']) ? $_GET['sort'] : 'date_mod';
$ordem = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Ordenar os itens conforme solicitado
usort($todos_itens, function($a, $b) use ($campo_ordenacao, $ordem) {
    $valor_a = '';
    $valor_b = '';
    
    switch($campo_ordenacao) {
        case 'tipo':
            $valor_a = $a['tipo_item'];
            $valor_b = $b['tipo_item'];
            break;
        case 'id':
            $valor_a = (int)$a['id'];
            $valor_b = (int)$b['id'];
            break;
        case 'name':
            $valor_a = isset($a['name']) ? strtolower($a['name']) : '';
            $valor_b = isset($b['name']) ? strtolower($b['name']) : '';
            break;
        case 'status':
            // Verificar se o campo existe antes de tentar acessá-lo
            $valor_a = isset($a['status']) ? (int)$a['status'] : 999; // Valor alto para itens sem status
            $valor_b = isset($b['status']) ? (int)$b['status'] : 999;
            break;
        case 'priority':
            // Verificar se o campo existe antes de tentar acessá-lo
            $valor_a = isset($a['priority']) ? (int)$a['priority'] : 999; // Valor alto para itens sem prioridade
            $valor_b = isset($b['priority']) ? (int)$b['priority'] : 999;
            break;
        case 'papel':
            $valor_a = strtolower($a['papel_usuario']);
            $valor_b = strtolower($b['papel_usuario']);
            break;
        case 'entities_id':
            $valor_a = isset($a['entities_id']) ? (int)$a['entities_id'] : 0;
            $valor_b = isset($b['entities_id']) ? (int)$b['entities_id'] : 0;
            break;
        case 'date_creation':
            $valor_a = isset($a['date_creation']) ? $a['date_creation'] : '1970-01-01 00:00:00';
            $valor_b = isset($b['date_creation']) ? $b['date_creation'] : '1970-01-01 00:00:00';
            $valor_a = strtotime($valor_a);
            $valor_b = strtotime($valor_b);
            break;
        case 'date_mod':
        default:
            $valor_a = isset($a['date_mod']) ? $a['date_mod'] : (isset($a['date_creation']) ? $a['date_creation'] : '1970-01-01 00:00:00');
            $valor_b = isset($b['date_mod']) ? $b['date_mod'] : (isset($b['date_creation']) ? $b['date_creation'] : '1970-01-01 00:00:00');
            $valor_a = strtotime($valor_a);
            $valor_b = strtotime($valor_b);
            break;
    }
    
    // Comparar os valores
    if ($valor_a == $valor_b) {
        return 0;
    }
    
    if (is_numeric($valor_a) && is_numeric($valor_b)) {
        $resultado = ($valor_a < $valor_b) ? -1 : 1;
    } else {
        $resultado = strcmp($valor_a, $valor_b);
    }
    
    return ($ordem == 'ASC') ? $resultado : -$resultado;
});

// Calcular estatísticas
$stats = array(
    'Ticket' => 0,
    'Problem' => 0, 
    'Change' => 0,
    'Project' => 0
);

// Para estatísticas, sempre buscar os itens do usuário (não incluir tickets sem atribuição)
$itens_para_stats = array();
if (Session::haveRight('ticket', READ)) {
    $tickets = buscarItens('Ticket', $user_id);
    $itens_para_stats = array_merge($itens_para_stats, $tickets);
}
if (Session::haveRight('problem', READ)) {
    $problemas = buscarItens('Problem', $user_id);
    $itens_para_stats = array_merge($itens_para_stats, $problemas);
}
if (Session::haveRight('change', READ)) {
    $mudancas = buscarItens('Change', $user_id);
    $itens_para_stats = array_merge($itens_para_stats, $mudancas);
}
if (Session::haveRight('project', READ)) {
    $projetos = buscarItens('Project', $user_id);
    $itens_para_stats = array_merge($itens_para_stats, $projetos);
}

foreach($itens_para_stats as $item) {
    $stats[$item['tipo_item']]++;
}

// Calcular quantidade de tickets sem NENHUM usuário atribuído
$tickets_sem_atribuicao = buscarTicketsSemUsuarioAtribuido();
$count_tickets_sem_atribuicao = count($tickets_sem_atribuicao);

// Cards de estatísticas no topo
echo "<div class='row' style='margin-bottom: 15px;'>";

$url_base = $_SERVER['PHP_SELF'];
$sort_param = !empty($campo_ordenacao) ? "&sort=$campo_ordenacao&order=$ordem" : '';

// CARD: Tickets Sem Atribuição
echo "<div class='col-md-2'>";
$classe_ativa = ($filtro_ativo == 'tickets_sem_atribuicao') ? 'card-ativo' : '';
echo "<a href='{$url_base}?filtro=tickets_sem_atribuicao$sort_param' style='text-decoration: none;'>";
echo "<div class='card text-center filtro-card $classe_ativa' style='cursor: pointer; border: 1px solid #ddd; height: 50px;'>";
echo "<div class='card-body' style='padding: 5px 10px; display: flex; justify-content: space-between; align-items: center; height: 100%;'>";
echo "<i class='fas fa-exclamation-circle' style='font-size: 16px; color: #666;'></i>";
echo "<span style='font-size: 11px; color: #666;'>Sem Técnico Atribuido</span>";
echo "<span style='font-size: 18px; font-weight: bold; color: #333;'>$count_tickets_sem_atribuicao</span>";
echo "</div>";
echo "</div>";
echo "</a>";
echo "</div>";

echo "<div class='col-md-2'>";
$classe_ativa = ($filtro_ativo == 'Ticket') ? 'card-ativo' : '';
echo "<a href='{$url_base}?filtro=Ticket$sort_param' style='text-decoration: none;'>";
echo "<div class='card text-center filtro-card $classe_ativa' style='cursor: pointer; border: 1px solid #ddd; height: 50px;'>";
echo "<div class='card-body' style='padding: 5px 10px; display: flex; justify-content: space-between; align-items: center; height: 100%;'>";
echo "<i class='fas fa-ticket-alt' style='font-size: 16px; color: #666;'></i>";
echo "<span style='font-size: 12px; color: #666;'>Meus Tickets</span>";
echo "<span style='font-size: 18px; font-weight: bold; color: #333;'>".$stats['Ticket']."</span>";
echo "</div>";
echo "</div>";
echo "</a>";
echo "</div>";

echo "<div class='col-md-2'>";
$classe_ativa = ($filtro_ativo == 'Problem') ? 'card-ativo' : '';
echo "<a href='{$url_base}?filtro=Problem$sort_param' style='text-decoration: none;'>";
echo "<div class='card text-center filtro-card $classe_ativa' style='cursor: pointer; border: 1px solid #ddd; height: 50px;'>";
echo "<div class='card-body' style='padding: 5px 10px; display: flex; justify-content: space-between; align-items: center; height: 100%;'>";
echo "<i class='fas fa-bug' style='font-size: 16px; color: #666;'></i>";
echo "<span style='font-size: 12px; color: #666;'>Meus Problemas</span>";
echo "<span style='font-size: 18px; font-weight: bold; color: #333;'>".$stats['Problem']."</span>";
echo "</div>";
echo "</div>";
echo "</a>";
echo "</div>";

echo "<div class='col-md-2'>";
$classe_ativa = ($filtro_ativo == 'Change') ? 'card-ativo' : '';
echo "<a href='{$url_base}?filtro=Change$sort_param' style='text-decoration: none;'>";
echo "<div class='card text-center filtro-card $classe_ativa' style='cursor: pointer; border: 1px solid #ddd; height: 50px;'>";
echo "<div class='card-body' style='padding: 5px 10px; display: flex; justify-content: space-between; align-items: center; height: 100%;'>";
echo "<i class='fas fa-exchange-alt' style='font-size: 16px; color: #666;'></i>";
echo "<span style='font-size: 12px; color: #666;'>Minhas Mudanças</span>";
echo "<span style='font-size: 18px; font-weight: bold; color: #333;'>".$stats['Change']."</span>";
echo "</div>";
echo "</div>";
echo "</a>";
echo "</div>";

echo "<div class='col-md-2'>";
$classe_ativa = ($filtro_ativo == 'Project') ? 'card-ativo' : '';
echo "<a href='{$url_base}?filtro=Project$sort_param' style='text-decoration: none;'>";
echo "<div class='card text-center filtro-card $classe_ativa' style='cursor: pointer; border: 1px solid #ddd; height: 50px;'>";
echo "<div class='card-body' style='padding: 5px 10px; display: flex; justify-content: space-between; align-items: center; height: 100%;'>";
echo "<i class='fas fa-project-diagram' style='font-size: 16px; color: #666;'></i>";
echo "<span style='font-size: 12px; color: #666;'>Meus Projetos</span>";
echo "<span style='font-size: 18px; font-weight: bold; color: #333;'>".$stats['Project']."</span>";
echo "</div>";
echo "</div>";
echo "</a>";
echo "</div>";

// Card "Mostrar Todos"
$total_itens_usuario = count($itens_para_stats);
echo "<div class='col-md-2'>";
$classe_ativa = (empty($filtro_ativo) || $filtro_ativo == 'todos') ? 'card-ativo' : '';
$url_mostrar_todos = $url_base;
if (!empty($campo_ordenacao) && $campo_ordenacao != 'date_mod') {
    $url_mostrar_todos .= "?sort=$campo_ordenacao&order=$ordem";
} elseif (!empty($ordem) && $ordem != 'DESC') {
    $url_mostrar_todos .= "?order=$ordem";
}
echo "<a href='$url_mostrar_todos' style='text-decoration: none;'>";
echo "<div class='card text-center filtro-card $classe_ativa' style='cursor: pointer; border: 1px solid #ddd; height: 50px;'>";
echo "<div class='card-body' style='padding: 5px 10px; display: flex; justify-content: space-between; align-items: center; height: 100%;'>";
echo "<i class='fas fa-list' style='font-size: 16px; color: #666;'></i>";
echo "<span style='font-size: 12px; color: #666;'>Mostrar Todos</span>";
echo "<span style='font-size: 18px; font-weight: bold; color: #333;'>$total_itens_usuario</span>";
echo "</div>";
echo "</div>";
echo "</a>";
echo "</div>";

echo "</div>";

// **NOVA SEÇÃO: Campo de Pesquisa Rápida**
echo "<div class='row' style='margin-bottom: 15px;'>";
echo "<div class='col-md-12'>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #dee2e6;'>";
echo "<div style='display: flex; align-items: center; gap: 10px;'>";
echo "<i class='fas fa-search' style='color: #666; font-size: 16px;'></i>";
echo "<input type='text' id='pesquisa-rapida' placeholder='Digite para pesquisar em todos os campos (ID, título, cliente, etc.)' style='flex: 1; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;' autocomplete='off'>";
echo "<button id='limpar-pesquisa' style='padding: 8px 12px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; display: none;' title='Limpar pesquisa'>";
echo "<i class='fas fa-times'></i>";
echo "</button>";
echo "</div>";
echo "<div id='info-pesquisa' style='margin-top: 8px; font-size: 12px; color: #666; display: none;'>";
echo "Resultados da pesquisa: <span id='count-resultados'>0</span> de <span id='count-total'>0</span> itens";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</div>";



// Exibir tabela
echo "<table class='tab_cadre_fixehov' id='tabela-itens'>";
echo "<tr class='tab_bg_1'>";
echo "<th>" . criarLinkOrdenacao('tipo', __('Tipo'), $campo_ordenacao, $ordem, $filtro_ativo) . "</th>";
echo "<th>" . criarLinkOrdenacao('id', __('ID'), $campo_ordenacao, $ordem, $filtro_ativo) . "</th>";
echo "<th>" . criarLinkOrdenacao('name', __('Título'), $campo_ordenacao, $ordem, $filtro_ativo) . "</th>";
echo "<th>" . criarLinkOrdenacao('status', __('Status'), $campo_ordenacao, $ordem, $filtro_ativo) . "</th>";
echo "<th>" . criarLinkOrdenacao('priority', __('Prioridade'), $campo_ordenacao, $ordem, $filtro_ativo) . "</th>";
echo "<th>" . criarLinkOrdenacao('entities_id', __('Cliente'), $campo_ordenacao, $ordem, $filtro_ativo) . "</th>";
echo "<th>" . criarLinkOrdenacao('papel', __('Meu Papel'), $campo_ordenacao, $ordem, $filtro_ativo) . "</th>";
echo "<th>" . criarLinkOrdenacao('date_creation', __('Data Abertura'), $campo_ordenacao, $ordem, $filtro_ativo) . "</th>";
echo "<th>" . criarLinkOrdenacao('date_mod', __('Data Modificação'), $campo_ordenacao, $ordem, $filtro_ativo) . "</th>";
echo "<th>".__('Ações')."</th>";
echo "</tr>";

$contador_linha = 0;
if (count($todos_itens) > 0) {
    foreach($todos_itens as $item) {
        $tipo = $item['tipo_item'];
        
        // Aplicar filtro se selecionado (exceto para o filtro especial de tickets sem atribuição)
        if (!empty($filtro_ativo) && $filtro_ativo != 'todos' && $filtro_ativo != 'tickets_sem_atribuicao' && $filtro_ativo != $tipo) {
            continue;
        }
        
        $contador_linha++;
        $classe_item = new $tipo();
        $url_item = gerarLink($tipo, $item['id']);
        
        // Preparar dados para pesquisa (todos os campos visíveis)
        $titulo = isset($item['name']) ? $item['name'] : 'Sem título';
        $status_texto = '';
        if (isset($item['status']) && method_exists($classe_item, 'getStatus')) {
            $status_texto = strip_tags($classe_item->getStatus($item['status']));
        }
        $prioridade_texto = '';
        if (isset($item['priority']) && method_exists($classe_item, 'getPriorityName')) {
            $prioridade_texto = $classe_item->getPriorityName($item['priority']);
        }
        $entidade_nome = obterNomeEntidade($item['entities_id'] ?? null);
        
        // Criar string de busca com todos os dados da linha
        $dados_busca = strtolower(implode(' ', array(
            $tipo,
            $item['id'],
            $titulo,
            $status_texto,
            $prioridade_texto,
            $entidade_nome,
            $item['papel_usuario'],
            isset($item['date_creation']) ? Html::convDateTime($item['date_creation']) : '',
            isset($item['date_mod']) ? Html::convDateTime($item['date_mod']) : ''
        )));
        
        echo "<tr class='tab_bg_2 linha-item' data-busca='" . htmlspecialchars($dados_busca) . "'>";
        
        // Tipo
        echo "<td>";
        switch($tipo) {
            case 'Ticket':
                echo "<i class='fas fa-ticket-alt' style='color: #666; margin-right: 5px;'></i>Ticket";
                break;
            case 'Problem':
                echo "<i class='fas fa-bug' style='color: #666; margin-right: 5px;'></i>Problema";
                break;
            case 'Change':
                echo "<i class='fas fa-exchange-alt' style='color: #666; margin-right: 5px;'></i>Mudança";
                break;
            case 'Project':
                echo "<i class='fas fa-project-diagram' style='color: #666; margin-right: 5px;'></i>Projeto";
                break;
        }
        echo "</td>";
        
        // ID - como link
        echo "<td>";
        echo "<a href='$url_item' target='_blank' style='font-weight: bold; color: #2f3f64;'>".$item['id']."</a>";
        echo "</td>";
        
        // Título - limitado para evitar quebra de linha
        $titulo_limitado = strlen($titulo) > 50 ? substr($titulo, 0, 50) . '...' : $titulo;
        echo "<td><strong><span title='" . Html::cleanInputText($titulo) . "'>" . Html::cleanInputText($titulo_limitado) . "</span></strong></td>";
        
        // Status
        echo "<td>";
        if (isset($item['status']) && method_exists($classe_item, 'getStatus')) {
            echo $classe_item->getStatus($item['status']);
        } else {
            echo "-";
        }
        echo "</td>";
        
        // Prioridade
        echo "<td>";
        if (isset($item['priority']) && method_exists($classe_item, 'getPriorityName')) {
            echo $classe_item->getPriorityName($item['priority']);
        } else {
            echo "-";
        }
        echo "</td>";
        
        // Nova coluna: Entidade
        echo "<td>";
        if (isset($item['entities_id'])) {
            echo obterNomeEntidade($item['entities_id']);
        } else {
            echo "-";
        }
        echo "</td>";
        
        // Papel do usuário
        echo "<td><span class='badge'>".$item['papel_usuario']."</span></td>";
        
        // Nova coluna: Data de abertura
        echo "<td>";
        if (isset($item['date_creation'])) {
            echo Html::convDateTime($item['date_creation']);
        } else {
            echo "-";
        }
        echo "</td>";
        
        // Data de modificação
        echo "<td>";
        $data = isset($item['date_mod']) ? $item['date_mod'] : (isset($item['date_creation']) ? $item['date_creation'] : '');
        if (!empty($data)) {
            echo Html::convDateTime($data);
        } else {
            echo "-";
        }
        echo "</td>";
        
        // Ações - botão Ver
        echo "<td>";
        echo "<a href='$url_item' target='_blank' class='btn btn-sm btn-primary'>";
        echo "<i class='fas fa-eye'></i> ".__('Ver');
        echo "</a>";
        echo "</td>";
        
        echo "</tr>";
    }
} else {
    echo "<tr class='tab_bg_2' id='linha-sem-resultados'>";
    echo "<td colspan='10' class='center'>".__('Nenhum item encontrado')."</td>";
    echo "</tr>";
}

echo "</table>";

echo "</div>";

// JavaScript para pesquisa em tempo real
echo "<script>
document.addEventListener('DOMContentLoaded', function() {
    const campoPesquisa = document.getElementById('pesquisa-rapida');
    const botaoLimpar = document.getElementById('limpar-pesquisa');
    const infoPesquisa = document.getElementById('info-pesquisa');
    const countResultados = document.getElementById('count-resultados');
    const countTotal = document.getElementById('count-total');
    const linhasItens = document.querySelectorAll('.linha-item');
    const linhaSemResultados = document.getElementById('linha-sem-resultados');
    
    // Contar total de itens
    const totalItens = linhasItens.length;
    countTotal.textContent = totalItens;
    
    // Função para normalizar texto (remover acentos)
    function normalizarTexto(texto) {
        return texto.toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '') // Remove acentos
            .trim();
    }
    
    // Função para dividir termos de pesquisa
    function dividirTermos(pesquisa) {
        return pesquisa.trim().split(/\s+/).filter(termo => termo.length > 0);
    }
    
    // Função para verificar se todos os termos estão presentes
    function contemTodosTermos(texto, termos) {
        const textoNormalizado = normalizarTexto(texto);
        return termos.every(termo => textoNormalizado.includes(normalizarTexto(termo)));
    }
    
    // Função principal de pesquisa
    function realizarPesquisa() {
        const termoPesquisa = campoPesquisa.value.trim();
        let resultadosVisiveis = 0;
        
        if (termoPesquisa === '') {
            // Mostrar todas as linhas
            linhasItens.forEach(linha => {
                linha.style.display = '';
            });
            resultadosVisiveis = totalItens;
            
            // Esconder info de pesquisa e botão limpar
            infoPesquisa.style.display = 'none';
            botaoLimpar.style.display = 'none';
            
            // Esconder linha 'sem resultados' se existir
            if (linhaSemResultados) {
                linhaSemResultados.style.display = totalItens > 0 ? 'none' : '';
            }
        } else {
            // Dividir termos de pesquisa
            const termos = dividirTermos(termoPesquisa);
            
            // Filtrar linhas
            linhasItens.forEach(linha => {
                const dadosBusca = linha.getAttribute('data-busca') || '';
                
                if (contemTodosTermos(dadosBusca, termos)) {
                    linha.style.display = '';
                    resultadosVisiveis++;
                } else {
                    linha.style.display = 'none';
                }
            });
            
            // Mostrar info de pesquisa e botão limpar
            infoPesquisa.style.display = 'block';
            botaoLimpar.style.display = 'block';
            
            // Gerenciar linha 'sem resultados'
            if (linhaSemResultados) {
                if (resultadosVisiveis === 0) {
                    linhaSemResultados.style.display = '';
                    linhaSemResultados.innerHTML = '<td colspan=\"10\" class=\"center\">Nenhum resultado encontrado para \"' + termoPesquisa + '\"</td>';
                } else {
                    linhaSemResultados.style.display = 'none';
                }
            }
        }
        
        // Atualizar contador
        countResultados.textContent = resultadosVisiveis;
    }
    
    // Event listeners
    campoPesquisa.addEventListener('input', realizarPesquisa);
    campoPesquisa.addEventListener('keyup', realizarPesquisa);
    
    // Botão limpar
    botaoLimpar.addEventListener('click', function() {
        campoPesquisa.value = '';
        realizarPesquisa();
        campoPesquisa.focus();
    });
    
    // Tecla ESC para limpar
    campoPesquisa.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            campoPesquisa.value = '';
            realizarPesquisa();
        }
    });
    
    // Destacar campo de pesquisa com Ctrl+F
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            campoPesquisa.focus();
            campoPesquisa.select();
        }
    });
});
</script>";

// CSS atualizado com estilos para pesquisa
echo "<style>
.card {
    transition: all 0.2s ease;
    background-color: #f8f9fa;
}
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    background-color: #e9ecef;
}
.card-ativo {
    background-color: #dee2e6 !important;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15) !important;
    transform: translateY(-1px) !important;
    border-color: #adb5bd !important;
}
.badge {
    background-color: #6c757d !important;
    color: white !important;
    padding: 4px 8px !important;
    border-radius: 4px !important;
    font-size: 0.8em !important;
}
.btn-primary {
    background-color: #ffc107 !important;
    border-color: #ffc107 !important;
    color: #212529 !important;
}
.btn-primary:hover {
    background-color: #e0a800 !important;
    border-color: #d39e00 !important;
    color: #212529 !important;
}

/* Estilos para pesquisa */
#pesquisa-rapida {
    transition: all 0.2s ease;
}
#pesquisa-rapida:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
    outline: 0;
}
#limpar-pesquisa:hover {
    background-color: #5a6268 !important;
}

/* Evitar quebra de linha na tabela */
#tabela-itens td {
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    max-width: 200px !important;
}
/* Permitir quebra apenas na coluna de ações */
#tabela-itens td:last-child {
    white-space: normal !important;
}

/* Animação suave para linhas que aparecem/desaparecem */
.linha-item {
    transition: opacity 0.2s ease;
}

/* Destacar campo de pesquisa quando tem conteúdo */
#pesquisa-rapida:not(:placeholder-shown) {
    background-color: #fff3cd;
    border-color: #ffeaa7;
}

/* Estilo responsivo */
@media (max-width: 768px) {
    #tabela-itens td {
        max-width: 100px !important;
        font-size: 12px;
    }
    
    .card-body span {
        font-size: 10px !important;
    }
}
</style>";

Html::footer();
?>

        