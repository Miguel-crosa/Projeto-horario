<?php
/**
 * AgendaModel.php
 * Lógica centralizada para buscar dados de aulas e reservas.
 *
 * FIX GLOBAL: Todos os loops `while ($cur <= $last)` foram substituídos por
 * `while ($cur->format('Y-m-d') <= $last->format('Y-m-d'))` e todos os objetos
 * DateTime recebem setTime(0,0,0) logo após a criação.
 * Isso evita que microssegundos residuais acumulados por modify('+1 day') façam
 * o último dia do intervalo ser ignorado silenciosamente.
 */

class AgendaModel
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Retorna todos os itens (aulas e reservas) para um conjunto de docentes numa margem de datas.
     */
    public function getUnifiedAgenda($docente_ids, $start_date, $end_date, $filters = [])
    {
        if (empty($docente_ids))
            return [];

        // Prepare IDs list securely
        $ids_count = count($docente_ids);
        $placeholders = implode(',', array_fill(0, $ids_count, '?'));
        $types = str_repeat('i', $ids_count);

        $results = [];

        // 1. Aulas Confirmadas (Tabela agenda)
        $query_classes = "
            SELECT a.id, a.dia_semana, a.periodo, a.horario_inicio, a.horario_fim,
                   a.data AS agenda_data, a.status, a.docente_id,
                   c.nome AS curso_nome, t.sigla as turma_nome, t.data_inicio, t.data_fim, t.id AS turma_id,
                   t.valor_turma, t.tipo_custeio, t.previsao_despesa,
                   COALESCE(amb.nome, t.local) AS ambiente_nome, 'AULA' as type
            FROM agenda a
            JOIN turma t ON a.turma_id = t.id
            JOIN curso c ON t.curso_id = c.id
            LEFT JOIN ambiente amb ON a.ambiente_id = amb.id
            WHERE a.docente_id IN ($placeholders)
              AND (
                  (a.data IS NOT NULL AND a.data BETWEEN ? AND ?)
                  OR
                  (a.data IS NULL AND t.data_inicio <= ? AND t.data_fim >= ?)
              )
        ";

        if (!empty($filters['periodo'])) {
            $p = $filters['periodo'];
            if ($p === 'Integral') {
                $query_classes .= " AND (a.periodo = 'Manhã' OR a.periodo = 'Tarde' OR a.periodo = 'Integral')";
            } else {
                $query_classes .= " AND a.periodo = ?";
            }
        }

        $stmt = $this->conn->prepare($query_classes);
        $bind_params = array_merge($docente_ids, [$start_date, $end_date, $end_date, $start_date]);
        $bind_types = $types . 'ssss';
        if (!empty($filters['periodo']) && $filters['periodo'] !== 'Integral') {
            $bind_params[] = $filters['periodo'];
            $bind_types .= 's';
        }
        $stmt->bind_param($bind_types, ...$bind_params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $results[] = $row;
        }

        // 2. Reservas (Tabela reservas)
        $query_reservas = "
            SELECT r.id, r.dias_semana AS dia_semana_list, r.periodo, r.hora_inicio AS horario_inicio, r.hora_fim AS horario_fim,
                   r.status, r.docente_id,
                   c.nome AS curso_nome, r.data_inicio, r.data_fim, NULL AS turma_id,
                   r.valor_turma, r.tipo_custeio, r.previsao_despesa,
                   COALESCE(amb.nome, r.local) AS ambiente_nome, r.usuario_id, r.sigla, 'RESERVA' as type
            FROM reservas r
            LEFT JOIN curso c ON r.curso_id = c.id
            LEFT JOIN ambiente amb ON r.ambiente_id = amb.id
            WHERE r.docente_id IN ($placeholders)
              AND r.status IN ('PENDENTE', 'APROVADA')
              AND r.data_inicio <= ? AND r.data_fim >= ?
        ";

        if (!empty($filters['periodo'])) {
            $p = $filters['periodo'];
            if ($p === 'Integral') {
                $query_reservas .= " AND (r.periodo = 'Manhã' OR r.periodo = 'Tarde' OR r.periodo = 'Integral')";
            } else {
                $query_reservas .= " AND r.periodo = ?";
            }
        }

        $stmt = $this->conn->prepare($query_reservas);
        $bind_params_res = array_merge($docente_ids, [$end_date, $start_date]);
        $bind_types_res = $types . 'ss';
        if (!empty($filters['periodo']) && $filters['periodo'] !== 'Integral') {
            $bind_params_res[] = $filters['periodo'];
            $bind_types_res .= 's';
        }
        $stmt->bind_param($bind_types_res, ...$bind_params_res);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $results[] = $row;
        }

        // 3. Reservas Legadas (Tabela agenda sem turma_id)
        $query_legacy = "
            SELECT a.id, a.dia_semana, a.periodo, a.horario_inicio, a.horario_fim,
                   a.data AS agenda_data, a.status, a.docente_id,
                   'Legado' AS curso_nome, a.data AS data_inicio, a.data AS data_fim, NULL AS turma_id,
                   amb.nome AS ambiente_nome, 'RESERVA_LEGADO' as type
            FROM agenda a
            LEFT JOIN ambiente amb ON a.ambiente_id = amb.id
            WHERE a.docente_id IN ($placeholders)
              AND a.turma_id IS NULL AND a.status = 'RESERVADO'
              AND a.data BETWEEN ? AND ?
        ";

        if (!empty($filters['periodo'])) {
            $p = $filters['periodo'];
            if ($p === 'Integral') {
                $query_legacy .= " AND (a.periodo = 'Manhã' OR a.periodo = 'Tarde' OR a.periodo = 'Integral')";
            } else {
                $query_legacy .= " AND a.periodo = ?";
            }
        }

        $stmt = $this->conn->prepare($query_legacy);
        $bind_params_leg = array_merge($docente_ids, [$start_date, $end_date]);
        $bind_types_leg = $types . 'ss';
        if (!empty($filters['periodo']) && $filters['periodo'] !== 'Integral') {
            $bind_params_leg[] = $filters['periodo'];
            $bind_types_leg .= 's';
        }
        $stmt->bind_param($bind_types_leg, ...$bind_params_leg);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $results[] = $row;
        }

        // 4. Feriados (Tabela holidays)
        $stmt = $this->conn->prepare("SELECT name, date, end_date, 'FERIADO' as type FROM holidays WHERE (date <= ? AND end_date >= ?)");
        $stmt->bind_param('ss', $end_date, $start_date);
        $stmt->execute();
        $res_h = $stmt->get_result();
        while ($row = $res_h->fetch_assoc()) {
            $results[] = $row;
        }

        // 5. Férias (Tabela vacations)
        $query_vacations = "
            SELECT type as vacation_type, start_date, end_date, teacher_id, 'FERIAS' as type
            FROM vacations
            WHERE (teacher_id IN ($placeholders) OR (type = 'collective' AND teacher_id IS NULL))
              AND start_date <= ? AND end_date >= ?
        ";
        $stmt = $this->conn->prepare($query_vacations);
        $bind_params_vac = array_merge($docente_ids, [$end_date, $start_date]);
        $bind_types_vac = $types . 'ss';
        $stmt->bind_param($bind_types_vac, ...$bind_params_vac);
        $stmt->execute();
        $res_v = $stmt->get_result();
        while ($row = $res_v->fetch_assoc()) {
            $results[] = $row;
        }

        // 6. Preparação e Atestados (Tabela preparacao_atestados)
        $query_prep = "
            SELECT id, docente_id, tipo, data_inicio, data_fim, horario_inicio, horario_fim, dias_semana, 'PREPARACAO' as type
            FROM preparacao_atestados
            WHERE docente_id IN ($placeholders)
              AND data_inicio <= ? AND data_fim >= ?
        ";
        $stmt = $this->conn->prepare($query_prep);
        $bind_params_prep = array_merge($docente_ids, [$end_date, $start_date]);
        $bind_types_prep = $types . 'ss';
        $stmt->bind_param($bind_types_prep, ...$bind_params_prep);
        $stmt->execute();
        $res_prep = $stmt->get_result();
        while ($row = $res_prep->fetch_assoc()) {
            $results[] = $row;
        }

        // 7. Horário de Trabalho — Blocos Sazonais
        $query_ht = "
            SELECT docente_id, dias, periodo, horario, data_inicio, data_fim, ano, 'WORK_SCHEDULE' as type
            FROM horario_trabalho
            WHERE docente_id IN ($placeholders)
              AND (
                  (data_inicio IS NULL AND data_fim IS NULL)
                  OR
                  (data_inicio <= ? AND data_fim >= ?)
              )
        ";
        $stmt = $this->conn->prepare($query_ht);
        $bind_params_ht = array_merge($docente_ids, [$end_date, $start_date]);
        $bind_types_ht = $types . 'ss';
        $stmt->bind_param($bind_types_ht, ...$bind_params_ht);
        $stmt->execute();
        $res_ht = $stmt->get_result();
        while ($row = $res_ht->fetch_assoc()) {
            $results[] = $row;
        }

        return $results;
    }

    /**
     * Quebra todos os itens da agenda e os expande em dias individuais.
     *
     * FIX: Todos os objetos DateTime são normalizados com setTime(0,0,0) após
     * a criação, e todos os loops usam comparação por string ('Y-m-d') em vez
     * de comparar objetos diretamente. Isso garante que o último dia do
     * intervalo nunca seja ignorado por diferença de microsegundos.
     */
    public function getExpandedAgenda($docente_ids, $start_date, $end_date, $filters = [])
    {
        $raw = $this->getUnifiedAgenda($docente_ids, $start_date, $end_date, $filters);
        $expanded = [];

        // FIX: normaliza as datas do intervalo solicitado
        $start_dt = new DateTime($start_date);
        $start_dt->setTime(0, 0, 0);
        $end_dt = new DateTime($end_date);
        $end_dt->setTime(0, 0, 0);

        foreach ($raw as $item) {

            // --- AULA com data individual já registrada no banco ---
            if (($item['type'] === 'AULA' || $item['type'] === 'RESERVA_LEGADO') && !empty($item['agenda_data'])) {
                $expanded[] = $item;

                // --- AULA recorrente vinculada a Turma (sem data individual) ---
            } elseif ($item['type'] === 'AULA' && empty($item['agenda_data'])) {
                $cur = new DateTime(max($item['data_inicio'], $start_date));
                $cur->setTime(0, 0, 0); // FIX
                $last = new DateTime(min($item['data_fim'], $end_date));
                $last->setTime(0, 0, 0); // FIX

                $dayTarget = $item['dia_semana'];

                while ($cur->format('Y-m-d') <= $last->format('Y-m-d')) { // FIX
                    $dow = (int) $cur->format('N');
                    $dayName = $this->getDiaSemanaName($dow);
                    if ($this->normalizeDayName($dayName) === $this->normalizeDayName($dayTarget)) {
                        $newItem = $item;
                        $newItem['agenda_data'] = $cur->format('Y-m-d');
                        $expanded[] = $newItem;
                    }
                    $cur->modify('+1 day');
                }

                // --- RESERVA recorrente ---
            } elseif ($item['type'] === 'RESERVA') {
                $dias_arr = explode(',', $item['dia_semana_list']);

                $cur = new DateTime(max($item['data_inicio'], $start_date));
                $cur->setTime(0, 0, 0); // FIX
                $last = new DateTime(min($item['data_fim'], $end_date));
                $last->setTime(0, 0, 0); // FIX

                while ($cur->format('Y-m-d') <= $last->format('Y-m-d')) { // FIX
                    $dow = (int) $cur->format('N');
                    $dayName = $this->getDiaSemanaName($dow);
                    
                    $normalizedDays = array_map([$this, 'normalizeDayName'], $dias_arr);
                    if (in_array($this->normalizeDayName($dayName), $normalizedDays)) {
                        $newItem = $item;
                        $newItem['agenda_data'] = $cur->format('Y-m-d');
                        $newItem['dia_semana'] = $dayName;
                        $expanded[] = $newItem;
                    }
                    $cur->modify('+1 day');
                }

                // --- FERIADO ---
            } elseif ($item['type'] === 'FERIADO') {
                $f_start = new DateTime(max($item['date'], $start_date));
                $f_start->setTime(0, 0, 0); // FIX
                $f_end = new DateTime(min($item['end_date'], $end_date));
                $f_end->setTime(0, 0, 0); // FIX

                while ($f_start->format('Y-m-d') <= $f_end->format('Y-m-d')) { // FIX
                    foreach ($docente_ids as $pid) {
                        $newItem = $item;
                        $newItem['docente_id'] = $pid;
                        $newItem['agenda_data'] = $f_start->format('Y-m-d');
                        $newItem['horario_inicio'] = '00:00:00';
                        $newItem['horario_fim'] = '23:59:59';
                        $newItem['curso_nome'] = 'FERIADO: ' . $item['name'];
                        $expanded[] = $newItem;
                    }
                    $f_start->modify('+1 day');
                }

                // --- FÉRIAS / FECHAMENTO ---
            } elseif ($item['type'] === 'FERIAS') {
                $cur = new DateTime(max($item['start_date'], $start_date));
                $cur->setTime(0, 0, 0); // FIX
                $last = new DateTime(min($item['end_date'], $end_date));
                $last->setTime(0, 0, 0); // FIX

                $lbl = ($item['vacation_type'] === 'collective' ? 'FECHAMENTO' : 'FÉRIAS');

                while ($cur->format('Y-m-d') <= $last->format('Y-m-d')) { // FIX
                    $pids_to_add = $item['teacher_id'] ? [$item['teacher_id']] : $docente_ids;
                    foreach ($pids_to_add as $pid) {
                        if (!in_array($pid, $docente_ids))
                            continue;

                        $newItem = $item;
                        $newItem['docente_id'] = $pid;
                        $newItem['agenda_data'] = $cur->format('Y-m-d');
                        $newItem['horario_inicio'] = '00:00:00';
                        $newItem['horario_fim'] = '23:59:59';
                        $newItem['curso_nome'] = $lbl;
                        $expanded[] = $newItem;
                    }
                    $cur->modify('+1 day');
                }

                // --- PREPARAÇÃO / ATESTADO ---
            } elseif ($item['type'] === 'PREPARACAO') {
                $cur = new DateTime(max($item['data_inicio'], $start_date));
                $cur->setTime(0, 0, 0); // FIX
                $last = new DateTime(min($item['data_fim'], $end_date));
                $last->setTime(0, 0, 0); // FIX

                $lbl = ($item['tipo'] === 'atestado' ? 'Atestado Médico' : 'Preparação de Aula');
                $dias_permitidos = !empty($item['dias_semana']) ? explode(',', $item['dias_semana']) : [];

                while ($cur->format('Y-m-d') <= $last->format('Y-m-d')) { // FIX
                    $dow = (int) $cur->format('N');

                    if (!empty($dias_permitidos) && $item['tipo'] === 'preparação') {
                        if (!in_array($dow, $dias_permitidos)) {
                            $cur->modify('+1 day');
                            continue;
                        }
                    }

                    $newItem = $item;
                    $newItem['agenda_data'] = $cur->format('Y-m-d');
                    $newItem['horario_inicio'] = $item['horario_inicio'] ?: '00:00:00';
                    $newItem['horario_fim'] = $item['horario_fim'] ?: '23:59:59';
                    $newItem['curso_nome'] = $lbl;
                    $expanded[] = $newItem;
                    $cur->modify('+1 day');
                }

                // --- HORÁRIO DE TRABALHO (Blocos Sazonais) ---
            } elseif ($item['type'] === 'WORK_SCHEDULE') {
                // Determina o intervalo efetivo do bloco conforme as datas de vigência
                $bloco_ini = !empty($item['data_inicio']) ? $item['data_inicio'] : $start_date;
                $bloco_fim = !empty($item['data_fim'])    ? $item['data_fim']    : $end_date;

                // Intersecta com o intervalo consultado
                $cur  = new DateTime(max($bloco_ini, $start_date));
                $cur->setTime(0, 0, 0);
                $last = new DateTime(min($bloco_fim, $end_date));
                $last->setTime(0, 0, 0);

                $days_list = array_map('trim', explode(',', $item['dias'] ?? ''));

                while ($cur->format('Y-m-d') <= $last->format('Y-m-d')) {
                    $dow     = (int) $cur->format('N');
                    $dayName = $this->getDiaSemanaName($dow);
                    $dayNameNormalized = $this->normalizeDayName($dayName);

                    $match = false;
                    foreach ($days_list as $d_target) {
                        if ($dayNameNormalized === $this->normalizeDayName($d_target)) {
                            $match = true;
                            break;
                        }
                    }

                    if ($match) {
                        $newItem = $item;
                        $newItem['agenda_data'] = $cur->format('Y-m-d');
                        $expanded[] = $newItem;
                    }
                    $cur->modify('+1 day');
                }
            }
        }

        return $expanded;
    }

    private function getDiaSemanaName($dow)
    {
        $map = [1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado', 7 => 'Domingo'];
        return $map[$dow] ?? 'Desconhecido';
    }

    private function normalizeDayName($str)
    {
        $str = mb_strtolower($str, 'UTF-8');
        $str = str_replace(['á', 'â', 'ã', 'é', 'ê', 'í', 'ó', 'ô', 'õ', 'ú', 'ç'], ['a', 'a', 'a', 'e', 'e', 'i', 'o', 'o', 'o', 'u', 'c'], $str);
        return trim($str);
    }
}