<?php

require_once __DIR__ . '/classes/Transacao.php';
require_once __DIR__ . '/classes/Receita.php';
require_once __DIR__ . '/classes/Despesa.php';
require_once __DIR__ . '/classes/Diario.php';
require_once __DIR__ . '/database/conexao.php';
require_once __DIR__ . '/database/TransacaoRepo.php';

session_start();

$repo = new TransacaoRepo($pdo);

// 1. Trata a resolução de pendências vindas dos botões do aviso/modal
if (isset($_POST['acao_pendencia'])) {
    $id = (int) ($_POST['id_pendencia'] ?? 0);
    $acao = $_POST['acao_pendencia'] ?? ''; // 'cancelar' ou 'acumular'

    if ($id > 0 && in_array($acao, ['cancelar', 'acumular'])) {
        $repo->resolverPendencia($id, $acao);
        $_SESSION['sucesso'] = "Pendência resolvida com sucesso.";
    }

    header("Location: index.php");
    exit;
}

$tipo = $_POST['tipo'] ?? '';
$valor = (float) ($_POST['valor'] ?? 0);
$data = $_POST['data'] ?? '';
$descricao = $_POST['descricao'] ?? '';
$frequencia = $_POST['frequencia'] ?? null;

// 2. Normaliza a frequência informada
if (!empty($frequencia)) {
    $freqFormatada = mb_strtolower(trim($frequencia));
    if (in_array($freqFormatada, ['diario', 'diário', 'diaria', 'diária'])) {
        $frequencia = 'Diário';
    } elseif (in_array($freqFormatada, ['semanal', 'semana'])) {
        $frequencia = 'Semanal';
    } elseif (in_array($freqFormatada, ['mensal', 'mes'])) {
        $frequencia = 'Mensal';
    } elseif (in_array($freqFormatada, ['anual', 'ano'])) {
        $frequencia = 'Anual';
    }
}

// 3. Validação do tipo de transação
if (empty($tipo) || !in_array($tipo, ['receita', 'despesa'])) {
    $_SESSION['erro'] = "A seleção do tipo (Receita ou Despesa) é obrigatória.";
    header("Location: index.php");
    exit;
}

try {
    if ($tipo === 'receita') {
        if (!empty($frequencia)) {
            $transacao = new Diario(null, 'receita', $valor, $data, $descricao, $frequencia);
        } else {
            $transacao = new Receita(null, $valor, $data, $descricao);
        }

        $repo->adicionarReceita($transacao);
        $_SESSION['sucesso'] = "Receita cadastrada com sucesso.";

    } elseif ($tipo === 'despesa') {

        // Impede novas despesas se existir uma cobrança recorrente travada por falta de saldo
        if ($repo->processarDiarios() !== null) {
            $_SESSION['erro'] = "Existe uma cobrança pendente. Cadastre uma Receita suficiente para liberar novas despesas.";
            header("Location: index.php");
            exit;
        }

        if (!empty($frequencia)) {
            // Verifica se o saldo atual é suficiente para cobrir os dias retroativos acumulados
            if (!$repo->validarSaldoRecorrenteRetroativo($valor, $data, $frequencia)) {
                $_SESSION['erro'] = "Saldo insuficiente para agendar esta despesa recorrente desde a data informada.";
                header("Location: index.php");
                exit;
            }
            $transacao = new Diario(null, 'despesa', $valor, $data, $descricao, $frequencia);
        } else {
            // Validação simples para despesa única
            if ($valor > $repo->calcularSaldo()) {
                $_SESSION['erro'] = "Saldo insuficiente para realizar esta despesa.";
                header("Location: index.php");
                exit;
            }
            $transacao = new Despesa(null, $valor, $data, $descricao);
        }

        $repo->adicionarDespesa($transacao);
        $_SESSION['sucesso'] = "Despesa cadastrada com sucesso.";
    }
} catch (Exception $e) {
    $_SESSION['erro'] = $e->getMessage();
}

header("Location: index.php");
exit;