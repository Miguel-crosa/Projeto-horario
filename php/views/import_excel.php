<?php
/**
 * Sistema de Importação Excel
 */
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';
?>
<link rel="stylesheet" href="../../css/import_excel.css">
<?php


// ────────────────────────────────────────────────────────
// HELPERS
// ────────────────────────────────────────────────────────
function normalizeKey($k)
{
    if (!$k)
        return '';
    $k = mb_strtolower(trim($k), 'UTF-8');
    // Remove accents and special chars
    $map = ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c', 'ª' => '', 'º' => '', 'ᵃ' => ''];
    $k = strtr($k, $map);
    // Remove everything that's not a-z or 0-9
    $k = preg_replace('/[^a-z0-9]/', '', $k);
    return $k;
}

function parseExcelDate($v)
{
    if (!$v)
        return null;
    $v = trim((string) $v);

    // d/m/Y or m/d/Y
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $v, $m)) {
        $day = (int) $m[1];
        $month = (int) $m[2];
        $year = (int) $m[3];
        if ($year < 100)
            $year += 2000;

        // Se o mês for > 12 e o dia <= 12, provavelmente é formato americano M/D/Y
        // Mas se o dia for > 12 e o mês <= 12, é certamente D/M/Y
        if ($month > 12 && $day <= 12) {
            // Swap
            $tmp = $day;
            $day = $month;
            $month = $tmp;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    // Y-m-d
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m))
        return $m[1] . '-' . $m[2] . '-' . $m[3];

    // Numeric (Excel Serial)
    if (is_numeric($v) && (float) $v > 30000 && (float) $v < 60000) {
        $unix = ((float) $v - 25568) * 86400;
        return gmdate('Y-m-d', (int) $unix);
    }

    // Try strtotime as fallback
    $ts = strtotime($v);
    if ($ts && $ts > 0)
        return date('Y-m-d', $ts);

    return null;
}

function parseTime($v)
{
    if ($v === null || $v === '' || $v === false)
        return null;

    // Check if it's an ISO/Excel XML DateTime: 1899-12-31T07:30:00.000
    if (preg_match('/T(\d{2}:\d{2})/', (string) $v, $m)) {
        return $m[1];
    }

    if (is_numeric($v) && (float) $v > 0 && (float) $v < 1) {
        $total_minutes = (int) round((float) $v * 24 * 60);
        $h = str_pad((int) floor($total_minutes / 60), 2, '0', STR_PAD_LEFT);
        $min = str_pad($total_minutes % 60, 2, '0', STR_PAD_LEFT);
        return "$h:$min";
    }
    $v = trim((string) $v);
    if ($v === '' || $v[0] === '#')
        return null;
    $v = preg_replace('/(\d{1,2})h(\d{2})?/i', '$1:$2', $v);
    $v = str_replace('::', ':', $v);
    if (preg_match('/(\d{1,2}):?(\d{2})?/', $v, $m)) {
        $h = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $min = isset($m[2]) && $m[2] !== '' ? $m[2] : '00';
        return "$h:$min";
    }
    return null;
}

function deriveTurno($horario)
{
    if (preg_match('/(\d{1,2})/', $horario, $m)) {
        $h = (int) $m[1];
        if ($h >= 18)
            return 'Noite';
        if ($h >= 12)
            return 'Tarde';
        return 'Manhã';
    }
    return null;
}

function parseHorarioRange($horario)
{
    if (!$horario)
        return [null, null];
    if (preg_match('/(\d{1,2})[h:]?(\d{2})?\s*(?:às|a|-|–)\s*(\d{1,2})[h:]?(\d{2})?/i', $horario, $m)) {
        $h1 = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $min1 = isset($m[2]) && $m[2] !== '' ? $m[2] : '00';
        $h2 = str_pad($m[3], 2, '0', STR_PAD_LEFT);
        $min2 = isset($m[4]) && $m[4] !== '' ? $m[4] : '00';
        return ["$h1:$min1", "$h2:$min2"];
    }
    return [null, null];
}

function normalizePeriodo($p)
{
    if (!$p)
        return null;
    $p = mb_strtolower(trim($p), 'UTF-8');
    if (strpos($p, 'man') !== false)
        return 'Manhã';
    if (strpos($p, 'tar') !== false)
        return 'Tarde';
    if (strpos($p, 'noi') !== false)
        return 'Noite';
    return null;
}

function _expandDiasRange($dias_str)
{
    // Normaliza: remove acentos, ordinais (ª, º, ᵃ), converte tudo para minúsculo
    $s = mb_strtolower(trim($dias_str), 'UTF-8');
    // Remove 'de ' no início (ex: "de 3ª, 5ª e 6ª")
    // Remove 'de ' no início (ex: "de 3ª, 5ª e 6ª")
    $s = preg_replace('/^de\s+/', '', $s);
    // Remove -feira
    $s = str_replace('-feira', '', $s);

    // Mapa de termos para números (0=dom ... 6=sáb) - PADRÃO PHP date('w')
    $toNum = [
        'segunda' => '1', 'terça' => '2', 'terca' => '2', 'quarta' => '3', 'quinta' => '4', 'sexta' => '5', 'sábado' => '6', 'sabado' => '6', 'domingo' => '0',
        'seg' => '1', 'ter' => '2', 'qua' => '3', 'qui' => '4', 'sex' => '5', 'sab' => '6', 'sáb' => '6', 'dom' => '0',
        '2ª' => '1', '3ª' => '2', '4ª' => '3', '5ª' => '4', '6ª' => '5',
        // Números puros (se vierem da planilha em algum formato padrão)
        '2' => '1', '3' => '2', '4' => '3', '5' => '4', '6' => '5', '7' => '6', '1' => '0'
    ];

    // Ordena as chaves pelo comprimento (descendente) para o strtr processar os termos mais longos primeiro
    uksort($toNum, function ($a, $b) {
        return mb_strlen($b) - mb_strlen($a);
    });

    $s = strtr($s, $toNum);

    // Agora remove qualquer ordinal remanescente (ª, º, ᵃ)
    $s = preg_replace('/[ªºᵃ]/u', '', $s);

    // Agora remove qualquer ordinal remanescente (ª, º, ᵃ)
    $s = preg_replace('/[ªºᵃ]/u', '', $s);

    // Detectar range: "1 a 5", "1-5" (Seg a Sex)
    if (preg_match('/([0-6])\s*([a\-]|ate)\s*([0-6])/', $s, $rm)) {
        $from = (int) $rm[1];
        $to = (int) $rm[3];
        $nums = [];
        for ($n = $from; $n <= $to; $n++)
            $nums[] = $n;
        // Também incluir números extras fora do range (ex: "1, 2 a 4" => 1,2,3,4)
        $rest = str_replace($rm[0], '', $s);
        preg_match_all('/[0-6]/', $rest, $extra);
        foreach ($extra[0] as $e)
            $nums[] = (int) $e;
        return array_values(array_unique($nums));
    }

    // Sem range, apenas lista: "1, 2 e 5"
    preg_match_all('/[0-6]/', $s, $matches);
    return array_values(array_unique(array_map('intval', $matches[0])));
}

function parseDiasSemana($dias_str)
{
    if (!$dias_str)
        return [];
    return _expandDiasRange($dias_str);
}

function parseDiasSemanaNomes($dias_str)
{
    if (!$dias_str || $dias_str === '-')
        return '';
    $numToName = [
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
        0 => 'Domingo'
    ];
    $nums = _expandDiasRange($dias_str);
    $result = [];
    foreach ($nums as $n) {
        if (isset($numToName[$n])) {
            $result[] = $numToName[$n];
        }
    }
    return implode(',', array_unique($result));
}

