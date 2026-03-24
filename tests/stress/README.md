# Beconnect — Stress Test

## Instalação
```bash
npm install -g artillery
```

## Executar testes

### Teste rápido (30s, até 100 users/seg)
```bash
artillery run tests/stress/quick-test.yml
```

### Teste completo de stress (100k simulados em 3min)
```bash
artillery run tests/stress/stress-test.yml
```

### Com relatório HTML
```bash
artillery run tests/stress/stress-test.yml --output report.json
artillery report report.json --output report.html
```

## Interpretar resultados

| Métrica | Alvo | Problema |
|---|---|---|
| `http.response_time.p99` | < 2000ms | > 2000ms = bottleneck |
| `http.response_time.p95` | < 1000ms | > 1000ms = lento |
| `http.codes.200` | > 95% | < 95% = erros |
| `http.codes.429` | < 1% | > 1% = rate limit muito agressivo |
| `http.codes.500` | 0% | qualquer = bug crítico |

## Fases do teste completo

| Fase | Duração | Users/seg | Objetivo |
|---|---|---|---|
| Aquecimento | 30s | 30 | Validar baseline |
| Carga crescente | 60s | 30→200 | Encontrar limite |
| Pico | 60s | 500 | Simular viral |
| Stress máximo | 30s | 1000 | Ponto de quebra |
| Recuperação | 30s | 50 | Verificar resiliência |

## Antes de correr

1. Certifica-te que o servidor está a correr: `php artisan serve`
2. A base de dados tem dados suficientes (pelo menos 10 lojas e 50 produtos)
3. Cria os utilizadores de teste:

```bash
php artisan tinker
User::factory()->create(['email' => 'cliente_teste@beconnect.co.mz', 'password' => bcrypt('password123'), 'role' => 'customer']);
User::factory()->create(['email' => 'loja_teste@beconnect.co.mz', 'password' => bcrypt('password123'), 'role' => 'store_owner']);
```
