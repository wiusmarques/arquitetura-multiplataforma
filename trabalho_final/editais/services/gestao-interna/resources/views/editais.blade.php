<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestão de Editais</title>
    <style>
        :root { color-scheme: light; }
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 1080px; padding: 0 1rem; color: #1f2937; background: #ffffff; }
        h1 { font-size: 1.4rem; margin-bottom: .25rem; } h2 { font-size: 1.05rem; margin: 2rem 0 .5rem; }
        small { color: #6b7280; }
        header { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; }
        .sessao { font-size: .85rem; color: #374151; white-space: nowrap; }
        .flash { margin-top: 1rem; padding: .6rem .9rem; border-radius: 6px; font-size: .9rem; }
        .flash-ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .flash-erro { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .erros { margin-top: 1rem; padding: .6rem .9rem; border-radius: 6px; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; font-size: .85rem; }
        form.criar { display: grid; grid-template-columns: repeat(4, 1fr); gap: .75rem; margin-top: .5rem; }
        form.criar label { display: flex; flex-direction: column; gap: .25rem; font-size: .8rem; color: #374151; }
        form.criar .larga { grid-column: span 2; }
        form.criar input { padding: .45rem .6rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: .9rem; }
        table { border-collapse: collapse; width: 100%; margin-top: 1rem; }
        th, td { border: 1px solid #d1d5db; padding: .5rem .6rem; text-align: left; font-size: .85rem; }
        th { background: #f3f4f6; }
        td.num { text-align: right; white-space: nowrap; }
        .status { font-weight: 600; }
        .status-rascunho { color: #92400e; }
        .status-publicado { color: #1d4ed8; }
        .status-encerrado { color: #6b7280; }
        .status-resultado_publicado { color: #166534; }
        button { padding: .4rem .8rem; border: 1px solid #d1d5db; border-radius: 6px; background: #f9fafb; cursor: pointer; font-size: .85rem; }
        button.primario { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
        td form { display: inline; }
        @media (max-width: 720px) { form.criar { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>
    <header>
        <div>
            <h1>Plataforma de Editais Federais — Gestão Interna</h1>
            <small>Monolito da administração (command side). A API está em <code>/v1/editais</code>.</small>
        </div>
        <div class="sessao">
            {{ session('usuario') }}
            <form method="post" action="/logout" style="display:inline">
                @csrf
                <button type="submit">Sair</button>
            </form>
        </div>
    </header>

    @if (session('ok'))
        <div class="flash flash-ok">{{ session('ok') }}</div>
    @endif
    @if (session('erro'))
        <div class="flash flash-erro">{{ session('erro') }}</div>
    @endif
    @if ($errors->any())
        <div class="erros">
            @foreach ($errors->all() as $erro)
                <div>{{ $erro }}</div>
            @endforeach
        </div>
    @endif

    <h2>Novo edital</h2>
    <form class="criar" method="post" action="/editais">
        @csrf
        <label class="larga">Nome
            <input name="titulo" maxlength="200" required value="{{ old('titulo') }}">
        </label>
        <label>Início
            <input name="data_abertura" type="date" required value="{{ old('data_abertura') }}">
        </label>
        <label>Fim
            <input name="data_encerramento" type="date" required value="{{ old('data_encerramento') }}">
        </label>
        <label>Valor total (R$)
            <input name="valor_total" type="number" step="0.01" min="0.01" required value="{{ old('valor_total') }}">
        </label>
        <label>Valor por candidato (R$)
            <input name="valor_por_candidato" type="number" step="0.01" min="0.01" required value="{{ old('valor_por_candidato') }}">
        </label>
        <label>Nº de candidatos
            <input name="numero_candidatos" type="number" min="1" required value="{{ old('numero_candidatos') }}">
        </label>
        <label>Nota máxima
            <input name="nota_maxima" type="number" step="0.01" min="0.01" max="999.99" required value="{{ old('nota_maxima') }}">
        </label>
        <label class="larga">Descrição
            <input name="descricao" value="{{ old('descricao') }}">
        </label>
        <label style="justify-content:end">
            <button type="submit" class="primario">Criar rascunho</button>
        </label>
    </form>

    <h2>Editais</h2>
    <table>
        <thead>
            <tr>
                <th>Edital</th><th>Valor total</th><th>Por candidato</th><th>Vagas</th>
                <th>Nota máx.</th><th>Vigência</th><th>Status</th><th>Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($editais as $edital)
                <tr>
                    <td>{{ $edital->titulo }}</td>
                    <td class="num">R$ {{ number_format((float) $edital->valor_total, 2, ',', '.') }}</td>
                    <td class="num">R$ {{ number_format((float) $edital->valor_por_candidato, 2, ',', '.') }}</td>
                    <td class="num">{{ $edital->numero_candidatos }}</td>
                    <td class="num">{{ number_format((float) $edital->nota_maxima, 2, ',', '.') }}</td>
                    <td>{{ $edital->data_abertura?->format('d/m/Y') }} — {{ $edital->data_encerramento?->format('d/m/Y') }}</td>
                    <td class="status status-{{ $edital->situacao() }}">{{ $edital->situacao() }}</td>
                    <td>
                        @if ($edital->status === 'rascunho')
                            <form method="post" action="/editais/{{ $edital->id }}/publicar">
                                @csrf
                                <button type="submit" class="primario">Publicar</button>
                            </form>
                            <form method="post" action="/editais/{{ $edital->id }}/excluir">
                                @csrf
                                <button type="submit">Excluir</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">Nenhum edital cadastrado ainda.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
