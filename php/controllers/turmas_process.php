<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';
require_once __DIR__ . '/../configs/utils.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    $id = mysqli_real_escape_string($conn, $_GET['id']);

    // Buscar dados da turma antes de deletar
    $res = mysqli_query($conn, "SELECT sigla, data_fim FROM turma WHERE id = '$id'");
    if ($row = mysqli_fetch_assoc($res)) {
        $sigla_deleted = $row['sigla'];
        $data_fim = $row['data_fim'];
        $hoje = date('Y-m-d');

        if ($data_fim < $hoje) {
            // Soft Delete: Turma encerrada (apenas desativar)
            mysqli_query($conn, "UPDATE turma SET ativo = 0 WHERE id = '$id'");
            $msg_notif = "A turma $sigla_deleted foi desativada/arquivada.";
            $url_msg = "deactivated";
        } else {
            // Hard Delete: Turma vigente ou futura (remover permanentemente)
            // 1. Limpar agenda primeiro
            mysqli_query($conn, "DELETE FROM agenda WHERE turma_id = '$id'");
            // 2. Remover turma
            mysqli_query($conn, "DELETE FROM turma WHERE id = '$id'");
            $msg_notif = "A turma $sigla_deleted foi removida permanentemente do sistema.";
            $url_msg = "deleted";
        }

        dispararNotificacaoGlobal($conn, 'exclusao_turma', 'Turma Excluída', $msg_notif, BASE_URL . "/php/views/turmas.php", ['admin', 'gestor', 'professor', 'cri']);
        header("Location: ../views/turmas.php?msg=$url_msg");
    } else {
        header("Location: ../views/turmas.php?msg=notfound");
    }
    exit;
}

