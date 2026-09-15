<?php

require_once __DIR__ . '/../classes/Transacao.php';
require_once __DIR__ . '/../classes/Receita.php';
require_once __DIR__ . '/../classes/Despesa.php';
require_once __DIR__ . '/../classes/Diario.php';

class TransacaoRepo
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // calcula o saldo direto no banco (substitui o antigo $carteira->getSaldo())
    public function calcularSaldo()
    {
        $sql = "SELECT 
                SUM(CASE WHEN tipo = 'Receita' THEN valor ELSE 0 END) - 
                SUM(CASE WHEN tipo = 'Despesa' THEN valor ELSE 0 END) AS saldo 
            FROM transacao";

        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['saldo'] ?? 0;
    }

    public function validarSaldoRecorrenteRetroativo(float $valor, string $dataInicial, string $frequencia): bool {
        $saldoAtual = $this->calcularSaldo();
        $hoje = new DateTime('today');
        $proxima = new DateTime($dataInicial);
        $totalNecessario = 0;

        while ($proxima <= $hoje) {
            $totalNecessario += $valor;
            $this->incrementarData($proxima, $frequencia);
        }

        return $saldoAtual >= $totalNecessario;
    }

    public function processarDiarios(): ?array {
        try {
            $sql = "SELECT * FROM transacao WHERE frequencia IS NOT NULL AND frequencia != ''";
            $stmt = $this->pdo->query($sql);
            $rotinas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $hoje = new DateTime('today');

            foreach ($rotinas as $reg) {
                $dataInicial = $reg['proxima_execucao'] ?? $reg['data_transacao'];
                $proximaData = new DateTime($dataInicial);
                $frequencia = $reg['frequencia'];

                while ($proximaData <= $hoje) {
                    $novaDataStr = $proximaData->format('Y-m-d');
                    $valor = (float) $reg['valor'];

                    // se for despesa e o saldo atual for insuficiente, BLOQUEIA e retorna a pendência
                    if (strtolower($reg['tipo']) === 'despesa') {
                        if ($this->calcularSaldo() < $valor) {
                            return [
                                'id' => $reg['id'],
                                'descricao' => $reg['descricao'],
                                'valor' => $valor,
                                'data_cobrança' => $novaDataStr,
                                'saldo_atual' => $this->calcularSaldo()
                            ];
                        }
                    }

                    // Se tiver saldo (ou for receita), efetua o lançamento
                    if (strtolower($reg['tipo']) === 'receita') {
                        $this->adicionarReceita(new Receita(null, $valor, $novaDataStr, $reg['descricao']));
                    } else {
                        $this->adicionarDespesa(new Despesa(null, $valor, $novaDataStr, $reg['descricao']));
                    }

                    $this->incrementarData($proximaData, $frequencia);
                }

                // atualiza a próxima execução
                $updateSql = "UPDATE transacao SET proxima_execucao = :proxima WHERE id = :id";
                $updateStmt = $this->pdo->prepare($updateSql);
                $updateStmt->execute([
                    ':proxima' => $proximaData->format('Y-m-d'),
                    ':id' => $reg['id']
                ]);
            }
        } catch (PDOException $e) {
        }

        return null; // nenhuma pendência travando o sistema
    }

    private function incrementarData(DateTime &$data, string $frequencia): void {
        switch ($frequencia) {
            case 'Diário': $data->modify('+1 day'); break;
            case 'Semanal': $data->modify('+1 week'); break;
            case 'Mensal': $data->modify('+1 month'); break;
            case 'Anual': $data->modify('+1 year'); break;
        }
    }

    public function resolverPendencia(int $id, string $acao): void {
        if ($acao === 'cancelar') {
            // Remove a frequência do registro para parar de cobrar
            $stmt = $this->pdo->prepare("UPDATE transacao SET frequencia = NULL WHERE id = :id");
            $stmt->execute([':id' => $id]);
        } elseif ($acao === 'acumular') {
            // Joga a próxima execução para o próximo mês
            $hoje = new DateTime('today');
            $proxima = $hoje->modify('+1 month')->format('Y-m-d');
            $stmt = $this->pdo->prepare("UPDATE transacao SET proxima_execucao = :proxima WHERE id = :id");
            $stmt->execute([':proxima' => $proxima, ':id' => $id]);
        }
    }

    // C - CREATE: receita
    public function adicionarReceita(Transacao $receita): void {
    $sql = "INSERT INTO transacao (tipo, valor, data_transacao, descricao, frequencia, proxima_execucao) 
            VALUES (:tipo, :valor, :data, :descricao, :frequencia, :proxima_execucao)";

    $frequencia = ($receita instanceof Diario) ? $receita->getFrequencia() : null;
    $proxima = ($receita instanceof Diario) ? $receita->calcularProximaData() : null;

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
        ':tipo' => 'receita',
        ':valor' => $receita->getValor(),
        ':data' => $receita->getData(),
        ':descricao' => $receita->getDescricao(),
        ':frequencia' => $frequencia,
        ':proxima_execucao' => $proxima
    ]);
}

