<?php

declare(strict_types=1);

require_once __DIR__ . '/Transacao.php';

class Despesa extends Transacao {

    public function __construct(?int $id, float $valor, string $data, string $descricao) {
        parent::__construct($id, 'despesa', $valor, $data, $descricao);
    }


    public function getTipo(): string {
        return "Saída";
    }
}
