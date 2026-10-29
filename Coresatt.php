<?php
// PIC I - Agenda diária: mostra cada serviço no horário e profissional corretos.
// Requer a atualização do banco DEV aplicada no pacote de salvamento.
session_start();
require_once __DIR__ . '/Config.php';
date_default_timezone_set('America/Sao_Paulo');

function h($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function dataValida($data): bool {
    if (!is_string($data) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data, $p)) return false;
    return checkdate((int)$p[2], (int)$p[3], (int)$p[1]);
}

function horarioMinutos($hora): ?int {
    if (!is_string($hora) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $hora, $p)) return null;
    return (int)$p[1] * 60 + (int)$p[2];
}

function horaCurta($hora): string {
    return $hora ? substr((string)$hora, 0, 5) : '--:--';
}

$statusOpcoes = [
    'agendado' => 'Agendado',
    'confirmado' => 'Confirmado',
    'aguardando' => 'Aguardando',
    'em_atendimento' => 'Em Atendimento',
    'finalizado' => 'Finalizado',
    'pago' => 'Pago',
    'cancelado' => 'Cancelado',
    'faltou' => 'Faltou',
];
$statusCores = [
    'agendado' => 'info',
    'confirmado' => 'primary',
    'aguardando' => 'warning',
    'em_atendimento' => 'success',
    'finalizado' => 'secondary',
    'pago' => 'success',
    'cancelado' => 'danger',
    'faltou' => 'dark',
];

$hoje = date('Y-m-d');
$dataSelecionada = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['data'] ?? $hoje) : ($_GET['data'] ?? $hoje);
if (!dataValida($dataSelecionada)) $dataSelecionada = $hoje;

if (empty($_SESSION['agenda_csrf'])) {
    $_SESSION['agenda_csrf'] = bin2hex(random_bytes(32));
}

$database = new Database();
$db = $database->getConnection();
$erroBanco = null;

// Alteração manual do status; não lança movimento financeiro no caixa.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$db instanceof PDO) throw new RuntimeException('Banco indisponível.');
        if (!isset($_POST['csrf_token']) || !is_string($_POST['csrf_token']) ||
            !hash_equals($_SESSION['agenda_csrf'], $_POST['csrf_token'])) {
            throw new InvalidArgumentException('Formulário expirado; atualize a página e tente novamente.');
        }
        $id = filter_var($_POST['agendamento_id'] ?? null, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]);
        $status = $_POST['status'] ?? null;
        if ($id === false || $id === null || !is_string($status) || !array_key_exists($status, $statusOpcoes)) {
            throw new InvalidArgumentException('Agendamento ou status inválido.');
        }
        if ($status === 'pago') {
            throw new InvalidArgumentException('Registre o pagamento pelo Caixa para marcar como Pago.');
        }

        $db->beginTransaction();
        $verificar = $db->prepare('SELECT status FROM agendamentos WHERE id = :id AND data_agendamento = :data FOR UPDATE');
        $verificar->execute([':id' => $id, ':data' => $dataSelecionada]);
        $statusAtual = $verificar->fetchColumn();
        if ($statusAtual === false) throw new InvalidArgumentException('Agendamento não encontrado nessa data.');
        if ($statusAtual === 'pago') {
            throw new InvalidArgumentException('Um agendamento pago não pode ser alterado pela agenda.');
        }
        // Protege também contra um status antigo/inconsistente com pagamento no caixa.
        $pagamento = $db->prepare('SELECT id FROM caixa WHERE agendamento_id = :id LIMIT 1');
        $pagamento->execute([':id' => $id]);
        if ($pagamento->fetchColumn()) {
            throw new InvalidArgumentException('Já existe um pagamento no caixa para este agendamento.');
        }

        $alterar = $db->prepare('UPDATE agendamentos SET status = :status WHERE id = :id AND data_agendamento = :data');
        $alterar->execute([':status' => $status, ':id' => $id, ':data' => $dataSelecionada]);
        $db->commit();
        $_SESSION['agenda_mensagem'] = ['tipo' => 'success', 'texto' => 'Status do agendamento nº ' . $id . ' atualizado para ' . $statusOpcoes[$status] . '.'];
    } catch (InvalidArgumentException $e) {
        if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
        $_SESSION['agenda_mensagem'] = ['tipo' => 'warning', 'texto' => $e->getMessage()];
    } catch (Throwable $e) {
        if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
        error_log('Erro ao atualizar status da agenda: ' . $e->getMessage());
        $_SESSION['agenda_mensagem'] = ['tipo' => 'danger', 'texto' => 'Não foi possível atualizar o status. Verifique a conexão com o banco.'];
    }
    header('Location: Coresatt.php?data=' . rawurlencode($dataSelecionada), true, 303);
    exit;
}

$mensagem = $_SESSION['agenda_mensagem'] ?? null;
unset($_SESSION['agenda_mensagem']);

