<?php

namespace Tests\Feature;

use Tests\TestCase;

class CarregamentoInterfaceTest extends TestCase
{
    public function test_tela_de_acesso_inclui_os_indicadores_padrao(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('js/carregamento.js', $html);
        $this->assertStringContainsString('id="nav-barra"', $html);
        $this->assertStringContainsString('id="erp-processo"', $html);
        $this->assertStringContainsString('id="tpl-skeleton-tabela"', $html);
        $this->assertStringContainsString('id="tpl-skeleton-painel"', $html);
        $this->assertStringContainsString('id="tpl-skeleton-relatorio"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
    }
}
