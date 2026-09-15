<?php

declare(strict_types=1);

require_once __DIR__ . '/Transacao.php';

class Diario extends Transacao {

    private string $frequencia;
    private ?string $proximaExecucao;
    private bool $ativo;

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

    public function setFrequencia(string $frequencia): void {
    // Normaliza a string convertendo para minúsculas e removendo espaços
    $freqFormatada = mb_strtolower(trim($frequencia));

    // Mapeia variações comuns para o formato aceito pelo sistema
    if (in_array($freqFormatada, ['diario', 'diário', 'diaria', 'diária'])) {
        $this->frequencia = 'Diário';
    } elseif (in_array($freqFormatada, ['semanal', 'semana'])) {
        $this->frequencia = 'Semanal';
    } elseif (in_array($freqFormatada, ['mensal', 'mes'])) {
        $this->frequencia = 'Mensal';
    } elseif (in_array($freqFormatada, ['anual', 'ano'])) {
        $this->frequencia = 'Anual';
    } else {
        throw new InvalidArgumentException("Frequência inválida: {$frequencia}");
    }
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

    // ler e atualizar status ativo
    public function isAtivo(): bool 
    {
        return $this->ativo;
    }

    public function setAtivo(bool $ativo): void 
    {
        $this->ativo = $ativo;
    }

    public function calcularProximaData(): string 
    {
        $dataStr = $this->proximaExecucao ?? $this->getData() ?? 'now';
        $dataBase = new DateTime($dataStr);

        switch ($this->frequencia) {
            case 'Diário':
                $dataBase->modify('+1 day');
                break;
            case 'Semanal':
                $dataBase->modify('+1 week');
                break;
            case 'Mensal':
                $dataBase->modify('+1 month');
                break;
            case 'Anual':
                $dataBase->modify('+1 year');
                break;
            default:
                throw new InvalidArgumentException("Frequência inválida: " . $this->frequencia);
        }

        return $dataBase->format('Y-m-d');
    }
}