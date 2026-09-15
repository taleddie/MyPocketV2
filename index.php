<?php

require_once __DIR__ . '/database/conexao.php';
require_once __DIR__ . '/database/TransacaoRepo.php';

session_start();

$repo = new TransacaoRepo($pdo);

$repo->processarDiarios();
$saldo = $repo->calcularSaldo();
$transacoes = $repo->listarTodas();
$pendencia = $repo->processarDiarios();

// captura e limpa mensagens de feedback
$erro = $_SESSION['erro'] ?? null;
$sucesso = $_SESSION['sucesso'] ?? null;
unset($_SESSION['erro'], $_SESSION['sucesso']);

$hoje = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <title>My Pocket</title>

    <style>
        .activeGreen {
            box-shadow: 0 0 0 3px #146c43 !important;
        }

        .activeRed {
            box-shadow: 0 0 0 3px #b02a37 !important;
        }

        .activeBlue {
            box-shadow: 0 0 0 3px #053c8f !important;
        }

        .bg {

            background-color: #2c2c2c;
            /*
            background-image: url(../img/bank.png);
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            width: 100%;
            */
        }

        #card-saldo,
        #card-form,
        #card-extrato,
        #card-pendencia {
            background: #ffffff9a;
            border-radius: 25px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.71);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.84);
        }

        #header {
            width: 100%;
            height: 100px;
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
        }

        @media (min-width: 768px) {
            #card-extrato { 
                max-height: 630px;
                overflow-y: auto;
            }
        }

        .acoes-transacao a {
            text-decoration: none;
            font-size: 0.85rem;
            color: #000000;
        }

        .acoes-transacao img {
            margin-top: -2px;
            width: 15px;
        }

    </style>

</head>