public function adicionarDespesa(Transacao $despesa): void {
    $sql = "INSERT INTO transacao (tipo, valor, data_transacao, descricao, frequencia, proxima_execucao) 
            VALUES (:tipo, :valor, :data, :descricao, :frequencia, :proxima_execucao)";

    $frequencia = ($despesa instanceof Diario) ? $despesa->getFrequencia() : null;
    $proxima = ($despesa instanceof Diario) ? $despesa->calcularProximaData() : null;

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
        ':tipo' => 'despesa',
        ':valor' => $despesa->getValor(),
        ':data' => $despesa->getData(),
        ':descricao' => $despesa->getDescricao(),
        ':frequencia' => $frequencia,
        ':proxima_execucao' => $proxima
    ]);
}

    public function addDiario(TransacaoDiaria $transacao): bool
    {
        if ($transacao->getTipo() === 'despesa' && $transacao->getValor() > $this->calcularSaldo()) {
            $_SESSION['erro'] = "Saldo insuficiente para agendar esta despesa.";
            return false;
        }

        $sql = "INSERT INTO transacoes (tipo, valor, data, descricao, recorrente, frequencia, proxima_execucao) 
                VALUES (:tipo, :valor, :data, :descricao, 1, :frequencia, :proxima_execucao)";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':tipo' => $transacao->getTipo(),
            ':valor' => $transacao->getValor(),
            ':data' => $transacao->getData(),
            ':descricao' => $transacao->getDescricao(),
            ':frequencia' => $transacao->getFrequencia(),
            ':proxima_execucao' => $transacao->getProximaExecucao()
        ]);
    }

    public function buscarPrimeiraId(): int
    {
        $stmt = $this->pdo->query("SELECT MIN(id) FROM transacoes");
        return (int) $stmt->fetchColumn();
    }

    // método auxiliar: o INSERT em si, reaproveitado pelos dois métodos acima
    private function inserir(string $tipo, Transacao $transacao): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO transacoes (tipo, valor, data, descricao) VALUES (:tipo, :valor, :data, :descricao)"
        );

        $stmt->execute([
            'tipo' => $tipo,
            'valor' => $transacao->getValor(),
            'data' => $transacao->getData(),
            'descricao' => $transacao->getDescricao(),
        ]);
    }

    // R - READ (todas, mais recentes primeiro) — usado no index.php pro extrato
    public function listarTodas(): array
    {
        $sql = "SELECT * FROM transacao ORDER BY data_transacao DESC, id DESC";
        $stmt = $this->pdo->query($sql);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $lista = [];
        foreach ($registros as $reg) {
            if (strtolower($reg['tipo']) === 'receita') {
                $lista[] = new Receita($reg['id'], (float) $reg['valor'], $reg['data_transacao'], $reg['descricao']);
            } else {
                $lista[] = new Despesa($reg['id'], (float) $reg['valor'], $reg['data_transacao'], $reg['descricao']);
            }
        }
        return $lista;
    }

    // R - READ (uma única) — usado no editar.php pra preencher o formulário
    public function buscarPorId(int $id): ?Transacao
    {
        $stmt = $this->pdo->prepare("SELECT * FROM transacoes WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return $linha ? $this->hidratar($linha) : null;
    }

    // U - UPDATE — usado no editar.php quando o form de edição é enviado
    public function atualizar($id, $tipo, $valor, $data, $descricao) {
    // Note que usamos 'transacao' (singular) e 'data_transacao' (conforme o Workbench)
    $sql = "UPDATE transacao 
            SET tipo = :tipo, 
                valor = :valor, 
                data_transacao = :data, 
                descricao = :descricao 
            WHERE id = :id";
            
    $stmt = $this->pdo->prepare($sql);
    return $stmt->execute([
        'tipo' => $tipo,
        'valor' => $valor,
        'data' => $data,
        'descricao' => $descricao,
        'id' => $id
    ]);
}

    // D - DELETE — usado no delete.php
    public function deletar(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM transacoes WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }



    // transforma uma linha do banco (array) num objeto Receita ou Despesa
    private function hidratar(array $linha): Transacao
    {
        if ($linha['tipo'] === 'receita') {
            return new Receita($linha['data'], (float) $linha['valor'], $linha['descricao'], (int) $linha['id']);
        }
        return new Despesa($linha['data'], (float) $linha['valor'], $linha['descricao'], (int) $linha['id']);
    }
}
