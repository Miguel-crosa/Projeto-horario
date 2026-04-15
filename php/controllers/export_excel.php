<?php
require_once __DIR__ . '/../configs/db.php';

// ── Excel: SpreadsheetML XML with Multiple Worksheets ──
$filename = 'SENAI_Exportacao_Completa_' . date('Y-m-d_H-i') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Garantir que não haja lixo no buffer
if (ob_get_length())
  ob_clean();
ob_start();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office"
  xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:html="http://www.w3.org/TR/REC-html40">

  <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
    <Title>Exportação Completa Base de Dados</Title>
    <Author>Gestão Escolar SENAI</Author>
    <LastAuthor>Claudemir Aparecido Flores</LastAuthor>
    <Created><?= date('Y-m-d\TH:i:s\Z') ?></Created>
    <LastSaved><?= date('Y-m-d\TH:i:s\Z') ?></LastSaved>
    <Version>16.00</Version>
  </DocumentProperties>

  <OfficeDocumentSettings xmlns="urn:schemas-microsoft-com:office:office">
    <AllowPNG />
  </OfficeDocumentSettings>

  <ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel">
    <WindowHeight>8676</WindowHeight>
    <WindowWidth>23040</WindowWidth>
    <WindowTopX>32767</WindowTopX>
    <WindowTopY>32767</WindowTopY>
    <ActiveSheet>0</ActiveSheet>
    <ProtectStructure>False</ProtectStructure>
    <ProtectWindows>False</ProtectWindows>
  </ExcelWorkbook>

  <Styles>
    <Style ss:ID="Default" ss:Name="Normal">
      <Alignment ss:Vertical="Bottom" /><Borders/><Font ss:FontName="Aptos Narrow" x:Family="Swiss" ss:Size="11" ss:Color="#000000" /><Interior/><NumberFormat/><Protection/>
    </Style>
    <Style ss:ID="s62">
      <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000" /><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000" /><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000" /><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000" /></Borders><Font ss:FontName="Segoe UI" ss:Size="11" ss:Color="#FFFFFF" ss:Bold="1" /><Interior ss:Color="#FF0000" ss:Pattern="Solid" /><NumberFormat/><Protection/>
    </Style>
    <Style ss:ID="s63">
      <Alignment ss:Horizontal="Center" ss:Vertical="Center" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000" /><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000" /><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000" /><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000" /></Borders><Font ss:FontName="Segoe UI" ss:Color="#000000" /><Interior ss:Color="#FFFFFF" ss:Pattern="Solid" /><NumberFormat/><Protection/>
    </Style>
    <Style ss:ID="s64">
      <Alignment ss:Vertical="Center" ss:WrapText="1" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000" /><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000" /><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000" /><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000" /></Borders><Font ss:FontName="Segoe UI" ss:Color="#000000" /><Interior ss:Color="#FFFFFF" ss:Pattern="Solid" /><NumberFormat/><Protection/>
    </Style>
    <Style ss:ID="s67">
      <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" /></Borders>
    </Style>
    <Style ss:ID="s68">
      <Alignment ss:Horizontal="Center" ss:Vertical="Center" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" /></Borders><Font ss:FontName="Segoe UI" ss:Color="#000000" /><Interior ss:Color="#FFFFFF" ss:Pattern="Solid" /><NumberFormat/><Protection/>
    </Style>
    <Style ss:ID="s70">
      <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" /></Borders><NumberFormat ss:Format="Short Date" />
    </Style>
    <Style ss:ID="s71">
      <Alignment ss:Vertical="Center" ss:WrapText="1" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" /></Borders><Font ss:FontName="Segoe UI" ss:Color="#000000" /><Interior ss:Color="#FFFFFF" ss:Pattern="Solid" /><NumberFormat/><Protection/>
    </Style>
    <Style ss:ID="s72">
      <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" /></Borders><NumberFormat ss:Format="Short Time" />
    </Style>
  </Styles>

  <?php
  function renderWorksheet($name, $query, $conn, $dateColumns = [], $timeColumns = [], $addCounter = true)
  {
    $res = mysqli_query($conn, $query);
    if (!$res || mysqli_num_rows($res) === 0)
      return;

    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
      $rows[] = $row;
    }
    $columns = array_keys($rows[0]);
    if ($addCounter) {
      array_unshift($columns, 'Nº');
    }
    $colCount = count($columns);
    $rowCount = count($rows);

    $sheetName = substr($name, 0, 31);
    $range = 'R1C1:R' . ($rowCount + 1) . 'C' . $colCount;

    echo '  <Worksheet ss:Name="' . xe($sheetName) . '">' . "\n";
    echo '    <Names>' . "\n";
    echo '      <NamedRange ss:Name="_FilterDatabase" ss:RefersTo="=\'' . str_replace("'", "''", $sheetName) . '\'!' . $range . '" ss:Hidden="1"/>' . "\n";
    echo '    </Names>' . "\n";
    echo '    <Table x:FullColumns="1" x:FullRows="1" ss:DefaultRowHeight="15">' . "\n";

    // Attempt somewhat smart column widths
    foreach ($columns as $c) {
      $width = 120;
      $c_low = mb_strtolower($c, 'UTF-8');

      if (in_array($c_low, ['id', 'vagas', 'capacidade', 'nº', 'status', 'semestral'])) {
        $width = 60;
      } else if (in_array($c_low, ['nome', 'curso', 'docente', 'email', 'ambiente', 'sigla', 'local', 'turma', 'docente 1', 'docente 2', 'docente 3', 'docente 4', 'docente_responsavel', 'detalhe', 'componentes', 'área', 'área de conhecimento', 'solicitado por', 'curso relacionado', 'dias da semana', 'dia de trabalho', 'tipo contrato', 'turno', 'cidade', 'parceiro', 'contato parceiro'])) {
        $width = 350;
      } else if (strpos($c_low, 'data') !== false || strpos($c_low, 'hora') !== false || strpos($c_low, 'início') !== false || strpos($c_low, 'fim') !== false) {
        $width = 110;
      } else if (in_array($c_low, ['valor turma', 'valor', 'previsão despesa', 'despesa'])) {
        $width = 130;
      } else if (strpos($c_low, 'dias') !== false || strpos($c_low, 'semana') !== false || strpos($c_low, 'trabalho') !== false) {
        $width = 250;
      }
      echo '      <Column ss:Width="' . $width . '" />' . "\n";
    }

    // Header Row
    echo '      <Row ss:Height="24">' . "\n";
    foreach ($columns as $c) {
      echo '        <Cell ss:StyleID="s62"><Data ss:Type="String">' . xe(str_replace('_', ' ', $c)) . '</Data><NamedCell ss:Name="_FilterDatabase"/></Cell>' . "\n";
    }
    echo '      </Row>' . "\n";

    // Data Rows
    $counter = 1;
    foreach ($rows as $row) {
      echo '      <Row>' . "\n";
      if ($addCounter) {
        echo '        <Cell ss:StyleID="s63"><Data ss:Type="Number">' . $counter++ . '</Data><NamedCell ss:Name="_FilterDatabase"/></Cell>' . "\n";
      }
      foreach ($row as $key => $val) {
        $style = 's64';
        $type = 'String';

        if (in_array($key, $dateColumns)) {
          $style = 's70';
          if ($val) {
            $val = date('Y-m-d\T00:00:00.000', strtotime($val));
            $type = 'DateTime';
          }
        } else if (in_array($key, $timeColumns)) {
          $style = 's72';
          if ($val) {
            $val = '1899-12-31T' . substr($val, 0, 5) . ':00.000';
            $type = 'DateTime';
          }
        } else if (is_numeric($val) && !preg_match('/^0[0-9]/', $val)) {
          $style = 's63';
          $type = 'Number';
        }

        if ($val === null)
          $val = '';

        // Abbreviate days if column looks like it contains days, BUT NOT if it is numeric
        $key_low = mb_strtolower($key, 'UTF-8');
        if (!is_numeric($val) && (strpos($key_low, 'dias') !== false || strpos($key_low, 'semana') !== false || strpos($key_low, 'trabalho') !== false || strpos($key_low, 'disponibilidade') !== false)) {
          $val = abbreviateDays($val);
        }

        echo '        <Cell ss:StyleID="' . $style . '">';
        if ($type === 'DateTime') {
          echo '<Data ss:Type="DateTime">' . $val . '</Data>';
        } else {
          echo '<Data ss:Type="' . $type . '">' . xe($val) . '</Data>';
        }
        echo '<NamedCell ss:Name="_FilterDatabase"/></Cell>' . "\n";
      }
      echo '      </Row>' . "\n";
    }
    echo '    </Table>' . "\n";

    // Freeze Top Row and AutoFilter
    echo '    <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">' . "\n";
    echo '      <PageSetup>' . "\n";
    echo '        <Header x:Margin="0.4921259845"/>' . "\n";
    echo '        <Footer x:Margin="0.4921259845"/>' . "\n";
    echo '        <PageMargins x:Bottom="0.984251969" x:Left="0.78740157499999996" x:Right="0.78740157499999996" x:Top="0.984251969"/>' . "\n";
    echo '      </PageSetup>' . "\n";
    echo '      <Unsynced/>' . "\n";
    echo '      <FreezePanes/>' . "\n";
    echo '      <FrozenNoSplit/>' . "\n";
    echo '      <SplitHorizontal>1</SplitHorizontal>' . "\n";
    echo '      <TopRowBottomPane>1</TopRowBottomPane>' . "\n";
    echo '      <ActivePane>2</ActivePane>' . "\n";
    echo '      <Panes>' . "\n";
    echo '        <Pane><Number>3</Number></Pane>' . "\n";
    echo '        <Pane><Number>2</Number></Pane>' . "\n";
    echo '      </Panes>' . "\n";
    echo '      <ProtectObjects>False</ProtectObjects>' . "\n";
    echo '      <ProtectScenarios>False</ProtectScenarios>' . "\n";
    echo '    </WorksheetOptions>' . "\n";
    echo '    <AutoFilter x:Range="' . $range . '" xmlns="urn:schemas-microsoft-com:office:excel"></AutoFilter>' . "\n";
    echo '  </Worksheet>' . "\n";
  }

  function abbreviateDays($val)
  {
    if (!$val)
      return $val;
    $map = [
      'segunda-feira' => '2ª',
      'terça-feira' => '3ª',
      'quarta-feira' => '4ª',
      'quinta-feira' => '5ª',
      'sexta-feira' => '6ª',
      'sábado' => 'sab',
      'sabado' => 'sab',
      'domingo' => 'dom'
    ];
    $parts = explode(',', $val);
    $newParts = [];
    foreach ($parts as $p) {
      $low = mb_strtolower(trim($p), 'UTF-8');
      if (isset($map[$low])) {
        $abbrev = $map[$low];
        if (!in_array($abbrev, $newParts)) {
          $newParts[] = $abbrev;
        }
      }
    }
    return implode(', ', $newParts);
  }

  // 1. Usuários
  // 1. Cursos
  $q_cursos = "
    SELECT 
        nome AS Nome, 
        tipo AS Tipo, 
        area AS Area, 
        carga_horaria_total AS `Carga Horária Total`,
        IF(semestral = 1, 'Sim', 'Não') AS Semestral
    FROM curso
    ORDER BY nome ASC
