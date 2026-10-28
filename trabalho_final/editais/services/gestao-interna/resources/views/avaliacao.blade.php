<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Avaliação — {{ $edital->titulo }}</title>
    <style>
        :root { color-scheme: light; }
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 1080px; padding: 0 1rem; color: #1f2937; background: #ffffff; }
        h1 { font-size: 1.3rem; margin-bottom: .25rem; }
        small { color: #6b7280; }
        a { color: #1d4ed8; }
        .flash { margin-top: 1rem; padding: .6rem .9rem; border-radius: 6px; font-size: .9rem; }
        .flash-ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .flash-erro { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        table { border-collapse: collapse; width: 100%; margin-top: 1rem; }
        th, td { border: 1px solid #d1d5db; padding: .5rem .6rem; text-align: left; font-size: .85rem; vertical-align: top; }
        th { background: #f3f4f6; }
        .status { font-weight: 600; }
        .status-pendente { color: #92400e; }
        .status-atribuida { color: #1d4ed8; }
        .status-concluida { color: #166534; }
        .detalhe { color: #6b7280; font-size: .8rem; margin-top: .25rem; }
        input, select { padding: .35rem .5rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: .85rem; width: 11rem; }
        button { padding: .4rem .8rem; border: 1px solid #d1d5db; border-radius: 6px; background: #f9fafb; cursor: pointer; font-size: .85rem; }
        button.primario { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
        td form { display: flex; gap: .35rem; }
        .rodape { margin-top: 1.5rem; }
    </style>
</head>
<body>
    <a href="/">&larr; Voltar aos editais</a>
    <h1>Avaliação — {{ $edital->titulo }}</h1>
    <small>Status do edital: <strong>{{ $edital->status }}</strong> ·
        {{ count($tarefas) }} tarefa(s) ·
        {{ collect($tarefas)->where('status', 'concluida')->count() }} concluída(s)</small>

    @if (session('ok'))
        <div class="flash flash-ok">{{ session('ok') }}</div>
    @endif
    @if (session('erro'))
        <div class="flash flash-erro">{{ session('erro') }}</div>
    @endif

    <table>
        <thead>
            <tr><th>Proposta</th><th>Proponente</th><th>Avaliador</th><th>Status</th><th>Resultado / Parecer</th></tr>
        </thead>
        <tbody>
            @forelse ($tarefas as $t)
                <tr>
                    <td>
                        {{ $t['titulo_proposta'] }}
                        <div class="detalhe">{{ $t['descricao'] }}</div>
                        <div class="detalhe"><em>Retorno esperado:</em> {{ $t['retorno_esperado'] }}</div>
                    </td>
                    <td>{{ $t['proponente'] }}</td>
                    <td>
                        @if ($t['status'] === 'concluida')
                            {{ $t['avaliador'] ?? '—' }}
                        @else
                            {{-- Atribuir (ou reatribuir): o avaliacao-service é quem efetiva.
                                 A lista vem do grupo /avaliadores do Keycloak; se o IdP não
                                 responder, cai no texto livre. --}}
                            <form method="post" action="/tarefas/{{ $t['id'] }}/avaliador">
                                @csrf
                                @if ($avaliadores !== [])
                                    <select name="avaliador" required>
                                        <option value="">Escolha…</option>
                                        @foreach ($avaliadores as $a)
                                            <option value="{{ $a }}" @selected($a === ($t['avaliador'] ?? null))>{{ $a }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input name="avaliador" placeholder="ex.: ana.avaliadora"
                                           value="{{ $t['avaliador'] ?? '' }}" required>
                                @endif
                                <button type="submit" class="primario">Atribuir</button>
                            </form>
                        @endif
                    </td>
                    <td class="status status-{{ $t['status'] }}">{{ $t['status'] }}</td>
                    <td>
                        @if ($t['resultado'])
                            <strong>{{ $t['resultado'] }}</strong> — {{ $t['parecer'] ?? 'sem observações' }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Nenhuma proposta submetida a este edital ainda.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($edital->status === 'publicado')
        <div class="rodape">
            {{-- Só conclui com todas as tarefas avaliadas (o controller valida) --}}
            <form method="post" action="/editais/{{ $edital->id }}/resultado">
                @csrf
                <button type="submit" class="primario">Publicar resultado do edital</button>
            </form>
            <small>Exige todas as tarefas concluídas. Publica o evento <code>resultado.publicado</code>
                (read model, transparência e, futuramente, os pagamentos em massa).</small>
        </div>
    @endif
</body>
</html>