// ────────────────────────────────────────────────────────
// IMPORT LOGIC — Todas as queries adaptadas para schema gestao_escolar
// ────────────────────────────────────────────────────────
$import_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['import_mode']) || isset($_POST['data']))) {
    // Set high execution time and memory for large imports
    ini_set('max_execution_time', 300);
    ini_set('memory_limit', '512M');

    // Global cache for IDs to avoid redundant DB lookups
    $id_cache = [
        'curso' => [],
        'docente' => [],
        'ambiente' => [],
        'turma' => []
    ];

    $mysqli->begin_transaction();
    try {
        $import_mode = $_POST['import_mode'] ?? 'single';
        $import_type = $_POST['import_type'] ?? 'agenda';
        $results = [];

        // ── MULTI-SHEET IMPORT ──
        if ($import_mode === 'multi') {
            $sheets_json = json_decode($_POST['sheets_json'] ?? '{}', true);
            $order = ['CURSOS', 'DOCENTES', 'AMBIENTES', 'USUARIOS', 'CARGOS DO SISTEMA', 'TURMAS', 'RESERVAS', 'AGENDA', 'AGENDA_CALENDARIO', 'FÉRIAS', 'FERIADOS', 'HORARIO_TRABALHO', 'BLOQUEIOS', 'PREPARACAO_ATESTADOS', 'BLOQUEIO'];
            $agenda_cleared_turmas = [];
            $turmas_processed_in_session = []; // Rastreia quais siglas já foram processadas nesta rodada
            $ht_cleared_profs = []; // Rastreia quais docentes já tiveram o horário limpo nesta sessão

            foreach ($order as $sheet_name) {
                $sheet_key = strtoupper($sheet_name);
                if (!isset($sheets_json[$sheet_key]) || empty($sheets_json[$sheet_key]))
                    continue;
                $rows = $sheets_json[$sheet_key];

                $results[$sheet_name] = [
                    'success' => 0,
                    'unique' => 0,
                    'errors' => [],
                    'total' => count($rows)
                ];

                $unique_tracker = [];
                $errors = [];

                foreach ($rows as $i => $row) {
                    $nome = $sigla = $pname = $tname = null; // Clear key variables
                    $r = [];
                    foreach ($row as $k => $v) {
                        $r[normalizeKey($k)] = trim((string) $v);
                    }

                    try {
                        if ($sheet_key === 'USUARIOS') {
                            $nome = $r['nome'] ?? '';
                            $email = $r['email'] ?? '';
                            $role = $r['cargopermissao'] ?? $r['cargo'] ?? $r['permissao'] ?? $r['papel'] ?? 'professor';
                            $vinculo_nome = trim($r['vinculodocente'] ?? $r['docente'] ?? '');

                            if (!$nome || !$email)
                                continue;

                            $docente_id_vinc = null;
                            if (!empty($vinculo_nome)) {
                                $sd = $mysqli->prepare("SELECT id FROM docente WHERE nome = ?");
                                $sd->bind_param('s', $vinculo_nome);
                                $sd->execute();
                                $res_sd = $sd->get_result()->fetch_row();
                                if ($res_sd) $docente_id_vinc = $res_sd[0];
                            }

                            $default_hash = password_hash('senaisp', PASSWORD_BCRYPT);
                            $stmt = $mysqli->prepare("INSERT INTO usuario (nome, email, role, senha, docente_id, obrigar_troca_senha) VALUES (?, ?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE role = VALUES(role), docente_id = VALUES(docente_id)");
                            $stmt->bind_param('ssssi', $nome, $email, $role, $default_hash, $docente_id_vinc);
                            $stmt->execute();
                        } elseif ($sheet_key === 'DOCENTES') {
                            $nome = $r['nome'] ?? '';
                            $area = $r['area'] ?? $r['especialidade'] ?? $r['areadeconhecimento'] ?? '';
                            $chmax = (int) ($r['cargahoraria'] ?? $r['cargahorariacontratual'] ?? $r['ch'] ?? 0);
                            $weekly_limit = (int) ($r['cargahorariasemanalmax'] ?? $r['limitesemanal'] ?? $r['weeklylimit'] ?? 0);
                            $monthly_limit = (int) ($r['cargahorariamensalmax'] ?? $r['limitemensal'] ?? $r['monthlylimit'] ?? 0);

                            // Auto-multiplier: só se mensal for 0 e semanal for > 0
                            if ($monthly_limit === 0 && $weekly_limit > 0) {
                                $monthly_limit = $weekly_limit * 3;
                            }

                            $cidade = $r['cidade'] ?? $r['cidadeunidade'] ?? '';
                            $turno_raw = $r['periodos'] ?? $r['periodo'] ?? $r['turno'] ?? '';
                            $periodos_list = array_filter(array_map('normalizePeriodo', explode(',', $turno_raw)));
                            $turno = implode(',', $periodos_list);

                            $tipo_contrato = $r['tipocontrato'] ?? $r['contrato'] ?? '';
                            $dias_semana = parseDiasSemanaNomes($r['diasdisponiveis'] ?? $r['diasdasemana'] ?? $r['diassemana'] ?? '');
                            $dias_trabalho = parseDiasSemanaNomes($r['diadetrabalho'] ?? $r['diastrabalho'] ?? '');
                            $disponibilidade = parseDiasSemanaNomes($r['disponibilidade'] ?? $r['disponibilidadesemanal'] ?? '');

                            if (!$nome)
                                throw new Exception("Nome vazio na linha " . ($i + 2));

                            // Miguel: tabela docente, colunas area_conhecimento, dias_semana, dias_trabalho, turno, tipo_contrato, weekly_hours_limit, monthly_hours_limit
                            $ck = $mysqli->prepare("SELECT id FROM docente WHERE nome = ?");
                            $ck->bind_param('s', $nome);
                            $ck->execute();
                            $res_ck = $ck->get_result();
                            $existing_row = $res_ck->fetch_assoc();

                            $doc_id = null;

                            if ($existing_row) {
                                $doc_id = $existing_row['id'];
                                $stmt_upd = $mysqli->prepare("UPDATE docente SET 
                                    area_conhecimento = COALESCE(NULLIF(?, ''), area_conhecimento), 
                                    carga_horaria_contratual = ?, 
                                    weekly_hours_limit = ?,
                                    monthly_hours_limit = ?,
                                    cidade = COALESCE(NULLIF(?, ''), cidade), 
                                    turno = COALESCE(NULLIF(?, ''), turno), 
                                    tipo_contrato = COALESCE(NULLIF(?, ''), tipo_contrato), 
                                    dias_semana = COALESCE(NULLIF(?, ''), dias_semana), 
                                    dias_trabalho = COALESCE(NULLIF(?, ''), dias_trabalho),
                                    disponibilidade_semanal = COALESCE(NULLIF(?, ''), disponibilidade_semanal)
                                    WHERE id = ?");
                                $stmt_upd->bind_param('siiissssssi', $area, $chmax, $weekly_limit, $monthly_limit, $cidade, $turno, $tipo_contrato, $dias_semana, $dias_trabalho, $disponibilidade, $doc_id);
                                $stmt_upd->execute();
                            } else {
                                $stmt_ins = $mysqli->prepare("INSERT INTO docente (nome, area_conhecimento, carga_horaria_contratual, weekly_hours_limit, monthly_hours_limit, cidade, turno, tipo_contrato, dias_semana, dias_trabalho, disponibilidade_semanal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt_ins->bind_param('ssiiissssss', $nome, $area, $chmax, $weekly_limit, $monthly_limit, $cidade, $turno, $tipo_contrato, $dias_semana, $dias_trabalho, $disponibilidade);
                                $stmt_ins->execute();
                                $doc_id = $mysqli->insert_id;
                            }

                            // Se vierem períodos e dias na aba DOCENTES, criamos slots padrão para aparecer no formulário
                            if ($doc_id && $turno && $dias_semana) {
                                $p_parts = explode(',', $turno);
                                foreach ($p_parts as $per) {
                                    $def_h = ($per == 'Manhã' ? '07:30 as 11:30' : ($per == 'Tarde' ? '13:00 as 17:00' : '18:00 as 23:00'));
                                    // Se já houver algo específico para este período, talvez não queiramos sobrescrever se estivermos no fluxo de DOCENTES apenas?
                                    // Mas se o usuário está importando a lista de docentes com turnos novos, faz sentido atualizar.
                                    $mysqli->query("DELETE FROM horario_trabalho WHERE docente_id = $doc_id AND periodo = '$per'");
                                    $stht = $mysqli->prepare("INSERT INTO horario_trabalho (docente_id, dias, periodo, horario) VALUES (?, ?, ?, ?)");
                                    $stht->bind_param('isss', $doc_id, $dias_semana, $per, $def_h);
                                    $stht->execute();
                                }
                            }
                        } elseif ($sheet_key === 'AMBIENTES') {
                            $nome = $r['nome'] ?? '';
                            $tipo = $r['tipo'] ?? 'Sala';
                            $area = $r['area_vinculada'] ?? $r['área'] ?? $r['area'] ?? '';
                            $cidade = $r['cidade'] ?? '';
                            $cap = (int) ($r['capacidade'] ?? 0);

                            if (!$nome)
                                throw new Exception("Nome de ambiente vazio.");

                            $ck = $mysqli->prepare("SELECT id FROM ambiente WHERE nome = ?");
                            $ck->bind_param('s', $nome);
                            $ck->execute();
                            if ($ck->get_result()->fetch_row()) {
                                $stmt_upd = $mysqli->prepare("UPDATE ambiente SET tipo = ?, area_vinculada = ?, cidade = ?, capacidade = ? WHERE nome = ?");
                                $stmt_upd->bind_param('sssis', $tipo, $area, $cidade, $cap, $nome);
                                $stmt_upd->execute();
                            } else {
                                $stmt_ins = $mysqli->prepare("INSERT INTO ambiente (nome, tipo, area_vinculada, cidade, capacidade) VALUES (?, ?, ?, ?, ?)");
                                $stmt_ins->bind_param('ssssi', $nome, $tipo, $area, $cidade, $cap);
                                $stmt_ins->execute();
                            }
                        } elseif ($sheet_key === 'CURSOS') {
                            $nome = $r['nome'] ?? '';
                            $tipo = $r['tipo'] ?? '';
                            $area = $r['area'] ?? '';
                            $ch = (int) ($r['cargahorariatotal'] ?? $r['carga_horaria_total'] ?? $r['cargahoraria'] ?? $r['ch'] ?? $r['chtotal'] ?? $r['cargahorariatotalcurso'] ?? 0);
                            $sem = (($r['semestral'] ?? '') == 'Sim' || ($r['semestral'] ?? '') == '1') ? 1 : 0;
                            if (!$nome)
                                throw new Exception("Nome de curso vazio.");

                            $nome_t = trim($nome);
                            $sc = $mysqli->prepare("SELECT id FROM curso WHERE TRIM(nome) = ?");
                            $sc->bind_param('s', $nome_t);
                            $sc->execute();
                            $existing_curso = $sc->get_result()->fetch_row();
                            if (!$existing_curso) {
                                $stmt_ins = $mysqli->prepare("INSERT INTO curso (nome, tipo, area, carga_horaria_total, semestral) VALUES (?, ?, ?, ?, ?)");
                                $stmt_ins->bind_param('sssii', $nome_t, $tipo, $area, $ch, $sem);
                                $stmt_ins->execute();
                            } else {
                                $stmt_upd = $mysqli->prepare("UPDATE curso SET tipo = ?, area = ?, carga_horaria_total = ?, semestral = ? WHERE id = ?");
                                $stmt_upd->bind_param('ssiii', $tipo, $area, $ch, $sem, $existing_curso[0]);
                                $stmt_upd->execute();
                            }
                        } elseif ($sheet_name === 'TURMAS') {

                            $curso_nome = $r['curso'] ?? '';
                            $sigla = $r['siglaturma'] ?? $r['sigladaturma'] ?? $r['turma'] ?? $r['sigla'] ?? $r['nome'] ?? '';
                            $tipo_t = $r['tipo'] ?? $r['tipodaturma'] ?? $r['tipoturma'] ?? '';
                            $vagas = (int) ($r['vagas'] ?? 0);
                            $di = parseExcelDate($r['datainicio'] ?? $r['data_inicio'] ?? '');
                            $df = parseExcelDate($r['datafim'] ?? $r['data_fim'] ?? '');
                            $horario = $r['horario'] ?? $r['tempototal'] ?? $r['tempo_total'] ?? '';
                            $dias_semana = parseDiasSemanaNomes($r['diassemana'] ?? $r['dias_semana'] ?? $r['dias'] ?? '');
                            // Extrai docentes dinamicamente: busca TODAS as chaves que contenham
                            // "docente" ou "professor" nos dados normalizados da linha.
                            // Isso lida com variações como: "Docente 1"→docente1, "Docente"→docente,
                            // SheetJS sufixos "_1"→docente1, "Professor 1"→professor1, etc.
                            $docente_values = [];
                            foreach ($r as $rk => $rv) {
                                $rv_trimmed = trim($rv);
                                if (!$rv_trimmed)
                                    continue;
                                // Chaves que contenham "docente" ou "professor" (ex: docente, docente1, docente2, professor, professor1...)
                                if (preg_match('/^(docente|professor)\d*$/', $rk)) {
                                    // Extrai o número do sufixo para ordenação (sem número = 0)
                                    preg_match('/(\d+)$/', $rk, $num_match);
                                    $order = isset($num_match[1]) ? (int) $num_match[1] : 0;
                                    $docente_values[$order] = $rv_trimmed;
                                }
                            }
                            // Ordena por número do sufixo e preenche os 4 slots
                            ksort($docente_values);
                            $docente_list = array_values($docente_values);
                            $doc1 = $docente_list[0] ?? '';
                            $doc2 = $docente_list[1] ?? '';
                            $doc3 = $docente_list[2] ?? '';
                            $doc4 = $docente_list[3] ?? '';
                            $ambiente = $r['ambiente'] ?? $r['sala'] ?? '';
                            $local = $r['local'] ?? $r['localturma'] ?? '';
                            $n_proposta = $r['numeroproposta'] ?? $r['nproposta'] ?? $r['proposta'] ?? '';
                            $periodo = $r['periodo'] ?? $r['turno'] ?? '';
                            
                            $t_atendimento = trim($r['tipoatendimento'] ?? $r['atendimento'] ?? '');
                            if ($t_atendimento === '') $t_atendimento = 'Balcão';
                            
                            $parceiro = $r['parceiro'] ?? '';
                            $contato_parceiro = $r['contatoparceiro'] ?? $r['contato'] ?? '';
                            
                            $tipo_custeio = trim($r['tipocusteio'] ?? $r['custeio'] ?? '');
                            if ($tipo_custeio === '') $tipo_custeio = 'Gratuidade';
                            
                            $previsao_despesa = (float)($r['previsaodespesa'] ?? $r['despesa'] ?? 0);
                            $valor_turma = (float)($r['valorturma'] ?? $r['preco'] ?? $r['valor'] ?? 0);
                            $tipo_agenda = trim($r['tipoagenda'] ?? $r['tipo_agenda'] ?? 'recorrente');
                            $agenda_flexivel = trim($r['agendaflexivel'] ?? $r['agenda_flexivel'] ?? '');

                            if (!$periodo) {
                                $periodo = deriveTurno($horario);
                            }

                            $hi_raw = trim($r['horarioinicio'] ?? $r['horariode'] ?? $r['horainicio'] ?? $r['hora_inicio'] ?? '');
                            $hf_raw = trim($r['horariofim'] ?? $r['horarioate'] ?? $r['horafim'] ?? $r['hora_fim'] ?? $r['horafinal'] ?? '');
                            $horario_inicio_excel = parseTime($hi_raw);
                            $horario_fim_excel = parseTime($hf_raw);

                            // Derive defaults if not in Excel
                            if (!$horario_inicio_excel || !$horario_fim_excel) {
                                $def_times = ['Manhã' => ['07:30', '11:30'], 'Tarde' => ['13:30', '17:30'], 'Noite' => ['18:00', '23:00'], 'Integral' => ['07:30', '17:30']];
                                $horario_inicio_excel = $horario_inicio_excel ?: ($def_times[$periodo][0] ?? '07:30');
                                $horario_fim_excel = $horario_fim_excel ?: ($def_times[$periodo][1] ?? '11:30');
                            }


                            if (!$sigla)
                                throw new Exception("Sigla/nome vazio na linha " . ($i + 2));
                            if (!$curso_nome)
                                throw new Exception("Curso vazio na linha " . ($i + 2));

                            // Lookup curso — Miguel: tabela curso
                            $curso_nome_t = trim($curso_nome);
                            $sc = $mysqli->prepare("SELECT id FROM curso WHERE TRIM(nome) = ?");
                            $sc->bind_param('s', $curso_nome_t);
                            $sc->execute();
                            $curso_id = $sc->get_result()->fetch_row()[0] ?? null;

                            if (!$curso_id) {
                                throw new Exception("Curso \"$curso_nome\" não encontrado (Linha " . ($i + 2) . "). Certifique-se que o curso existe na aba CURSOS.");
                            }

                            // Resolve docentes to IDs — Miguel: tabela docente
                            $resolve_doc_id = function ($nome) use ($mysqli) {
                                if (!$nome || $nome === '?' || mb_strtolower($nome) === 'a contratar' || mb_strtolower($nome) === 'definir')
                                    return null;
                                $nome_t = trim($nome);
                                // 1. Busca exata
                                $s = $mysqli->prepare("SELECT id FROM docente WHERE TRIM(nome) = ?");
                                $s->bind_param('s', $nome_t);
                                $s->execute();
                                $row = $s->get_result()->fetch_row();
                                if ($row)
                                    return (int) $row[0];
                                // 2. Busca LIKE parcial (ignora acentos com COLLATE)
                                $like_val = '%' . $nome_t . '%';
                                $s2 = $mysqli->prepare("SELECT id FROM docente WHERE nome COLLATE utf8mb4_general_ci LIKE ? ORDER BY id ASC LIMIT 1");
                                $s2->bind_param('s', $like_val);
                                $s2->execute();
                                $row2 = $s2->get_result()->fetch_row();
                                if ($row2)
                                    return (int) $row2[0];
                                // 3. Busca multi-palavra: cada palavra do nome deve aparecer no nome do docente
                                $words = preg_split('/\s+/', $nome_t);
                                if (count($words) > 1) {
                                    $where_parts = [];
                                    $params = [];
                                    $types = '';
                                    foreach ($words as $w) {
                                        $where_parts[] = "nome COLLATE utf8mb4_general_ci LIKE ?";
                                        $params[] = '%' . $w . '%';
                                        $types .= 's';
                                    }
                                    $sql = "SELECT id FROM docente WHERE " . implode(' AND ', $where_parts) . " ORDER BY id ASC LIMIT 1";
                                    $s3 = $mysqli->prepare($sql);
                                    $s3->bind_param($types, ...$params);
                                    $s3->execute();
                                    $row3 = $s3->get_result()->fetch_row();
                                    if ($row3)
                                        return (int) $row3[0];
                                }
                                return null;
                            };
                            $did1 = $resolve_doc_id($doc1);
                            $did2 = $resolve_doc_id($doc2);
                            $did3 = $resolve_doc_id($doc3);
                            $did4 = $resolve_doc_id($doc4);


                            // Resolve ambiente to ID — Miguel: tabela ambiente
                            $amb_id = null;
                            if ($ambiente) {
                                $sa = $mysqli->prepare("SELECT id FROM ambiente WHERE nome = ?");
                                $amb_t = trim($ambiente);
                                $sa->bind_param('s', $amb_t);
                                $sa->execute();
                                $sa_row = $sa->get_result()->fetch_row();
                                $amb_id = $sa_row ? (int) $sa_row[0] : null;
                            }

                            // Miguel: tabela turma — INSERT ou UPDATE CONDICIONAL
                            $ck = $mysqli->prepare("SELECT id, docente_id1, docente_id2, docente_id3, docente_id4 FROM turma WHERE sigla = ?");
                            $ck->bind_param('s', $sigla);
                            $ck->execute();
                            $existing_turma = $ck->get_result()->fetch_assoc();

                            // Inicializa current_docs com os valores importados (será sobrescrito no UPDATE)
                            $current_docs = [1 => $did1, 2 => $did2, 3 => $did3, 4 => $did4];

                            if ($existing_turma) {
                                $tid_for_agenda = $existing_turma['id'];
                                // UPDATE CONDICIONAL: NÃO sobrescreve campos com valores vazios, e mescla docentes
                                $update_parts = [];
                                $update_params = [];
                                $update_types = '';

                                // Sempre atualizar curso_id e vagas
                                $update_parts[] = "curso_id = ?";
                                $update_params[] = $curso_id;
                                $update_types .= 'i';

                                $update_parts[] = "vagas = ?";
                                $update_params[] = $vagas;
                                $update_types .= 'i';

                                if ($di) {
                                    $update_parts[] = "data_inicio = ?";
                                    $update_params[] = $di;
                                    $update_types .= 's';
                                }
                                if ($df) {
                                    $update_parts[] = "data_fim = ?";
                                    $update_params[] = $df;
                                    $update_types .= 's';
                                }
                                if ($periodo) {
                                    $update_parts[] = "periodo = ?";
                                    $update_params[] = $periodo;
                                    $update_types .= 's';
                                }
                                if ($dias_semana) {
                                    $update_parts[] = "dias_semana = ?";
                                    $update_params[] = $dias_semana;
                                    $update_types .= 's';
                                }

                                // Merge Docentes: atualiza slots diretamente se informados no CSV
                                $current_docs = [
                                    1 => $existing_turma['docente_id1'],
                                    2 => $existing_turma['docente_id2'],
                                    3 => $existing_turma['docente_id3'],
                                    4 => $existing_turma['docente_id4']
                                ];

                                // Atribui diretamente nos slots correspondentes
                                if ($did1)
                                    $current_docs[1] = $did1;
                                if ($did2)
                                    $current_docs[2] = $did2;
                                if ($did3)
                                    $current_docs[3] = $did3;
                                if ($did4)
                                    $current_docs[4] = $did4;

                                // Atualiza no banco se mudou
                                for ($slot = 1; $slot <= 4; $slot++) {
                                    if ($current_docs[$slot] != $existing_turma["docente_id$slot"]) {
                                        $update_parts[] = "docente_id$slot = ?";
                                        $update_params[] = $current_docs[$slot];
                                        $update_types .= 'i';
                                    }
                                }

                                if ($amb_id) {
                                    $update_parts[] = "ambiente_id = ?";
                                    $update_params[] = $amb_id;
                                    $update_types .= 'i';
                                }
                                if ($local) {
                                    $update_parts[] = "local = ?";
                                    $update_params[] = $local;
                                    $update_types .= 's';
                                }
                                if ($tipo_t) {
                                    $update_parts[] = "tipo = ?";
                                    $update_params[] = $tipo_t;
                                    $update_types .= 's';
                                }
                                if ($horario_inicio_excel) {
                                    $update_parts[] = "horario_inicio = ?";
                                    $update_params[] = $horario_inicio_excel;
                                    $update_types .= 's';
                                }
                                if ($horario_fim_excel) {
                                    $update_parts[] = "horario_fim = ?";
                                    $update_params[] = $horario_fim_excel;
                                    $update_types .= 's';
                                }
                                if ($n_proposta) {
                                    $update_parts[] = "numero_proposta = ?";
                                    $update_params[] = $n_proposta;
                                    $update_types .= 's';
                                }
                                if ($t_atendimento) {
                                    $update_parts[] = "tipo_atendimento = ?";
                                    $update_params[] = $t_atendimento;
                                    $update_types .= 's';
                                }
                                if ($parceiro) {
                                    $update_parts[] = "parceiro = ?";
                                    $update_params[] = $parceiro;
                                    $update_types .= 's';
                                }
                                if ($contato_parceiro) {
                                    $update_parts[] = "contato_parceiro = ?";
                                    $update_params[] = $contato_parceiro;
                                    $update_types .= 's';
                                }
                                if ($tipo_custeio) {
                                    $update_parts[] = "tipo_custeio = ?";
                                    $update_params[] = $tipo_custeio;
                                    $update_types .= 's';
                                }
                                if ($previsao_despesa) {
                                    $update_parts[] = "previsao_despesa = ?";
                                    $update_params[] = $previsao_despesa;
                                    $update_types .= 'd';
                                }
                                if ($valor_turma) {
                                    $update_parts[] = "valor_turma = ?";
                                    $update_params[] = $valor_turma;
                                    $update_types .= 'd';
                                }
                                if ($tipo_agenda) {
                                    $update_parts[] = "tipo_agenda = ?";
                                    $update_params[] = $tipo_agenda;
                                    $update_types .= 's';
                                }
                                if ($agenda_flexivel) {
                                    $update_parts[] = "agenda_flexivel = ?";
                                    $update_params[] = $agenda_flexivel;
                                    $update_types .= 's';
                                }

                                $update_params[] = $sigla;
                                $update_types .= 's';

                                $sql_upd = "UPDATE turma SET " . implode(', ', $update_parts) . " WHERE sigla = ?";
                                $stmt_upd = $mysqli->prepare($sql_upd);
                                $stmt_upd->bind_param($update_types, ...$update_params);
                                $stmt_upd->execute();
                            } else {
                                // SQL com exatamente 25 interrogações
                                $sql_turma = "INSERT INTO turma (sigla, curso_id, vagas, data_inicio, data_fim, periodo, dias_semana, docente_id1, docente_id2, docente_id3, docente_id4, ambiente_id, local, tipo, horario_inicio, horario_fim, numero_proposta, tipo_atendimento, parceiro, contato_parceiro, tipo_custeio, previsao_despesa, valor_turma, tipo_agenda, agenda_flexivel) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                                $stmt_ins = $mysqli->prepare($sql_turma);
                                // Tipos (25): s, i, i, s, s, s, s, i, i, i, i, i, s, s, s, s, s, s, s, s, s, d, d, s, s
                                $stmt_ins->bind_param('siissssiiiiisssssssisddss', $sigla, $curso_id, $vagas, $di, $df, $periodo, $dias_semana, $did1, $did2, $did3, $did4, $amb_id, $local, $tipo_t, $horario_inicio_excel, $horario_fim_excel, $n_proposta, $t_atendimento, $parceiro, $contato_parceiro, $tipo_custeio, $previsao_despesa, $valor_turma, $tipo_agenda, $agenda_flexivel);
                                $stmt_ins->execute();
                                $tid_for_agenda = $mysqli->insert_id;
                            }

                            // ── AUTO-GENERATE AGENDA ENTRIES ──
                            $has_agenda_sheet = isset($sheets_json['AGENDA']) && !empty($sheets_json['AGENDA']);
                            // Alteração: verificar se há qualquer docente (mesclado) para gerar agenda, não apenas se novos foram importados
                            if (!$has_agenda_sheet && $di && $df && ($horario || ($horario_inicio_excel && $horario_fim_excel)) && $dias_semana && ($current_docs[1] || $current_docs[2] || $current_docs[3] || $current_docs[4])) {
                                if (!empty($horario)) {
                                    list($hi_auto, $hf_auto) = parseHorarioRange($horario);
                                } else {
                                    $hi_auto = $horario_inicio_excel;
                                    $hf_auto = $horario_fim_excel;
                                }
                                $weekdays_auto = parseDiasSemana($dias_semana);

                                if ($tid_for_agenda && ($current_docs[1] || $current_docs[2] || $current_docs[3] || $current_docs[4])) {

                                    // SÓ deleta se for a primeira vez processando esta sigla NO ARQUIVO
                                    if (!isset($turmas_processed_in_session[$sigla])) {
                                        $stmt_del = $mysqli->prepare("DELETE FROM agenda WHERE turma_id = ?");
                                        $stmt_del->bind_param('i', $tid_for_agenda);
                                        $stmt_del->execute();
                                        $turmas_processed_in_session[$sigla] = true;
                                    }

                                    // Miguel agenda: docente_id, ambiente_id, horario_inicio, horario_fim, dia_semana, periodo, data
                                    $cur_d = new DateTime($di);
                                    $end_d = new DateTime($df);
                                    $end_d->modify('+1 day');
                                    $agenda_gen_count = 0;
                                    $daysMap = [
                                        0 => 'Domingo',
                                        1 => 'Segunda-feira',
                                        2 => 'Terça-feira',
                                        3 => 'Quarta-feira',
                                        4 => 'Quinta-feira',
                                        5 => 'Sexta-feira',
                                        6 => 'Sábado'
                                    ];

                                    // ── FLEXIBLE AGENDA HANDLING ──
                                    if ($tipo_agenda === 'flexivel' && !empty($agenda_flexivel)) {
                                        $flex_dates = array_filter(array_map('trim', explode(',', $agenda_flexivel)));
                                        foreach ($flex_dates as $dateStr) {
                                            if (isHoliday($conn, $dateStr)) continue;
                                            $w = (int) date('w', strtotime($dateStr));
                                            $ag_dia_semana = $daysMap[$w] ?? '';
                                            $ag_periodo = $periodo ?: 'Manhã';
                                            $ids_for_agenda = array_filter($current_docs);
                                            foreach ($ids_for_agenda as $doc_id) {
                                                $stmt_ag = $mysqli->prepare("INSERT IGNORE INTO agenda (turma_id, docente_id, ambiente_id, dia_semana, periodo, horario_inicio, horario_fim, data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                                $stmt_ag->bind_param('iiisssss', $tid_for_agenda, $doc_id, $amb_id, $ag_dia_semana, $ag_periodo, $hi_auto, $hf_auto, $dateStr);
                                                $stmt_ag->execute();
                                                $agenda_gen_count++;
                                            }
                                        }
                                    } else {
                                        // ── RECURRING AGENDA HANDLING ──
                                        while ($cur_d < $end_d) {
                                            $dow_n = (int) $cur_d->format('w');
                                            if (in_array($dow_n, $weekdays_auto)) {
                                                $ag_data = $cur_d->format('Y-m-d');
                                                if (isHoliday($conn, $ag_data)) {
                                                    $cur_d->modify('+1 day');
                                                    continue;
                                                }
                                                $ag_dia_semana = $daysMap[$dow_n] ?? '';
                                                $ag_periodo = $periodo ?: 'Manhã';
                                                $ids_for_agenda = array_filter($current_docs);
                                                foreach ($ids_for_agenda as $doc_id) {
                                                    $stmt_ag = $mysqli->prepare("INSERT IGNORE INTO agenda (turma_id, docente_id, ambiente_id, dia_semana, periodo, horario_inicio, horario_fim, data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                                    $stmt_ag->bind_param('iiisssss', $tid_for_agenda, $doc_id, $amb_id, $ag_dia_semana, $ag_periodo, $hi_auto, $hf_auto, $ag_data);
                                                    $stmt_ag->execute();
                                                    $agenda_gen_count++;
                                                }
                                            }
                                            $cur_d->modify('+1 day');
                                        }
                                    }
                                    if ($agenda_gen_count > 0) {
                                        if (!isset($results['AGENDA_AUTO'])) {
                                            $results['AGENDA_AUTO'] = ['success' => 0, 'unique' => 0, 'errors' => [], 'total' => 0];
                                        }
                                        $results['AGENDA_AUTO']['success'] += $agenda_gen_count;
                                        $results['AGENDA_AUTO']['total'] += $agenda_gen_count;
                                    }
                                }
                            }
                        } elseif ($sheet_key === 'AGENDA' || $sheet_key === 'AGENDA_CALENDARIO') {
                            $tname = $r['turma'] ?? '';
                            $pname1 = $r['docente1'] ?? $r['docente'] ?? $r['professor'] ?? '';
                            $sname = $r['ambiente'] ?? $r['sala'] ?? '';
                            $data_val = parseExcelDate($r['data'] ?? '');
                            $hi = parseTime($r['horainicio'] ?? $r['hora inicio'] ?? $r['hora_inicio'] ?? $r['horarioinicio'] ?? '');
                            $hf = parseTime($r['horafim'] ?? $r['hora fim'] ?? $r['hora_fim'] ?? $r['horariofim'] ?? '');

                            if (!$tname || !$data_val || !$hi || !$hf) {
                                throw new Exception("Dados incompletos na linha " . ($i + 2) . " (turma='$tname', doc='$pname1', amb='$sname', data='$data_val', hi='$hi', hf='$hf')");
                            }

                            $st = $mysqli->prepare("SELECT id FROM turma WHERE sigla = ?");
                            $st->bind_param('s', $tname);
                            $st->execute();
                            $turma_id = $st->get_result()->fetch_row()[0] ?? null;


                            $pnames = [
                                $r['docente1'] ?? $r['docente'] ?? $r['professor'] ?? '',
                                $r['docente2'] ?? '',
                                $r['docente3'] ?? '',
                                $r['docente4'] ?? ''
                            ];

                            $dids = [];
                            foreach ($pnames as $pn) {
                                $pn_t = trim($pn);
                                if (!$pn_t || $pn_t === '?' || mb_strtolower($pn_t) === 'a contratar')
                                    continue;

                                if (!isset($id_cache['docente'][$pn_t])) {
                                    $sp = $mysqli->prepare("SELECT id FROM docente WHERE nome = ?");
                                    $sp->bind_param('s', $pn_t);
                                    $sp->execute();
                                    $id_cache['docente'][$pn_t] = $sp->get_result()->fetch_row()[0] ?? null;
                                }
                                if ($id_cache['docente'][$pn_t]) {
                                    $dids[] = $id_cache['docente'][$pn_t];
                                }
                            }

                            if (empty($dids))
                                $dids = [null];

                            // Cache Ambiente
                            $sname_t = trim($sname);
                            if (!isset($id_cache['ambiente'][$sname_t])) {
                                $ss = $mysqli->prepare("SELECT id FROM ambiente WHERE nome = ?");
                                $ss->bind_param('s', $sname_t);
                                $ss->execute();
                                $id_cache['ambiente'][$sname_t] = $ss->get_result()->fetch_row()[0] ?? null;
                            }
                            $amb_id_ag = $id_cache['ambiente'][$sname_t];

                            if (!$turma_id)
                                throw new Exception("Turma \"$tname\" não existe (Linha " . ($i + 2) . ")");

                            if (!isset($agenda_cleared_turmas[$turma_id])) {
                                $stmt_del_ag = $mysqli->prepare("DELETE FROM agenda WHERE turma_id = ?");
                                $stmt_del_ag->bind_param('i', $turma_id);
                                $stmt_del_ag->execute();
                                $agenda_cleared_turmas[$turma_id] = true;
                            }

                            // Derive dia_semana and periodo from data and time
                            $daysMapAg = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];
                            $dia_semana_val = $daysMapAg[(int) date('w', strtotime($data_val))] ?? '';
                            $periodo_val = 'Manhã';
                            if ($hi >= '12:00')
                                $periodo_val = 'Tarde';
                            if ($hi >= '18:00')
                                $periodo_val = 'Noite';

                            foreach ($dids as $doc_id_val) {
                                if ($doc_id_val === null) {
                                    $stmt_ag_ins = $mysqli->prepare("INSERT IGNORE INTO agenda (turma_id, docente_id, ambiente_id, dia_semana, periodo, horario_inicio, horario_fim, data) VALUES (?, NULL, ?, ?, ?, ?, ?, ?)");
                                    $stmt_ag_ins->bind_param('iisssss', $turma_id, $amb_id_ag, $dia_semana_val, $periodo_val, $hi, $hf, $data_val);
                                } else {
                                    $stmt_ag_ins = $mysqli->prepare("INSERT IGNORE INTO agenda (turma_id, docente_id, ambiente_id, dia_semana, periodo, horario_inicio, horario_fim, data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                    $stmt_ag_ins->bind_param('iiisssss', $turma_id, $doc_id_val, $amb_id_ag, $dia_semana_val, $periodo_val, $hi, $hf, $data_val);
                                }
                                $stmt_ag_ins->execute();
                            }
                        } elseif ($sheet_key === 'FÉRIAS') {
                            $pname = $r['docente'] ?? $r['professor'] ?? '';
                            $di = parseExcelDate($r['datainicio'] ?? $r['data_inicio'] ?? '');
                            $df = parseExcelDate($r['datafim'] ?? $r['data_fim'] ?? '');
                            $tipo = (mb_strtolower($r['tipo'] ?? '') === 'coletiva') ? 'collective' : 'individual';

                            if ((!$pname && $tipo === 'individual') || !$di || !$df) {
                                throw new Exception("Dados insuficientes para FÉRIAS (Docente: $pname, Início: $di, Fim: $df)");
                            }

                            $did_vac = null;
                            if (!empty($pname)) {
                                $sp = $mysqli->prepare("SELECT id FROM docente WHERE nome = ?");
                                $sp->bind_param('s', $pname);
                                $sp->execute();
                                $did_vac = $sp->get_result()->fetch_row()[0] ?? null;

                                if (!$did_vac && $tipo === 'individual') {
                                    throw new Exception("Docente \"$pname\" não encontrado para férias.");
                                }
                            }

                            // Evitar duplicidade
                            $ck_sql = "SELECT id FROM vacations WHERE type = ? AND start_date = ? AND end_date = ?";
                            $params = [$tipo, $di, $df];
                            $types = "sss";
                            if ($did_vac !== null) {
                                $ck_sql .= " AND teacher_id = ?";
                                $params[] = (int) $did_vac;
                                $types .= "i";
                            } else {
                                $ck_sql .= " AND teacher_id IS NULL";
                            }
                            $stmt_ck = $mysqli->prepare($ck_sql);
                            $stmt_ck->bind_param($types, ...$params);
                            $stmt_ck->execute();

                            if (!$stmt_ck->get_result()->fetch_row()) {
                                $stmt_vac = $mysqli->prepare("INSERT INTO vacations (type, teacher_id, start_date, end_date) VALUES (?, ?, ?, ?)");
                                $stmt_vac->bind_param('siss', $tipo, $did_vac, $di, $df);
                                $stmt_vac->execute();
                            }

                        } elseif ($sheet_key === 'FERIADOS') {
                            $nome_fer = $r['nome'] ?? $r['feriado'] ?? '';
                            $di = parseExcelDate($r['datainicio'] ?? $r['data_inicio'] ?? $r['data'] ?? '');
                            $df = parseExcelDate($r['datafim'] ?? $r['data_fim'] ?? $di);

                            if (!$nome_fer || !$di)
                                throw new Exception("Dados insuficientes para FERIADOS.");

                            // Evitar duplicidade
                            $ck_fer = $mysqli->prepare("SELECT id FROM holidays WHERE name = ? AND date = ? AND (end_date = ? OR (end_date IS NULL AND ? IS NULL))");
                            $ck_fer->bind_param('ssss', $nome_fer, $di, $df, $df);
                            $ck_fer->execute();
                            if (!$ck_fer->get_result()->fetch_row()) {
                                $stmt_fer = $mysqli->prepare("INSERT INTO holidays (name, date, end_date) VALUES (?, ?, ?)");
                                $stmt_fer->bind_param('sss', $nome_fer, $di, $df);
                                $stmt_fer->execute();
                            }
                        } elseif ($sheet_key === 'BLOQUEIOS' || $sheet_key === 'PREPARACAO_ATESTADOS' || $sheet_key === 'BLOQUEIO') {
                            $pname = $r['docente'] ?? $r['professor'] ?? '';
                            $tipo = mb_strtolower($r['tipo'] ?? $r['motivo'] ?? 'preparação');
                            if (strpos($tipo, 'atestado') !== false)
                                $tipo = 'atestado';
                            else
                                $tipo = 'preparação';

                            $di = parseExcelDate($r['datainicio'] ?? $r['data_inicio'] ?? $r['data'] ?? '');
                            $df = parseExcelDate($r['datafim'] ?? $r['data_fim'] ?? $di);
                            $hi = parseTime($r['horariode'] ?? $r['horarioinicio'] ?? $r['horainicio'] ?? '00:00');
                            $hf = parseTime($r['horarioate'] ?? $r['horariofim'] ?? $r['horafim'] ?? '23:59');

                            if (!$pname || !$di)
                                throw new Exception("Dados insuficientes para BLOQUEIOS.");

                            $sp = $mysqli->prepare("SELECT id FROM docente WHERE TRIM(nome) = ?");
                            $p_t = trim($pname);
                            $sp->bind_param('s', $p_t);
                            $sp->execute();
                            $did_prep = $sp->get_result()->fetch_row()[0] ?? null;

                            if ($did_prep) {
                                $stmt_prep = $mysqli->prepare("INSERT INTO preparacao_atestados (docente_id, tipo, data_inicio, data_fim, horario_inicio, horario_fim, status) VALUES (?, ?, ?, ?, ?, ?, 'ativo')");
                                $stmt_prep->bind_param('isssss', $did_prep, $tipo, $di, $df, $hi, $hf);
                                $stmt_prep->execute();
                            } else {
                                throw new Exception("Docente \"$pname\" não encontrado para bloqueio.");
                            }
                        } elseif ($sheet_key === 'RESERVAS') {
                            $pname = $r['docente'] ?? $r['professor'] ?? '';
                            $di = parseExcelDate($r['datainicio'] ?? $r['data_inicio'] ?? '');
                            $df = parseExcelDate($r['datafim'] ?? $r['data_fim'] ?? $di);
                            $dias = parseDiasSemanaNomes($r['dias'] ?? $r['diassemana'] ?? $r['dias_semana'] ?? 'Segunda-feira,Terça-feira,Quarta-feira,Quinta-feira,Sexta-feira');
                            $hi = parseTime($r['horarioinicio'] ?? $r['horariode'] ?? $r['horainicio'] ?? '07:30');
                            $hf = parseTime($r['horariofim'] ?? $r['horarioate'] ?? $r['horafim'] ?? '11:30');
                            $notas = $r['notas'] ?? $r['observacao'] ?? '';
                            
                            $tipo_custeio = trim($r['tipocusteio'] ?? $r['custeio'] ?? '');
                            if ($tipo_custeio === '') $tipo_custeio = 'Gratuidade';
                            
                            $previsao_despesa = (float)($r['previsaodespesa'] ?? $r['despesa'] ?? 0);
                            $valor_turma = (float)($r['valorturma'] ?? $r['preco'] ?? $r['valor'] ?? 0);
                            $n_proposta = $r['numeroproposta'] ?? $r['nproposta'] ?? '';
                            
                            $t_atendimento = trim($r['tipoatendimento'] ?? $r['atendimento'] ?? '');
                            if ($t_atendimento === '') $t_atendimento = 'Balcão';
                            $parceiro = $r['parceiro'] ?? '';
                            $contato_parceiro = $r['contatoparceiro'] ?? '';

                            $sp = $mysqli->prepare("SELECT id FROM docente WHERE TRIM(nome) = ?");
                            $p_t = trim($pname);
                            $sp->bind_param('s', $p_t);
                            $sp->execute();
                            $did_res = $sp->get_result()->fetch_row()[0] ?? null;

                            if ($did_res) {
                                $uid = (isset($_SESSION['user_id'])) ? $_SESSION['user_id'] : 1;
                                $status_val = $r['status'] ?? $r['situação'] ?? 'PENDENTE';
                                
                                // Garantir que o valor de status importado seja válido no sistema
                                $status_val = mb_strtoupper(trim($status_val), 'UTF-8');
                                if (!in_array($status_val, ['PENDENTE', 'APROVADA', 'CONCLUIDA', 'RECUSADA'])) {
                                    $status_val = 'PENDENTE';
                                }
                                
                                // Verificação de duplicata: mesma combinação de docente + datas + horários
                                $sp_dup = $mysqli->prepare("SELECT id FROM reservas WHERE docente_id = ? AND data_inicio = ? AND data_fim = ? AND hora_inicio = ? AND hora_fim = ?");
                                $sp_dup->bind_param('issss', $did_res, $di, $df, $hi, $hf);
                                $sp_dup->execute();
                                $dup_result = $sp_dup->get_result();
                                
                                if ($dup_result->num_rows > 0) {
                                    // Já existe — atualiza os campos financeiros e status em vez de duplicar
                                    $existing_id = $dup_result->fetch_row()[0];
                                    $sql_upd = "UPDATE reservas SET status = ?, tipo_custeio = ?, previsao_despesa = ?, valor_turma = ?, numero_proposta = ?, tipo_atendimento = ?, parceiro = ?, contato_parceiro = ?, notas = ? WHERE id = ?";
                                    $st_upd = $mysqli->prepare($sql_upd);
                                    $st_upd->bind_param('ssddsssssi',
                                        $status_val, $tipo_custeio, $previsao_despesa, $valor_turma,
                                        $n_proposta, $t_atendimento, $parceiro, $contato_parceiro, $notas, $existing_id
                                    );
                                    $st_upd->execute();
                                } else {
                                    // SQL com exatamente 16 placeholders
                                    $sql_res = "INSERT INTO reservas (docente_id, usuario_id, data_inicio, data_fim, dias_semana, hora_inicio, hora_fim, notas, status, tipo_custeio, previsao_despesa, valor_turma, numero_proposta, tipo_atendimento, parceiro, contato_parceiro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                                    
                                    $st_res = $mysqli->prepare($sql_res);
                                    
                                    // Tipos (16): iisssssssddsssss
                                    $st_res->bind_param('iisssssssddsssss', 
                                        $did_res,          // 1
                                        $uid,              // 2
                                        $di,               // 3
                                        $df,               // 4
                                        $dias,             // 5
                                        $hi,               // 6
                                        $hf,               // 7
                                        $notas,            // 8
                                        $status_val,       // 9 
                                        $tipo_custeio,     // 10
                                        $previsao_despesa, // 11
                                        $valor_turma,      // 12
                                        $n_proposta,       // 13
                                        $t_atendimento,    // 14
                                        $parceiro,         // 15
                                        $contato_parceiro  // 16
                                    );
                                    $st_res->execute();
                                }
                            }
                        } elseif ($sheet_key === 'HORARIO_TRABALHO') {
                            // Mapeamento das colunas super tolerante a variações
                            $pname = $r['docente'] ?? $r['professor'] ?? $r['nome'] ?? $r['docentes'] ?? $r['professores'] ?? '';

                            $dias_bruto = $r['dias'] ?? $r['dia'] ?? $r['diadasemana'] ?? $r['diassemana'] ?? $r['diasdasemana'] ?? '';
                            $dias_str = parseDiasSemanaNomes($dias_bruto);

                            $periodo = $r['periodo'] ?? $r['turno'] ?? '';
                            $horario = $r['horario'] ?? $r['horarios'] ?? $r['horariodetrabalho'] ?? '';
                            
                            // NOVOS CAMPOS: Vigência e Ano
                            $dt_ini = parseExcelDate($r['datainicio'] ?? $r['data_inicio'] ?? $r['inicio'] ?? '');
                            $dt_fim = parseExcelDate($r['datafim'] ?? $r['data_fim'] ?? $r['fim'] ?? '');
                            $ano = (int) ($r['ano'] ?? 0);
                            
                            // Fallbacks
                            if (!$ano && $dt_ini) $ano = (int) date('Y', strtotime($dt_ini));
                            if (!$ano) $ano = (int) date('Y');
                            if (!$dt_ini) $dt_ini = $ano . '-01-01';
                            if (!$dt_fim) $dt_fim = $ano . '-12-31';

                            if (empty($pname) || empty($dias_str)) {
                                continue;
                            }

                            $sp = $mysqli->prepare("SELECT id FROM docente WHERE TRIM(nome) = ?");
                            $p_t = trim($pname);
                            $sp->bind_param('s', $p_t);
                            $sp->execute();
                            $did_ht = $sp->get_result()->fetch_row()[0] ?? null;

                            if ($did_ht) {
                                // Limpa apenas na primeira vez que o professor aparece na planilha
                                if (!isset($ht_cleared_profs[$did_ht])) {
                                    $mysqli->query("DELETE FROM horario_trabalho WHERE docente_id = $did_ht");
                                    $ht_cleared_profs[$did_ht] = true;
                                }

                                if ($periodo === 'Noite') {
                                    $dias_list = explode(',', $dias_str);
                                    $dias_list = array_filter($dias_list, function ($d) {
                                        return trim($d) !== 'Sábado';
                                    });
                                    $dias_str = implode(',', $dias_list);
                                }

                                if (!empty($dias_str)) {
                                    $stmt_ht = $mysqli->prepare("INSERT INTO horario_trabalho (docente_id, dias, periodo, horario, data_inicio, data_fim, ano) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                    $stmt_ht->bind_param('isssssi', $did_ht, $dias_str, $periodo, $horario, $dt_ini, $dt_fim, $ano);
                                    $stmt_ht->execute();
                                }
                            } else {
                                throw new Exception("Docente \"$pname\" não encontrado no banco de dados. Cadastre-o primeiro.");
                            }
                        }

                        $results[$sheet_name]['success']++;

                        // Unique tracking based on key fields
                        $ukey = $nome ?: $sigla ?: $pname ?: $tname ?: null;
                        if ($ukey && !isset($unique_tracker[$ukey])) {
                            $unique_tracker[$ukey] = true;
                            $results[$sheet_name]['unique']++;
                        }
                    } catch (Exception $e) {
                        $results[$sheet_name]['errors'][] = $e->getMessage();
                    }
                }
            }

            $import_result = ['mode' => 'multi', 'results' => $results];
        }

        // ── SINGLE-SHEET IMPORT ──
        elseif ($import_mode === 'single') {
            $import_type = $_POST['import_type'];
            $json_data = json_decode($_POST['import_data'], true);
            $success = 0;
            $errors = [];
            $agenda_cleared_turmas_single = [];

            foreach ($json_data as $i => $row) {
                $r = [];
                foreach ($row as $k => $v) {
                    $r[normalizeKey($k)] = trim((string) $v);
                }

                try {
                    if ($import_type === 'professores') {
                        $nome = $r['nome'] ?? $r['professor'] ?? '';
                        $esp = $r['especialidade'] ?? $r['área'] ?? $r['area'] ?? '';
                        $chc = (int) ($r['cargahoraria'] ?? $r['cargahorariamax'] ?? $r['ch'] ?? $r['carga_horaria_contratual'] ?? 0);
                        $cidade = $r['cidade'] ?? '';
                        $turno = $r['turno'] ?? $r['periodo'] ?? '';
                        $tc = $r['tipocontrato'] ?? $r['tipo_contrato'] ?? $r['contrato'] ?? '';
                        if (!$nome)
                            throw new Exception("Nome vazio na linha " . ($i + 2));
                        $stmt_s = $mysqli->prepare("INSERT INTO docente (nome, area_conhecimento, carga_horaria_contratual, cidade, turno, tipo_contrato) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE area_conhecimento = VALUES(area_conhecimento), carga_horaria_contratual = VALUES(carga_horaria_contratual), cidade = VALUES(cidade), turno = VALUES(turno), tipo_contrato = VALUES(tipo_contrato)");
                        $stmt_s->bind_param('ssisss', $nome, $esp, $chc, $cidade, $turno, $tc);
                        $stmt_s->execute();
                    } elseif ($import_type === 'salas') {
                        $nome = $r['nome'] ?? $r['ambiente'] ?? $r['sala'] ?? '';
                        $tipo = $r['tipo'] ?? 'Sala';
                        $area = $r['area_vinculada'] ?? $r['área'] ?? $r['area'] ?? '';
                        $cidade = $r['cidade'] ?? '';
                        $cap = (int) ($r['capacidade'] ?? 0);
                        if (!$nome)
                            throw new Exception("Nome vazio na linha " . ($i + 2));
                        $area_val = $area ?: null;
                        $cidade_val = $cidade ?: null;
                        $stmt_s = $mysqli->prepare("INSERT INTO ambiente (nome, tipo, area_vinculada, cidade, capacidade) VALUES (?, ?, ?, ?, ?)");
                        $stmt_s->bind_param('ssssi', $nome, $tipo, $area_val, $cidade_val, $cap);
                        $stmt_s->execute();
                    } elseif ($import_type === 'cursos') {
                        $nome = $r['nome'] ?? $r['curso'] ?? '';
                        $tipo = $r['tipo'] ?? '';
                        $area = $r['área'] ?? $r['area'] ?? '';
                        $ch = (int) ($r['cargahorariatotal'] ?? $r['cargahoraria'] ?? $r['carga_horaria'] ?? 0);
                        if (!$nome)
                            throw new Exception("Nome vazio na linha " . ($i + 2));
                        $tipo_val = $tipo ?: null;
                        $area_val = $area ?: null;
                        $stmt_s = $mysqli->prepare("INSERT INTO curso (nome, tipo, area, carga_horaria_total) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE tipo=VALUES(tipo), area=VALUES(area), carga_horaria_total=VALUES(carga_horaria_total)");
                        $stmt_s->bind_param('sssi', $nome, $tipo_val, $area_val, $ch);
                        $stmt_s->execute();
                    } elseif ($import_type === 'turmas') {
                        $sigla = $r['siglaturma'] ?? $r['sigladaturma'] ?? $r['turma'] ?? $r['sigla'] ?? $r['nome'] ?? '';
                        $cnome = $r['curso'] ?? '';
                        $periodo = $r['periodo'] ?? $r['turno'] ?? '';
                        $tipo_t = $r['tipo'] ?? $r['tipodaturma'] ?? $r['tipoturma'] ?? '';
                        $horario = $r['horario'] ?? '';
                        $di = parseExcelDate($r['datainicio'] ?? $r['data_inicio'] ?? '');
                        $df = parseExcelDate($r['datafim'] ?? $r['data_fim'] ?? '');

                        if (!$periodo && $horario)
                            $periodo = deriveTurno($horario);
                        if (!$periodo)
                            $periodo = 'Manhã';

                        if (!$sigla)
                            throw new Exception("Turma não identificada na linha " . ($i + 2));
                        if (!$cnome)
                            throw new Exception("Curso não identificado na linha " . ($i + 2));
                        if (!$di || !$df)
                            throw new Exception("Datas inválidas na linha " . ($i + 2));

                        $sc = $mysqli->prepare("SELECT id FROM curso WHERE nome = ?");
                        $sc->bind_param('s', $cnome);
                        $sc->execute();
                        $curso_id = $sc->get_result()->fetch_row()[0] ?? null;
                        if (!$curso_id)
                            throw new Exception("Curso \"$cnome\" não encontrado (Linha " . ($i + 2) . ")");

                        $vagas = (int) ($r['vagas'] ?? 0);
                        $dias_semana = $r['diassemana'] ?? $r['dias_semana'] ?? $r['dias'] ?? '';

                        $hi_excel = parseTime($r['horarioinicio'] ?? $r['horariode'] ?? $r['horainicio'] ?? $r['hora_inicio'] ?? '');
                        $hf_excel = parseTime($r['horariofim'] ?? $r['horarioate'] ?? $r['horafim'] ?? $r['hora_fim'] ?? '');

                        // Fallbacks if missing
                        if (!$hi_excel || !$hf_excel) {
                            $def_times = ['Manhã' => ['07:30', '11:30'], 'Tarde' => ['13:30', '17:30'], 'Noite' => ['18:00', '23:00'], 'Integral' => ['07:30', '17:30']];
                            $hi_excel = $hi_excel ?: ($def_times[$periodo][0] ?? '07:30');
                            $hf_excel = $hf_excel ?: ($def_times[$periodo][1] ?? '11:30');
                        }

                        $dias_val = $dias_semana ?: null;
                        $stmt_s = $mysqli->prepare("INSERT INTO turma (sigla, curso_id, periodo, data_inicio, data_fim, vagas, dias_semana, tipo, horario_inicio, horario_fim) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE periodo=VALUES(periodo), data_inicio=VALUES(data_inicio), data_fim=VALUES(data_fim), vagas=VALUES(vagas), dias_semana=VALUES(dias_semana), tipo=VALUES(tipo), horario_inicio=VALUES(horario_inicio), horario_fim=VALUES(horario_fim)");
                        $stmt_s->bind_param('sisssissss', $sigla, $curso_id, $periodo, $di, $df, $vagas, $dias_val, $tipo_t, $hi_excel, $hf_excel);
                        $stmt_s->execute();
                    } elseif ($import_type === 'agenda') {
                        $tname = $r['turma'] ?? '';
                        $pname = $r['docente'] ?? $r['professor'] ?? '';
                        $sname = $r['ambiente'] ?? $r['sala'] ?? '';
                        $data_val = parseExcelDate($r['data'] ?? '');
                        $hi = parseTime($r['horainicio'] ?? $r['horarioinicio'] ?? $r['hora_inicio'] ?? '');
                        $hf = parseTime($r['horafim'] ?? $r['horariofim'] ?? $r['hora_fim'] ?? '');

                        if ($hi && strlen($hi) > 5)
                            $hi = substr($hi, 0, 5);
                        if ($hf && strlen($hf) > 5)
                            $hf = substr($hf, 0, 5);

                        if (!$tname || !$pname || !$sname || !$data_val || !$hi || !$hf) {
                            throw new Exception("Dados incompletos na linha " . ($i + 2));
                        }

                        $st = $mysqli->prepare("SELECT id FROM turma WHERE sigla = ?");
                        $st->bind_param('s', $tname);
                        $st->execute();
                        $turma_id = $st->get_result()->fetch_row()[0] ?? null;

                        $sp = $mysqli->prepare("SELECT id FROM docente WHERE nome = ?");
                        $sp->bind_param('s', $pname);
                        $sp->execute();
                        $doc_id = $sp->get_result()->fetch_row()[0] ?? null;

                        $ss = $mysqli->prepare("SELECT id FROM ambiente WHERE nome = ?");
                        $ss->bind_param('s', $sname);
                        $ss->execute();
                        $amb_id = $ss->get_result()->fetch_row()[0] ?? null;

                        if (!$turma_id)
                            throw new Exception("Turma \"$tname\" não existe (Linha " . ($i + 2) . ")");
                        if (!$doc_id)
                            throw new Exception("Docente \"$pname\" não existe (Linha " . ($i + 2) . ")");
                        if (!$amb_id)
                            throw new Exception("Ambiente \"$sname\" não existe (Linha " . ($i + 2) . ")");

                        $daysMapSingle = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];
                        $dia_sem = $daysMapSingle[(int) date('w', strtotime($data_val))] ?? '';
                        $per = 'Manhã';
                        if ($hi >= '12:00')
                            $per = 'Tarde';
                        if ($hi >= '18:00')
                            $per = 'Noite';

                        if (!isset($agenda_cleared_turmas_single[$turma_id])) {
                            $stmt_del_ag = $mysqli->prepare("DELETE FROM agenda WHERE turma_id = ?");
                            $stmt_del_ag->bind_param('i', $turma_id);
                            $stmt_del_ag->execute();
                            $agenda_cleared_turmas_single[$turma_id] = true;
                        }

                        $stmt_s = $mysqli->prepare("INSERT IGNORE INTO agenda (turma_id, docente_id, ambiente_id, dia_semana, periodo, horario_inicio, horario_fim, data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt_s->bind_param('iiisssss', $turma_id, $doc_id, $amb_id, $dia_sem, $per, $hi, $hf, $data_val);
                        $stmt_s->execute();
                    }
                    $success++;
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
        $mysqli->commit();
    } catch (Exception $e) {
        $mysqli->rollback();
        $import_result = ['mode' => 'error', 'message' => $e->getMessage()];
    }
}
?>
<div id="loadingOverlay" class="import-loading-overlay">
    <div class="import-spinner"></div>
    <div id="loadingText">Processando dados...</div>
</div>

<div class="page-header import-header-flex">
    <div>
        <h2><i class="fas fa-file-import"></i> Sistema de Importação</h2>
        <p>Arraste o arquivo <strong>Controle de Ocupação.xlsx</strong> ou qualquer planilha compatível.</p>
    </div>
    <div>
        <a href="../../prototipoSenai.csv" download="prototipoSenai.csv" class="btn btn-primary import-download-btn">
            <i class="fas fa-download"></i> Baixar modelo de planilha
        </a>
    </div>
</div>

<?php if ($import_result): ?>
    <div class="card import-result-card" style="border-left: 5px solid <?php
    $has_err = false;
    foreach ($import_result['results'] as $sr) {
        if (!empty($sr['errors']))
            $has_err = true;
    }
    echo $has_err ? '#ffc107' : '#28a745';
    ?>;">
        <h3><i class="fas fa-info-circle"></i> Resultado da Importação</h3>

        <div
            style="background: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2); padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 12px;">
            <i class="fas fa-lightbulb" style="color: var(--primary-color); font-size: 1.4em; margin-top: 2px;"></i>
            <div>
                <strong style="color: var(--primary-color); display: block; margin-bottom: 4px;">Nota sobre Mesclagem de
                    Dados</strong>
                <p style="margin: 0; font-size: 0.95em; line-height: 1.5; color: var(--text-muted);">
                    As linhas da planilha que possuem a mesma <strong>Sigla de Turma</strong> são mescladas automaticamente.
                    Isso consolida múltiplos docentes e horários em um único registro, garantindo que a sua lista de turmas
                    fique organizada e sem duplicatas.
                </p>
            </div>
        </div>
        <?php
        $sheet_display_names = [
            'DOCENTES' => 'Docentes',
            'AMBIENTES' => 'Ambientes',
            'CURSOS' => 'Cursos',
            'TURMAS' => 'Turmas',
            'AGENDA' => 'Agenda',
            'FÉRIAS' => 'Férias',
            'FERIADOS' => 'Feriados',
            'HORARIO_TRABALHO' => 'Horário de Trabalho',
            'AGENDA_AUTO' => 'Agenda (Auto-gerada das Turmas)'
        ];
        foreach ($import_result['results'] as $sheet_name => $res): ?>
            <div class="import-result-row"
                style="display: flex; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid rgba(0,0,0,0.05);">
                <div style="flex: 1; min-width: 150px; font-weight: 600;">
                    <i class="fas fa-caret-right" style="margin-right: 5px; opacity: 0.5;"></i>
                    <?php echo xe($sheet_display_names[$sheet_name] ?? ucfirst(strtolower($sheet_name))); ?>:
                </div>

                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <div class="result-count success"
                        style="background: rgba(40, 167, 69, 0.1); color: #28a745; padding: 4px 12px; border-radius: 20px; display: flex; align-items: center; gap: 6px; font-weight: 600;">
                        <span class="count-icon">✓</span>
                        <span class="count-value"><?= (int) $res['success'] ?></span>
                        <small
                            style="margin-left: 2px; opacity: 0.8; font-size: 0.8em;"><?php echo $sheet_name === 'AGENDA_AUTO' ? 'aulas geradas' : '(linhas)'; ?></small>
                    </div>

                    <?php if (($res['unique'] ?? 0) > 0 && ($res['unique'] ?? 0) < $res['success']): ?>
                        <div class="result-count unique"
                            style="background: rgba(0, 123, 255, 0.1); color: #007bff; padding: 4px 12px; border-radius: 20px; display: flex; align-items: center; gap: 6px; font-weight: 600;">
                            <span class="count-icon">★</span>
                            <span class="count-value"><?= (int) $res['unique'] ?></span>
                            <small style="margin-left: 2px; opacity: 0.8; font-size: 0.8em;">(únicos)</small>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($res['errors'])): ?>
                        <div class="result-count error"
                            style="background: rgba(220, 53, 69, 0.1); color: #dc3545; padding: 4px 12px; border-radius: 20px; display: flex; align-items: center; gap: 6px; font-weight: 600;">
                            <span class="count-icon">✗</span>
                            <span class="count-value"><?php echo count($res['errors']); ?></span>
                            <small style="margin-left: 2px; opacity: 0.8; font-size: 0.8em;">erros</small>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($res['errors'])): ?>
                    <div class="import-error-box"
                        style="margin-top: 5px; width: 100%; font-size: 0.9em; background: rgba(220, 53, 69, 0.05); padding: 10px; border-radius: 8px; border-left: 3px solid #dc3545;">
                        <?php foreach ($res['errors'] as $err)
                            echo "<div style='color: #842029; margin-bottom: 2px;'>• " . xe($err) . "</div>"; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card import-main-card">
    <div class="import-tabs">
        <button class="tab-btn active" onclick="switchTab('multi')" id="tab_multi">
            <i class="fas fa-layer-group"></i> Planilha Completa (Multi-aba)
        </button>
        <button class="tab-btn" onclick="switchTab('single')" id="tab_single">
            <i class="fas fa-file-alt"></i> Aba Única (Legado)
        </button>
    </div>

    <div id="single_opts" class="import-opts-group" style="display:none;">
        <label class="import-label">Tipo de Dado</label>
        <select id="import_type" class="form-input">
            <option value="agenda">Agenda (Datas, Horários, Docentes, Ambientes)</option>
            <option value="turmas">Turmas (Sigla, Curso, Turno, etc)</option>
            <option value="professores">Docentes (Nome, Área, CH)</option>
            <option value="salas">Ambientes (Nome, Tipo, Capacidade)</option>
            <option value="cursos">Cursos (Nome, Tipo, CH)</option>
        </select>
    </div>

    <div id="drop_zone" class="drop-zone">
        <i class="fas fa-cloud-upload-alt"></i>
        <h3>Arraste o Excel ou CSV aqui</h3>
        <p style="color:var(--text-muted);">Formatos aceitos: .xlsx, .xls, .csv</p>
        <input type="file" id="file_input" accept=".xlsx,.xls,.csv" style="display: none;">
    </div>

    <div id="preview_multi" class="preview-container" style="display: none;">
        <h3 class="preview-title"><i class="fas fa-layer-group"></i> Abas Detectadas</h3>
        <div id="sheets_summary" class="sheets-summary"></div>
        <div id="sheets_previews"></div>
        <form method="POST" id="confirm_form_multi" style="margin-top: 25px; text-align: right;"
            onsubmit="showLoading('Importando para o banco... Isso pode levar alguns segundos.')">
            <input type="hidden" name="import_mode" value="multi">
            <input type="hidden" name="sheets_json" id="form_sheets_json">
            <button type="button" class="btn" onclick="location.reload()"
                style="background:var(--bg-color); border: 1px solid var(--border-color); color: var(--corTxt3);">
                <i class="fas fa-redo"></i> Limpar
            </button>
            <button type="submit" class="btn btn-primary" style="padding: 12px 35px;">
                <i class="fas fa-database"></i> Confirmar Importação
            </button>
        </form>
    </div>

    <div id="preview_single" class="preview-container" style="display: none;">
        <h3 class="preview-title">Prévia dos Dados (<span id="count_label">0</span>)</h3>
        <div class="table-wrapper">
            <table id="preview_table" class="import-table">
                <thead>
                    <tr id="preview_header"></tr>
                </thead>
                <tbody id="preview_body"></tbody>
            </table>
        </div>
        <form method="POST" id="confirm_form_single" style="margin-top: 25px; text-align: right;">
            <input type="hidden" name="import_mode" value="single">
            <input type="hidden" name="import_type" id="form_import_type">
            <input type="hidden" name="import_data" id="form_import_data">
            <button type="button" class="btn" onclick="location.reload()"
                style="background:var(--bg-color); border: 1px solid var(--border-color); color: var(--corTxt3);">
                <i class="fas fa-redo"></i> Limpar
            </button>
            <button type="submit" class="btn btn-primary" style="padding: 12px 35px;">
                <i class="fas fa-database"></i> Confirmar Importação
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
<script>
    let currentMode = 'multi';
    const knownSheets = ['DOCENTES', 'AMBIENTES', 'CURSOS', 'TURMAS', 'AGENDA', 'BLOQUEIOS', 'RESERVAS', 'FERIADOS'];
    const sheetLabels = {
        DOCENTES: { icon: 'fa-chalkboard-teacher', color: '#0d6efd', label: 'Docentes' },
        AMBIENTES: { icon: 'fa-building', color: '#198754', label: 'Ambientes' },
        CURSOS: { icon: 'fa-graduation-cap', color: '#6f42c1', label: 'Cursos' },
        TURMAS: { icon: 'fa-users', color: '#fd7e14', label: 'Turmas' },
        AGENDA: { icon: 'fa-calendar-alt', color: '#dc3545', label: 'Agenda' },
        'FÉRIAS': { icon: 'fa-plane', color: '#8c52ff', label: 'Férias' },
        'FERIADOS': { icon: 'fa-umbrella-beach', color: '#ff5252', label: 'Feriados' },
        HORARIO_TRABALHO: { icon: 'fa-briefcase', color: '#007bff', label: 'Horário de Trabalho' },
        BLOQUEIOS: { icon: 'fa-ban', color: '#ff6b35', label: 'Bloqueios (Preparação / Ausências)' },
        PREPARACAO_ATESTADOS: { icon: 'fa-ban', color: '#ff6b35', label: 'Preparação / Ausências' },
        RESERVAS: { icon: 'fa-bookmark', color: '#17a2b8', label: 'Reservas' }
    };

    function switchTab(mode) {
        currentMode = mode;
        document.getElementById('tab_multi').classList.toggle('active', mode === 'multi');
        document.getElementById('tab_single').classList.toggle('active', mode === 'single');
        document.getElementById('tab_multi').style.color = mode === 'multi' ? 'var(--primary-red, #e63946)' : 'var(--text-muted)';
        document.getElementById('tab_single').style.color = mode === 'single' ? 'var(--primary-red, #e63946)' : 'var(--text-muted)';
        document.getElementById('tab_multi').style.borderBottomColor = mode === 'multi' ? 'var(--primary-red, #e63946)' : 'transparent';
        document.getElementById('tab_single').style.borderBottomColor = mode === 'single' ? 'var(--primary-red, #e63946)' : 'transparent';
        document.getElementById('single_opts').style.display = mode === 'single' ? 'block' : 'none';
        document.getElementById('preview_multi').style.display = 'none';
        document.getElementById('preview_single').style.display = 'none';
    }

    const dropZone = document.getElementById('drop_zone');
    const fileInput = document.getElementById('file_input');

    dropZone.onclick = () => fileInput.click();
    dropZone.ondragover = e => { e.preventDefault(); dropZone.style.background = 'rgba(237,28,36,0.08)'; };
    dropZone.ondragleave = () => dropZone.style.background = 'rgba(237,28,36,0.01)';
    dropZone.ondrop = e => { e.preventDefault(); dropZone.style.background = 'rgba(237,28,36,0.01)'; handleFile(e.dataTransfer.files[0]); };
    fileInput.onchange = e => handleFile(e.target.files[0]);

    function showLoading(text = "Processando...") {
        document.getElementById('loadingText').innerText = text;
        document.getElementById('loadingOverlay').style.display = 'flex';
    }

    function hideLoading() {
        document.getElementById('loadingOverlay').style.display = 'none';
    }

    function handleFile(file) {
        if (!file) return;
        showLoading("Lendo arquivo...");
        const reader = new FileReader();
        reader.onload = e => {
            try {
                const wb = XLSX.read(new Uint8Array(e.target.result), { type: 'array', cellDates: false });
                const detected = wb.SheetNames.filter(n => knownSheets.includes(n.toUpperCase()));
                if (detected.length >= 2 && currentMode === 'multi') {
                    handleMultiSheet(wb);
                } else if (currentMode === 'multi' && wb.SheetNames.length > 1) {
                    handleMultiSheet(wb);
                } else {
                    handleSingleSheet(wb);
                }
            } catch (err) {
                console.error(err);
                alert("Erro ao ler arquivo. Verifique o formato.");
            } finally {
                hideLoading();
            }
        };
        reader.readAsArrayBuffer(file);
    }

    function handleMultiSheet(wb) {
        const summaryEl = document.getElementById('sheets_summary');
        const previewsEl = document.getElementById('sheets_previews');
        summaryEl.innerHTML = '';
        previewsEl.innerHTML = '';

        const sheetsData = {};
        let totalRows = 0;

        wb.SheetNames.forEach(name => {
            // Normalize name: Upper case, spaces to underscore, remove accents for matching
            let normalized = name.toUpperCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            normalized = normalized.replace(/\s+/g, '_');

            // Special mapping for provided XML names to known internal keys
            const mapping = {
                'USUARIOS': 'USUARIOS',
                'CARGOS_DO_SISTEMA': 'CARGOS DO SISTEMA',
                'TURMAS': 'TURMAS',
                'RESERVAS': 'RESERVAS',
                'AGENDA_CALENDARIO': 'AGENDA_CALENDARIO',
                'CURSOS': 'CURSOS',
                'DOCENTES': 'DOCENTES',
                'AMBIENTES': 'AMBIENTES',
                'FERIAS': 'FÉRIAS',
                'FERIADOS': 'FERIADOS',
                'HORARIO_DE_TRABALHO': 'HORARIO_TRABALHO',
                'BLOQUEIOS': 'BLOQUEIOS',
                'PREPARACAO_ATESTADOS': 'PREPARACAO_ATESTADOS',
                'BLOQUEIO': 'BLOQUEIO'
            };

            const targetKey = mapping[normalized] || normalized;
            const ws = wb.Sheets[name];
            const data = XLSX.utils.sheet_to_json(ws, { raw: true, dateNF: 'dd/mm/yyyy', defval: '' });
            if (!data.length) return;

            sheetsData[targetKey] = data;
            totalRows += data.length;

            const info = sheetLabels[targetKey] || { icon: 'fa-file', color: '#6c757d', label: name };

            summaryEl.innerHTML += '<div style="padding: 10px 16px; border-radius: 10px; background: ' + info.color + '15; border: 1px solid ' + info.color + '40; display: flex; align-items: center; gap: 8px;">' +
                '<i class="fas ' + info.icon + '" style="color:' + info.color + ';"></i>' +
                '<span style="font-weight: 700;">' + info.label + '</span>' +
                '<span style="background:' + info.color + '; color:#fff; border-radius: 12px; padding: 2px 10px; font-size: 0.78rem;">' + data.length + '</span>' +
                '</div>';

            const cols = Object.keys(data[0]);
            let tableHtml = '<div style="margin-bottom: 20px;">';
            tableHtml += '<h4 style="margin: 10px 0; color: ' + info.color + ';"><i class="fas ' + info.icon + '"></i> ' + info.label + ' (' + data.length + ' registros)</h4>';
            tableHtml += '<div style="max-height: 200px; overflow: auto; border: 1px solid var(--border-color); border-radius: 8px;">';
            tableHtml += '<table style="font-size: 0.78rem; min-width: 100%;"><thead style="position:sticky; top:0; background: var(--bg-color); z-index:3;"><tr>';
            cols.forEach(c => { tableHtml += '<th style="padding: 6px 10px; white-space: nowrap;">' + c + '</th>'; });
            tableHtml += '</tr></thead><tbody>';
            data.slice(0, 20).forEach(row => {
                tableHtml += '<tr>';
                cols.forEach(c => {
                    let v = row[c] !== undefined ? row[c] : '';
                    if (typeof v === 'number' && v > 30000 && v < 60000) {
                        // Converte serial do Excel para data UTC para evitar offset de timezone local
                        // Ajustado de 25569 para 25568 para corrigir bug de 1 dia de atraso
                        const dateObj = new Date(Math.round((v - 25568) * 86400) * 1000);
                        const day = String(dateObj.getUTCDate()).padStart(2, '0');
                        const month = String(dateObj.getUTCMonth() + 1).padStart(2, '0');
                        const year = dateObj.getUTCFullYear();
                        v = `${day}/${month}/${year}`;
                    }
                    tableHtml += '<td style="padding: 4px 10px; white-space: nowrap;">' + v + '</td>';
                });
                tableHtml += '</tr>';
            });
            if (data.length > 20) tableHtml += '<tr><td colspan="' + cols.length + '" style="text-align:center; color: var(--text-muted); padding: 8px;">... e mais ' + (data.length - 20) + ' registros</td></tr>';
            tableHtml += '</tbody></table></div></div>';
            previewsEl.innerHTML += tableHtml;
        });

        if (Object.keys(sheetsData).length === 0) {
            alert("Nenhuma aba conhecida encontrada (DOCENTES, AMBIENTES, CURSOS, TURMAS, AGENDA).");
            return;
        }

        document.getElementById('form_sheets_json').value = JSON.stringify(sheetsData);
        document.getElementById('preview_multi').style.display = 'block';

        dropZone.innerHTML = '<i class="fas fa-check-circle" style="font-size:3rem; color:#28a745;"></i>' +
            '<h3>Arquivo pronto!</h3>' +
            '<p>' + Object.keys(sheetsData).length + ' abas detectadas · ' + totalRows + ' registros no total</p>';
    }

    function handleSingleSheet(wb) {
        switchTab('single');
        const ws = wb.Sheets[wb.SheetNames[0]];
        const data = XLSX.utils.sheet_to_json(ws, { raw: false, dateNF: 'dd/mm/yyyy', defval: '' });
        if (!data.length) return alert("Arquivo sem dados!");

        const header = document.getElementById('preview_header');
        const body = document.getElementById('preview_body');
        const cols = Object.keys(data[0]);

        header.innerHTML = '';
        body.innerHTML = '';
        cols.forEach(c => { const th = document.createElement('th'); th.textContent = c; header.appendChild(th); });

        data.slice(0, 50).forEach(row => {
            const tr = document.createElement('tr');
            cols.forEach(c => { const td = document.createElement('td'); td.textContent = row[c] || ''; tr.appendChild(td); });
            body.appendChild(tr);
        });

        document.getElementById('count_label').textContent = data.length + " registros";
        document.getElementById('form_import_type').value = document.getElementById('import_type').value;
        document.getElementById('form_import_data').value = JSON.stringify(data);
        document.getElementById('preview_single').style.display = 'block';

        dropZone.innerHTML = '<i class="fas fa-check-circle" style="font-size:3rem; color:#28a745;"></i>' +
            '<h3>Arquivo pronto!</h3><p>' + data.length + ' registros detectados.</p>';
    }
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>