";
  renderWorksheet("CURSOS", $q_cursos, $conn);

  // 2. Docentes
  $q_docentes = "
    SELECT 
        d.nome AS Nome, 
        d.area_conhecimento AS Área, 
        d.cidade AS Cidade, 
        (SELECT GROUP_CONCAT(DISTINCT periodo ORDER BY FIELD(periodo, 'Manhã', 'Tarde', 'Noite') SEPARATOR ', ') FROM horario_trabalho WHERE docente_id = d.id) AS Periodos,
        d.tipo_contrato AS `Tipo Contrato`,
        (SELECT GROUP_CONCAT(dias SEPARATOR ', ') FROM horario_trabalho WHERE docente_id = d.id) AS `Dias Disponíveis`,
        d.weekly_hours_limit AS `Carga Horaria Semanal Max`,
        d.monthly_hours_limit AS `Carga Horaria Mensal Max`
    FROM docente d
    ORDER BY d.nome ASC
";
  renderWorksheet("DOCENTES", $q_docentes, $conn);

  // 3. Ambientes
  $q_ambientes = "
    SELECT 
        nome AS Nome, 
        tipo AS Tipo, 
        area_vinculada AS Area_Vinculada, 
        cidade AS Cidade, 
        capacidade AS Capacidade 
    FROM ambiente
    ORDER BY nome ASC
