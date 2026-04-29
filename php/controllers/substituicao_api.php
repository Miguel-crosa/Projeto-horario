<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';
require_once __DIR__ . '/../configs/utils.php';

// Previne que avisos/erros do PHP quebrem o JSON
ob_start();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'buscar_disponiveis':
        $periodo = mysqli_real_escape_string($conn, $_GET['periodo'] ?? 'Manhã');
        $data_inicio = mysqli_real_escape_string($conn, $_GET['data_inicio'] ?? date('Y-m-d'));
        $data_fim = mysqli_real_escape_string($conn, $_GET['data_fim'] ?? date('Y-m-d'));
        $area = mysqli_real_escape_string($conn, $_GET['area'] ?? '');

        $titular_id = (int)($_GET['titular_id'] ?? 0);

        $where = "WHERE id != $titular_id AND ativo = 1";
        if (!empty($area)) {
            $where .= " AND area_conhecimento = '$area'";
        }

        $res_profs = mysqli_query($conn, "SELECT id, nome, area_conhecimento FROM docente $where ORDER BY nome ASC");
        $professores_disponiveis = [];

        // 1. Descobrir em quais datas e períodos a TURMA alvo tem aulas no intervalo solicitado
        $turma_id_alvo = (int)($_GET['turma_id'] ?? 0);
        
        if (!$turma_id_alvo) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['professores' => [], 'debug' => 'ID da turma não fornecido.']);
            exit;
        }

        $q_dias = "SELECT DISTINCT data, periodo FROM agenda WHERE turma_id = $turma_id_alvo AND data BETWEEN '$data_inicio' AND '$data_fim'";
        $res_dias_aula = mysqli_query($conn, $q_dias);
        $dias_aula_turma = [];
        while($row = mysqli_fetch_assoc($res_dias_aula)) {
            $dias_aula_turma[] = $row;
        }

        if (empty($dias_aula_turma)) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['professores' => [], 'debug' => 'Nenhuma aula encontrada para esta turma no período selecionado.']);
            exit;
        }

        while ($prof = mysqli_fetch_assoc($res_profs)) {
            $did = (int) $prof['id'];
            $conflito_real = false;
            $motivo_conflito = '';

            foreach ($dias_aula_turma as $aula) {
                $dt = $aula['data'];
                $p = $aula['periodo'];

                // A. Verificação de Férias/Feriado (Bloqueio Total do Dia)
                if (isHoliday($conn, $dt)) {
                    $conflito_real = true;
                    $motivo_conflito = "Feriado em $dt";
                    break;
                }
                if (isVacation($conn, $did, $dt)) {
                    $conflito_real = true;
                    $motivo_conflito = "Férias em $dt";
                    break;
                }

                // B. Conflito de Agenda (Professor já tem aula nesse horário?)
                $q_conf = "SELECT id FROM agenda WHERE docente_id = $did AND data = '$dt' AND (periodo = '$p' OR periodo = 'Integral')";
                $res_c = mysqli_query($conn, $q_conf);
                if (mysqli_num_rows($res_c) > 0) {
                    $conflito_real = true;
                    $motivo_conflito = "Já tem aula em $dt ($p)";
                    break;
                }

                // C. Disponibilidade Técnica (Horário de Trabalho)
                if (!isWithinWorkSchedule($conn, $did, $dt, $p)) {
                    $conflito_real = true;
                    $motivo_conflito = "Sem autorização de trabalho em $dt ($p)";
                    break;
                }
            }

            if (!$conflito_real) {
                $professores_disponiveis[] = [
                    'id' => $did,
                    'nome' => $prof['nome'],
                    'area' => $prof['area_conhecimento']
                ];
            }
        }

        if (ob_get_length()) ob_clean();
        echo json_encode(['professores' => $professores_disponiveis]);
        break;

    case 'get_turmas_ativas':
        // Removido o filtro restritivo de data para mostrar todas as turmas, 
        // priorizando as mais recentes no topo.
        $res = mysqli_query($conn, "
            SELECT t.id, t.sigla, c.nome as curso_nome, t.periodo, t.data_inicio, t.data_fim
            FROM turma t 
            JOIN curso c ON t.curso_id = c.id 
            WHERE t.ativo = 1
            ORDER BY t.data_inicio DESC, t.sigla ASC
        ");
        $turmas = mysqli_fetch_all($res, MYSQLI_ASSOC);
        if (ob_get_length()) ob_clean();
        echo json_encode(['turmas' => $turmas]);
        break;

    case 'get_docentes_por_turma':
        $turma_id = (int) ($_GET['turma_id'] ?? 0);
        if (!$turma_id) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['docentes' => []]);
            exit;
        }

        // Busca docentes vinculados à turma (tabela turma) OU que já têm aulas agendadas (tabela agenda)
        $res = mysqli_query($conn, "
            SELECT DISTINCT d.id, d.nome, d.area_conhecimento as area
            FROM docente d
            WHERE d.id IN (
                SELECT docente_id1 FROM turma WHERE id = $turma_id
                UNION SELECT docente_id2 FROM turma WHERE id = $turma_id
                UNION SELECT docente_id3 FROM turma WHERE id = $turma_id
                UNION SELECT docente_id4 FROM turma WHERE id = $turma_id
                UNION SELECT docente_id FROM agenda WHERE turma_id = $turma_id
            )
            ORDER BY d.nome ASC
        ");
        $docentes = mysqli_fetch_all($res, MYSQLI_ASSOC);
        if (ob_get_length()) ob_clean();
        echo json_encode(['docentes' => $docentes]);
        break;

    case 'executar_substituicao':
        if (!isAdmin() && !isGestor()) {
            echo json_encode(['success' => false, 'message' => 'Permissão negada.']);
            exit;
        }

        $docente_substituto_id = (int) ($_POST['docente_id'] ?? 0);
        $docente_titular_id = (int) ($_POST['docente_titular_id'] ?? 0);
        $turma_id = (int) ($_POST['turma_id'] ?? 0);
        $data_inicio = mysqli_real_escape_string($conn, $_POST['data_inicio'] ?? '');
        $data_fim = mysqli_real_escape_string($conn, $_POST['data_fim'] ?? '');

        if (!$docente_substituto_id || !$turma_id || !$data_inicio || !$data_fim || !$docente_titular_id) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'message' => 'Parâmetros insuficientes. Selecione o titular e o substituto.']);
            exit;
        }

        // Executa a substituição: Atualiza APENAS as aulas do titular selecionado
        $sql = "UPDATE agenda 
                SET docente_id = $docente_substituto_id 
                WHERE turma_id = $turma_id 
                AND docente_id = $docente_titular_id
                AND data BETWEEN '$data_inicio' AND '$data_fim'";
        
        if (mysqli_query($conn, $sql)) {
            $afetados = mysqli_affected_rows($conn);
            
            // Log e Notificação (Opcional, mas recomendado)
            $res_t = mysqli_query($conn, "SELECT sigla FROM turma WHERE id = $turma_id");
            $sigla = ($row = mysqli_fetch_assoc($res_t)) ? $row['sigla'] : 'Turma #'.$turma_id;
            
            $res_d = mysqli_query($conn, "SELECT nome FROM docente WHERE id = $docente_substituto_id");
            $nome_prof = ($row = mysqli_fetch_assoc($res_d)) ? $row['nome'] : 'Professor';

            // Notificação com detalhes de quem realizou a ação
            $usuario_acao = getUserName(); // Pega nome da sessão
            
            dispararNotificacaoGlobal($conn, 'substituicao', 'Substituição Temporária Realizada', 
                "O professor $nome_prof foi alocado para substituir na turma $sigla entre $data_inicio e $data_fim. Ação realizada por $usuario_acao.", 
                BASE_URL . "/php/views/agenda_professores.php?docente_id=$docente_substituto_id", ['admin', 'gestor', 'professor']);

            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => true, 'message' => "Substituição realizada em $afetados aulas.", 'count' => $afetados]);
        } else {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar banco: ' . mysqli_error($conn)]);
        }
        break;

    case 'get_datas_relevantes':
        $turma_id = (int) ($_GET['turma_id'] ?? 0);
        if (!$turma_id) {
            echo json_encode(['data_mais_recente' => date('Y-m-d')]);
            exit;
        }

        // Busca a data da última aula registrada na agenda para esta turma
        $res = mysqli_query($conn, "SELECT MAX(data) as ultima FROM agenda WHERE turma_id = $turma_id");
        $row = mysqli_fetch_assoc($res);
        $data = $row['ultima'] ?: date('Y-m-d');

        if (ob_get_length()) ob_clean();
        echo json_encode(['data_mais_recente' => $data]);
        break;

    default:
        if (ob_get_length()) ob_clean();
        echo json_encode(['error' => 'Ação não encontrada']);
        break;
}
