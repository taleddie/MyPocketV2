<?php

require_once __DIR__ . '/../classes/Transacao.php';
require_once __DIR__ . '/../classes/Receita.php';
require_once __DIR__ . '/../classes/Despesa.php';

class TransacaoRepo {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function buscarPorId(int $id): ?Transacao {
        $sql = "SELECT * FROM transacao WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dados) return null;

        return $this->hidratar($dados);
    }

    public function salvar(Transacao $transacao): bool {
        $sql = "INSERT INTO transacao (descricao, valor, data_transacao, tipo) 
                VALUES (:descricao, :valor, :data_transacao, :tipo)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':descricao' => $transacao->getDescricao(),
            ':valor' => $transacao->getValor(),
            ':data_transacao' => $transacao->getData(),
            ':tipo' => $transacao->getTipo()
        ]);
    }

    public function atualizar(Transacao $transacao): bool {
        $sql = "UPDATE transacao 
                SET descricao = :descricao, 
                    valor = :valor, 
                    data_transacao = :data, 
                    tipo = :tipo 
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':descricao' => $transacao->getDescricao(),
            ':valor' => $transacao->getValor(),
            ':data' => $transacao->getData(),
            ':tipo' => $transacao->getTipo(),
            ':id' => $transacao->getId()
        ]);
    }

    public function calcularSaldo(): float {
        $sql = "SELECT 
                    SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) - 
                    SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) as saldo 
                FROM transacao";
        $stmt = $this->pdo->query($sql);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float) ($resultado['saldo'] ?? 0);
    }

    public function buscarPrimeiraId(): int {
        $sql = "SELECT id FROM transacao ORDER BY id ASC LIMIT 1";
        $stmt = $this->pdo->query($sql);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($resultado['id'] ?? 0);
    }

    public function processarDiarios(): void {
        // Rotina para transações diárias se houver
    }

    public function listarTodas(): array {
        return $this->buscarTodas();
    }

    public function buscarTodas(): array {
        $sql = "SELECT * FROM transacao ORDER BY data_transacao DESC, id DESC";
        $stmt = $this->pdo->query($sql);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $transacoes = [];
        foreach ($registros as $dados) {
            $transacoes[] = $this->hidratar($dados);
        }

        return $transacoes;
    }

    private function hidratar(array $dados): Transacao {
        if ($dados['tipo'] === 'despesa') {
            return new Despesa(
                (int)$dados['id'],
                (float)$dados['valor'],
                $dados['data_transacao'] ?? $dados['data'] ?? '',
                $dados['descricao']
            );
        }
        return new Receita(
            (int)$dados['id'],
            (float)$dados['valor'],
            $dados['data_transacao'] ?? $dados['data'] ?? '',
            $dados['descricao']
        );
    }
}