";
  renderWorksheet("AMBIENTES", $q_ambientes, $conn);

  // 4. Usuários
  $q_usuarios = "
    SELECT 
        u.nome AS Nome, 
        u.email AS Email, 
        u.role AS `Cargo Permissão`
    FROM usuario u 
    ORDER BY u.nome ASC
";
  renderWorksheet("USUARIOS", $q_usuarios, $conn);

  // 5. Turmas
  $q_turmas = "
    SELECT 
        t.sigla AS Sigla, 
        c.nome AS Curso, 
        t.tipo AS Tipo,
        t.periodo AS Período, 
        amb.nome AS Ambiente,
        t.vagas AS Vagas,
        t.numero_proposta AS `Nº Proposta`,
        t.tipo_atendimento AS `Tipo Atendimento`,
        t.parceiro AS `Parceiro`,
        t.contato_parceiro AS `Contato Parceiro`,
        t.local AS Local,
        t.data_inicio AS `Data Início`, 
        t.data_fim AS `Data Fim`, 
        t.dias_semana AS `Dias Semana`, 
        t.horario_inicio AS `Horário Início`,
        t.horario_fim AS `Horário Fim`,
        d1.nome AS `Docente 1`, 
        d2.nome AS `Docente 2`,
        d3.nome AS `Docente 3`,
        d4.nome AS `Docente 4`,
        t.componentes AS Componentes,
        t.tipo_custeio AS `Tipo Custeio`,
        t.previsao_despesa AS `Previsão Despesa`,
        t.valor_turma AS `Valor Turma`,
        t.numero_proposta AS `Nº Proposta`,
        t.tipo_atendimento AS `Tipo Atendimento`,
        t.parceiro AS `Parceiro`,
        t.contato_parceiro AS `Contato Parceiro`
    FROM turma t 
    LEFT JOIN curso c ON t.curso_id = c.id 
    LEFT JOIN ambiente amb ON t.ambiente_id = amb.id 
    LEFT JOIN docente d1 ON t.docente_id1 = d1.id 
    LEFT JOIN docente d2 ON t.docente_id2 = d2.id
    LEFT JOIN docente d3 ON t.docente_id3 = d3.id
    LEFT JOIN docente d4 ON t.docente_id4 = d4.id
    ORDER BY t.data_inicio DESC
