<?php

declare(strict_types=1);

require_once __DIR__ . '/Transacao.php';

class Diario extends Transacao {

    private string $frequencia;
    private ?string $proximaExecucao;

    public function __construct(
        ?int $id,
        string $tipo,
        float $valor,
        string $data,
        string $descricao,
        string $frequencia,
        ?string $proximaExecucao = null,
        bool $ativo = true
    ) {
        parent::__construct($id, $tipo, $valor, $data, $descricao);
        $this->frequencia = $frequencia;
        $this->proximaExecucao = $proximaExecucao;
        $this->ativo = $ativo;
    }

    // ler e atualizar frequencia
    public function getFrequencia(): string 
    {
        return $this->frequencia;
    }

    public function setFrequencia(string $frequencia): void 
    {
        $this->frequencia = $frequencia;
    }

    // ler e atualizar a proxima execucao
    public function getProximaExecucao(): ?string 
    {
        return $this->proximaExecucao;
    }

    public function setProximaExecucao(string $proximaExecucao): void 
    {
        $this->proximaExecucao = $proximaExecucao;
    }

    public function calcularProximaData(): string 
    {
        $dataBase = new DateTime($this->proximaExecucao ?? $this->getData());
        switch ($this->frequencia) {
            case 'Diário':
                $dataAtual->modify('+1 day');
                break;
            case 'Semanal':
                $dataAtual->modify('+1 week');
                break;
            case 'Mensal':
                $dataAtual->modify('+1 month');
                break;
            case 'Anual':
                $dataAtual->modify('+1 year');
                break;
            default:
                throw new InvalidArgumentException("Frequência inválida: " . $this->frequencia);
        }
        return $dataAtual->format('Y-m-d');
    }

}