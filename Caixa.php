<?php
// PIC I - Caixa manual e recebimento de agendamentos.
// Um agendamento só é marcado como pago quando o lançamento é gravado.
session_start();
require_once __DIR__ . '/Config.php';
date_default_timezone_set('America/Sao_Paulo');

function hCaixa($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function flashCaixa(string $tipo, string $mensagem): void {
    $_SESSION['caixa_flash'] = ['tipo' => $tipo, 'mensagem' => $mensagem];
}

function redirecionarCaixa(string $data): void {
    header('Location: Caixa.php?data=' . rawurlencode($data), true, 303);
    exit;
}

function dataCaixaValida($data): bool {
    if (!is_string($data) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data, $p)) return false;
    return checkdate((int)$p[2], (int)$p[3], (int)$p[1]);
}

$hoje = date('Y-m-d');
$dataExibida = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['data_tela'] ?? $hoje) : ($_GET['data'] ?? $hoje);
if (!dataCaixaValida($dataExibida)) $dataExibida = $hoje;

if (empty($_SESSION['caixa_csrf'])) {
    $_SESSION['caixa_csrf'] = bin2hex(random_bytes(32));
}

$formasPagamento = ['dinheiro' => 'Dinheiro', 'debito' => 'Débito', 'credito' => 'Crédito', 'pix' => 'PIX'];
$database = new Database();
$db = $database->getConnection();
$erroBanco = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$db instanceof PDO) throw new RuntimeException('Conexão indisponível.');
        $token = $_POST['csrf_token'] ?? null;
        if (!is_string($token) || !hash_equals($_SESSION['caixa_csrf'], $token)) {
            throw new InvalidArgumentException('Formulário expirado. Atualize a página e tente novamente.');
        }
        $acao = $_POST['acao'] ?? '';
        if ($acao === 'receber_agendamento') {
            $id = filter_var($_POST['agendamento_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            $forma = $_POST['pagamento'] ?? '';
            if ($id === false || $id === null || !is_string($forma) || !array_key_exists($forma, $formasPagamento)) {
                throw new InvalidArgumentException('Selecione um agendamento e a forma de pagamento.');
            }

            // Bloqueio do agendamento evita cobranças simultâneas via este formulário.
            $db->beginTransaction();
            $consulta = $db->prepare('SELECT a.id, a.cliente_id, a.status
                                      FROM agendamentos a WHERE a.id = :id FOR UPDATE');
            $consulta->execute([':id' => $id]);
            $agendamento = $consulta->fetch(PDO::FETCH_ASSOC);
            if (!$agendamento) throw new InvalidArgumentException('Agendamento não encontrado.');
            if (in_array($agendamento['status'], ['pago', 'cancelado', 'faltou'], true)) {
                throw new InvalidArgumentException('Esse agendamento está pago, cancelado ou marcado como falta.');
            }

            $jaExiste = $db->prepare('SELECT id FROM caixa WHERE agendamento_id = :id LIMIT 1');
            $jaExiste->execute([':id' => $id]);
            if ($jaExiste->fetchColumn()) {
                throw new InvalidArgumentException('Já existe lançamento no caixa para este agendamento.');
            }

            $soma = $db->prepare('SELECT COUNT(*) AS quantidade, COALESCE(SUM(valor), 0) AS total
                                  FROM agendamento_servicos WHERE agendamento_id = :id');
            $soma->execute([':id' => $id]);
            $totais = $soma->fetch(PDO::FETCH_ASSOC);
            if (!$totais || (int)$totais['quantidade'] < 1 || (float)$totais['total'] <= 0) {
                throw new InvalidArgumentException('Agendamento sem serviços ou sem valor para cobrança.');
            }

            // Usa valores armazenados no banco; não aceita preço enviado pelo navegador.
            $inserir = $db->prepare('INSERT INTO caixa
                (data_movimento, titulo, forma_pagamento, valor, tipo, agendamento_id, cliente_id)
                VALUES (:data, :titulo, :forma, :valor, :tipo, :agendamento_id, :cliente_id)');
            $inserir->execute([
                ':data' => $hoje,
                ':titulo' => 'Pagamento agendamento #' . $id,
                ':forma' => $forma,
                ':valor' => $totais['total'],
                ':tipo' => 'entrada',
                ':agendamento_id' => $id,
                ':cliente_id' => $agendamento['cliente_id']
            ]);
            $mudarStatus = $db->prepare("UPDATE agendamentos SET status = 'pago' WHERE id = :id");
            $mudarStatus->execute([':id' => $id]);
            $db->commit();
            flashCaixa('success', 'Agendamento nº ' . $id . ' recebido: R$ ' . number_format((float)$totais['total'], 2, ',', '.') . '. Status atualizado para Pago.');

        } elseif ($acao === 'manual') {
            $data = $_POST['data'] ?? null;
            $titulo = $_POST['titulo'] ?? null;
            $forma = $_POST['pagamento'] ?? '';
            $tipo = $_POST['tipo'] ?? '';
            $valor = $_POST['valor'] ?? null;
            if (!dataCaixaValida($data) || !is_string($titulo) || trim($titulo) === '' || strlen($titulo) > 100
                || !is_string($forma) || !array_key_exists($forma, $formasPagamento)
                || !in_array($tipo, ['entrada', 'saida'], true) || !is_scalar($valor)
                || !is_numeric($valor) || !is_finite((float)$valor) || (float)$valor <= 0) {
                throw new InvalidArgumentException('Preencha corretamente os dados do lançamento manual.');
            }
            $inserir = $db->prepare('INSERT INTO caixa
                (data_movimento, titulo, forma_pagamento, valor, tipo)
                VALUES (:data, :titulo, :forma, :valor, :tipo)');
            $inserir->execute([
                ':data' => $data, ':titulo' => trim($titulo), ':forma' => $forma,
                ':valor' => number_format((float)$valor, 2, '.', ''), ':tipo' => $tipo
            ]);
            flashCaixa('success', 'Lançamento manual registrado com sucesso.');
            $dataExibida = $data;

        } elseif ($acao === 'excluir_manual') {
            $id = filter_var($_POST['movimento_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            if ($id === false || $id === null) throw new InvalidArgumentException('Lançamento inválido.');
            $db->beginTransaction();
            $consulta = $db->prepare('SELECT agendamento_id FROM caixa WHERE id = :id FOR UPDATE');
            $consulta->execute([':id' => $id]);
            $movimento = $consulta->fetch(PDO::FETCH_ASSOC);
            if (!$movimento) throw new InvalidArgumentException('Lançamento não encontrado.');
            if ($movimento['agendamento_id'] !== null) {
                throw new InvalidArgumentException('Pagamentos vinculados a agendamentos não podem ser excluídos por esta tela.');
            }
            $excluir = $db->prepare('DELETE FROM caixa WHERE id = :id AND agendamento_id IS NULL');
            $excluir->execute([':id' => $id]);
            $db->commit();
            flashCaixa('success', 'Lançamento manual excluído.');
        } else {
            throw new InvalidArgumentException('Ação não reconhecida.');
        }
    } catch (InvalidArgumentException $e) {
        if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
        flashCaixa('warning', $e->getMessage());
    } catch (Throwable $e) {
        if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
        error_log('Falha na operação do caixa: ' . $e->getMessage());
        flashCaixa('danger', 'Falha ao processar a operação. Confira o banco e tente novamente.');
    }
    redirecionarCaixa($dataExibida);
}

$flash = $_SESSION['caixa_flash'] ?? null;
unset($_SESSION['caixa_flash']);
$movimentos = [];
$pendentes = [];
$total = 0.0;
try {
    if (!$db instanceof PDO) throw new RuntimeException('Conexão indisponível.');
    $movimentos = getMovimentosCaixa($dataExibida);
    foreach ($movimentos as $mov) {
        $total += $mov['tipo'] === 'entrada' ? (float)$mov['valor'] : -(float)$mov['valor'];
    }
    $consultaPendentes = $db->query(<<<'SQL'
SELECT a.id, a.data_agendamento, a.status, COALESCE(c.nome, 'Cliente não identificado') AS cliente_nome,
       COUNT(i.id) AS quantidade_servicos, SUM(i.valor) AS total
FROM agendamentos a
JOIN agendamento_servicos i ON i.agendamento_id = a.id
LEFT JOIN clientes c ON c.id = a.cliente_id
WHERE a.status NOT IN ('pago', 'cancelado', 'faltou')
  AND NOT EXISTS (SELECT 1 FROM caixa cx WHERE cx.agendamento_id = a.id)
GROUP BY a.id, a.data_agendamento, a.status, c.nome
ORDER BY a.data_agendamento DESC, a.id DESC
SQL);
    $pendentes = $consultaPendentes->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Falha ao consultar caixa: ' . $e->getMessage());
    $erroBanco = 'Não foi possível carregar o caixa. Confira sua conexão com o banco de desenvolvimento.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Caixa — Régua & Machado</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .bg-roxo { background: #6f42c1 !important; }
    .btn-roxo { background: #6f42c1; color: #fff; border-color: #6f42c1; }
    .btn-roxo:hover { background: #5a34a3; color: #fff; }
    .entrada { color: green; font-weight: bold; }
    .saida { color: #ba1212; font-weight: bold; }
    @media print { .nao-imprimir, .navbar { display: none !important; } }
  </style>
</head>
<body>
  <nav class="navbar navbar-dark bg-roxo mb-4">
    <div class="container">
      <a class="navbar-brand" href="Pagina_inicial.html">← Voltar ao Menu</a>
      <span class="navbar-text text-light">Controle de Caixa</span>
    </div>
  </nav>
  <main class="container mb-5">
    <h1 class="mb-3">Controle de Caixa</h1>
    <?php if ($flash): ?>
      <div class="alert alert-<?= hCaixa($flash['tipo']) ?>" role="alert"><?= hCaixa($flash['mensagem']) ?></div>
    <?php endif; ?>
    <?php if ($erroBanco): ?>
      <div class="alert alert-danger" role="alert"><?= hCaixa($erroBanco) ?></div>
    <?php endif; ?>

    <section class="card mb-4 nao-imprimir">
      <div class="card-body">
        <h2 class="h5">Receber agendamento</h2>
        <p class="text-muted small">O valor é calculado a partir dos serviços salvos no banco. Ao receber, o agendamento fica com status Pago.</p>
        <?php if (!$pendentes): ?>
          <p class="mb-0">Nenhum agendamento disponível para cobrança.</p>
        <?php else: ?>
          <form method="post" action="Caixa.php" class="row g-3 align-items-end">
            <input type="hidden" name="csrf_token" value="<?= hCaixa($_SESSION['caixa_csrf']) ?>">
            <input type="hidden" name="acao" value="receber_agendamento">
            <input type="hidden" name="data_tela" value="<?= hCaixa($dataExibida) ?>">
            <div class="col-md-8">
              <label class="form-label" for="agendamento_id">Agendamento pendente</label>
              <select class="form-select" name="agendamento_id" id="agendamento_id" required>
                <option value="">Selecione...</option>
                <?php foreach ($pendentes as $a): ?>
                  <option value="<?= (int)$a['id'] ?>">#<?= (int)$a['id'] ?> — <?= hCaixa($a['cliente_nome']) ?> — <?= hCaixa(date('d/m/Y', strtotime($a['data_agendamento']))) ?> — R$ <?= number_format((float)$a['total'], 2, ',', '.') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label" for="forma_agendamento">Forma de pagamento</label>
              <select class="form-select" name="pagamento" id="forma_agendamento" required>
                <?php foreach ($formasPagamento as $chave => $rotulo): ?>
                  <option value="<?= hCaixa($chave) ?>"><?= hCaixa($rotulo) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <button type="submit" class="btn btn-roxo w-100">Registrar pagamento</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </section>

    <section class="card mb-4 nao-imprimir">
      <div class="card-body">
        <h2 class="h5">Lançamento manual</h2>
        <form method="post" action="Caixa.php" class="row g-3">
          <input type="hidden" name="csrf_token" value="<?= hCaixa($_SESSION['caixa_csrf']) ?>">
          <input type="hidden" name="acao" value="manual">
          <input type="hidden" name="data_tela" value="<?= hCaixa($dataExibida) ?>">
          <div class="col-md-3">
            <label class="form-label">Data</label>
            <input type="date" name="data" class="form-control" value="<?= hCaixa($dataExibida) ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Título</label>
            <select name="titulo" class="form-select" required>
              <option value="">Selecione...</option>
              <?php foreach (['Corte Masculino', 'Corte Feminino', 'Barba', 'Barboterapia', 'Hidratação', 'Produtos', 'Aluguel', 'Salários', 'Outro'] as $titulo): ?>
                <option value="<?= hCaixa($titulo) ?>"><?= hCaixa($titulo) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Forma de pagamento</label>
            <select name="pagamento" class="form-select">
              <?php foreach ($formasPagamento as $chave => $rotulo): ?>
                <option value="<?= hCaixa($chave) ?>"><?= hCaixa($rotulo) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Valor</label>
            <input type="number" name="valor" class="form-control" required min="0.01" step="0.01" placeholder="0.00">
          </div>
          <div class="col-md-2">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select">
              <option value="entrada">Entrada</option><option value="saida">Saída</option>
            </select>
          </div>
          <div class="col-12"><button type="submit" class="btn btn-roxo">Adicionar lançamento</button></div>
        </form>
      </div>
    </section>

    <section class="nao-imprimir mb-3">
      <form method="get" action="Caixa.php" class="row g-2 align-items-end">
        <div class="col-auto"><label for="dataFiltro" class="form-label">Consultar movimentos do dia</label><input type="date" class="form-control" id="dataFiltro" name="data" value="<?= hCaixa($dataExibida) ?>"></div>
        <div class="col-auto"><button type="submit" class="btn btn-outline-secondary">Mostrar</button></div>
        <div class="col-auto"><a href="Caixa.php" class="btn btn-outline-secondary">Hoje</a></div>
        <div class="col-auto"><button type="button" class="btn btn-outline-primary" onclick="window.print()">Imprimir / PDF</button></div>
      </form>
    </section>

    <h2 class="h5">Movimentos de <?= hCaixa(date('d/m/Y', strtotime($dataExibida))) ?></h2>
    <table class="table table-bordered table-responsive align-middle">
      <thead class="table-light">
        <tr><th>Data</th><th>Título</th><th>Forma de pagamento</th><th>Valor</th><th>Tipo</th><th>Ações</th></tr>
      </thead>
      <tbody>
        <?php if (!$movimentos): ?>
          <tr><td colspan="6" class="text-center">Nenhum lançamento encontrado nessa data.</td></tr>
        <?php else: ?>
          <?php foreach ($movimentos as $mov): ?>
            <tr>
              <td><?= hCaixa(date('d/m/Y', strtotime($mov['data_movimento']))) ?></td>
              <td><?= hCaixa($mov['titulo']) ?>
                <?php if ($mov['agendamento_id'] !== null): ?>
                  <span class="badge bg-info text-dark">Agendamento #<?= (int)$mov['agendamento_id'] ?></span>
                <?php endif; ?>
              </td>
              <td><?= hCaixa($formasPagamento[$mov['forma_pagamento']] ?? $mov['forma_pagamento']) ?></td>
              <td class="<?= $mov['tipo'] === 'entrada' ? 'entrada' : 'saida' ?>">R$ <?= number_format((float)$mov['valor'], 2, ',', '.') ?></td>
              <td><?= $mov['tipo'] === 'entrada' ? '<span class="badge bg-success">Entrada</span>' : '<span class="badge bg-danger">Saída</span>' ?></td>
              <td>
                <?php if ($mov['agendamento_id'] === null): ?>
                  <form method="post" action="Caixa.php" onsubmit="return confirm('Excluir este lançamento manual?');">
                    <input type="hidden" name="csrf_token" value="<?= hCaixa($_SESSION['caixa_csrf']) ?>">
                    <input type="hidden" name="acao" value="excluir_manual">
                    <input type="hidden" name="movimento_id" value="<?= (int)$mov['id'] ?>">
                    <input type="hidden" name="data_tela" value="<?= hCaixa($dataExibida) ?>">
                    <button type="submit" class="btn btn-sm btn-danger nao-imprimir">Excluir</button>
                  </form>
                <?php else: ?>
                  <span class="text-muted small">Vinculado</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
    <div class="alert <?= $total >= 0 ? 'alert-success' : 'alert-danger' ?>">
      <strong>SALDO DO DIA: R$ <?= number_format($total, 2, ',', '.') ?></strong>
    </div>
  </main>
</body>
</html>
