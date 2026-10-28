<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Propostas — {{ $edital->titulo }}</title>
    <style>
        :root { color-scheme: light; }
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 1080px; padding: 0 1rem; color: #1f2937; background: #ffffff; }
        h1 { font-size: 1.3rem; margin-bottom: .25rem; }
        small { color: #6b7280; }
        a { color: #1d4ed8; }
        .resumo { display: flex; gap: 1.5rem; margin: 1rem 0; font-size: .9rem; color: #374151; flex-wrap: wrap; }
        .resumo strong { display: block; font-size: 1.15rem; color: #111827; }
        table { border-collapse: collapse; width: 100%; margin-top: 1rem; }
        th, td { border: 1px solid #d1d5db; padding: .5rem .6rem; text-align: left; font-size: .85rem; vertical-align: top; }
        th { background: #f3f4f6; }
        td.num { text-align: right; white-space: nowrap; }
        .status { font-weight: 600; }
        .status-submetida { color: #92400e; }
        .status-aprovada { color: #166534; }
        .status-reprovada { color: #991b1b; }
        .status-paga { color: #1d4ed8; }
        .detalhe { color: #6b7280; font-size: .8rem; margin-top: .25rem; }
    </style>
</head>
<body>
    <a href="/">&larr; Voltar aos editais</a>
    <h1>Propostas — {{ $edital->titulo }}</h1>
    <small>Vigência {{ $edital->data_abertura?->format('d/m/Y') }} — {{ $edital->data_encerramento?->format('d/m/Y') }}
        · {{ $edital->numero_candidatos }} vagas · R$ {{ number_format((float) $edital->valor_por_candidato, 2, ',', '.') }} por candidato</small>

    @php
        $porStatus = collect($propostas)->countBy('status');
    @endphp
    <div class="resumo">
        <span><strong>{{ count($propostas) }}</strong> recebidas</span>
        <span><strong>{{ $porStatus['submetida'] ?? 0 }}</strong> aguardando avaliação</span>
        <span><strong>{{ $porStatus['aprovada'] ?? 0 }}</strong> aprovadas</span>
        <span><strong>{{ $porStatus['reprovada'] ?? 0 }}</strong> reprovadas</span>
        <span><strong>{{ $porStatus['paga'] ?? 0 }}</strong> pagas</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Proposta</th><th>Proponente</th><th>Início</th><th>Meses</th>
                <th>Status</th><th>Parecer</th><th>Pagamento</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($propostas as $p)
                <tr>
                    <td>
                        {{ $p['titulo'] }}
                        <div class="detalhe">{{ $p['descricao'] }}</div>
                        <div class="detalhe"><em>Retorno esperado:</em> {{ $p['retorno_esperado'] }}</div>
                    </td>
                    <td>{{ $p['proponente'] }}</td>
                    <td>{{ \Carbon\Carbon::parse($p['data_inicio'])->format('d/m/Y') }}</td>
                    <td class="num">{{ $p['meses_execucao'] }}</td>
                    <td class="status status-{{ $p['status'] }}">{{ $p['status'] }}</td>
                    <td>{{ $p['parecer'] ?? '—' }}</td>
                    <td>
                        @if ($p['status'] === 'paga')
                            pago
                        @elseif ($p['status'] === 'aprovada')
                            aguardando pagamento (R$ {{ number_format((float) $edital->valor_por_candidato, 2, ',', '.') }})
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">Nenhuma proposta recebida para este edital.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
