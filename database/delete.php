<?php
require_once 'conexao.php';

$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("DELETE FROM transacao WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: ../index.php");
exit;