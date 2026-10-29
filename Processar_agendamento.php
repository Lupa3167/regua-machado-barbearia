<?php
require_once __DIR__ . '/Config.php';
header('Content-Type: application/json; charset=utf-8');

// Respostas consistentes para o fetch() do Agendamento.php.
function responderAgendamento(int $codigo, bool $sucesso, string $mensagem, ?int $id = null): void {
    http_response_code($codigo);
    $resposta = ['success' => $sucesso, 'message' => $mensagem];
    if ($id !== null) $resposta['agendamento_id'] = $id;
    echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
    exit;
}

function idPositivo($valor): int {
    if (!is_scalar($valor)) throw new InvalidArgumentException('ID inválido no formulário.');
    $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) throw new InvalidArgumentException('ID inválido no formulário.');
    return $id;
}

function minutosDoHorario($horario): int {
    if (!is_string($horario) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $horario, $partes)) {
        throw new InvalidArgumentException('Horário inválido. Informe no formato HH:MM.');
    }
    return (int)$partes[1] * 60 + (int)$partes[2];
}

function horarioDosMinutos(int $minutos): string {
    return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderAgendamento(405, false, 'Método não permitido.');
}

$db = null;
try {
    $clienteId = idPositivo($_POST['cliente_id'] ?? null);
    $data = $_POST['data_agendamento'] ?? null;
    if (!is_string($data) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data, $partes)
        || !checkdate((int)$partes[2], (int)$partes[3], (int)$partes[1])) {
        throw new InvalidArgumentException('Data do agendamento inválida.');
    }

    $statusPermitidos = ['agendado', 'confirmado', 'aguardando', 'em_atendimento',
                         'finalizado', 'cancelado', 'faltou'];
    $status = $_POST['status'] ?? 'agendado';
    if (!is_string($status) || !in_array($status, $statusPermitidos, true)) {
        throw new InvalidArgumentException('Status do atendimento inválido.');
    }

    $itens = $_POST['servicos'] ?? null;
    if (!is_array($itens) || count($itens) < 1 || count($itens) > 20) {
        throw new InvalidArgumentException('Informe de 1 a 20 serviços.');
    }

    $database = new Database();
    $db = $database->getConnection();
    if (!$db instanceof PDO) {
        throw new RuntimeException('Não foi possível conectar ao banco.');
    }

    $consultaCliente = $db->prepare('SELECT id FROM clientes WHERE id = :id');
    $consultaCliente->execute([':id' => $clienteId]);
    if (!$consultaCliente->fetchColumn()) {
        throw new InvalidArgumentException('Cliente não encontrado no banco.');
    }

    $consultaServico = $db->prepare('SELECT id, duracao, valor FROM servicos WHERE id = :id AND ativo = 1');
    $consultaProfissional = $db->prepare('SELECT id FROM profissionais WHERE id = :id AND ativo = 1');

    $servicosValidados = [];
    $inicioGeral = 1440;
    $fimGeral = 0;
    $ocupacoes = [];

    foreach ($itens as $indice => $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Dados inválidos em um dos serviços.');
        }
        $servicoId = idPositivo($item['servico_id'] ?? null);
        $profissionalId = idPositivo($item['profissional_id'] ?? null);
        $inicio = $item['hora_inicio'] ?? null;
        $fimInformado = $item['hora_fim'] ?? null;
        $inicioMin = minutosDoHorario($inicio);
        $fimMin = minutosDoHorario($fimInformado);

        $consultaServico->execute([':id' => $servicoId]);
        $servico = $consultaServico->fetch(PDO::FETCH_ASSOC);
        if (!$servico || (int)$servico['duracao'] <= 0) {
            throw new InvalidArgumentException('Um serviço não existe ou está inativo.');
        }
        $consultaProfissional->execute([':id' => $profissionalId]);
        if (!$consultaProfissional->fetchColumn()) {
            throw new InvalidArgumentException('Um profissional não existe ou está inativo.');
        }

        // Duração e valor são definidos pelo cadastro do banco, não pelo navegador.
        $fimEsperado = $inicioMin + (int)$servico['duracao'];
        if ($fimEsperado >= 1440 || $fimMin !== $fimEsperado) {
            throw new InvalidArgumentException('Os horários de um serviço não correspondem à sua duração cadastrada.');
        }

        // Validar expediente da barbearia: 08:00 às 19:00
        if ($inicioMin < 8 * 60 || $fimEsperado > 19 * 60) {
            throw new InvalidArgumentException('O atendimento deve ocorrer entre 08:00 e 19:00.');
        }

        // Impedir dois serviços simultâneos para o mesmo profissional neste formulário.
        foreach ($ocupacoes[$profissionalId] ?? [] as [$ocupacaoInicio, $ocupacaoFim]) {
            if ($inicioMin < $ocupacaoFim && $fimMin > $ocupacaoInicio) {
                throw new InvalidArgumentException('Há dois serviços sobrepostos para o mesmo profissional.');
            }
        }
        $ocupacoes[$profissionalId][] = [$inicioMin, $fimMin];

        $inicioGeral = min($inicioGeral, $inicioMin);
        $fimGeral = max($fimGeral, $fimMin);
        $servicosValidados[] = [
            'servico_id' => $servicoId,
            'profissional_id' => $profissionalId,
            'hora_inicio' => horarioDosMinutos($inicioMin),
            'hora_fim' => horarioDosMinutos($fimMin),
            'valor' => $servico['valor']
        ];
    }

    // Tudo foi validado antes de gravar. Qualquer falha faz rollback.
    $db->beginTransaction();

    // T03: serializa agendamentos dos mesmos profissionais. O bloqueio impede
    // que duas requisições simultâneas validem um horário livre ao mesmo tempo.
    // Ordenar os IDs reduz o risco de deadlock quando há vários profissionais.
    $profissionaisUsados = array_values(array_unique(array_column($servicosValidados, 'profissional_id')));
    sort($profissionaisUsados, SORT_NUMERIC);
    $bloquearProfissional = $db->prepare('SELECT id FROM profissionais WHERE id = :id FOR UPDATE');
    foreach ($profissionaisUsados as $idProfissional) {
        $bloquearProfissional->execute([':id' => $idProfissional]);
        if (!$bloquearProfissional->fetchColumn()) {
            throw new InvalidArgumentException('Um profissional selecionado não está disponível.');
        }
    }

    // Verifica conflitos com agendamentos JÁ existentes no banco. Intervalos
    // [inicio, fim) se sobrepõem quando inicio_existente < novo_fim E
    // fim_existente > novo_inicio. Horários encostados são permitidos.
    // Cancelamentos e faltas não ocupam mais a agenda; pagamentos, sim.
    $consultaConflito = $db->prepare(<<<'SQL'
SELECT a.id AS agendamento_id,
       TIME_FORMAT(s.hora_inicio, '%H:%i') AS inicio_existente,
       TIME_FORMAT(s.hora_fim, '%H:%i') AS fim_existente
FROM agendamento_servicos AS s
JOIN agendamentos AS a ON a.id = s.agendamento_id
WHERE a.data_agendamento = :data
  AND a.status NOT IN ('cancelado', 'faltou')
  AND s.profissional_id = :profissional_id
  AND s.hora_inicio < :novo_fim
  AND s.hora_fim > :novo_inicio
LIMIT 1 FOR UPDATE
SQL);
    foreach ($servicosValidados as $indice => $item) {
        $consultaConflito->execute([
            ':data' => $data,
            ':profissional_id' => $item['profissional_id'],
            ':novo_inicio' => $item['hora_inicio'],
            ':novo_fim' => $item['hora_fim']
        ]);
        $conflito = $consultaConflito->fetch(PDO::FETCH_ASSOC);
        $consultaConflito->closeCursor();
        if ($conflito) {
            throw new InvalidArgumentException(sprintf(
                'Conflito na linha %d: o profissional já tem o agendamento #%d das %s às %s.',
                $indice + 1,
                (int)$conflito['agendamento_id'],
                $conflito['inicio_existente'],
                $conflito['fim_existente']
            ));
        }
    }
    $sqlAgendamento = 'INSERT INTO agendamentos
        (cliente_id, profissional_id, data_agendamento, hora_inicio, hora_fim, status)
        VALUES (:cliente_id, :profissional_id, :data, :inicio, :fim, :status)';
    $novoAgendamento = $db->prepare($sqlAgendamento);
    $novoAgendamento->execute([
        ':cliente_id' => $clienteId,
        // Compatibilidade com a agenda antiga, que usa um único profissional no agendamento.
        ':profissional_id' => $servicosValidados[0]['profissional_id'],
        ':data' => $data,
        ':inicio' => horarioDosMinutos($inicioGeral),
        ':fim' => horarioDosMinutos($fimGeral),
        ':status' => $status
    ]);
    $agendamentoId = (int)$db->lastInsertId();

    $sqlItem = 'INSERT INTO agendamento_servicos
        (agendamento_id, servico_id, profissional_id, hora_inicio, hora_fim, valor)
        VALUES (:agendamento_id, :servico_id, :profissional_id, :inicio, :fim, :valor)';
    $novoItem = $db->prepare($sqlItem);
    foreach ($servicosValidados as $item) {
        $novoItem->execute([
            ':agendamento_id' => $agendamentoId,
            ':servico_id' => $item['servico_id'],
            ':profissional_id' => $item['profissional_id'],
            ':inicio' => $item['hora_inicio'],
            ':fim' => $item['hora_fim'],
            ':valor' => $item['valor']
        ]);
    }
    $db->commit();
    responderAgendamento(200, true, 'Agendamento salvo com sucesso!', $agendamentoId);

} catch (InvalidArgumentException $erro) {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    responderAgendamento(422, false, $erro->getMessage());
} catch (Throwable $erro) {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    error_log('Falha ao salvar agendamento: ' . $erro->getMessage());
    responderAgendamento(500, false, 'Erro interno ao salvar. Confira se a atualização SQL foi aplicada.');
}
