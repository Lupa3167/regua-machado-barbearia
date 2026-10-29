<?php
require_once __DIR__ . '/Config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (($_POST['acao'] ?? '') !== 'cadastrar_cliente') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ação inválida.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $nome = trim((string)($_POST['nome'] ?? ''));
    $telefone = trim((string)($_POST['telefone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    if ($nome === '' || strlen($nome) > 100 || strlen($telefone) > 20 || strlen($email) > 100 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Informe nome válido (até 100 caracteres), telefone e email válidos.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        $ok = cadastrarCliente($nome, $telefone, $email);
        if (!$ok) http_response_code(500);
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Cliente cadastrado com sucesso!' : 'Erro ao cadastrar cliente.'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('Erro ao cadastrar cliente: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro interno ao cadastrar cliente.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
$clientes = getClientes();
