<?php

declare(strict_types=1);

abstract class Transacao {
    protected int $id;
    protected string $tipo;
    protected string $data;
    protected float $valor;
    protected string $descricao;

    public function __construct(int $data, string $tipo, float $valor, string $descricao, int $id = null) {
        $this->id = $id;
        $this->tipo = $tipo;
        $this->data = $data;
        $this->valor = $valor;
        $this->descricao = $descricao;
    }

    public function getId (): int {
        return $this->id;
    }

    public function getTipo (): string {
        return $this->tipo;
    }

    public function getData (): string {
        return $this->data;
    }

    public function getValor (): float {
        return $this->valor;
    }

    public function getDescricao (): string {
        return $this->descricao;
    }

}