";
  renderWorksheet("TURMAS", $q_turmas, $conn, ['Data Início', 'Data Fim'], ['Horário Início', 'Horário Fim']);

  // 6. Reservas
  $q_reservas = "
    SELECT 
        r.status AS Status,
        d.nome AS Docente, 
        u.nome AS `Solicitado Por`, 
        c.nome AS `Curso Relacionado`, 
        COALESCE(amb.nome, r.local) AS Ambiente, 
        r.periodo AS Periodo,
        r.data_inicio AS `Data Início`, 
        r.data_fim AS `Data Fim`, 
        r.dias_semana AS `Dias Semana`, 
        r.hora_inicio AS `Horário Início`, 
        r.hora_fim AS `Horário Fim`,
        r.tipo_custeio AS `Tipo Custeio`,
        r.previsao_despesa AS `Previsão Despesa`,
        r.valor_turma AS `Valor Turma`,
        r.numero_proposta AS `Nº Proposta`,
        r.tipo_atendimento AS `Tipo Atendimento`,
        r.parceiro AS `Parceiro`,
        r.contato_parceiro AS `Contato Parceiro`
    FROM reservas r 
    LEFT JOIN docente d ON r.docente_id = d.id 
    LEFT JOIN usuario u ON r.usuario_id = u.id 
    LEFT JOIN curso c ON r.curso_id = c.id 
    LEFT JOIN ambiente amb ON r.ambiente_id = amb.id
    ORDER BY r.created_at DESC
