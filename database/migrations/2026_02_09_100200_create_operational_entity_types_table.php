<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operational_entity_types')) {
            return;
        }

        Schema::create('operational_entity_types', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_uuid')->nullable()->index();
            $table->string('key', 32);
            $table->string('label', 90);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();

            $table->unique(['tenant_uuid', 'key'], 'operational_entity_types_tenant_key_unique');
        });

        $now = now();
        $defaults = [
            ['key' => 'lead', 'label' => 'Lead', 'sort_order' => 10],
            ['key' => 'cliente', 'label' => 'Cliente', 'sort_order' => 20],
            ['key' => 'fornecedor', 'label' => 'Fornecedor', 'sort_order' => 30],
            ['key' => 'prospect_frio', 'label' => 'Prospect frio', 'sort_order' => 40],
            ['key' => 'ex_cliente', 'label' => 'Ex-cliente', 'sort_order' => 50],
            ['key' => 'parceiro', 'label' => 'Parceiro/Afiliado', 'sort_order' => 60],
            ['key' => 'representante', 'label' => 'Representante/Comercial', 'sort_order' => 70],
            ['key' => 'suporte', 'label' => 'Contato de suporte', 'sort_order' => 80],
            ['key' => 'cobranca', 'label' => 'Cobrança/Financeiro', 'sort_order' => 90],
            ['key' => 'evento', 'label' => 'Eventos/Inscrições', 'sort_order' => 100],
            ['key' => 'assinante', 'label' => 'Assinante de conteúdo', 'sort_order' => 110],
            ['key' => 'candidato', 'label' => 'Candidato/RH', 'sort_order' => 120],
            ['key' => 'produto_servico', 'label' => 'Produto/Serviço', 'sort_order' => 130],
        ];

        DB::table('operational_entity_types')->insert(array_map(
            static fn (array $row): array => [
                'tenant_uuid' => null,
                'key' => $row['key'],
                'label' => $row['label'],
                'is_active' => true,
                'is_system' => true,
                'sort_order' => $row['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $defaults
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_entity_types');
    }
};

