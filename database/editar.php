<?php

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/TransacaoRepo.php';

session_start();

$repo = new TransacaoRepo($pdo);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if (!$id) {
    header('Location: ../index.php');
    exit;
}

$transacaoAtual = $repo->buscarPorId($id);
$transacao = $transacaoAtual;

if (!$transacaoAtual) {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // pegar os novos dados vindos do formulário
    $novoTipo = $_POST['tipo'] ?? '';
    $novoValor = (float) ($_POST['valor'] ?? 0);

    if ($novoTipo !== 'receita' && $novoTipo !== 'despesa') {
        $_SESSION['erro'] = "Selecione obrigatoriamente se é Receita ou Despesa.";
        header("Location: editar.php?id={$id}");
        exit;
    }

    $saldoAtual = $repo->calcularSaldo();
    $saldoAjustado = $saldoAtual;

    // U - UPDATE: estornar o valor antigo para calcular o saldo disponível
    if ($transacaoAtual->getTipo() === 'despesa') {
        $saldoAjustado += $transacaoAtual->getValor();
    } elseif ($transacaoAtual->getTipo() === 'receita') {
        $saldoAjustado -= $transacaoAtual->getValor();
    }

    // saldo insuficiente
    if ($novoTipo === 'despesa' && $novoValor > $saldoAjustado) {
        $_SESSION['erro'] = "Saldo insuficiente para esta alteração.";
        header("Location: editar.php?id={$id}");
        exit;
    }

    // saldo insuficiente para despesa na primeira transação
    if ($novoTipo === 'despesa' && $id === $repo->buscarPrimeiraId()) {
        $_SESSION['erro'] = "Saldo insuficiente para esta alteração.";
        header("Location: editar.php?id={$id}");
        exit;
    }

    // U - UPDATE: salvar alterações
    $tipo = $novoTipo;
    $valor = $novoValor;
    $data = $_POST['data'] ?? '';
    $descricao = trim($_POST['descricao'] ?? '');

    if (!empty($descricao) && $valor > 0 && !empty($tipo)) {
        $repo->atualizar($id, $tipo, $valor, $data, $descricao);
        $_SESSION['sucesso'] = "Transação atualizada com sucesso.";
        header('Location: ../index.php');
        exit;
    }
}

$hoje = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <title>Editar transação</title>
    <style>
        body {
            background-color: #2c2c2c;
        }

        .activeGreen {
            box-shadow: 0 0 0 3px #146c43 !important;
        }

        .activeRed {
            box-shadow: 0 0 0 3px #b02a37 !important;
        }

        #card-form {
            background: #ffffff9a;
            border-radius: 25px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.71);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.84);
        }
    </style>
</head>
<body>

    <div class="container py-5 d-flex justify-content-center">
        <div class="col-md-6">
            <div class="p-4" id="card-form">
                <h2 class="mb-4 text-center fw-bold">Editar transação</h2>

                <?php if (isset($_SESSION['erro'])): ?>
                    <div class="alert alert-danger rounded-5 text-center">
                        <?= $_SESSION['erro']; unset($_SESSION['erro']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="id" value="<?= $transacao->getId() ?>">

                    <div class="d-flex justify-content-center gap-3 mb-3">
                        <input type="hidden" id="tipo" name="tipo" value="<?= $transacao->getTipo() ?>" required>
                        <button type="button" id="btnReceita" class="btn btn-success rounded-5 <?= $transacao->getTipo() === 'receita' ? 'activeGreen' : '' ?>">Receita</button>
                        <button type="button" id="btnDespesa" class="btn btn-danger rounded-5 <?= $transacao->getTipo() === 'despesa' ? 'activeRed' : '' ?>">Despesa</button>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Valor</label>
                        <input type="number" name="valor" class="form-control rounded-5" min="0.01" step="0.01"
                            value="<?= $transacao->getValor() ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Data</label>
                        <input type="date" name="data" class="form-control rounded-5" max="<?= $hoje ?>"
                            value="<?= $transacao->getData() ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <input type="text" name="descricao" class="form-control rounded-5"
                            value="<?= htmlspecialchars($transacao->getDescricao()) ?>" required>
                    </div>

                    <div class="d-flex justify-content-center gap-3 mt-4">
                        <button type="submit" class="btn btn-dark rounded-5">Salvar</button>
                        <a href="../index.php" class="btn btn-outline-dark rounded-5">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const btnReceita = document.getElementById("btnReceita");
        const btnDespesa = document.getElementById("btnDespesa");
        const tipo = document.getElementById("tipo");

        document.querySelector("form").addEventListener("submit", (e) => {
            if (!tipo.value || (tipo.value !== "receita" && tipo.value !== "despesa")) {
                e.preventDefault();
                alert("Selecione Receita ou Despesa.");
            }
        });

        btnReceita.addEventListener("click", () => {
            tipo.value = "receita";
            btnReceita.classList.add("activeGreen");
            btnDespesa.classList.remove("activeRed");
        });

        btnDespesa.addEventListener("click", () => {
            tipo.value = "despesa";
            btnDespesa.classList.add("activeRed");
            btnReceita.classList.remove("activeGreen");
        });
    </script>

</body>
</html>