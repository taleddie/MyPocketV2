<?php

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/TransacaoRepo.php';

session_start();

$repo = new TransacaoRepo($pdo);

$id = $_GET['id'] ?? null;

if ($id) {
    $repo->deletar((int) $id);
    $_SESSION['sucesso'] = "Transação excluída com sucesso.";
}

header('Location: ../index.php');
exit;
