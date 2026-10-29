<?php
// Banco de desenvolvimento local do PIC I. Não trocar por forja_corte (original).
date_default_timezone_set('America/Sao_Paulo');
class Database {
    private $host = "localhost";
    private $db_name = "forja_corte_dev";  // NOME ATUALIZADO
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch(PDOException $exception) {
            error_log("Erro de conexão com o banco: " . $exception->getMessage());
        }
        return $this->conn;
    }
}

// Função para buscar agendamentos do dia
function getAgendamentosDoDia($data) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return [];
    
    $query = "SELECT a.*, c.nome as cliente_nome, p.nome as profissional_nome 
              FROM agendamentos a 
              LEFT JOIN clientes c ON a.cliente_id = c.id 
              LEFT JOIN profissionais p ON a.profissional_id = p.id
              WHERE a.data_agendamento = :data 
              ORDER BY a.hora_inicio";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":data", $data);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Função para buscar profissionais
function getProfissionais() {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return [];
    
    $query = "SELECT * FROM profissionais WHERE ativo = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Função para buscar serviços
function getServicos() {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return [];
    
    $query = "SELECT * FROM servicos WHERE ativo = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Função para adicionar movimento no caixa
function addMovimentoCaixa($data, $titulo, $forma_pagamento, $valor, $tipo = 'entrada', $cliente_id = null) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return false;
    
    $query = "INSERT INTO caixa (data_movimento, titulo, forma_pagamento, valor, tipo, cliente_id) 
              VALUES (:data, :titulo, :forma_pagamento, :valor, :tipo, :cliente_id)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":data", $data);
    $stmt->bindParam(":titulo", $titulo);
    $stmt->bindParam(":forma_pagamento", $forma_pagamento);
    $stmt->bindParam(":valor", $valor);
    $stmt->bindParam(":tipo", $tipo);
    $stmt->bindParam(":cliente_id", $cliente_id);
    
    return $stmt->execute();
}

// Função para buscar movimentos do caixa
function getMovimentosCaixa($data = null) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return [];
    
    if ($data) {
        $query = "SELECT c.*, cl.nome as cliente_nome 
                  FROM caixa c 
                  LEFT JOIN clientes cl ON c.cliente_id = cl.id 
                  WHERE c.data_movimento = :data 
                  ORDER BY c.data_criacao DESC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":data", $data);
    } else {
        $query = "SELECT c.*, cl.nome as cliente_nome 
                  FROM caixa c 
                  LEFT JOIN clientes cl ON c.cliente_id = cl.id 
                  ORDER BY c.data_movimento DESC, c.data_criacao DESC";
        $stmt = $db->prepare($query);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Função para cadastrar cliente
function cadastrarCliente($nome, $telefone = null, $email = null) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return false;
    
    $query = "INSERT INTO clientes (nome, telefone, email) VALUES (:nome, :telefone, :email)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":nome", $nome);
    $stmt->bindParam(":telefone", $telefone);
    $stmt->bindParam(":email", $email);
    
    return $stmt->execute();
}

// Função para buscar clientes
function getClientes() {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return [];
    
    $query = "SELECT * FROM clientes ORDER BY nome";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Função para buscar cliente por ID
function getClienteById($id) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return null;
    
    $query = "SELECT * FROM clientes WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>