<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acesso negado</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 4rem auto; max-width: 480px; padding: 0 1rem; color: #1f2937; }
        .caixa { border: 1px solid #fecaca; background: #fef2f2; border-radius: 8px; padding: 1.5rem; }
        h1 { font-size: 1.1rem; color: #991b1b; margin-top: 0; }
        a { color: #1d4ed8; }
    </style>
</head>
<body>
    <div class="caixa">
        <h1>Acesso restrito à administração</h1>
        <p>O usuário <strong>{{ $usuario }}</strong> autenticou no Keycloak, mas não tem o papel
            <code>admin</code> — este painel é exclusivo da gestão interna.</p>
        <p><a href="/login?prompt=login">Entrar com outra conta</a></p>
    </div>
</body>
</html>
