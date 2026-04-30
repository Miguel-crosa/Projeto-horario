<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';
require_once __DIR__ . '/../configs/utils.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    $id = mysqli_real_escape_string($conn, $_GET['id']);

    // Buscar dados da turma antes de deletar
    $res = mysqli_query($conn, "SELECT sigla FROM turma WHERE id = '$id'");
    if ($row = mysqli_fetch_assoc($res)) {
        $sigla_deleted = $row['sigla'];

        // --- HARD DELETE SEMPRE PERMANENTE ---
        // 1. Limpar agenda primeiro para evitar orfãos (se houver constraints)
        mysqli_query($conn, "DELETE FROM agenda WHERE turma_id = '$id'");
        // 2. Remover turma
        if (mysqli_query($conn, "DELETE FROM turma WHERE id = '$id'")) {
            $msg_notif = "A turma $sigla_deleted foi removida permanentemente do sistema.";
            dispararNotificacaoGlobal($conn, 'exclusao_turma', 'Turma Excluída', $msg_notif, BASE_URL . "/php/views/turmas.php", ['admin', 'gestor', 'professor', 'cri']);
            header("Location: ../views/turmas.php?msg=deleted");
        } else {
            header("Location: ../views/turmas.php?msg=error&error_text=" . urlencode(mysqli_error($conn)));
        }
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

if ($action == 'bulk_update' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $ids_str = mysqli_real_escape_string($conn, $_POST['ids'] ?? '');
    $ids = array_filter(explode(',', $ids_str));
    $return_url = $_POST['return_url'] ?? '../views/turmas.php';

    if (empty($ids)) {
        header("Location: $return_url&msg=error&error_text=Nenhuma turma selecionada.");
        exit;
    }

    $periodo = mysqli_real_escape_string($conn, $_POST['periodo'] ?? '');
    $horario_inicio = mysqli_real_escape_string($conn, $_POST['horario_inicio'] ?? '');
    $horario_fim = mysqli_real_escape_string($conn, $_POST['horario_fim'] ?? '');

    $update_fields = [];
    if (!empty($periodo))
        $update_fields[] = "periodo = '$periodo'";
    if (!empty($horario_inicio))
        $update_fields[] = "horario_inicio = '$horario_inicio'";
    if (!empty($horario_fim))
        $update_fields[] = "horario_fim = '$horario_fim'";

    if (empty($update_fields)) {
        header("Location: $return_url&msg=info&info_text=Nenhuma alteração informada.");
        exit;
    }

    $success_count = 0;
    $errors = [];

    foreach ($ids as $t_id) {
        $t_id = mysqli_real_escape_string($conn, $t_id);
        $res = mysqli_query($conn, "SELECT * FROM turma WHERE id = '$t_id'");
        if ($t_data = mysqli_fetch_assoc($res)) {
            $sigla_display = $t_data['sigla'] ?: "Turma #$t_id";

            // Valores novos (se informados) ou atuais
            $new_periodo = !empty($_POST['periodo']) ? mysqli_real_escape_string($conn, $_POST['periodo']) : $t_data['periodo'];
            $new_h_ini = !empty($_POST['horario_inicio']) ? mysqli_real_escape_string($conn, $_POST['horario_inicio']) : $t_data['horario_inicio'];
            $new_h_fim = !empty($_POST['horario_fim']) ? mysqli_real_escape_string($conn, $_POST['horario_fim']) : $t_data['horario_fim'];

            $dias_arr = !empty($t_data['dias_semana']) ? explode(',', $t_data['dias_semana']) : [];
            $docentes = array_values(array_filter([$t_data['docente_id1'], $t_data['docente_id2'], $t_data['docente_id3'], $t_data['docente_id4']], function ($val) {
                return $val !== null && $val > 0;
            }));

            $error_turma = null;

            // --- VALIDAÇÃO ---
            foreach ($docentes as $did) {
                // 1. Conflito de Horário (Agenda)
                $conf_res = checkDocenteConflicts($conn, $did, $t_id, $t_data['data_inicio'], $t_data['data_fim'], $dias_arr, $new_h_ini, $new_h_fim, $t_data['tipo_agenda'], $t_data['agenda_flexivel']);
                if ($conf_res !== true) {
                    $error_turma = $conf_res;
                    break;
                }

                // 2. Horário de Trabalho (Blocos Autorizados)
                $work_res = checkDocenteWorkSchedule($conn, $did, $t_data['data_inicio'], $t_data['data_fim'], $dias_arr, $new_periodo, $new_h_ini, $new_h_fim, $t_data['tipo_agenda'], $t_data['agenda_flexivel']);
                if ($work_res !== true) {
                    $error_turma = $work_res;
                    break;
                }

                // 3. Limites de Carga Horária
                $limit_res = checkDocenteLimits($conn, $did, $t_id, $t_data['data_inicio'], $t_data['data_fim'], $dias_arr, $new_h_ini, $new_h_fim, $new_periodo, $t_data['tipo_agenda'], $t_data['agenda_flexivel']);
                if ($limit_res !== true) {
                    $error_turma = $limit_res;
                    break;
                }
            }

            // 4. Conflito de Ambiente
            if (!$error_turma && !empty($t_data['ambiente_id']) && $t_data['ambiente_id'] > 0) {
                $amb_res = checkAmbienteConflict($conn, $t_data['ambiente_id'], $t_id, $t_data['data_inicio'], $t_data['data_fim'], $dias_arr, $new_h_ini, $new_h_fim, $t_data['tipo_agenda'], $t_data['agenda_flexivel']);
                if ($amb_res !== true) {
                    $error_turma = $amb_res;
                }
            }

            if ($error_turma) {
                $errors[] = "<strong>$sigla_display</strong>: $error_turma";
                continue;
            }

            // --- EXECUÇÃO DO UPDATE ---
            $fields = [];
            if (!empty($_POST['periodo']))
                $fields[] = "periodo = '$new_periodo'";
            if (!empty($_POST['horario_inicio']))
                $fields[] = "horario_inicio = '$new_h_ini'";
            if (!empty($_POST['horario_fim']))
                $fields[] = "horario_fim = '$new_h_fim'";

            if (!empty($fields)) {
                $sql = "UPDATE turma SET " . implode(', ', $fields) . " WHERE id = '$t_id'";
                if (mysqli_query($conn, $sql)) {
                    // Regenera Agenda
                    mysqli_query($conn, "DELETE FROM agenda WHERE turma_id = '$t_id'");
                    generateAgendaRecords($conn, $t_id, $dias_arr, $new_periodo, $new_h_ini, $new_h_fim, $t_data['data_inicio'], $t_data['data_fim'], $t_data['ambiente_id'], $docentes, $t_data['tipo_agenda'], $t_data['agenda_flexivel']);
                    $success_count++;
                }
            }
        }
    }

    // Prepara mensagem final
    $msg_text = "Edição concluída: $success_count turmas atualizadas.";
    if (!empty($errors)) {
        $msg_text .= "<br><br><strong>Atenção:</strong> " . count($errors) . " turmas não puderam ser alteradas por conflitos:<br>" . implode("<br>", $errors);
    }

    $msg_type = $success_count > 0 ? "bulk_success" : "error";
    $separator = (strpos($return_url, '?') !== false) ? '&' : '?';
    header("Location: $return_url" . $separator . "msg=$msg_type&msg_text=" . urlencode($msg_text));
    exit;
}

if ($action === 'delete_bulk' && isAdmin()) {
    $ids = $_POST['ids'] ?? [];
    $return_url = $_POST['return_url'] ?? '../views/turmas.php';
    if (!empty($ids)) {
        $ids_sql = implode("','", array_map(function ($id) use ($conn) {
            return mysqli_real_escape_string($conn, $id); }, $ids));

        // 1. Limpar Agendas
        mysqli_query($conn, "DELETE FROM agenda WHERE turma_id IN ('$ids_sql')");

        // 2. Excluir Turmas
        if (mysqli_query($conn, "DELETE FROM turma WHERE id IN ('$ids_sql')")) {
            $msg = urlencode(count($ids) . " turmas foram excluídas permanentemente.");
            $separator = (strpos($return_url, '?') !== false) ? '&' : '?';
            header("Location: $return_url" . $separator . "msg=success&msg_text=$msg");
        } else {
            header("Location: $return_url?msg=error&msg_text=" . urlencode("Erro ao excluir: " . mysqli_error($conn)));
        }
    } else {
        header("Location: $return_url");
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

    // Início da Transação Atômica
    mysqli_begin_transaction($conn);

    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $is_reserva = isset($_POST['is_reserva']) && $_POST['is_reserva'] == '1';
    $curso_id = !empty($_POST['curso_id']) ? mysqli_real_escape_string($conn, $_POST['curso_id']) : "NULL";
    $tipo = mysqli_real_escape_string($conn, $_POST['tipo'] ?? 'Presencial');
    $periodo = mysqli_real_escape_string($conn, $_POST['periodo']);
    $data_inicio = mysqli_real_escape_string($conn, $_POST['data_inicio']);
    $data_fim = mysqli_real_escape_string($conn, $_POST['data_fim']);
    $ambiente_id = !empty($_POST['ambiente_id']) ? mysqli_real_escape_string($conn, $_POST['ambiente_id']) : "NULL";
    if ($ambiente_id === 'outro')
        $ambiente_id = "NULL";

    $sigla = mysqli_real_escape_string($conn, $_POST['sigla'] ?? '');
    $vagas = (int) ($_POST['vagas'] ?? 32);
    $local = mysqli_real_escape_string($conn, $_POST['local'] ?? 'Sede');
    $docente_id1 = !empty($_POST['docente_id1']) ? (int) $_POST['docente_id1'] : "NULL";
    $docente_id2 = !empty($_POST['docente_id2']) ? (int) $_POST['docente_id2'] : "NULL";
    $docente_id3 = !empty($_POST['docente_id3']) ? (int) $_POST['docente_id3'] : "NULL";
    $docente_id4 = !empty($_POST['docente_id4']) ? (int) $_POST['docente_id4'] : "NULL";
    $dias_semana_arr = $_POST['dias_semana'] ?? [];
    $horario_inicio = mysqli_real_escape_string($conn, $_POST['horario_inicio'] ?? '07:30');
    $horario_fim = mysqli_real_escape_string($conn, $_POST['horario_fim'] ?? '11:30');

    // Normalização: Garante que tenha minutos (ex: "08" -> "08:00")
    if (!empty($horario_inicio) && strpos($horario_inicio, ':') === false)
        $horario_inicio .= ':00';
    if (!empty($horario_fim) && strpos($horario_fim, ':') === false)
        $horario_fim .= ':00';

    $tipo_custeio = mysqli_real_escape_string($conn, $_POST['tipo_custeio'] ?? 'Gratuidade');
    $previsao_despesa = (float) ($_POST['previsao_despesa'] ?? 0);
    $valor_turma = $tipo_custeio === 'Ressarcido' ? (float) ($_POST['valor_turma'] ?? 0) : 0;
    $numero_proposta = mysqli_real_escape_string($conn, $_POST['numero_proposta'] ?? '');
    $tipo_atendimento = mysqli_real_escape_string($conn, $_POST['tipo_atendimento'] ?? 'Balcão');
    $parceiro = mysqli_real_escape_string($conn, $_POST['parceiro'] ?? '');
    $contato_parceiro = mysqli_real_escape_string($conn, $_POST['contato_parceiro'] ?? '');
    $tipo_agenda = mysqli_real_escape_string($conn, $_POST['tipo_agenda'] ?? 'recorrente');
    $agenda_flexivel = mysqli_real_escape_string($conn, $_POST['agenda_flexivel'] ?? '');

    // FIX: No modo flexível, sempre derivamos dias_semana a partir das datas selecionadas.
    // Ignoramos os checkboxes pois eles podem conter valores legados ou ocultos.
    if ($tipo_agenda === 'flexivel' && !empty($agenda_flexivel)) {
        $daysMapFlex = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];
        $flex_dates = explode(',', $agenda_flexivel);
        $derived_days = [];
        foreach ($flex_dates as $fd) {
            $fd = trim($fd);
            if (empty($fd))
                continue;
            $dow = (int) date('w', strtotime($fd));
            $dayName = $daysMapFlex[$dow] ?? '';
            if ($dayName && !in_array($dayName, $derived_days)) {
                $derived_days[] = $dayName;
            }
        }
        $dias_semana_arr = $derived_days;
        $dias_semana_str = mysqli_real_escape_string($conn, implode(',', $dias_semana_arr));
    } else {
        $dias_semana_str = mysqli_real_escape_string($conn, implode(',', $dias_semana_arr));
    }

    // TRAVA: Aulas Noturnas não podem passar das 23:00
    if ($periodo === 'Noite' && !empty($horario_fim) && $horario_fim > '23:00') {
        $horario_fim = '23:00';
    }

    // Auto-derive if needed
    if (empty($horario_inicio) || empty($horario_fim)) {
        $period_times = [
            'Manhã' => ['07:30', '11:30'],
            'Tarde' => ['13:30', '17:30'],
            'Noite' => ['18:00', '23:00'],
            'Integral' => ['07:30', '17:30'],
        ];
        $horario_inicio = $horario_inicio ?: ($period_times[$periodo][0] ?? '07:30');
        $horario_fim = $horario_fim ?: ($period_times[$periodo][1] ?? '11:30');
    }

    $display_nome = !empty($sigla) ? $sigla : ($is_reserva ? "Reserva" : "Turma (Sem Sigla)");

    // --- DADOS PARA O E-MAIL (Busca nomes amigáveis) ---
    $curso_nome_email = "Não informado";
    if ($curso_id !== "NULL") {
        $c_res = mysqli_query($conn, "SELECT nome FROM curso WHERE id = $curso_id");
        if ($c_row = mysqli_fetch_assoc($c_res))
            $curso_nome_email = $c_row['nome'];
    }
    $ambiente_nome_email = $local;
    if ($ambiente_id !== "NULL" && $ambiente_id > 0) {
        $a_res = mysqli_query($conn, "SELECT nome FROM ambiente WHERE id = $ambiente_id");
        if ($a_row = mysqli_fetch_assoc($a_res))
            $ambiente_nome_email = $a_row['nome'];
    }
    $di_fmt = date('d/m/Y', strtotime($data_inicio));
    $df_fmt = date('d/m/Y', strtotime($data_fim));
    $h_ini_fmt = substr($horario_inicio, 0, 5);
    $h_fim_fmt = substr($horario_fim, 0, 5);
    $dias_fmt = !empty($dias_semana_str) ? str_replace(',', ', ', $dias_semana_str) : 'Datas específicas (Flexível)';


    // --- VALIDATION: Professor Hour Limits & Conflicts ---
    $docentes_to_check = array_values(array_filter([$docente_id1, $docente_id2, $docente_id3, $docente_id4], function ($val) {
        return $val !== "NULL" && $val > 0;
    }));

    if (empty($docentes_to_check) && !$is_reserva && empty($_POST['validate_only'])) {
        mysqli_rollback($conn);
        handle_response($conn, false, "Pelo menos um docente deve ser selecionado.", "", $is_ajax);
    }

    foreach ($docentes_to_check as $did) {
        $val_res = checkDocenteLimits($conn, $did, (!$is_reserva ? $id : null), $data_inicio, $data_fim, $dias_semana_arr, $horario_inicio, $horario_fim, $periodo, $tipo_agenda, $agenda_flexivel);
        if ($val_res !== true) {
            mysqli_rollback($conn);
            handle_response($conn, false, $val_res, "", $is_ajax);
        }

        $conf_res = checkDocenteConflicts($conn, $did, (!$is_reserva ? $id : null), $data_inicio, $data_fim, $dias_semana_arr, $horario_inicio, $horario_fim, $tipo_agenda, $agenda_flexivel);
        if ($conf_res !== true) {
            mysqli_rollback($conn);
            handle_response($conn, false, $conf_res, "", $is_ajax);
        }

        $work_res = checkDocenteWorkSchedule($conn, $did, $data_inicio, $data_fim, $dias_semana_arr, $periodo, $horario_inicio, $horario_fim, $tipo_agenda, $agenda_flexivel);
        if ($work_res !== true) {
            mysqli_rollback($conn);
            handle_response($conn, false, $work_res, "", $is_ajax);
        }
    }

    // --- VALIDATION: Environment (Ambiente) Conflict ---
    if ($ambiente_id !== "NULL" && $ambiente_id > 0) {
        $amb_res = checkAmbienteConflict($conn, $ambiente_id, (!$is_reserva ? $id : null), $data_inicio, $data_fim, $dias_semana_arr, $horario_inicio, $horario_fim, $tipo_agenda, $agenda_flexivel);
        if ($amb_res !== true) {
            mysqli_rollback($conn);
            handle_response($conn, false, $amb_res, "", $is_ajax);
        }
    }

    if (isset($_POST['validate_only']) && $_POST['validate_only'] == '1') {
        mysqli_commit($conn); // Simulação não precisa de rollback mas liberamos a trava
        handle_response($conn, true, "Horário disponível", "", $is_ajax);
    }

    if ($is_reserva) {
        // --- PROCESSAMENTO DE RESERVA ---
        $usuario_id = $_SESSION['user_id'] ?? null;
        if (!$usuario_id) {
            $admin_res = mysqli_query($conn, "SELECT id FROM usuario WHERE role = 'admin' LIMIT 1");
            if ($row_adm = mysqli_fetch_assoc($admin_res)) {
                $usuario_id = (int) $row_adm['id'];
            } else {
                mysqli_rollback($conn);
                handle_response($conn, false, "Sessão expirada. Por favor, faça login novamente.", "login.php", $is_ajax);
            }
        }
        $usuario_id_sql = $usuario_id ? (int) $usuario_id : "NULL";

        $principal_docente = !empty($docentes_to_check) ? $docentes_to_check[0] : 0;
        if (!$principal_docente && empty($id)) {
            mysqli_rollback($conn);
            handle_response($conn, false, "É necessário selecionar um docente para a reserva.", "", $is_ajax);
        }

        if ($id) {
            $query = "UPDATE reservas SET docente_id = $principal_docente, curso_id = $curso_id, ambiente_id = $ambiente_id, data_inicio = '$data_inicio', data_fim = '$data_fim', hora_inicio = '$horario_inicio', hora_fim = '$horario_fim', dias_semana = '$dias_semana_str', sigla = '$sigla', periodo = '$periodo', vagas = $vagas, local = '$local', tipo = '$tipo', tipo_custeio = '$tipo_custeio', previsao_despesa = $previsao_despesa, valor_turma = $valor_turma, numero_proposta = '$numero_proposta', tipo_atendimento = '$tipo_atendimento', parceiro = '$parceiro', contato_parceiro = '$contato_parceiro', tipo_agenda = '$tipo_agenda', agenda_flexivel = '$agenda_flexivel' WHERE id = '$id'";
        } else {
            $status_inicial = 'PENDENTE';
            $query = "INSERT INTO reservas (docente_id, curso_id, ambiente_id, usuario_id, data_inicio, data_fim, dias_semana, hora_inicio, hora_fim, sigla, periodo, status, vagas, local, tipo, tipo_custeio, previsao_despesa, valor_turma, numero_proposta, tipo_atendimento, parceiro, contato_parceiro, tipo_agenda, agenda_flexivel) VALUES ($principal_docente, $curso_id, $ambiente_id, $usuario_id_sql, '$data_inicio', '$data_fim', '$dias_semana_str', '$horario_inicio', '$horario_fim', '$sigla', '$periodo', '$status_inicial', $vagas, '$local', '$tipo', '$tipo_custeio', $previsao_despesa, $valor_turma, '$numero_proposta', '$tipo_atendimento', '$parceiro', '$contato_parceiro', '$tipo_agenda', '$agenda_flexivel')";
        }

        if (mysqli_query($conn, $query)) {
            $res_id = $id ?: mysqli_insert_id($conn);
            $msg = $id ? "Reserva atualizada" : "Reserva criada com sucesso";
            $executor = $_SESSION['user_nome'] ?? 'Usuário';
            $notif_tipo = $id ? 'edicao_turma' : 'reserva_realizada';
            $notif_titulo = $id ? 'Reserva Atualizada' : 'Nova Reserva Solicitada';
            $notif_msg = "A reserva ($sigla) foi " . ($id ? "atualizada" : "solicitada") . " por $executor.";
            dispararNotificacaoGlobal($conn, $notif_tipo, $notif_titulo, $notif_msg, BASE_URL . "/php/views/gerenciar_reservas.php?status=PENDENTE&reserva_id=$res_id", ['admin', 'gestor']);

            if (!$id && isset($_POST['send_email']) && $_POST['send_email'] == '1') {
                require_once __DIR__ . '/../configs/mailer.php';
                $did = $principal_docente;
                $d_res = mysqli_query($conn, "SELECT d.nome, u.email FROM docente d LEFT JOIN usuario u ON u.docente_id = d.id WHERE d.id = $did");
                if ($d_res && $d_row = mysqli_fetch_assoc($d_res)) {
                    $d_email = trim($d_row['email'] ?? '');
                    $d_nome = $d_row['nome'];
                    if (!empty($d_email)) {
                        $subject = "Solicitação de Reserva: $display_nome";
                        $body = "
                            <div style='font-family: sans-serif; color: #333;'>
                                <h2 style='color: #ed1c24;'>Olá, $d_nome!</h2>
                                <p>Uma nova <strong>solicitação de reserva</strong> foi realizada no sistema e está aguardando aprovação.</p>
                                <div style='background: #f4f4f4; padding: 15px; border-radius: 8px; border-left: 4px solid #ffb300;'>
                                    <p style='margin: 5px 0;'><strong>Curso:</strong> $curso_nome_email</p>
                                    <p style='margin: 5px 0;'><strong>Sigla:</strong> $display_nome</p>
                                    <p style='margin: 5px 0;'><strong>Datas:</strong> $di_fmt até $df_fmt</p>
                                    <p style='margin: 5px 0;'><strong>Horário:</strong> $h_ini_fmt às $h_fim_fmt ($periodo)</p>
                                    <p style='margin: 5px 0;'><strong>Dias:</strong> $dias_fmt</p>
                                    <p style='margin: 5px 0;'><strong>Ambiente:</strong> $ambiente_nome_email</p>
                                </div>
                                <p style='margin-top: 10px; font-size: 0.9rem; color: #666;'>Você receberá uma nova notificação assim que a reserva for aprovada ou recusada pela coordenação.</p>
                                <p style='margin-top: 15px; font-size: 0.85rem; color: #555; line-height: 1.4;'><strong>Importante:</strong> prepare-se para essa nova turma, elaborando os Planos de Ensino e o Cronograma de Aulas e entregando-os à coordenação com, no mínimo, dois dias de antecedência, a necessidade de materiais de apoio (apostilas ou livros), materiais de consumo e a adequação do ambiente, alinhando essas demandas previamente com o Prof. Flávio.</p>
                                <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                                <p style='font-size: 0.85rem; color: #888;'>Confira agora: <a href='https://ocupacaodocente.senaivotuporanga.com.br/' style='color: #ed1c24; text-decoration: none; font-weight: 700;'>https://ocupacaodocente.senaivotuporanga.com.br/</a></p>
                            </div>
                        ";
                        if (!sendEmail($d_email, $subject, $body)) {
                            mysqli_rollback($conn);
                            handle_response($conn, false, "Falha ao enviar e-mail de notificação da reserva. A operação foi cancelada.", "", $is_ajax);
                        }

                        // Enviar cópia para a coordenação (configurado em mailer.php)
                        $f_subject = "Cópia: Solicitação de Reserva - $display_nome";
                        $f_body = "
                            <div style='font-family: sans-serif; color: #333;'>
                                <h2 style='color: #ed1c24;'>Olá, " . NOME_COPIA . "!</h2>
                                <p>Informamos que uma <strong>nova reserva</strong> foi cadastrada no sistema.</p>
                                <div style='background: #f4f4f4; padding: 15px; border-radius: 8px; border-left: 4px solid #ed1c24;'>
                                    <p style='margin: 5px 0;'><strong>Docente:</strong> $d_nome</p>
                                    <p style='margin: 5px 0;'><strong>Curso:</strong> $curso_nome_email</p>
                                    <p style='margin: 5px 0;'><strong>Sigla:</strong> $display_nome</p>
                                    <p style='margin: 5px 0;'><strong>Datas:</strong> $di_fmt até $df_fmt</p>
                                    <p style='margin: 5px 0;'><strong>Horário:</strong> $h_ini_fmt às $h_fim_fmt ($periodo)</p>
                                    <p style='margin: 5px 0;'><strong>Dias:</strong> $dias_fmt</p>
                                    <p style='margin: 5px 0;'><strong>Ambiente:</strong> $ambiente_nome_email</p>
                                </div>
                                <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                                <p style='font-size: 0.85rem; color: #888;'>Confira agora: <a href='https://ocupacaodocente.senaivotuporanga.com.br/' style='color: #ed1c24; text-decoration: none; font-weight: 700;'>https://ocupacaodocente.senaivotuporanga.com.br/</a></p>
                            </div>
                        ";
                        sendEmail(EMAIL_COPIA, $f_subject, $f_body);
                    } else {
                        mysqli_rollback($conn);
                        handle_response($conn, false, "O docente selecionado não possui e-mail cadastrado. Não é possível enviar a notificação solicitada.", "", $is_ajax);
                    }
                }
            }
            mysqli_commit($conn);
            $next_url = !empty($_POST['return_url']) ? $_POST['return_url'] : "../views/agenda_professores.php?docente_id=" . $docentes_to_check[0] . "&msg=created";
            handle_response($conn, true, $msg, $next_url, $is_ajax);
        } else {
            mysqli_rollback($conn);
            handle_response($conn, false, "Erro ao salvar reserva: " . mysqli_error($conn), "", $is_ajax);
        }

    } else {
        // --- PROCESSAMENTO DE TURMA ---
        if ($id) {
            $query = "UPDATE turma SET curso_id = $curso_id, tipo = '$tipo', periodo = '$periodo', data_inicio = '$data_inicio', data_fim = '$data_fim', ambiente_id = $ambiente_id, sigla = '$sigla', vagas = $vagas, local = '$local', dias_semana = '$dias_semana_str', horario_inicio = '$horario_inicio', horario_fim = '$horario_fim', docente_id1 = $docente_id1, docente_id2 = $docente_id2, docente_id3 = $docente_id3, docente_id4 = $docente_id4, tipo_custeio = '$tipo_custeio', previsao_despesa = $previsao_despesa, valor_turma = $valor_turma, numero_proposta = '$numero_proposta', tipo_atendimento = '$tipo_atendimento', parceiro = '$parceiro', contato_parceiro = '$contato_parceiro', tipo_agenda = '$tipo_agenda', agenda_flexivel = '$agenda_flexivel' WHERE id = '$id'";
            if (!mysqli_query($conn, $query)) {
                mysqli_rollback($conn);
                handle_response($conn, false, "Erro ao atualizar turma: " . mysqli_error($conn), "", $is_ajax);
            }
            dispararNotificacaoGlobal($conn, 'edicao_turma', 'Turma Atualizada', "A turma $display_nome ($periodo) teve seus dados atualizados.", BASE_URL . "/php/views/turmas.php?id=$id", ['admin', 'gestor', 'professor', 'cri']);
            mysqli_query($conn, "DELETE FROM agenda WHERE turma_id = '$id'");
            generateAgendaRecords($conn, $id, $dias_semana_arr, $periodo, $horario_inicio, $horario_fim, $data_inicio, $data_fim, $ambiente_id, $docentes_to_check, $tipo_agenda, $agenda_flexivel);
            mysqli_commit($conn);
            $next_url = !empty($_POST['return_url']) ? $_POST['return_url'] : "../views/agenda_professores.php?docente_id=" . (!empty($docentes_to_check) ? $docentes_to_check[0] : '') . "&msg=updated";
            handle_response($conn, true, "Turma atualizada com sucesso", $next_url, $is_ajax);
        } else {
            // DEBUG
            if (isset($_POST['send_email'])) {
                // die("DEBUG: send_email=" . $_POST['send_email']);
            }
            $query = "INSERT INTO turma (curso_id, tipo, periodo, data_inicio, data_fim, ambiente_id, sigla, vagas, local, dias_semana, horario_inicio, horario_fim, docente_id1, docente_id2, docente_id3, docente_id4, tipo_custeio, previsao_despesa, valor_turma, numero_proposta, tipo_atendimento, parceiro, contato_parceiro, tipo_agenda, agenda_flexivel) VALUES ($curso_id, '$tipo', '$periodo', '$data_inicio', '$data_fim', $ambiente_id, '$sigla', $vagas, '$local', '$dias_semana_str', '$horario_inicio', '$horario_fim', $docente_id1, $docente_id2, $docente_id3, $docente_id4, '$tipo_custeio', $previsao_despesa, $valor_turma, '$numero_proposta', '$tipo_atendimento', '$parceiro', '$contato_parceiro', '$tipo_agenda', '$agenda_flexivel')";
            if (!mysqli_query($conn, $query)) {
                mysqli_rollback($conn);
                handle_response($conn, false, "Erro ao criar turma: " . mysqli_error($conn), "", $is_ajax);
            }
            $turma_id = mysqli_insert_id($conn);
            dispararNotificacaoGlobal($conn, 'registro_turma', 'Nova Turma Registrada', "A turma $display_nome ($periodo) foi cadastrada no sistema.", BASE_URL . "/php/views/turmas.php?id=$turma_id", ['admin', 'gestor', 'professor', 'cri']);
            generateAgendaRecords($conn, $turma_id, $dias_semana_arr, $periodo, $horario_inicio, $horario_fim, $data_inicio, $data_fim, $ambiente_id, $docentes_to_check, $tipo_agenda, $agenda_flexivel);

            if (isset($_POST['send_email']) && $_POST['send_email'] == '1') {
                require_once __DIR__ . '/../configs/mailer.php';
                $emails_enviados = 0;
                foreach ($docentes_to_check as $did) {
                    $d_res = mysqli_query($conn, "SELECT d.nome, u.email FROM docente d LEFT JOIN usuario u ON u.docente_id = d.id WHERE d.id = $did");
                    if ($d_res && $d_row = mysqli_fetch_assoc($d_res)) {
                        $d_email = trim($d_row['email'] ?? '');
                        $d_nome = $d_row['nome'];
                        if (!empty($d_email)) {
                            $subject = "Nova Turma Atribuída: $display_nome";
                            $body = "
                                <div style='font-family: sans-serif; color: #333;'>
                                    <h2 style='color: #ed1c24;'>Olá, $d_nome!</h2>
                                    <p>Informamos que uma <strong>nova turma</strong> foi cadastrada e atribuída a você no sistema.</p>
                                    <div style='background: #f4f4f4; padding: 15px; border-radius: 8px; border-left: 4px solid #ed1c24;'>
                                        <p style='margin: 5px 0;'><strong>Curso:</strong> $curso_nome_email</p>
                                        <p style='margin: 5px 0;'><strong>Turma:</strong> $display_nome</p>
                                        <p style='margin: 5px 0;'><strong>Período:</strong> $di_fmt até $df_fmt</p>
                                        <p style='margin: 5px 0;'><strong>Horário:</strong> $h_ini_fmt às $h_fim_fmt ($periodo)</p>
                                        <p style='margin: 5px 0;'><strong>Dias:</strong> $dias_fmt</p>
                                        <p style='margin: 5px 0;'><strong>Ambiente:</strong> $ambiente_nome_email</p>
                                        <p style='margin: 5px 0;'><strong>Tipo:</strong> $tipo</p>
                                    </div>
                                    <p style='margin-top: 10px; font-size: 0.9rem; color: #666;'>O seu cronograma já foi gerado na agenda do sistema.</p>
                                    <p style='margin-top: 15px; font-size: 0.85rem; color: #555; line-height: 1.4;'><strong>Importante:</strong> prepare-se para essa nova turma, elaborando os Planos de Ensino e o Cronograma de Aulas e entregando-os à coordenação com, no mínimo, dois dias de antecedência, a necessidade de materiais de apoio (apostilas ou livros), materiais de consumo e a adequação do ambiente, alinhando essas demandas previamente com o Prof. Flávio.</p>
                                    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                                    <p style='font-size: 0.85rem; color: #888;'>Confira agora: <a href='https://ocupacaodocente.senaivotuporanga.com.br/' style='color: #ed1c24; text-decoration: none; font-weight: 700;'>https://ocupacaodocente.senaivotuporanga.com.br/</a></p>
                                </div>
                            ";
                            if (!sendEmail($d_email, $subject, $body)) {
                                mysqli_rollback($conn);
                                handle_response($conn, false, "Falha ao enviar e-mail de notificação para $d_nome ($d_email). O cadastro foi revertido.", "", $is_ajax);
                            }
                            $emails_enviados++;
                        }
                    }
                }

                if ($emails_enviados === 0) {
                    mysqli_rollback($conn);
                    handle_response($conn, false, "Nenhum dos docentes selecionados possui e-mail cadastrado. Não é possível enviar a notificação solicitada.", "", $is_ajax);
                } else {
                    // Enviar cópia única para a coordenação (configurado em mailer.php)
                    $f_subject = "Cópia: Nova Turma Cadastrada - $display_nome";
                    $f_body = "
                        <div style='font-family: sans-serif; color: #333;'>
                            <h2 style='color: #ed1c24;'>Olá, " . NOME_COPIA . "!</h2>
                            <p>Informamos que uma <strong>nova turma</strong> foi cadastrada no sistema.</p>
                            <div style='background: #f4f4f4; padding: 15px; border-radius: 8px; border-left: 4px solid #ed1c24;'>
                                <p style='margin: 5px 0;'><strong>Curso:</strong> $curso_nome_email</p>
                                <p style='margin: 5px 0;'><strong>Turma:</strong> $display_nome</p>
                                <p style='margin: 5px 0;'><strong>Período:</strong> $di_fmt até $df_fmt</p>
                                <p style='margin: 5px 0;'><strong>Horário:</strong> $h_ini_fmt às $h_fim_fmt ($periodo)</p>
                                <p style='margin: 5px 0;'><strong>Dias:</strong> $dias_fmt</p>
                                <p style='margin: 5px 0;'><strong>Ambiente:</strong> $ambiente_nome_email</p>
                                <p style='margin: 5px 0;'><strong>Tipo:</strong> $tipo</p>
                            </div>
                            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                            <p style='font-size: 0.85rem; color: #888;'>Confira agora: <a href='https://ocupacaodocente.senaivotuporanga.com.br/' style='color: #ed1c24; text-decoration: none; font-weight: 700;'>https://ocupacaodocente.senaivotuporanga.com.br/</a></p>
                        </div>
                    ";
                    sendEmail(EMAIL_COPIA, $f_subject, $f_body);
                }
            }
            mysqli_commit($conn);
            $next_url = !empty($_POST['return_url']) ? $_POST['return_url'] : "../views/agenda_professores.php?docente_id=" . (!empty($docentes_to_check) ? $docentes_to_check[0] : '') . "&msg=created";
            handle_response($conn, true, "Turma criada com sucesso", $next_url, $is_ajax);
        }
    }
    exit;
}