// Agrupar por (hora de início do SERVIÇO, profissional), não pelo agendamento pai.
$porHorario = [];
$foraDoHorario = [];
$semProfissional = [];
$profissionais = [];
$contagem = 0;
try {
    if (!$db instanceof PDO) throw new RuntimeException('Sem conexão com o banco.');
    // Mostrar também profissionais inativos com agendamentos antigos.
    $profissionais = $db->query('SELECT id, nome FROM profissionais ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

    $sql = <<<'SQL'
SELECT a.id AS agendamento_id,
       a.status,
       COALESCE(c.nome, 'Cliente não identificado') AS cliente_nome,
       a.profissional_id AS profissional_principal,
       a.hora_inicio AS inicio_principal,
       a.hora_fim AS fim_principal,
       item.id AS item_id,
       item.profissional_id AS profissional_servico,
       item.hora_inicio AS inicio_servico,
       item.hora_fim AS fim_servico,
       s.nome AS servico_nome
FROM agendamentos a
LEFT JOIN clientes c ON c.id = a.cliente_id
LEFT JOIN agendamento_servicos item ON item.agendamento_id = a.id
LEFT JOIN servicos s ON s.id = item.servico_id
WHERE a.data_agendamento = :data
ORDER BY COALESCE(item.hora_inicio, a.hora_inicio), a.id, item.id
SQL;
    $consulta = $db->prepare($sql);
    $consulta->execute([':data' => $dataSelecionada]);
    foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $item['profissional_id'] = $item['profissional_servico'] ?: $item['profissional_principal'];
        $item['inicio'] = $item['inicio_servico'] ?: $item['inicio_principal'];
        $item['fim'] = $item['fim_servico'] ?: $item['fim_principal'];
        $item['servico_nome'] = $item['servico_nome'] ?: 'Serviço não informado';
        $minutos = horarioMinutos($item['inicio']);
        ++$contagem;
        if (!$item['profissional_id'] || $minutos === null) {
            $semProfissional[] = $item;
        } elseif ($minutos < 8 * 60 || $minutos >= 19 * 60) {
            $foraDoHorario[] = $item;
        } else {
            $chaveHora = sprintf('%02d:00', intdiv($minutos, 60));
            $porHorario[$chaveHora][(int)$item['profissional_id']][] = $item;
        }
    }
} catch (Throwable $e) {
    error_log('Erro ao consultar agenda: ' . $e->getMessage());
    $erroBanco = 'Não foi possível carregar a agenda. Confira se a atualização SQL do pacote anterior foi aplicada ao banco DEV.';
}

$nomesProfissionais = array_column($profissionais, 'nome', 'id');
$horaAtualMinutos = (int)date('H') * 60 + (int)date('i');
$dataExibida = DateTimeImmutable::createFromFormat('!Y-m-d', $dataSelecionada);
$dataFormatada = $dataExibida ? $dataExibida->format('d/m/Y') : $dataSelecionada;

