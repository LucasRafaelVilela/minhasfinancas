<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class JogoDaVelhaController extends Controller
{
    public function jogadaIA(Request $request) 
    {
        $tabuleiro = $request->input('tabuleiro');

        if (is_string($tabuleiro)) {
            $tabuleiro = json_decode($tabuleiro, true);
        }

        if (!is_array($tabuleiro)) {
            return response()->json(['erro' => 'Formato de tabuleiro inválido'], 400);
        }

        $nivel = $request->input('nivel', 'medio');

        // Aleatório para nível fácil
        if ($nivel === 'facil' && rand(0, 100) < 70) {
            return response()->json($this->jogadaAleatoria($tabuleiro));
        }

        // Chamada à API
        $prompt = "Você é um jogador de Jogo da Velha.
            Tabuleiro atual (3x3, com 'X', 'O' ou null):
            " . json_encode($tabuleiro) . "
            Seu símbolo é 'O'. 
            Escolha a melhor jogada para vencer ou empatar.
            Responda SOMENTE com JSON no formato: {\"linha\": x, \"coluna\": y}";

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . env('HUGGINGFACE_API_KEY'),
        ])->post('https://api-inference.huggingface.co/models/meta-llama/Llama-3-8b-instruct', [
            'inputs' => $prompt,
        ]);

        $resultado = $response->json();

        $textoGerado = $resultado['generated_text']
            ?? ($resultado[0]['generated_text'] ?? null)
            ?? null;

        $jogada = $textoGerado ? json_decode($textoGerado, true) : null;

        // Se a API não retornar algo válido ou já ocupado, usa IA inteligente local
        if (
            !$jogada ||
            !isset($jogada['linha']) ||
            !isset($jogada['coluna']) ||
            !is_numeric($jogada['linha']) ||
            !is_numeric($jogada['coluna']) ||
            (isset($tabuleiro[$jogada['linha']][$jogada['coluna']]) && $tabuleiro[$jogada['linha']][$jogada['coluna']] !== null)
        ) {
            $jogada = $this->jogadaIAInteligente($tabuleiro, $nivel);
        }

        return response()->json($jogada);
    }

    private function jogadaAleatoria($tabuleiro)
    {
        $vazios = [];
        for ($i = 0; $i < 3; $i++) {
            for ($j = 0; $j < 3; $j++) {
                if (!isset($tabuleiro[$i][$j]) || $tabuleiro[$i][$j] === null) {
                    $vazios[] = ['linha' => $i, 'coluna' => $j];
                }
            }
        }

        if (empty($vazios)) {
            return ['linha' => 0, 'coluna' => 0];
        }

        return $vazios[array_rand($vazios)];
    }
    private function jogadaIAInteligente($tabuleiro, $nivel = 'medio')
    {
        // Símbolos
        $simboloIA = 'O';
        $simboloJogador = 'X';

        // 1. Tentar ganhar: se houver duas em linha, finalize
        $jogada = $this->verificaVitoriaOuBloqueio($tabuleiro, $simboloIA);
        if ($jogada) return $jogada;

        // 2. Bloquear jogador: se o jogador tiver duas em linha, bloqueie
        if ($nivel !== 'facil') {
            $jogada = $this->verificaVitoriaOuBloqueio($tabuleiro, $simboloJogador);
            if ($jogada) return $jogada;
        }

        // 3. Estratégia intermediária: ocupar centro ou canto
        if ($nivel === 'medio' || $nivel === 'dificil') {
            // Centro
            if (!isset($tabuleiro[1][1]) || $tabuleiro[1][1] === null) {
                return ['linha' => 1, 'coluna' => 1];
            }

            // Canto
            $cantos = [[0,0],[0,2],[2,0],[2,2]];
            shuffle($cantos);
            foreach ($cantos as $c) {
                if (!isset($tabuleiro[$c[0]][$c[1]]) || $tabuleiro[$c[0]][$c[1]] === null) {
                    return ['linha' => $c[0], 'coluna' => $c[1]];
                }
            }
        }

        // 4. Aleatório como último recurso
        return $this->jogadaAleatoria($tabuleiro);
    }

    private function verificaVitoriaOuBloqueio($tabuleiro, $simbolo)
    {
        // Linhas e colunas
        for ($i = 0; $i < 3; $i++) {
            // Linhas
            if ($this->duasEmLinha($tabuleiro[$i], $simbolo)) {
                for ($j = 0; $j < 3; $j++) {
                    if (!isset($tabuleiro[$i][$j]) || $tabuleiro[$i][$j] === null) {
                        return ['linha' => $i, 'coluna' => $j];
                    }
                }
            }

            // Colunas
            $coluna = [$tabuleiro[0][$i] ?? null, $tabuleiro[1][$i] ?? null, $tabuleiro[2][$i] ?? null];
            if ($this->duasEmLinha($coluna, $simbolo)) {
                for ($j = 0; $j < 3; $j++) {
                    if (!isset($tabuleiro[$j][$i]) || $tabuleiro[$j][$i] === null) {
                        return ['linha' => $j, 'coluna' => $i];
                    }
                }
            }
        }

        // Diagonais
        $diagonal1 = [$tabuleiro[0][0] ?? null, $tabuleiro[1][1] ?? null, $tabuleiro[2][2] ?? null];
        if ($this->duasEmLinha($diagonal1, $simbolo)) {
            for ($i = 0; $i < 3; $i++) {
                if (!isset($tabuleiro[$i][$i]) || $tabuleiro[$i][$i] === null) {
                    return ['linha' => $i, 'coluna' => $i];
                }
            }
        }

        $diagonal2 = [$tabuleiro[0][2] ?? null, $tabuleiro[1][1] ?? null, $tabuleiro[2][0] ?? null];
        if ($this->duasEmLinha($diagonal2, $simbolo)) {
            for ($i = 0; $i < 3; $i++) {
                $j = 2 - $i;
                if (!isset($tabuleiro[$i][$j]) || $tabuleiro[$i][$j] === null) {
                    return ['linha' => $i, 'coluna' => $j];
                }
            }
        }

        return null;
    }

    private function duasEmLinha($linha, $simbolo)
    {
        $contagem = 0;
        $vazio = 0;
        foreach ($linha as $c) {
            if ($c === $simbolo) $contagem++;
            if ($c === null || !isset($c)) $vazio++;
        }
        return $contagem === 2 && $vazio === 1;
    }
}
