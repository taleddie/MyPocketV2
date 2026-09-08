<?php

declare(strict_types=1);

require_once __DIR__ . '/Transacao.php';

class Despesa extends Transacao {

    public function getTipo() {
        return "Saída";
    }
}
