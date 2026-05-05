<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';
require_once __DIR__ . '/../configs/utils.php';
require_once __DIR__ . '/../models/AgendaModel.php';

if (empty($_SESSION['user_id']))
    die("Acesso negado.");

$docente_id = (int) ($_GET['docente_id'] ?? 0);
$year = (int) ($_GET['year'] ?? date('Y'));
if (!$docente_id)
    die("ID do professor não fornecido.");

$res_doc = mysqli_query($conn, "SELECT nome, area_conhecimento FROM docente WHERE id = $docente_id");
$docente = mysqli_fetch_assoc($res_doc);
$nome_docente = $docente['nome'] ?? 'Professor';
$area_doc = $docente['area_conhecimento'] ?? 'Não informado';

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Relatorio_Anual_" . str_replace(' ', '_', $nome_docente) . "_$year.xls");
header("Pragma: no-cache");
header("Expires: 0");
echo "\xEF\xBB\xBF";

$meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$diasAbrev = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

$agendaModel = new AgendaModel($conn);
$agendas_year = $agendaModel->getExpandedAgenda([$docente_id], "$year-01-01", "$year-12-31");

function secFromTime($t)
{
    if (!$t)
        return 0;
    [$h, $m] = explode(':', $t);
    return (int) $h * 3600 + (int) $m * 60;
}

// ── PRÉ-PROCESSAR TODOS OS 12 MESES ─────────────────────────────────────────
$monthsData = [];
$totalAnualSec = 0;
$totalAnualDaysWorked = 0;
$totalAnualDaysFree = 0;

