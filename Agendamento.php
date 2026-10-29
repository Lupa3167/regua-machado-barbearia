<?php
require_once __DIR__ . '/Config.php';
date_default_timezone_set('America/Sao_Paulo');

$clientes = getClientes();
$servicos = getServicos();
$profissionais = getProfissionais();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agendamento</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #fafafa; }
    .cliente-box {
      background-color: #f3e8ff;
      color: #6f42c1;
      padding: 10px;
      margin-top: 10px;
      border-radius: 6px;
      border: none;
    }
    .btn-roxo {
      background-color: #6f42c1;
      border-color: #6f42c1;
      color: #fff;
    }
    .btn-roxo:hover {
      background-color: #5a34a3;
      border-color: #5a34a3;
      color: #fff;
    }
    .date-input { width: 160px; }
    .status-btns .btn { margin: 4px 4px 0 0; }
    .status-btns .btn.active {
      box-shadow: 0 0 0 0.2rem rgba(111, 66, 193, 0.25);
    }
    .service-table th, .service-table td {
      vertical-align: middle;
      text-align: center;
    }
    .bg-roxo { background-color: #6f42c1 !important; }
    .cliente-list {
      max-height: 200px;
      overflow-y: auto;
    }
  </style>
</head>
<body>
  <!-- Navegação -->
  <nav class="navbar navbar-dark bg-roxo mb-4">
    <div class="container">
      <a class="navbar-brand" href="Pagina_inicial.html">← Voltar ao Menu</a>
      <span class="navbar-text">Sistema de Agendamento</span>
    </div>
  </nav>

  <div class="container">
  <h4 class="mb-3">Cliente:</h4>
  <div class="row g-2 mb-3">
    <div class="col-md-3">
      <input type="text" id="nomeCliente" class="form-control" placeholder="Nome do Cliente" required>
    </div>
    <div class="col-md-3">
      <input type="text" id="telefoneCliente" class="form-control" placeholder="Telefone (opcional)">
    </div>
    <div class="col-md-3">
      <input type="email" id="emailCliente" class="form-control" placeholder="Email (opcional)">
    </div>
    <div class="col-md-3">
      <button id="cadastrarBtn" class="btn btn-roxo w-100">Cadastrar Cliente</button>
    </div>
  </div>

  <!-- Lista de clientes existentes -->
  <div class="mb-3">
    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#listaClientes">
      Clientes Cadastrados (<?= count($clientes) ?>)
    </button>
    <div class="collapse mt-2" id="listaClientes">
      <div class="card card-body cliente-list">
        <?php if (empty($clientes)): ?>
          <p class="text-muted">Nenhum cliente cadastrado.</p>
        <?php else: ?>
          <?php foreach($clientes as $cliente): ?>
            <div class="d-flex justify-content-between align-items-center border-bottom py-1">
              <div>
                <strong><?= htmlspecialchars($cliente['nome']) ?></strong>
                <?php if ($cliente['telefone']): ?>
                  <small class="text-muted"> - <?= htmlspecialchars($cliente['telefone']) ?></small>
                <?php endif; ?>
              </div>
              <button type="button" class="btn btn-sm btn-roxo btn-selecionar-cliente" 
                      data-id="<?= (int)$cliente['id'] ?>" 
                      data-nome="<?= htmlspecialchars($cliente['nome'], ENT_QUOTES, 'UTF-8') ?>">
                Selecionar
              </button>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div id="clienteInfo"></div>

  <div class="mb-3">
    <label class="form-label">Data:</label>
    <input type="date" id="dataAgendamento" class="form-control date-input" value="<?= date('Y-m-d') ?>">
  </div>

  <!-- Tabela de Serviços -->
  <h5 class="mb-2">Serviços</h5>
  <div class="table-responsive">
    <table class="table table-bordered service-table" id="serviceTable">
      <thead class="table-light">
        <tr>
          <th>Serviço</th>
          <th>Profissional</th>
          <th>Tempo (min)</th>
          <th>Início</th>
          <th>Fim</th>
          <th>Valor (R$)</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody id="serviceBody">
        <tr>
           <td>  
              <select class="form-select">
                <?php foreach ($servicos as $servico): ?>
                  <option
                    value="<?= (int)$servico['id'] ?>"
                    data-duracao="<?= (int)$servico['duracao'] ?>"
                    data-valor="<?= htmlspecialchars((string)$servico['valor'], ENT_QUOTES, 'UTF-8') ?>"
                  >
                    <?= htmlspecialchars($servico['nome']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
           </td>
          <td>
            <select class="form-select">
              <?php foreach ($profissionais as $profissional): ?>
                <option value="<?= (int)$profissional['id'] ?>">
                  <?= htmlspecialchars($profissional['nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input type="number" class="form-control tempo" value="40" readonly></td>
          <td><input type="time" class="form-control inicio"></td>
          <td><input type="time" class="form-control fim" readonly></td>
          <td><input type="number" class="form-control valor" value="40" min="0" step="0.01" readonly></td>
          <td></td>
        </tr>
      </tbody>
    </table>
  </div>

  <button id="addService" class="btn btn-sm btn-roxo mb-3">Adicionar serviço</button>

  <div class="mb-3">
    <strong>Total (R$): <span id="totalValor">0,00</span></strong>
  </div>

  <div class="mb-3">
    <label class="form-label d-block">Status:</label>
    <div class="status-btns btn-group flex-wrap" role="group" id="statusGroup">
      <button data-status="agendado" type="button" class="btn btn-outline-primary active">Agendado</button>
      <button data-status="confirmado" type="button" class="btn btn-outline-info">Confirmado</button>
      <button data-status="aguardando" type="button" class="btn btn-outline-warning">Aguardando</button>
      <button data-status="em_atendimento" type="button" class="btn btn-outline-success">Em Atendimento</button>
      <button data-status="finalizado" type="button" class="btn btn-outline-secondary">Finalizado</button>
      <button data-status="cancelado" type="button" class="btn btn-outline-secondary">Cancelado</button>
      <button data-status="faltou" type="button" class="btn btn-outline-dark">Faltou</button>
    </div>
    <div class="form-text">O status Pago é definido no Caixa, depois de registrar o recebimento.</div>
  </div>

  <div class="d-flex justify-content-end mt-3">
    <button type="button" id="cancelarAgendamento" class="btn btn-secondary me-2">Cancelar</button>
    <button type="button" class="btn btn-roxo" id="salvarAgendamento">Salvar Agendamento</button>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Cadastrar cliente via AJAX
  document.getElementById('cadastrarBtn').addEventListener('click', function() {
    const nome = document.getElementById('nomeCliente').value.trim();
    const telefone = document.getElementById('telefoneCliente').value.trim();
    const email = document.getElementById('emailCliente').value.trim();
    
    if(!nome){
      alert('Por favor, informe o nome do cliente.');
      return;
    }

    // Mostrar loading
    const btn = this;
    btn.innerHTML = 'Cadastrando...';
    btn.disabled = true;

    // Fazer requisição AJAX
    fetch('Cad_Cli.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `acao=cadastrar_cliente&nome=${encodeURIComponent(nome)}&telefone=${encodeURIComponent(telefone)}&email=${encodeURIComponent(email)}`
    })
    .then(response => response.json())
    .then(data => {
      if(data.success) {
        alert(data.message);
        const info = document.getElementById('clienteInfo');
        info.innerHTML = '<div class="cliente-box"><strong></strong></div>';
        info.querySelector('strong').textContent = `Cliente: ${nome}`;
        
        // Limpar campos
        document.getElementById('nomeCliente').value = '';
        document.getElementById('telefoneCliente').value = '';
        document.getElementById('emailCliente').value = '';
        
        // Recarregar a página para atualizar a lista
        setTimeout(() => {
          window.location.reload();
        }, 1000);
      } else {
        alert('Erro: ' + data.message);
      }
    })
    .catch(error => {
      console.error('Erro:', error);
      alert('Erro ao cadastrar cliente.');
    })
    .finally(() => {
      btn.innerHTML = 'Cadastrar Cliente';
      btn.disabled = false;
    });
  });

  let clienteId = null;

  // Selecionar cliente da lista
  document.querySelectorAll('.btn-selecionar-cliente').forEach(btn => {
    btn.addEventListener('click', function() {
      const nome = this.getAttribute('data-nome');
      clienteId = Number(this.getAttribute('data-id'));

      console.log('Cliente selecionado:', nome);
      console.log('ID do cliente:', clienteId);
      const info = document.getElementById('clienteInfo');
      info.innerHTML = '<div class="cliente-box"><strong></strong>' +
        '</div>';
      info.querySelector('strong').textContent = `Cliente: ${nome}`;
    });
  });

  const serviceBody = document.getElementById('serviceBody');
  const totalSpan = document.getElementById('totalValor');

  function atualizarTotal() {
    let total = 0;
    serviceBody.querySelectorAll('.valor').forEach(v => {
      total += parseFloat(v.value) || 0;
    });
    totalSpan.textContent = total.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
  }

  function atualizarHorarioFim(linha) {
  const inicio = linha.querySelector('.inicio').value;
  const duracao = Number(linha.querySelector('.tempo').value);
  const campoFim = linha.querySelector('.fim');

  if (!inicio || !Number.isFinite(duracao) || duracao <= 0) {
    campoFim.value = '';
    return;
  }

  const [horas, minutos] = inicio.split(':').map(Number);

  const totalMinutos = horas * 60 + minutos + duracao;

  // Por enquanto, não permitir ultrapassar a meia-noite
  if (totalMinutos >= 1440) {
    campoFim.value = '';
    return;
  }

  const horaFinal = String(Math.floor(totalMinutos / 60)).padStart(2, '0');
  const minutoFinal = String(totalMinutos % 60).padStart(2, '0');

  campoFim.value = `${horaFinal}:${minutoFinal}`;
  }
  
  // Atualizar duração e preço ao selecionar um serviço
  serviceBody.addEventListener('change', function(event) {
    // Apenas alterações na coluna Serviço
    if (!event.target.matches('td:first-child select')) {
      return;
    }

    const linha = event.target.closest('tr');
    const opcao = event.target.selectedOptions[0];
    const duracao = Number(opcao.dataset.duracao);
    const valor = Number(opcao.dataset.valor);

    linha.querySelector('.tempo').value = duracao;
    linha.querySelector('.valor').value = valor.toFixed(2);

    atualizarHorarioFim(linha);
    atualizarTotal();
  });

  // Recalcular o horário final quando o início ou a duração mudar
  serviceBody.addEventListener('input', function(event) {
    if (event.target.matches('.inicio, .tempo')) {
      atualizarHorarioFim(event.target.closest('tr'));
    }
  });

  // Preencher duração, preço e término já na primeira abertura da página
  serviceBody.querySelectorAll('tr').forEach(linha => {
    const select = linha.querySelector('td:first-child select');
    select.dispatchEvent(new Event('change', { bubbles: true }));
  });

  serviceBody.addEventListener('input', e => {
    if (e.target.classList.contains('valor')) atualizarTotal();
  });

  // Remover linha
  serviceBody.addEventListener('click', e => {
    if (e.target.classList.contains('remover')) {
      e.target.closest('tr').remove();
      atualizarTotal();
    }
  });

  // Adicionar nova linha de serviço
  document.getElementById('addService').addEventListener('click', () => {

    // Copia a primeira linha da tabela
    const modelo = serviceBody.querySelector('tr');
    const row = modelo.cloneNode(true);

    // Reinicia as seleções
    row.querySelectorAll('select').forEach(select => {
      select.selectedIndex = 0;
    });

    // Nova linha começa sem horário; duração e preço virão do serviço escolhido
    row.querySelector('.inicio').value = '';
    row.querySelector('.fim').value = '';

    // Adiciona o botão de remover
    row.querySelector('td:last-child').innerHTML =
      '<button type="button" class="btn btn-sm btn-danger remover">Remover</button>';

    // Adiciona a nova linha e preenche preço/duração pelo serviço selecionado
    serviceBody.appendChild(row);
    row.querySelector('td:first-child select').dispatchEvent(
      new Event('change', { bubbles: true })
    );
  });

  // Seleção exclusiva de status
  const statusGroup = document.getElementById('statusGroup');
  statusGroup.querySelectorAll('button').forEach(btn => {
    btn.addEventListener('click', () => {
      statusGroup.querySelectorAll('button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
  });

  // Salvar o agendamento com todos os serviços em uma única requisição.
  document.getElementById('salvarAgendamento').addEventListener('click', async function() {
    if (!Number.isInteger(clienteId) || clienteId <= 0) {
      alert('Selecione um cliente cadastrado antes de salvar.');
      return;
    }

    const data = document.getElementById('dataAgendamento').value;
    if (!data) {
      alert('Selecione a data do agendamento.');
      return;
    }

    const linhas = Array.from(serviceBody.querySelectorAll('tr'));
    if (linhas.length === 0) {
      alert('Adicione pelo menos um serviço.');
      return;
    }

    const parametros = new URLSearchParams();
    parametros.set('cliente_id', String(clienteId));
    parametros.set('data_agendamento', data);
    parametros.set('status', statusGroup.querySelector('button.active')?.dataset.status || 'agendado');

    for (const [indice, linha] of linhas.entries()) {
      const servicoId = Number(linha.querySelector('td:first-child select').value);
      const profissionalId = Number(linha.querySelector('td:nth-child(2) select').value);
      const inicio = linha.querySelector('.inicio').value;
      const fim = linha.querySelector('.fim').value;
      const duracao = Number(linha.querySelector('.tempo').value);

      if (!servicoId || !profissionalId || !inicio || !fim || !Number.isFinite(duracao) || duracao <= 0) {
        alert(`Complete serviço, profissional e horário de início da linha ${indice + 1}.`);
        return;
      }

      const prefixo = `servicos[${indice}]`;
      parametros.set(`${prefixo}[servico_id]`, String(servicoId));
      parametros.set(`${prefixo}[profissional_id]`, String(profissionalId));
      parametros.set(`${prefixo}[hora_inicio]`, inicio);
      parametros.set(`${prefixo}[hora_fim]`, fim);
    }

    const botao = this;
    botao.disabled = true;
    botao.textContent = 'Salvando...';
    try {
      const resposta = await fetch('Processar_agendamento.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: parametros.toString()
      });
      const resultado = await resposta.json();
      if (!resposta.ok || !resultado.success) {
        throw new Error(resultado.message || 'Não foi possível salvar o agendamento.');
      }
      alert(`${resultado.message} Número: ${resultado.agendamento_id}`);
      // Um novo cadastro só começa depois que o anterior foi confirmado no banco.
      window.location.reload();
    } catch (erro) {
      console.error('Falha ao salvar agendamento:', erro);
      alert('Não foi possível salvar: ' + erro.message);
    } finally {
      botao.disabled = false;
      botao.textContent = 'Salvar Agendamento';
    }
  });

  // Cancelar um formulário não salvo: restaura os valores iniciais da página.
  document.getElementById('cancelarAgendamento').addEventListener('click', () => {
    if (confirm('Descartar os dados deste agendamento?')) window.location.reload();
  });

  // Verificar dados carregados no banco
  console.log('Serviços encontrados:', <?= count($servicos) ?>);
  console.log('Profissionais encontrados:', <?= count($profissionais) ?>);
</script>
</body>
</html>