if ($action == 'activate') {
    $id = mysqli_real_escape_string($conn, $_GET['id']);

    // Buscar sigla para notificação
    $res = mysqli_query($conn, "SELECT sigla FROM turma WHERE id = '$id'");
    if ($row = mysqli_fetch_assoc($res)) {
        $sigla = $row['sigla'];
        mysqli_query($conn, "UPDATE turma SET ativo = 1 WHERE id = '$id'");

        dispararNotificacaoGlobal($conn, 'ativacao_turma', 'Turma Reativada', "A turma $sigla foi restaurada ao status ativo.", BASE_URL . "/php/views/turmas.php", ['admin', 'gestor', 'professor', 'cri']);
        header("Location: ../views/turmas.php?msg=activated");
    } else {
        header("Location: ../views/turmas.php?msg=notfound");
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $is_ajax = isset($_POST['ajax']) && $_POST['ajax'] == '1';

    // SEGURANÇA: Se o usuário for CRI, ele SÓ pode criar reservas pendentes.
    if (isCRI()) {
        $_POST['is_reserva'] = '1';
        $is_reserva = true;
    }

    function handle_response($conn, $success, $message, $redirect_url, $is_ajax)
    {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => $success, 'message' => $message, 'redirect' => $redirect_url]);
            exit;
        } else {
            if ($success) {
                header("Location: $redirect_url");
            } else {
                $msg = urlencode($message);
                header("Location: ../views/turmas_form.php?msg=error&error_text=$msg");
            }
            exit;
        }
    }

    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $is_reserva = isset($_POST['is_reserva']) && $_POST['is_reserva'] == '1';
    $curso_id = !empty($_POST['curso_id']) ? mysqli_real_escape_string($conn, $_POST['curso_id']) : "NULL";
    $tipo = mysqli_real_escape_string($conn, $_POST['tipo'] ?? 'Presencial');
    $periodo = mysqli_real_escape_string($conn, $_POST['periodo']);
    $data_inicio = mysqli_real_escape_string($conn, $_POST['data_inicio']);
    $data_fim = mysqli_real_escape_string($conn, $_POST['data_fim']);
    $ambiente_id = !empty($_POST['ambiente_id']) ? mysqli_real_escape_string($conn, $_POST['ambiente_id']) : "NULL";
    if ($ambiente_id === 'outro') $ambiente_id = "NULL";
    
    $sigla = mysqli_real_escape_string($conn, $_POST['sigla'] ?? '');
    $vagas = (int) ($_POST['vagas'] ?? 32);
    $local = mysqli_real_escape_string($conn, $_POST['local'] ?? 'Sede');
    $docente_id1 = !empty($_POST['docente_id1']) ? (int) $_POST['docente_id1'] : "NULL";
    $docente_id2 = !empty($_POST['docente_id2']) ? (int) $_POST['docente_id2'] : "NULL";
    $docente_id3 = !empty($_POST['docente_id3']) ? (int) $_POST['docente_id3'] : "NULL";
    $docente_id4 = !empty($_POST['docente_id4']) ? (int) $_POST['docente_id4'] : "NULL";
    $dias_semana_arr = $_POST['dias_semana'] ?? [];
    $dias_semana_str = mysqli_real_escape_string($conn, implode(',', $dias_semana_arr));
    $horario_inicio = mysqli_real_escape_string($conn, $_POST['horario_inicio'] ?? '07:30');
    $horario_fim = mysqli_real_escape_string($conn, $_POST['horario_fim'] ?? '11:30');
    $tipo_custeio = mysqli_real_escape_string($conn, $_POST['tipo_custeio'] ?? 'Gratuidade');
    $previsao_despesa = (float) ($_POST['previsao_despesa'] ?? 0);
    $valor_turma = $tipo_custeio === 'Ressarcido' ? (float) ($_POST['valor_turma'] ?? 0) : 0;
    $numero_proposta = mysqli_real_escape_string($conn, $_POST['numero_proposta'] ?? '');
    $tipo_atendimento = mysqli_real_escape_string($conn, $_POST['tipo_atendimento'] ?? 'Balcão');
    $parceiro = mysqli_real_escape_string($conn, $_POST['parceiro'] ?? '');
    $contato_parceiro = mysqli_real_escape_string($conn, $_POST['contato_parceiro'] ?? '');

    // Auto-derive if needed
    if (empty($horario_inicio) || empty($horario_fim)) {
        $period_times = [
            'Manhã' => ['07:30', '11:30'],
            'Tarde' => ['13:30', '17:30'],
            'Noite' => ['19:30', '23:30'],
            'Integral' => ['07:30', '17:30'],
        ];
        $horario_inicio = $horario_inicio ?: ($period_times[$periodo][0] ?? '07:30');
        $horario_fim = $horario_fim ?: ($period_times[$periodo][1] ?? '11:30');
    }

    $display_nome = !empty($sigla) ? $sigla : ($is_reserva ? "Reserva" : "Turma (Sem Sigla)");

    // --- VALIDATION: Professor Hour Limits & Conflicts ---
    $docentes_to_check = array_values(array_filter([$docente_id1, $docente_id2, $docente_id3, $docente_id4], function ($val) {
        return $val !== "NULL" && $val > 0;
    }));

    if (empty($docentes_to_check) && !$is_reserva && empty($_POST['validate_only'])) {
        handle_response($conn, false, "Pelo menos um docente deve ser selecionado.", "", $is_ajax);
    }

    foreach ($docentes_to_check as $did) {
        // Reservas também devem respeitar os limites de carga horária? Sim, por segurança.
        $val_res = checkDocenteLimits($conn, $did, (!$is_reserva ? $id : null), $data_inicio, $data_fim, $dias_semana_arr, $horario_inicio, $horario_fim);
        if ($val_res !== true) {
            handle_response($conn, false, $val_res, "", $is_ajax);
        }

        $conf_res = checkDocenteConflicts($conn, $did, (!$is_reserva ? $id : null), $data_inicio, $data_fim, $dias_semana_arr, $horario_inicio, $horario_fim);
        if ($conf_res !== true) {
            handle_response($conn, false, $conf_res, "", $is_ajax);
        }

        $work_res = checkDocenteWorkSchedule($conn, $did, $data_inicio, $data_fim, $dias_semana_arr, $periodo, $horario_inicio, $horario_fim);
        if ($work_res !== true) {
            handle_response($conn, false, $work_res, "", $is_ajax);
        }
    }

    // --- VALIDATION: Environment (Ambiente) Conflict ---
    if ($ambiente_id !== "NULL" && $ambiente_id > 0) {
        $amb_res = checkAmbienteConflict($conn, $ambiente_id, (!$is_reserva ? $id : null), $data_inicio, $data_fim, $dias_semana_arr, $horario_inicio, $horario_fim);
        if ($amb_res !== true) {
            handle_response($conn, false, $amb_res, "", $is_ajax);
        }
    }

    if (isset($_POST['validate_only']) && $_POST['validate_only'] == '1') {
        handle_response($conn, true, "Horário disponível", "", $is_ajax);
    }

    if ($is_reserva) {
        // --- PROCESSAMENTO DE RESERVA ---
        $usuario_id = $_SESSION['user_id'] ?? null;

        // Se o usuário não estiver logado (sessão expirada), tenta buscar o primeiro admin ou retorna erro
        if (!$usuario_id) {
            $admin_res = mysqli_query($conn, "SELECT id FROM usuario WHERE role = 'admin' LIMIT 1");
            if ($row_adm = mysqli_fetch_assoc($admin_res)) {
                $usuario_id = (int)$row_adm['id'];
            } else {
                handle_response($conn, false, "Sessão expirada. Por favor, faça login novamente.", "login.php", $is_ajax);
            }
        }

        // Garante que o ID para a query não seja uma string vazia
        $usuario_id_sql = $usuario_id ? (int)$usuario_id : "NULL";

        $principal_docente = !empty($docentes_to_check) ? $docentes_to_check[0] : 0;
        if (!$principal_docente && empty($id)) {
            handle_response($conn, false, "É necessário selecionar um docente para a reserva.", "", $is_ajax);
        }

        if ($id) {
            // Update reserva
            $query = "UPDATE reservas SET 
                      docente_id = $principal_docente,
                      curso_id = $curso_id,
                      ambiente_id = $ambiente_id,
                      data_inicio = '$data_inicio',
                      data_fim = '$data_fim',
                      hora_inicio = '$horario_inicio',
                      hora_fim = '$horario_fim',
                      dias_semana = '$dias_semana_str',
                      sigla = '$sigla',
                      periodo = '$periodo',
                      vagas = $vagas,
                      local = '$local',
                      tipo = '$tipo',
                      tipo_custeio = '$tipo_custeio',
                      previsao_despesa = $previsao_despesa,
                      valor_turma = $valor_turma,
                      numero_proposta = '$numero_proposta',
                      tipo_atendimento = '$tipo_atendimento',
                      parceiro = '$parceiro',
                      contato_parceiro = '$contato_parceiro'
                      WHERE id = '$id'";
        } else {
            // Alterado para sempre iniciar como PENDENTE, mesmo para Admin/Gestor,
            // permitindo que o fluxo de aprovação (Aceitar/Recusar) ocorra no painel.
            $status_inicial = 'PENDENTE';

            $query = "INSERT INTO reservas (docente_id, curso_id, ambiente_id, usuario_id, data_inicio, data_fim, dias_semana, hora_inicio, hora_fim, sigla, periodo, status, vagas, local, tipo, tipo_custeio, previsao_despesa, valor_turma, numero_proposta, tipo_atendimento, parceiro, contato_parceiro) 
                      VALUES ($principal_docente, $curso_id, $ambiente_id, $usuario_id_sql, '$data_inicio', '$data_fim', '$dias_semana_str', '$horario_inicio', '$horario_fim', '$sigla', '$periodo', '$status_inicial', $vagas, '$local', '$tipo', '$tipo_custeio', $previsao_despesa, $valor_turma, '$numero_proposta', '$tipo_atendimento', '$parceiro', '$contato_parceiro')";
        }

        if (mysqli_query($conn, $query)) {
            $res_id = $id ?: mysqli_insert_id($conn);
            $msg = $id ? "Reserva atualizada" : "Reserva criada com sucesso";
            $next_url = "../views/agenda_professores.php?docente_id=" . $docentes_to_check[0] . "&msg=created";
            handle_response($conn, true, $msg, $next_url, $is_ajax);
        } else {
            handle_response($conn, false, "Erro ao salvar reserva: " . mysqli_error($conn), "", $is_ajax);
        }

    } else {
        // --- PROCESSAMENTO DE TURMA ---
        if ($id) {
            // UPDATE existing turma
            $query = "UPDATE turma SET 
                      curso_id = $curso_id, 
                      tipo = '$tipo', 
                      periodo = '$periodo', 
                      data_inicio = '$data_inicio', 
                      data_fim = '$data_fim', 
                      ambiente_id = $ambiente_id, 
                      sigla = '$sigla',
                      vagas = $vagas,
                      local = '$local',
                      dias_semana = '$dias_semana_str',
                      horario_inicio = '$horario_inicio',
                      horario_fim = '$horario_fim',
                      docente_id1 = $docente_id1,
                      docente_id2 = $docente_id2,
                      docente_id3 = $docente_id3,
                      docente_id4 = $docente_id4,
                      tipo_custeio = '$tipo_custeio',
                      previsao_despesa = $previsao_despesa,
                      valor_turma = $valor_turma,
                      numero_proposta = '$numero_proposta',
                      tipo_atendimento = '$tipo_atendimento',
                      parceiro = '$parceiro',
                      contato_parceiro = '$contato_parceiro'
                      WHERE id = '$id'";
            mysqli_query($conn, $query);

            dispararNotificacaoGlobal($conn, 'edicao_turma', 'Turma Atualizada', "A turma $display_nome ($periodo) teve seus dados atualizados.", BASE_URL . "/php/views/turmas.php", ['admin', 'gestor', 'professor', 'cri']);

            mysqli_query($conn, "DELETE FROM agenda WHERE turma_id = '$id'");
            generateAgendaRecords($conn, $id, $dias_semana_arr, $periodo, $horario_inicio, $horario_fim, $data_inicio, $data_fim, $ambiente_id, $docentes_to_check);

            $next_url = "../views/agenda_professores.php?docente_id=" . (!empty($docentes_to_check) ? $docentes_to_check[0] : '') . "&msg=updated";
            handle_response($conn, true, "Turma atualizada com sucesso", $next_url, $is_ajax);
        } else {
            // INSERT new turma
            $query = "INSERT INTO turma (curso_id, tipo, periodo, data_inicio, data_fim, ambiente_id, sigla, vagas, local, dias_semana, horario_inicio, horario_fim, docente_id1, docente_id2, docente_id3, docente_id4, tipo_custeio, previsao_despesa, valor_turma, numero_proposta, tipo_atendimento, parceiro, contato_parceiro) 
                      VALUES ($curso_id, '$tipo', '$periodo', '$data_inicio', '$data_fim', $ambiente_id, '$sigla', $vagas, '$local', '$dias_semana_str', '$horario_inicio', '$horario_fim', $docente_id1, $docente_id2, $docente_id3, $docente_id4, '$tipo_custeio', $previsao_despesa, $valor_turma, '$numero_proposta', '$tipo_atendimento', '$parceiro', '$contato_parceiro')";

            mysqli_query($conn, $query);
            $turma_id = mysqli_insert_id($conn);

            dispararNotificacaoGlobal($conn, 'registro_turma', 'Nova Turma Registrada', "A turma $display_nome ($periodo) foi cadastrada no sistema.", BASE_URL . "/php/views/turmas.php", ['admin', 'gestor', 'professor', 'cri']);

            generateAgendaRecords($conn, $turma_id, $dias_semana_arr, $periodo, $horario_inicio, $horario_fim, $data_inicio, $data_fim, $ambiente_id, $docentes_to_check);

            $next_url = "../views/agenda_professores.php?docente_id=" . (!empty($docentes_to_check) ? $docentes_to_check[0] : '') . "&msg=created";
            handle_response($conn, true, "Turma criada com sucesso", $next_url, $is_ajax);
        }
    }

    exit;
}
