<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Loja;
use App\Models\Message;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@catalogo.test'],
            [
                'name' => 'Admin Teste',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $loja = Loja::firstOrCreate(
            ['user_id' => $user->id, 'slug' => 'loja-demo'],
            [
                'nome' => 'Loja Demo',
                'whatsapp' => '5511999999999',
                'endereco' => 'Rua das Flores, 123',
                'cidade' => 'São Paulo',
                'latitude' => -23.5505,
                'longitude' => -46.6333,
                'raio_atendimento' => 15,
            ]
        );

        $produtos = [
            ['nome' => 'Produto Premium A', 'preco' => 149.90, 'descricao' => 'Produto de alta qualidade.'],
            ['nome' => 'Produto Essencial B', 'preco' => 79.90, 'descricao' => 'Perfeito para o dia a dia.'],
            ['nome' => 'Kit Completo C', 'preco' => 299.00, 'descricao' => 'Kit com tudo que você precisa.'],
            ['nome' => 'Produto Básico D', 'preco' => 39.90, 'descricao' => 'Ótima relação custo-benefício.'],
        ];

        foreach ($produtos as $i => $prod) {
            Produto::firstOrCreate(
                ['loja_id' => $loja->id, 'nome' => $prod['nome']],
                [...$prod, 'loja_id' => $loja->id, 'ordem' => $i, 'ativo' => true]
            );
        }

        $leadData = [
            ['nome' => 'João Silva', 'telefone' => '11988880001', 'lat' => -23.5480, 'lon' => -46.6350, 'score' => 92],
            ['nome' => 'Maria Santos', 'telefone' => '11988880002', 'lat' => -23.5600, 'lon' => -46.6400, 'score' => 75],
            ['nome' => 'Pedro Costa', 'telefone' => '11988880003', 'lat' => -23.5700, 'lon' => -46.6200, 'score' => 60],
            ['nome' => 'Ana Lima', 'telefone' => '11988880004', 'lat' => -23.4500, 'lon' => -46.5000, 'score' => 25],
        ];

        foreach ($leadData as $ld) {
            $lead = Lead::firstOrCreate(
                ['loja_id' => $loja->id, 'telefone' => $ld['telefone']],
                [
                    'nome' => $ld['nome'],
                    'latitude' => $ld['lat'],
                    'longitude' => $ld['lon'],
                    'cidade' => 'São Paulo',
                    'distancia_km' => round(abs($ld['lat'] - (-23.5505)) * 111, 2),
                    'is_nearby' => $ld['score'] > 50,
                    'lead_score' => $ld['score'],
                ]
            );

            $conv = Conversation::firstOrCreate(
                ['loja_id' => $loja->id, 'telefone' => $ld['telefone']],
                ['lead_id' => $lead->id, 'nome_contato' => $ld['nome'], 'status' => 'active', 'last_message_at' => now()]
            );

            if ($conv->messages()->count() === 0) {
                $msg = 'Olá! Vi o catálogo e quero saber mais.';
                Message::create([
                    'conversation_id' => $conv->id,
                    'sender' => 'lead',
                    'message' => $msg,
                    'status' => 'delivered',
                ]);
                $conv->update(['last_message' => $msg]);
            }
        }

        $this->command->info('Seed completo!');
        $this->command->info('Login: admin@catalogo.test / password');
        $this->command->info('Catálogo público: /loja/loja-demo');
    }
}
