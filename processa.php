<?php

require_once __DIR__ . '/classes/Receita.php';
require_once __DIR__ . '/classes/Despesa.php';
require_once __DIR__ . '/database/conexao.php';
require_once __DIR__ . '/database/TransacaoRepo.php';

session_start();

$repo = new TransacaoRepo($pdo);

$tipo = $_POST['tipo'] ?? '';
$valor = (float) ($_POST['valor'] ?? 0);
$data = $_POST['data'] ?? '';
$descricao = $_POST['descricao'] ?? '';

// tipo validação
if (empty($tipo) || !in_array($tipo, ['receita', 'despesa'])) {
    $_SESSION['erro'] = "A seleção do tipo (Receita ou Despesa) é obrigatória.";
    header("Location: index.php");
    exit;
}

try {
    if ($tipo === 'receita') {
        $transacao = new Receita($data, $valor, $descricao);
        $repo->adicionarReceita($transacao);
        $_SESSION['sucesso'] = "Transação cadastrada com sucesso.";
    } elseif ($tipo === 'despesa') {

        $saldoAtual = $repo->calcularSaldo();

        if ($valor > $saldoAtual) {
            $_SESSION['erro'] = "Saldo insuficiente para realizar esta despesa.";
            header("Location: index.php");
            exit;
        }

        $transacao = new Despesa($data, $valor, $descricao);
        $repo->adicionarDespesa($transacao);
        $_SESSION['sucesso'] = "Transação cadastrada com sucesso.";
    }
} catch (Exception $e) {
    $_SESSION['erro'] = $e->getMessage();
}

header("Location: index.php");
exit;