";
  renderWorksheet("RESERVAS", $q_reservas, $conn, ['Data Início', 'Data Fim'], ['Horário Início', 'Horário Fim']);

  // 7. Agenda
  $q_agenda = "
    SELECT 
        t.sigla AS Turma, 
        d.nome AS `Docente 1`, 
        COALESCE(amb.nome, amb_t.nome, t.local) AS Ambiente,
        t.local AS Local,
        a.data AS Data, 
        a.horario_inicio AS `Hora Início`, 
        a.horario_fim AS `Hora Fim`
    FROM agenda a 
    LEFT JOIN turma t ON a.turma_id = t.id 
    LEFT JOIN docente d ON a.docente_id = d.id 
    LEFT JOIN ambiente amb ON a.ambiente_id = amb.id
    LEFT JOIN ambiente amb_t ON t.ambiente_id = amb_t.id
    ORDER BY a.data DESC, a.horario_inicio ASC
";
  renderWorksheet("AGENDA", $q_agenda, $conn, ['Data'], ['Hora Início', 'Hora Fim']);

  // 8. Férias
  $q_ferias = "
    SELECT 
        COALESCE(d.nome, 'Todos (Coletiva)') AS Docente, 
        v.start_date AS `Data Início`, 
        v.end_date AS `Data Fim`,
        IF(v.type = 'collective', 'Coletiva', 'Individual') AS Tipo
     FROM vacations v 
     LEFT JOIN docente d ON v.teacher_id = d.id
     ORDER BY v.start_date DESC
";
  renderWorksheet("FÉRIAS", $q_ferias, $conn, ['Data Início', 'Data Fim']);

  // 9. Feriados
  $q_feriados = "
    SELECT 
        name AS Nome, 
        date AS `Data Início`, 
        end_date AS `Data Fim` 
    FROM holidays 
    ORDER BY date ASC
";
  renderWorksheet("FERIADOS", $q_feriados, $conn, ['Data Início', 'Data Fim']);

  // 10. Horário de Trabalho
  $q_ht = "
    SELECT 
        d.nome AS Docente,
        h.dias AS Dias,
        h.periodo AS Período,
        h.horario AS Horário,
        h.data_inicio AS `Data Início`,
        h.data_fim AS `Data Fim`,
        h.ano AS Ano
    FROM horario_trabalho h
    JOIN docente d ON h.docente_id = d.id
    ORDER BY d.nome ASC, h.data_inicio ASC
";
  renderWorksheet("HORARIO_TRABALHO", $q_ht, $conn, ['Data Início', 'Data Fim']);

  // 11. Bloqueios
  $q_bloqueios = "
    SELECT 
        d.nome AS Docente, 
        tipo AS Tipo,
        data_inicio AS `Data Início`, 
        data_fim AS `Data Fim`,
        horario_inicio AS `Horário de`,
        horario_fim AS `Horário até`
     FROM preparacao_atestados pa 
     JOIN docente d ON pa.docente_id = d.id
     ORDER BY data_inicio DESC
";
  renderWorksheet("BLOQUEIOS", $q_bloqueios, $conn, ['Data Início', 'Data Fim'], ['Horário de', 'Horário até']);

  ?>
</Workbook>