for ($m = 0; $m < 12; $m++) {
    $month = $m + 1;
    $monStr = str_pad($month, 2, '0', STR_PAD_LEFT);
    $lastDay = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    $monthSec = 0;
    $daysWorked = 0;
    $daysFree = 0;
    $daysHol = 0;
    $rows = [];

    for ($day = 1; $day <= $lastDay; $day++) {
        $dateISO = "$year-$monStr-" . str_pad($day, 2, '0', STR_PAD_LEFT);
        $ts = strtotime($dateISO);
        $dow = (int) date('w', $ts);
        $dayName = $diasAbrev[$dow];
        $isWeekend = ($dow === 0 || $dow === 6);

        $events = array_filter($agendas_year, fn($a) => $a['agenda_data'] === $dateISO);
        $schedules = array_filter($events, fn($a) => $a['type'] === 'WORK_SCHEDULE');
        $classes = array_filter($events, fn($a) => $a['type'] !== 'WORK_SCHEDULE');

        $isHol = $isVac = false;
        foreach ($classes as $c) {
            if ($c['type'] === 'FERIADO')
                $isHol = true;
            if ($c['type'] === 'FERIAS')
                $isVac = true;
        }

        // Calcular horas do dia
        $daySec = 0;
        if (!$isHol && !$isVac) {
            $hasIntegral = false;
            foreach ($schedules as $s)
                if ($s['periodo'] === 'Integral')
                    $hasIntegral = true;
            $seen = [];
            foreach ($schedules as $s) {
                if (!$s['horario'] || in_array($s['periodo'], $seen))
                    continue;
                if ($hasIntegral && in_array($s['periodo'], ['Manhã', 'Tarde']))
                    continue;
                $parts = preg_split('/ as | até | - /i', strtolower($s['horario']));
                if (count($parts) >= 2) {
                    $st = secFromTime(trim($parts[0]));
                    $en = secFromTime(trim($parts[1]));
                    if ($en > $st) {
                        $daySec += ($en - $st);
                        $seen[] = $s['periodo'];
                    }
                }
            }
        }
        $monthSec += $daySec;
        $hrsLabel = $daySec > 0 ? number_format($daySec / 3600, 1) . 'h' : '-';

        // Turnos
        $turnosCols = '';
        foreach (['Manhã', 'Tarde', 'Noite'] as $turno) {
            $sch = null;
            $cls = null;
            foreach ($schedules as $s) {
                if ($s['periodo'] === $turno) {
                    $sch = $s;
                    break;
                }
                if ($s['periodo'] === 'Integral' && in_array($turno, ['Manhã', 'Tarde'])) {
                    $sch = $s;
                    break;
                }
            }
            foreach ($classes as $c) {
                if ($c['type'] === 'FERIADO' || $c['type'] === 'FERIAS')
                    continue;
                if ($c['periodo'] === $turno) {
                    $cls = $c;
                    break;
                }
                if ($c['periodo'] === 'Integral' && in_array($turno, ['Manhã', 'Tarde'])) {
                    $cls = $c;
                    break;
                }
                if ($c['horario_inicio']) {
                    $h = (int) explode(':', $c['horario_inicio'])[0];
                    if ($turno === 'Manhã' && $h < 12) {
                        $cls = $c;
                        break;
                    }
                    if ($turno === 'Tarde' && $h >= 12 && $h < 18) {
                        $cls = $c;
                        break;
                    }
                    if ($turno === 'Noite' && $h >= 18) {
                        $cls = $c;
                        break;
                    }
                }
            }
            $e = $s2 = '-';
            $cc = 'bg-none';
            if ($isHol) {
                $cc = 'bg-hol';
            } elseif ($isVac) {
                $cc = 'bg-vac';
            } elseif ($sch) {
                if ($sch['horario']) {
                    $pts = preg_split('/ as | até | - /i', strtolower($sch['horario']));
                    $e = isset($pts[0]) ? substr(trim($pts[0]), 0, 5) : '-';
                    $s2 = isset($pts[1]) ? substr(trim($pts[1]), 0, 5) : '-';
                }
                $cc = $cls ? 'bg-busy' : 'bg-free';
            }
            // W = 36px por célula de horário (width forçado no td, único método confiável no Excel)
            $turnosCols .= "<td class='$cc' width='36' style='width:36px;'>$e</td><td class='$cc' width='36' style='width:36px;'>$s2</td>";
        }

        // Status da linha
        $rowClass = 'bg-none';
        $statusLabel = 'Sem Exp.';
        if ($isHol) {
            $rowClass = 'bg-hol';
            $statusLabel = 'Feriado';
            $daysHol++;
        } elseif ($isVac) {
            $rowClass = 'bg-vac';
            $statusLabel = 'Férias';
        } elseif (count($schedules) > 0) {
            $hasClass = false;
            foreach ($classes as $c) {
                if (in_array($c['type'], ['FERIADO', 'FERIAS']))
                    continue;
                foreach ($schedules as $s) {
                    if ($c['periodo'] === $s['periodo'] || ($s['periodo'] === 'Integral' && in_array($c['periodo'], ['Manhã', 'Tarde']))) {
                        $hasClass = true;
                        break 2;
                    }
                }
            }
            $rowClass = $hasClass ? 'bg-busy' : 'bg-free';
            $statusLabel = $hasClass ? 'Com Aula' : 'Livre';
            if ($hasClass)
                $daysWorked++;
            else
                $daysFree++;
        }

        $rows[] = compact('day', 'dayName', 'isWeekend', 'hrsLabel', 'turnosCols', 'rowClass', 'statusLabel');
    }

    $totalAnualSec += $monthSec;
    $totalAnualDaysWorked += $daysWorked;
    $totalAnualDaysFree += $daysFree;

    $monthsData[$m] = [
        'month' => $month,
        'monStr' => $monStr,
        'lastDay' => $lastDay,
        'monthSec' => $monthSec,
        'daysWorked' => $daysWorked,
        'daysFree' => $daysFree,
        'daysHol' => $daysHol,
        'rows' => $rows,
    ];
}