// Um único cartão de serviço por linha, com ação de status no AGENDAMENTO pai.
function exibirCartao(array $item, array $statusOpcoes, array $statusCores, string $data): void {
    $id = (int)$item['agendamento_id'];
    $status = (string)$item['status'];
    $rotulo = $statusOpcoes[$status] ?? $status;
    $cor = $statusCores[$status] ?? 'secondary';
    ?>
    <div class="cartao-agendamento text-start mb-2 p-2 rounded border">
      <div class="fw-bold small"><?= h($item['cliente_nome']) ?></div>
      <div class="small"><?= h($item['servico_nome']) ?></div>
      <div class="small text-nowrap"><?= h(horaCurta($item['inicio'])) ?>–<?= h(horaCurta($item['fim'])) ?></div>
      <div class="d-flex gap-1 align-items-center flex-wrap my-1">
        <span class="badge bg-<?= h($cor) ?>"><?= h($rotulo) ?></span>
        <span class="text-muted" style="font-size:.72rem">#<?= $id ?></span>
      </div>
      <form method="post" action="Coresatt.php" class="d-flex gap-1 flex-wrap align-items-center mt-2">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['agenda_csrf']) ?>">
        <input type="hidden" name="data" value="<?= h($data) ?>">
        <input type="hidden" name="agendamento_id" value="<?= $id ?>">
        <label class="visually-hidden" for="status-<?= h($item['item_id'] ?? 'a' . $id) ?>">Alterar status do agendamento <?= $id ?></label>
        <select class="form-select form-select-sm status-select" <?= $status === 'pago' ? 'disabled' : '' ?> id="status-<?= h($item['item_id'] ?? 'a' . $id) ?>" name="status">
          <?php foreach ($statusOpcoes as $valor => $nome): ?>
            <?php if ($valor === 'pago' && $status !== 'pago') continue; ?>
            <option value="<?= h($valor) ?>" <?= $status === $valor ? 'selected' : '' ?>><?= h($nome) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-outline-primary" <?= $status === 'pago' ? 'disabled' : '' ?>>Atualizar</button>
      </form>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agenda dos Profissionais</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .passado { background-color: #f8d7da !important; }
    .atual { background-color: #d4edda !important; }
    .futuro { background-color: #cce5ff !important; }
    .bg-roxo { background-color: #6f42c1 !important; }
    .cartao-agendamento { background-color: #e7f3ff; border-left: 3px solid #007bff !important; min-width: 140px; }
    .status-select { max-width: 155px; font-size: .77rem; }
    .agenda-table th, .agenda-table td { min-width: 145px; vertical-align: top; }
    .agenda-table th:first-child, .agenda-table td:first-child { min-width: 85px; }
  </style>
</head>
<body>
  <nav class="navbar navbar-dark bg-roxo mb-4">
    <div class="container">
      <a class="navbar-brand" href="Pagina_inicial.html">← Voltar ao Menu</a>
      <span class="navbar-text text-light">Agenda dos Profissionais — <?= h($dataFormatada) ?></span>
    </div>
  </nav>

  <div class="container mb-5">
    <h2 class="mb-3">Agenda de <?= $dataSelecionada === $hoje ? 'Hoje' : h($dataFormatada) ?> <small class="text-muted fs-6">(<?= h(date('H:i')) ?>)</small></h2>

    <form class="row g-2 align-items-end mb-3" method="get" action="Coresatt.php">
      <div class="col-auto">
        <label for="dataAgenda" class="form-label">Consultar data</label>
        <input type="date" class="form-control" id="dataAgenda" name="data" value="<?= h($dataSelecionada) ?>" required>
      </div>
      <div class="col-auto"><button class="btn btn-primary" type="submit">Mostrar agenda</button></div>
      <div class="col-auto"><a class="btn btn-outline-secondary" href="Coresatt.php">Hoje</a></div>
    </form>

    <?php if ($mensagem): ?>
      <div class="alert alert-<?= h($mensagem['tipo']) ?>" role="alert"><?= h($mensagem['texto']) ?></div>
    <?php endif; ?>
    <?php if ($erroBanco): ?>
      <div class="alert alert-danger" role="alert"><?= h($erroBanco) ?></div>
    <?php endif; ?>

    <div class="alert alert-info">
      <strong>Legenda:</strong>
      <span class="badge bg-success">Horário atual</span>
      <span class="badge bg-primary">Futuro</span>
      <span class="badge bg-danger">Passado</span>
      <span class="badge bg-info">Agendado</span>
      <div class="small mt-1">Cada cartão mostra um serviço no horário do profissional responsável. Alterar o status não registra pagamento no caixa.</div>
    </div>

    <?php if (!$erroBanco && !$profissionais): ?>
      <div class="alert alert-warning">Não existem profissionais cadastrados.</div>
    <?php endif; ?>

    <?php if (!$erroBanco): ?>
    <div class="table-responsive">
      <table class="table table-bordered text-center agenda-table">
        <thead class="table-dark"><tr><th>Horário</th>
          <?php foreach ($profissionais as $profissional): ?>
            <th><?= h($profissional['nome']) ?></th>
          <?php endforeach; ?>
        </tr></thead>
        <tbody>
        <?php for ($hora = 8; $hora <= 18; $hora++):
            $horaStr = sprintf('%02d:00', $hora);
            $slotMinutos = $hora * 60;
            $classe = $dataSelecionada < $hoje ? 'passado' : ($dataSelecionada > $hoje ? 'futuro' :
                ($horaAtualMinutos >= $slotMinutos + 60 ? 'passado' : ($horaAtualMinutos >= $slotMinutos ? 'atual' : 'futuro')));
        ?>
          <tr>
            <td class="<?= $classe ?>"><strong><?= $horaStr ?></strong></td>
            <?php foreach ($profissionais as $profissional): ?>
              <td class="<?= $classe ?>">
                <?php $itens = $porHorario[$horaStr][(int)$profissional['id']] ?? []; ?>
                <?php if (!$itens): ?>–<?php else: ?>
                  <?php foreach ($itens as $item) exibirCartao($item, $statusOpcoes, $statusCores, $dataSelecionada); ?>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endfor; ?>
        </tbody>
      </table>
    </div>

    <?php if ($foraDoHorario): ?>
      <h5 class="mt-4">Atendimentos fora da grade (08h–19h)</h5>
      <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-2">
        <?php foreach ($foraDoHorario as $item): ?>
          <div class="col"><div class="small fw-bold mb-1">Profissional: <?= h($nomesProfissionais[$item['profissional_id']] ?? 'Não encontrado') ?></div>
            <?php exibirCartao($item, $statusOpcoes, $statusCores, $dataSelecionada); ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($semProfissional): ?>
      <h5 class="mt-4">Atendimentos sem profissional ou horário definido</h5>
      <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-2">
        <?php foreach ($semProfissional as $item): ?>
          <div class="col"><?php exibirCartao($item, $statusOpcoes, $statusCores, $dataSelecionada); ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <p class="text-muted small mt-3"><?= $contagem ?> serviço(s)/registro(s) exibido(s) na data selecionada.</p>
    <?php endif; ?>
  </div>
</body>
</html>