<body>

    <!-- background div -->
    <div class="bg">

    <header class="d-flex justify-content-center align-items-center" id="header">
        <h1 class="">MyPocket</h1>
    </header>

    <div class="container py-5">
        <div class="gx-5 row justify-content-center">
            <div class="col justify-content-center">

                <!-- saldo -->
                <div class="p-3 mb-2 text-dark" id="card-saldo">
                    <h2 class="m-3 text-center fw-bold">Saldo</h2>
                    <div class="saldo-valor">
                        <p class="text-center fs-4">R$ <?= number_format($saldo, 2, ',', '.') ?></p>
                    </div>
                </div>

                <!-- alertas -->
                <?php if ($erro): ?>
                    <div class="alert alert-danger rounded-4 py-2 px-3" role="alert">
                        <?= htmlspecialchars($erro) ?>
                    </div>
                <?php endif; ?>

                <?php if ($sucesso): ?>
                    <div class="alert alert-success rounded-4 py-2 px-3" role="alert">
                        <?= htmlspecialchars($sucesso) ?>
                    </div>
                <?php endif; ?>

                <!-- alerta especifico para pendencias -->
                <?php if ($pendencia): ?>
                    <div class="p-3 mb-3 text-dark border border-danger border-2" id="card-pendencia" style="background-color: #ffdddd;">
                        <h4 class="text-danger fw-bold text-center">Cobrança Pendente!</h4>
                        <p class="text-center mb-2">
                            A despesa <strong><?= htmlspecialchars($pendencia['descricao']) ?></strong> de 
                            <strong>R$ <?= number_format($pendencia['valor'], 2, ',', '.') ?></strong> tentou ser cobrada em 
                            <?= date('d/m/Y', strtotime($pendencia['data_cobrança'])) ?>, mas seu saldo atual é de 
                            <strong>R$ <?= number_format($pendencia['saldo_atual'], 2, ',', '.') ?></strong>.
                        </p>
                        <p class="text-center text-muted small">
                            Cadastre uma <strong>Receita</strong> para cobrir o valor ou escolha uma das opções abaixo:
                        </p>
                        <form action="processa.php" method="POST" id="formPendencia" class="d-flex justify-content-center gap-2">
                            <input type="hidden" name="id_pendencia" value="<?= $pendencia['id'] ?>">
                            <button type="submit" name="acao_pendencia" value="cancelar" class="btn btn-sm btn-outline-secondary rounded-4">Cancelar este diário</button>
                            <button type="submit" name="acao_pendencia" value="acumular" class="btn btn-sm btn-outline-secondary rounded-4">Pagar na próx. cobrança</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!--form-->
                <div class="p-3 mb-2 text-dark" id="card-form">
                    <form action="processa.php" method="POST">
                        <h2 class="m-3 text-center fw-bold">Nova transação</h2>

                        <!-- botoes receita, despesa ou diario + bloqueio de despesa-->
                        <div class="d-flex justify-content-center gap-3 ">
                            <input type="hidden" id="tipo" name="tipo">
                            <input type="hidden" id="tipo_diario" name="tipo_diario">

                            <button type="button" id="btnReceita" class="btn btn-success rounded-5">Receita</button>
                            <button type="button" id="btnDespesa" class="btn btn-danger rounded-5" <?= $pendencia ? 'disabled title="Bloqueado por pendência de saldo"' : '' ?>>Despesa</button>

                            <button type="button" id="btnDiario" class="btn btn-primary rounded-5">Diário</button>
                        </div>

                        <!-- area de definir frequencia do diario (some quando nao esta selecionado) -->
                        <div class="mb-3 mt-3 mx-5 d-none" id="campo-frequencia">
                            <label for="frequencia" class="form-label">Período</label>
                            <select name="frequencia" id="frequencia" class="rounded-5 form-select shadow">
                                <option value="">Selecione o período...</option>
                                <option value="diario">Diário</option>
                                <option value="semanal">Semanal</option>
                                <option value="mensal">Mensal</option>
                                <option value="anual">Anual</option>
                            </select>
                        </div>

                        <!-- valor -->
                        <div class="mb-3 mt-3 mx-5">
                            <label for="formGroupExampleInput">Valor</label>
                            <input type="number" name="valor" class="rounded-5 form-control shadow"
                                id="formGroupExampleInput" min="0.01" step="0.01" placeholder="0,00" required>
                        </div>

                        <!-- data -->
                        <div class="mb-3 mt-3 mx-5">
                            <label for="data" class="form-label">Data</label>
                            <input type="date" class="rounded-5 form-control shadow" max="<?= $hoje ?>" name="data" required>
                        </div>

                        <!-- descrição -->
                        <div class="mb-3 mt-3 mx-5">
                            <label for="formGroupExampleInput">Descrição</label>
                            <input type="text" name="descricao" class="rounded-5 form-control shadow"
                                id="formGroupExampleInput" placeholder="Conta de luz, aluguel, etc..." required>
                        </div>

                        <!-- botao cadastrar -->
                        <div class="d-flex justify-content-center gap-3 ">
                            <button type="submit" class="btn btn-dark rounded-5 mb-3 mt-3">
                                Cadastrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- extrato --->
            <div class="col-md-5 mb-2">
                <div class="p-4" id="card-extrato">
                <?php if (empty($transacoes)): ?>
                <p class="text-muted text-center mt-5">Nenhuma transação cadastrada ainda.</p>
                <?php else: ?>
                <div class="list-group list-group-flush">

                    <?php foreach ($transacoes as $info): ?>
                    <div class="d-flex justify-content-between align-items-center border rounded p-3 mb-2">

                        <div>
                            <span class="badge <?= $info->getTipo() === 'Entrada' ? 'bg-success' : 'bg-danger' ?>">
                                <?= $info->getTipo() ?>
                            </span>

                            <div class="mt-1">
                                <?= htmlspecialchars($info->getDescricao()) ?>
                            </div>

                            <div class="acoes-transacao mt-1">
                                <a href="database/editar.php?id=<?= $info->getId() ?>"><img src="img/editar.png" alt="Editar"> Editar</a> |
                                <a href="database/delete.php?id=<?= $info->getId() ?>" onclick="return confirm('Excluir esta transação?')"><img src="img/excluir.png" alt="Excluir"> Excluir</a>
                            </div>
                        </div>

                        <div class="text-end">
                            <div class="fw-bold">
                                R$ <?= number_format($info->getValor(), 2, ',', '.') ?>
                            </div>

                            <small class="text-muted">
                                <?= date('d/m/Y', strtotime($info->getData())) ?> <!-- altera o formato da data -->
                            </small>
                        </div>

                    </div>
                    <?php endforeach ?>
                    <?php endif ?>
                </div>
            </div>
            
            </div>
        </div>

    </div>

        <script>
            const btnReceita = document.getElementById("btnReceita");
            const btnDespesa = document.getElementById("btnDespesa");
            const btnDiario = document.getElementById("btnDiario");
            const inputTipo = document.getElementById("tipo");
            const inputTipoDiario = document.getElementById("tipo_diario");
            const campofrequencia = document.getElementById("campo-frequencia");
            const selectfrequencia = document.getElementById("frequencia");

            let operacaoSelecionada = "";
            let diarioAtivo = false;

            document.querySelector("#card-form form").addEventListener("submit", (e) => {
                if (!inputTipo.value) {
                    e.preventDefault();
                    alert("Selecione Receita, Despesa ou Diário.");
                }
            });

            btnReceita.addEventListener("click", () => {
                operacaoSelecionada = "receita";
                btnReceita.classList.add("activeGreen");
                if (btnDespesa) btnDespesa.classList.remove("activeRed");

                atualizarTipoEVisual();
            });

            if (btnDespesa) {
                btnDespesa.addEventListener("click", () => {
                    operacaoSelecionada = "despesa";
                    btnDespesa.classList.add("activeRed");
                    btnReceita.classList.remove("activeGreen");

                    atualizarTipoEVisual();
                });
            }

            btnDiario.addEventListener("click", () => {
                diarioAtivo = !diarioAtivo;
                if (diarioAtivo) {
                    btnDiario.classList.add("activeBlue");
                    campofrequencia.classList.remove("d-none");
                    selectfrequencia.required = true;
                } else {
                    btnDiario.classList.remove("activeBlue");
                    campofrequencia.classList.add("d-none");
                    selectfrequencia.required = false;
                }
                atualizarTipoEVisual();
            });

            function atualizarTipoEVisual() {
                if (diarioAtivo) {
                    inputTipo.value = operacaoSelecionada;
                    inputTipoDiario.value = "diario"; 
                } else {
                    inputTipo.value = operacaoSelecionada;
                    inputTipoDiario.value = "";
                }
            }

        </script>

</body>

</html>