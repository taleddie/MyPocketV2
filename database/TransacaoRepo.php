<?php

require_once __DIR__ . '/../classes/Transacao.php';
require_once __DIR__ . '/../classes/Receita.php';
require_once __DIR__ . '/../classes/Despesa.php';

class TransacaoRepo {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // C - CREATE: receita
    public function adicionarReceita(Receita $receita): void {
        $this->inserir('receita', $receita);
    }

    // C - CREATE: despesa
    public function adicionarDespesa(Despesa $despesa): void {
        if ($despesa->getValor() > $this->calcularSaldo()) {
            throw new Exception("Saldo insuficiente.");
        }
        $this->inserir('despesa', $despesa);
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
        $stmt = $this->pdo->query("SELECT * FROM transacoes ORDER BY data DESC, id DESC");
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hidratar'], $linhas);
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

    // calcula o saldo direto no banco (substitui o antigo $carteira->getSaldo())
    public function calcularSaldo(): float {
        $stmt = $this->pdo->query(
            "SELECT COALESCE(SUM(CASE WHEN tipo = 'receita' THEN valor ELSE -valor END), 0) AS saldo FROM transacoes"
        );
        return (float) $stmt->fetch(PDO::FETCH_ASSOC)['saldo'];
    }

    // transforma uma linha do banco (array) num objeto Receita ou Despesa
    private function hidratar(array $linha): Transacao {
        if ($linha['tipo'] === 'receita') {
            return new Receita($linha['data'], (float) $linha['valor'], $linha['descricao'], (int) $linha['id']);
        }
        return new Despesa($linha['data'], (float) $linha['valor'], $linha['descricao'], (int) $linha['id']);
    }
}
