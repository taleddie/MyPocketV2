<?php

require_once __DIR__ . '/../classes/Transacao.php';
require_once __DIR__ . '/../classes/Receita.php';
require_once __DIR__ . '/../classes/Despesa.php';
require_once __DIR__ . '/../classes/Diario.php';

class TransacaoRepo {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // calcula o saldo direto no banco (substitui o antigo $carteira->getSaldo())
    public function calcularSaldo(): float {
        $sql = "SELECT 
                    (SELECT IFNULL(SUM(valor), 0) FROM transacoes WHERE tipo = 'receita' AND recorrente = 0) - 
                    (SELECT IFNULL(SUM(valor), 0) FROM transacoes WHERE tipo = 'despesa' AND recorrente = 0) AS saldo";

        $stmt = $this->pdo->query($sql);
        return (float) $stmt->fetch(PDO::FETCH_ASSOC)['saldo'];
    }

    // C - CREATE: receita
    public function adicionarReceita(Receita $receita): bool {
        $sql = "INSERT INTO transacoes (tipo, valor, data, descricao, recorrente) 
                VALUES ('receita', :valor, :data, :descricao, 0)";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':valor' => $receita->getValor(),
            ':data' => $receita->getData(),
            ':descricao' => $receita->getDescricao()
        ]);
    }

    // C - CREATE: despesa
    public function adicionarDespesa(Despesa $despesa): bool {
        if ($despesa->getValor() > $this->calcularSaldo()) {
            throw new Exception("Saldo insuficiente.");
        }
        
        $sql = "INSERT INTO transacoes (tipo, valor, data, descricao, recorrente) 
                VALUES ('despesa', :valor, :data, :descricao, 0)";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':valor' => $despesa->getValor(),
            ':data' => $despesa->getData(),
            ':descricao' => $despesa->getDescricao()
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

    public function processarDiario(): void
    {
        $sql = "SELECT * FROM transacoes WHERE recorrente = 1 AND proxima_execucao <= CURDATE()";
        $stmt = $this->pdo->query($sql);
        $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($agendamentos as $item) {
            $sqlInsert = "INSERT INTO transacoes (tipo, valor, data, descricao, recorrente) 
                          VALUES (:tipo, :valor, CURDATE(), :descricao, 0)";
            $stmtInsert = $this->pdo->prepare($sqlInsert);
            $stmtInsert->execute([
                ':tipo' => $item['tipo'],
                ':valor' => $item['valor'],
                ':descricao' => '[Recorrente] ' . $item['descricao']
            ]);

            $recorrente = new TransacaoDiaria(
                $item['id'],
                $item['tipo'],
                (float)$item['valor'],
                $item['data'],
                $item['descricao'],
                $item['frequencia'],
                $item['proxima_execucao']
            );

            $novaData = $recorrente->calcularProximaData();

            $sqlUpdate = "UPDATE transacoes SET proxima_execucao = :novaData WHERE id = :id";
            $stmtUpdate = $this->pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':novaData' => $novaData,
                ':id' => $item['id']
            ]);
        }
    }

    public function buscarPrimeiraId(): int{
        $stmt = $this->pdo->query("SELECT MIN(id) FROM transacoes");
        return (int) $stmt->fetchColumn();
    }

    // método auxiliar: o INSERT em si, reaproveitado pelos dois métodos acima
    private function inserir(string $tipo, Transacao $transacao): void {
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
    public function listarTodas(): array {
        $sql = "SELECT * FROM transacoes WHERE recorrente = 0 ORDER BY data DESC, id DESC";
        $stmt = $this->pdo->query($sql);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $lista = [];
        foreach ($registros as $reg) {
            if ($reg['tipo'] === 'receita') {
                $lista[] = new Receita($reg['id'], (float)$reg['valor'], $reg['data'], $reg['descricao']);
            } else {
                $lista[] = new Despesa($reg['id'], (float)$reg['valor'], $reg['data'], $reg['descricao']);
            }
        }
        return $lista;
    }

    // R - READ (uma única) — usado no editar.php pra preencher o formulário
    public function buscarPorId(int $id): ?Transacao {
        $stmt = $this->pdo->prepare("SELECT * FROM transacoes WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return $linha ? $this->hidratar($linha) : null;
    }

    // U - UPDATE — usado no editar.php quando o form de edição é enviado
    public function atualizar(int $id, string $tipo, float $valor, string $data, string $descricao): void {
        $stmt = $this->pdo->prepare(
            "UPDATE transacoes SET tipo = :tipo, valor = :valor, data = :data, descricao = :descricao WHERE id = :id"
        );

        $stmt->execute([
            'tipo' => $tipo,
            'valor' => $valor,
            'data' => $data,
            'descricao' => $descricao,
            'id' => $id,
        ]);
    }

    // D - DELETE — usado no delete.php
    public function deletar(int $id): void {
        $stmt = $this->pdo->prepare("DELETE FROM transacoes WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    

    // transforma uma linha do banco (array) num objeto Receita ou Despesa
    private function hidratar(array $linha): Transacao {
        if ($linha['tipo'] === 'receita') {
            return new Receita($linha['data'], (float) $linha['valor'], $linha['descricao'], (int) $linha['id']);
        }
        return new Despesa($linha['data'], (float) $linha['valor'], $linha['descricao'], (int) $linha['id']);
    }
}
