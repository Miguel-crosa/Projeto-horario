<?php
/**
 * Reservation API
 * Reativada e modernizada para suporte ao legado e integração com o novo sistema.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'reserve':
        if (!can_edit()) {
            echo json_encode(['success' => false, 'ok' => false, 'message' => 'Sem permissão para realizar reservas.']);
            exit;
        }
        // Create RESERVADO records for selected slots
        $docente_id = (int) ($_POST['docente_id'] ?? 0);
        $ambiente_id = (int) ($_POST['ambiente_id'] ?? 0);
        $slots = json_decode($_POST['slots'] ?? '[]', true);

        if (!$docente_id || empty($slots)) {
            echo json_encode(['success' => false, 'ok' => false, 'message' => 'Dados insuficientes para reserva.']);
            exit;
        }

        $created = 0;
        $errors = [];

        foreach ($slots as $slot) {
            $data = $slot['data'] ?? '';
            $dia_semana = $slot['dia_semana'] ?? '';
            $periodo = $slot['periodo'] ?? 'Manhã';
            $horario_inicio = $slot['horario_inicio'] ?? '07:30';
            $horario_fim = $slot['horario_fim'] ?? '11:30';

            if (empty($data) || empty($dia_semana))
                continue;

            // 1. Check for existing record in Agenda (confirmed or reserved)
            $stmt_check = $mysqli->prepare("SELECT id FROM agenda WHERE docente_id = ? AND data = ? AND periodo = ?");
            $stmt_check->bind_param('iss', $docente_id, $data, $periodo);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                continue; // Already occupied in Agenda
            }

            // 2. Check for conflicts in the 'reservas' table (from Parafal system)
            // We check if the date falls within a reservation range and overlaps in time
            $stmt_res = $mysqli->prepare("
                SELECT dias_semana FROM reservas 
                WHERE docente_id = ? 
                AND status = 'ativo' 
                AND ? BETWEEN data_inicio AND data_fim
                AND (hora_inicio < ? AND hora_fim > ?)
            ");
            $stmt_res->bind_param('isss', $docente_id, $data, $horario_fim, $horario_inicio);
            $stmt_res->execute();
            $res_conflicts = $stmt_res->get_result();

            // Check if any of those reservations actually include this day of week
            $has_res_conflict = false;
            if ($res_conflicts->num_rows > 0) {
                $dt = new DateTime($data);
                $dow = (int) $dt->format('N'); // 1 (Mon) to 7 (Sun)

                while ($row = $res_conflicts->fetch_assoc()) {
                    $dias_arr = explode(',', $row['dias_semana']);
                    if (in_array((string) $dow, $dias_arr)) {
                        $has_res_conflict = true;
                        break;
                    }
                }
            }

            if ($has_res_conflict)
                continue;

            $amb_val = ($ambiente_id > 0 && $ambiente_id != ID_AMBIENTE_OUTROS) ? $ambiente_id : ($ambiente_id == ID_AMBIENTE_OUTROS ? ID_AMBIENTE_OUTROS : null);
            $status = 'RESERVADO';

            $stmt_ins = $mysqli->prepare("INSERT INTO agenda (docente_id, ambiente_id, dia_semana, periodo, horario_inicio, horario_fim, data, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_ins->bind_param('iissssss', $docente_id, $amb_val, $dia_semana, $periodo, $horario_inicio, $horario_fim, $data, $status);

            if ($stmt_ins->execute()) {
                $created++;
            } else {
                $errors[] = $mysqli->error;
            }
        }

        echo json_encode([
            'success' => $created > 0,
            'ok' => $created > 0,
            'message' => $created > 0 ? "$created horário(s) reservado(s) com sucesso!" : "Nenhum horário pôde ser reservado (conflitos ou erro).",
            'count' => $created,
            'errors' => $errors
        ]);
        break;

    case 'confirm':
        if (!can_edit()) {
            echo json_encode(['success' => false, 'ok' => false, 'message' => 'Sem permissão para confirmar reservas.']);
            exit;
        }
        // Confirm reservation (RESERVADO -> CONFIRMADO)
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $mysqli->prepare("UPDATE agenda SET status = 'CONFIRMADO' WHERE id = ? AND status = 'RESERVADO'");
            $stmt->bind_param('i', $id);
            $stmt->execute();

            if ($mysqli->affected_rows > 0) {
                echo json_encode(['success' => true, 'ok' => true, 'message' => 'Reserva confirmada!']);
            } else {
                echo json_encode(['success' => false, 'ok' => false, 'message' => 'Nenhuma reserva pendente encontrada para este ID.']);
            }
        } else {
            echo json_encode(['success' => false, 'ok' => false, 'message' => 'ID inválido.']);
        }
        break;

    case 'cancel':
        if (!can_edit()) {
            echo json_encode(['success' => false, 'ok' => false, 'message' => 'Sem permissão para cancelar reservas.']);
            exit;
        }
        // Cancel reservation (delete RESERVADO records)
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $mysqli->prepare("DELETE FROM agenda WHERE id = ? AND status = 'RESERVADO'");
            $stmt->bind_param('i', $id);
            $stmt->execute();

            if ($mysqli->affected_rows > 0) {
                echo json_encode(['success' => true, 'ok' => true, 'message' => 'Reserva cancelada.']);
            } else {
                echo json_encode(['success' => false, 'ok' => false, 'message' => 'Nenhuma reserva pendente encontrada para este ID.']);
            }
        } else {
            echo json_encode(['success' => false, 'ok' => false, 'message' => 'ID inválido.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'ok' => false, 'error' => 'Ação inválida']);
        break;
}
?>