// ── ESTILOS ──────────────────────────────────────────────────────────────────
echo <<<CSS
<style>
  * { font-family: Calibri, Arial, sans-serif; font-size:9pt; }

  /* CAPA */
  .capa-wrap  { background:#1a252f; padding:18px 24px; }
  .capa-inst  { color:#95a5a6; font-size:9pt; letter-spacing:2px; text-transform:uppercase; }
  .capa-title { color:#ffffff; font-size:24pt; font-weight:900; margin:2px 0; }
  .capa-year  { color:#e74c3c; font-size:22pt; font-weight:900; }
  .capa-prof  { color:#ecf0f1; font-size:14pt; margin-top:4px; }
  .capa-area  { color:#bdc3c7; font-size:10pt; }
  .capa-date  { color:#7f8c8d; font-size:9pt; margin-top:6px; }

  /* CARDS DE RESUMO */
  .card-wrap      { background:#ecf0f1; padding:12px 16px; }
  .card-title     { color:#7f8c8d; font-size:8pt; font-weight:bold; text-transform:uppercase; letter-spacing:1px; }
  .card-val-red   { color:#c0392b; font-size:18pt; font-weight:900; }
  .card-val-green { color:#27ae60; font-size:18pt; font-weight:900; }
  .card-val-blue  { color:#2980b9; font-size:18pt; font-weight:900; }
  .card-val-gray  { color:#7f8c8d; font-size:18pt; font-weight:900; }
  .card-sub       { color:#95a5a6; font-size:8pt; }

  /* LEGENDA */
  .leg { font-size:8.5pt; padding:3px 9px; border:1.5px solid rgba(0,0,0,0.12); font-weight:600; }
  .leg-free { background:#d5f5e3; color:#1a6b3a; }
  .leg-busy { background:#fadbd8; color:#78281f; }
  .leg-hol  { background:#d6eaf8; color:#1a4f72; }
  .leg-vac  { background:#e8daef; color:#512e5f; }
  .leg-wknd { background:#fef9e7; color:#7d6608; }
  .leg-none { background:#f0f3f4; color:#808b96; }

  /* TABELAS DOS MESES */
  .tbl { border-collapse:collapse; font-size:8pt; }
  .tbl td, .tbl th { border:1px solid #ccc; text-align:center; padding:2px 3px; height:20px; }

  /* Cabeçalho do mês */
  .th-month { font-size:13pt; font-weight:900; letter-spacing:2px; padding:9px; }

  /* Mini-stats do mês */
  .th-stats { font-size:7.5pt; padding:5px 4px; font-weight:bold; }
  .stats-worked { background:#1a6b3a; color:#d5f5e3; }
  .stats-free   { background:#1a5276; color:#d6eaf8; }
  .stats-hol    { background:#6c3483; color:#e8daef; }
  .stats-hrs    { background:#922b21; color:#fadbd8; }

  /* Sub-cabeçalhos de turno */
  .th-meta  { background:#2c3e50; color:#ecf0f1; font-size:8pt; font-weight:bold; }
  .th-manha { background:#1e8449; color:#fff; font-size:7.5pt; font-weight:bold; }
  .th-tarde { background:#1a5276; color:#fff; font-size:7.5pt; font-weight:bold; }
  .th-noite { background:#512e5f; color:#fff; font-size:7.5pt; font-weight:bold; }
  .th-sub   { font-size:7pt; background:#f2f3f4; color:#666; }

  /* Células de dados */
  .bg-free { background:#d5f5e3; color:#1a5f37; }
  .bg-busy { background:#fadbd8; color:#78281f; }
  .bg-hol  { background:#d6eaf8; color:#1a4f72; }
  .bg-vac  { background:#e8daef; color:#512e5f; }
  .bg-none { background:#f0f3f4; color:#aaa; }
  .bg-wknd { background:#fef9e7; color:#9a7d0a; }
  .bg-emp  { background:#fafafa; border-color:#eee; }

  /* Linha de total */
  .tr-total td { background:#1a252f; color:#fff; font-weight:bold; font-size:8.5pt; height:22px; border-color:#111; }

  /* RODAPÉ ANUAL */
  .anu-main { background:#c0392b; color:#fff; font-size:17pt; font-weight:900; text-align:center; padding:16px; letter-spacing:1px; border:3px solid #922b21; }
  .anu-sub  { background:#1a252f; color:#ecf0f1; font-size:11pt; font-weight:bold; text-align:center; padding:9px; }
  .anu-grid { background:#ecf0f1; text-align:center; padding:10px; font-size:10pt; }
</style>
CSS;

// ── CAPA / CABEÇALHO ────────────────────────────────────────────────────────
echo "<table width='100%'>";
echo "<tr><td class='capa-wrap'>";
echo "<div class='capa-inst'>SENAI &nbsp;·&nbsp; Sistema de Gerenciamento de Horários</div>";
echo "<div class='capa-title'>RELATÓRIO ANUAL DE HORÁRIOS <span class='capa-year'>{$year}</span></div>";
echo "<div class='capa-prof'>&#128100; {$nome_docente}</div>";
echo "<div class='capa-area'>&#128218; Área: {$area_doc}</div>";
echo "<div class='capa-date'>&#128197; Emitido em " . date('d \d\e F \d\e Y \à\s H:i') . "</div>";
echo "</td></tr></table>";

// ── CARDS DE RESUMO ANUAL ───────────────────────────────────────────────────
$totH = floor($totalAnualSec / 3600);
$totM = floor(($totalAnualSec % 3600) / 60);
echo "<table width='100%' style='margin-top:1px;'><tr>";
echo "<td width='25%' class='card-wrap' style='border-left:5px solid #c0392b;'>
        <div class='card-title'>&#9201; Total de Horas no Ano</div>
        <div class='card-val-red'>{$totH}h {$totM}m</div>
        <div class='card-sub'>Horas lecionadas em {$year}</div>
      </td>";
echo "<td width='5%'></td>";
echo "<td width='25%' class='card-wrap' style='border-left:5px solid #27ae60;'>
        <div class='card-title'>&#128197; Dias com Aula</div>
        <div class='card-val-green'>{$totalAnualDaysWorked}</div>
        <div class='card-sub'>Dias efetivamente lecionados</div>
      </td>";
echo "<td width='5%'></td>";
echo "<td width='25%' class='card-wrap' style='border-left:5px solid #2980b9;'>
        <div class='card-title'>&#128274; Dias Livres (com exp.)</div>
        <div class='card-val-blue'>{$totalAnualDaysFree}</div>
        <div class='card-sub'>Dias com expediente, sem aula</div>
      </td>";
echo "</tr></table>";

// ── LEGENDA ─────────────────────────────────────────────────────────────────
echo "<table width='100%' style='margin-top:1px; background:#fafafa; padding:8px;'><tr>";
echo "<td style='padding:8px; font-size:8.5pt;'><b>LEGENDA:</b>&nbsp;&nbsp;";
echo "<span class='leg leg-free'>&#9632; Livre</span>&nbsp;";
echo "<span class='leg leg-busy'>&#9632; Com Aula</span>&nbsp;";
echo "<span class='leg leg-hol'>&#9632; Feriado</span>&nbsp;";
echo "<span class='leg leg-vac'>&#9632; Férias</span>&nbsp;";
echo "<span class='leg leg-wknd'>&#9632; Final de Semana</span>&nbsp;";
echo "<span class='leg leg-none'>&#9632; Sem Expediente</span>";
echo "</td></tr></table>";

// ── GRID DE MESES (3 × 4) ───────────────────────────────────────────────────
echo "<table border='0' cellpadding='0' cellspacing='0' style='margin-top:10px;'>";

for ($row = 0; $row < 4; $row++) {
    echo "<tr>";
    for ($col = 0; $col < 3; $col++) {
        $m = $row * 3 + $col;
        if ($m >= 12) {
            echo "<td style='padding:6px;'></td>";
            continue;
        }

        $d = $monthsData[$m];
        $monH = floor($d['monthSec'] / 3600);
        $monM = floor(($d['monthSec'] % 3600) / 60);

        // Cor alternada por linha
        $monthColors = [
            '#c0392b',
            '#e74c3c',
            '#e67e22',
            '#f39c12',
            '#27ae60',
            '#16a085',
            '#2980b9',
            '#8e44ad',
            '#2c3e50',
            '#d35400',
            '#1a5276',
            '#6c3483',
        ];
        $mc = $monthColors[$m];

        // Largura fixa da célula externa para garantir proporcionalidade
        echo "<td valign='top' style='padding:6px; width:420px;'>";
        echo "<table class='tbl' style='width:420px; table-layout:fixed;'>";
        // Linha fantasma para fixar larguras absolutas de cada coluna
        echo "<tr style='height:0; line-height:0; visibility:collapse;'>
            <td style='width:30px; padding:0;'></td>
            <td style='width:30px; padding:0;'></td>
            <td style='width:36px; padding:0;'></td>
            <td style='width:36px; padding:0;'></td>
            <td style='width:36px; padding:0;'></td>
            <td style='width:36px; padding:0;'></td>
            <td style='width:36px; padding:0;'></td>
            <td style='width:36px; padding:0;'></td>
            <td style='width:36px; padding:0;'></td>
            <td style='width:36px; padding:0;'></td>
            <td style='width:50px; padding:0;'></td>
          </tr>";

        // Cabeçalho do mês
        echo "<tr><th colspan='11' class='th-month' style='background:{$mc}; color:#fff; text-align:center;'>" . strtoupper($meses[$m]) . " &nbsp;·&nbsp; {$year}</th></tr>";

        // Mini-stats do mês (4 blocos proporcionais)
        echo "<tr>
            <th colspan='3' class='th-stats stats-hrs'>&#9201;&nbsp;{$monH}h&nbsp;{$monM}m</th>
            <th colspan='2' class='th-stats stats-worked'>{$d['daysWorked']}&nbsp;c/&nbsp;aula</th>
            <th colspan='2' class='th-stats stats-free'>{$d['daysFree']}&nbsp;livres</th>
            <th colspan='2' class='th-stats stats-hol'>{$d['daysHol']}&nbsp;feriados</th>
            <th colspan='2' class='th-stats' style='background:#374151; color:#d1d5db;'>" . $d['lastDay'] . "&nbsp;dias</th>
          </tr>";

        // Cabeçalho das colunas
        echo "<tr>
            <th class='th-meta' rowspan='2' style='width:30px;'>DIA</th>
            <th class='th-meta' rowspan='2' style='width:30px;'>SEM</th>
            <th class='th-meta' rowspan='2' style='width:36px;'>HRS</th>
            <th colspan='2' class='th-manha' style='width:72px;'>MANHÃ</th>
            <th colspan='2' class='th-tarde' style='width:72px;'>TARDE</th>
            <th colspan='2' class='th-noite' style='width:72px;'>NOITE</th>
            <th class='th-meta' rowspan='2' style='width:50px;'>STATUS</th>
          </tr>
          <tr>
            <th class='th-sub' style='width:36px;'>Ent.</th><th class='th-sub' style='width:36px;'>Saí.</th>
            <th class='th-sub' style='width:36px;'>Ent.</th><th class='th-sub' style='width:36px;'>Saí.</th>
            <th class='th-sub' style='width:36px;'>Ent.</th><th class='th-sub' style='width:36px;'>Saí.</th>
          </tr>";

        foreach ($d['rows'] as $r) {
            $wkBg = $r['isWeekend'] ? 'background:#fef9e7; color:#9a7d0a;' : '';
            $wkClass = $r['isWeekend'] ? 'bg-wknd' : $r['rowClass'];
            // width em cada td: único método 100% confiável para equalizar colunas no Excel HTML
            echo "<tr>
                <td class='{$wkClass}' width='30' style='width:30px; font-weight:900; {$wkBg}'>{$r['day']}</td>
                <td class='{$wkClass}' width='30' style='width:30px; font-size:7.5pt;'>{$r['dayName']}</td>
                <td class='{$r['rowClass']}' width='36' style='width:36px; font-weight:bold;'>{$r['hrsLabel']}</td>
                {$r['turnosCols']}
                <td class='{$r['rowClass']}' width='50' style='width:50px; font-size:7pt; font-weight:bold;'>{$r['statusLabel']}</td>
              </tr>";
        }

        // Padding com width forçado em cada td individual
        for ($pad = $d['lastDay']; $pad < 31; $pad++) {
            echo "<tr>
                <td class='bg-emp' width='30' style='width:30px; height:19px;'></td>
                <td class='bg-emp' width='30' style='width:30px;'></td>
                <td class='bg-emp' width='36' style='width:36px;'></td>
                <td class='bg-emp' width='36' style='width:36px;'></td>
                <td class='bg-emp' width='36' style='width:36px;'></td>
                <td class='bg-emp' width='36' style='width:36px;'></td>
                <td class='bg-emp' width='36' style='width:36px;'></td>
                <td class='bg-emp' width='36' style='width:36px;'></td>
                <td class='bg-emp' width='36' style='width:36px;'></td>
                <td class='bg-emp' width='36' style='width:36px;'></td>
                <td class='bg-emp' width='50' style='width:50px;'></td>
              </tr>";
        }

        // Linha de total do mês
        echo "<tr class='tr-total'>
            <td colspan='3' style='text-align:right; letter-spacing:1px; background:{$mc};'>TOTAL:</td>
            <td colspan='7' style='text-align:left; padding-left:8px; background:{$mc};'>&#9654; {$monH}h " . str_pad($monM, 2, '0', STR_PAD_LEFT) . "min &nbsp;|&nbsp; {$d['daysWorked']} aulas &nbsp;·&nbsp; {$d['daysFree']} livres</td>
            <td style='background:{$mc};'></td>
          </tr>";

        echo "</table></td>";
    }
    echo "</tr><tr><td colspan='3' style='height:10px;'></td></tr>";
}
echo "</table>";

// ── RODAPÉ ANUAL ─────────────────────────────────────────────────────────────
$totFmt = sprintf('%02dh %02dmin', $totH, $totM);
echo "<br><table width='100%'>";
echo "<tr><td class='anu-sub'>&#128203; RESUMO ANUAL &nbsp;·&nbsp; {$nome_docente} &nbsp;·&nbsp; {$year}</td></tr>";
echo "<tr><td class='anu-main'>&#9201; TOTAL DE HORAS: {$totFmt} &nbsp;|&nbsp; {$totalAnualDaysWorked} dias com aula &nbsp;|&nbsp; {$totalAnualDaysFree} dias livres</td></tr>";
echo "<tr><td class='anu-grid'>Formato Relógio Completo: " . sprintf('%02d:%02d:%02d', $totH, $totM, $totalAnualSec % 60) . " &nbsp;&nbsp; Média mensal: " . number_format($totH / 12, 1) . "h/mês &nbsp;&nbsp; Média por dia com aula: " . ($totalAnualDaysWorked > 0 ? number_format(($totalAnualSec / 3600) / $totalAnualDaysWorked, 1) : '0') . "h/dia</td></tr>";
echo "</table>";
