<?php

declare(strict_types=1);

require_once __DIR__ . '/Transacao.php';

class Receita extends Transacao {

    public function __construct(?int $id, float $valor, string $data, string $descricao) {
        parent::__construct($id, 'receita', $valor, $data, $descricao);
    }

    public function getTipo(): string {
        return "Entrada";
    }
}
