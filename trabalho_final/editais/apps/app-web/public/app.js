/* Portal externo: proponente, cidadão (sem login) e área do avaliador.
 * Toda chamada passa pelo BFF — o app nunca conhece os serviços internos. */

import Keycloak from 'https://cdn.jsdelivr.net/npm/keycloak-js@26.0.0/+esm';

const { BFF_URL, KEYCLOAK_URL, KEYCLOAK_REALM, KEYCLOAK_CLIENT } = window.CONFIG;

const keycloak = new Keycloak({
    url: KEYCLOAK_URL,
    realm: KEYCLOAK_REALM,
    clientId: KEYCLOAK_CLIENT,
});

const el = (id) => document.getElementById(id);

function mensagem(texto, tipo = 'aviso') {
    const div = el('mensagem');
    div.textContent = texto;
    div.className = `aviso ${tipo}`;
    div.hidden = false;
    setTimeout(() => { div.hidden = true; }, 6000);
}

async function api(caminho, opcoes = {}) {
    const headers = { 'Content-Type': 'application/json', Accept: 'application/json', ...(opcoes.headers || {}) };
    if (keycloak.authenticated) {
        await keycloak.updateToken(30).catch(() => keycloak.login());
        headers.Authorization = `Bearer ${keycloak.token}`;
    }
    const resposta = await fetch(`${BFF_URL}${caminho}`, { ...opcoes, headers });
    const corpo = await resposta.json().catch(() => ({}));
    if (!resposta.ok) {
        // `erro` vem dos serviços PHP; `detail` é o formato do FastAPI.
        throw new Error(corpo.erro || corpo.detail || corpo.message || `HTTP ${resposta.status}`);
    }
    return corpo;
}

const dinheiro = (v) => Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const temRole = (role) => keycloak.authenticated && keycloak.hasRealmRole(role);

// Tudo que vai para innerHTML passa por aqui — dado de usuário nunca vira HTML.
const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
));

// Edital aberto para propostas: publicado e dentro da vigência.
const vigente = (e) => e.status === 'publicado'
    && (!e.data_encerramento || e.data_encerramento >= new Date().toISOString().slice(0, 10));

// ---- Abas -------------------------------------------------------------------

const abas = {
    editais: carregarEditais,
    transparencia: carregarTransparencia,
    propostas: carregarPropostas,
    avaliacao: carregarAvaliacao,
};

document.querySelectorAll('nav button').forEach((botao) => {
    botao.addEventListener('click', () => {
        document.querySelectorAll('nav button').forEach((b) => b.classList.remove('ativa'));
        botao.classList.add('ativa');
        document.querySelectorAll('main section').forEach((s) => { s.hidden = true; });
        el(`aba-${botao.dataset.aba}`).hidden = false;
        abas[botao.dataset.aba]().catch((e) => mensagem(e.message, 'erro'));
    });
});

// ---- Editais (público) ------------------------------------------------------

async function carregarEditais() {
    const editais = await api('/v1/editais');
    el('aba-editais').innerHTML = `
        <table>
            <thead><tr><th>Edital</th><th>Valor total</th><th>Por candidato</th><th>Vagas</th><th>Encerramento</th><th>Status</th></tr></thead>
            <tbody>${editais.map((e) => `
                <tr>
                    <td>${esc(e.titulo)}</td>
                    <td>${dinheiro(e.valor_total)}</td>
                    <td>${e.valor_por_candidato != null ? dinheiro(e.valor_por_candidato) : '—'}</td>
                    <td>${e.numero_candidatos ?? '—'}</td>
                    <td>${esc(e.data_encerramento) || '—'}</td>
                    <td class="status-${esc(e.status)}">${esc(e.status)}</td>
                </tr>`).join('') || '<tr><td colspan="6">Nenhum edital publicado.</td></tr>'}
            </tbody>
        </table>`;
}

// ---- Transparência (público) ------------------------------------------------

async function carregarTransparencia() {
    const linhas = await api('/v1/transparencia/recursos');
    el('aba-transparencia').innerHTML = `
        <p>Distribuição de recursos por edital — os mesmos dados servidos aos órgãos de controle
           e portais de dados abertos (somente leitura, consistência eventual).</p>
        <table>
            <thead><tr><th>Edital</th><th>Valor do edital</th><th>Propostas</th><th>Aprovadas</th><th>Valor aprovado</th></tr></thead>
            <tbody>${linhas.map((t) => `
                <tr>
                    <td>${esc(t.titulo)}</td>
                    <td>${dinheiro(t.valor_total)}</td>
                    <td>${t.propostas_recebidas}</td>
                    <td>${t.propostas_aprovadas}</td>
                    <td>${dinheiro(t.valor_aprovado)}</td>
                </tr>`).join('') || '<tr><td colspan="5">Sem dados ainda.</td></tr>'}
            </tbody>
        </table>`;
}

// ---- Minhas propostas (role proponente) -------------------------------------

async function carregarPropostas() {
    const secao = el('aba-propostas');
    if (!temRole('proponente')) {
        secao.innerHTML = '<p>Entre com uma conta de <strong>proponente</strong> para submeter e acompanhar propostas.</p>';
        return;
    }
    const [editais, propostas] = await Promise.all([
        api('/v1/editais'), api('/v1/propostas'),
    ]);
    const abertos = editais.filter(vigente);
    const porId = Object.fromEntries(editais.map((e) => [e.id, e]));

    // A coluna Pagamento é o acompanhamento pós-aprovação: aprovada aguarda o
    // pagamento (valor = valor por candidato do edital); paga encerra o ciclo.
    const pagamento = (p) => {
        if (p.status === 'paga') return 'pago';
        if (p.status !== 'aprovada') return '—';
        const valor = porId[p.edital_id]?.valor_por_candidato;
        return `aguardando pagamento${valor != null ? ` (${dinheiro(valor)})` : ''}`;
    };

    secao.innerHTML = `
        <form id="form-proposta">
            <strong>Nova proposta</strong>
            <select name="edital_id" required>
                <option value="">Escolha o edital…</option>
                ${abertos.map((e) => `<option value="${esc(e.id)}">${esc(e.titulo)}${e.valor_por_candidato != null ? ` — ${dinheiro(e.valor_por_candidato)} por candidato` : ''}</option>`).join('')}
            </select>
            <input name="titulo" placeholder="Título da proposta" required maxlength="200">
            <textarea name="descricao" placeholder="Descrição do projeto *" rows="3" required></textarea>
            <textarea name="retorno_esperado" placeholder="Retorno esperado *" rows="3" required></textarea>
            <label>Data de início <input name="data_inicio" type="date" required></label>
            <label>Meses de execução <input name="meses_execucao" type="number" min="1" required></label>
            <small>* Título, descrição e retorno esperado são os campos avaliados; data de início e
            meses de execução são informativos. O valor do apoio é o definido pelo edital.</small>
            <button class="acao">Submeter proposta</button>
        </form>
        <table>
            <thead><tr><th>Proposta</th><th>Edital</th><th>Status</th><th>Parecer</th><th>Pagamento</th></tr></thead>
            <tbody>${propostas.map((p) => `
                <tr>
                    <td>${esc(p.titulo)}</td>
                    <td>${esc(porId[p.edital_id]?.titulo ?? p.edital_id)}</td>
                    <td class="status-${esc(p.status)}">${esc(p.status)}</td>
                    <td>${esc(p.parecer) || '—'}</td>
                    <td>${esc(pagamento(p))}</td>
                </tr>`).join('') || '<tr><td colspan="5">Nenhuma proposta ainda.</td></tr>'}
            </tbody>
        </table>`;

    el('form-proposta').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const dados = Object.fromEntries(new FormData(ev.target));
        dados.meses_execucao = Number(dados.meses_execucao);
        try {
            await api('/v1/propostas', { method: 'POST', body: JSON.stringify(dados) });
            mensagem('Proposta submetida! Você receberá a confirmação por e-mail.', 'ok');
            await carregarPropostas();
        } catch (e) {
            mensagem(e.message, 'erro');
        }
    });
}

// ---- Avaliação (role avaliador) ---------------------------------------------

async function carregarAvaliacao() {
    const secao = el('aba-avaliacao');
    if (!temRole('avaliador')) {
        secao.innerHTML = '<p>Área restrita: entre com uma conta de <strong>avaliador</strong>.</p>';
        return;
    }
    const tarefas = await api('/v1/avaliacao/tarefas');
    secao.innerHTML = `
        <table>
            <thead><tr><th>Proposta</th><th>Proponente</th><th>Status</th><th>Parecer</th></tr></thead>
            <tbody>${tarefas.map((t) => `
                <tr>
                    <td>
                        ${esc(t.titulo_proposta)}
                        <div class="detalhe">${esc(t.descricao)}</div>
                        <div class="detalhe"><em>Retorno esperado:</em> ${esc(t.retorno_esperado)}</div>
                    </td>
                    <td>${esc(t.proponente)}</td>
                    <td class="status-${esc(t.status)}">${esc(t.status)}${t.resultado ? ` (${esc(t.resultado)})` : ''}</td>
                    <td>${t.status === 'concluida' ? (esc(t.parecer) || '—') : `
                        <input id="parecer-${esc(t.id)}" placeholder="Parecer" style="width:9rem">
                        <button class="acao" data-tarefa="${esc(t.id)}" data-resultado="aprovada">Aprovar</button>
                        <button class="acao secundaria" data-tarefa="${esc(t.id)}" data-resultado="reprovada">Reprovar</button>`}
                    </td>
                </tr>`).join('') || '<tr><td colspan="4">Nenhuma tarefa atribuída a você.</td></tr>'}
            </tbody>
        </table>`;

    secao.querySelectorAll('button[data-tarefa]').forEach((botao) => {
        botao.addEventListener('click', async () => {
            const id = botao.dataset.tarefa;
            try {
                await api(`/v1/avaliacao/tarefas/${id}/parecer`, {
                    method: 'POST',
                    body: JSON.stringify({
                        resultado: botao.dataset.resultado,
                        parecer: el(`parecer-${id}`).value || '(sem observações)',
                    }),
                });
                mensagem('Parecer registrado.', 'ok');
                await carregarAvaliacao();
            } catch (e) {
                mensagem(e.message, 'erro');
            }
        });
    });
}

// ---- Login ------------------------------------------------------------------

el('btn-login').addEventListener('click', () => keycloak.login());
el('btn-logout').addEventListener('click', () => keycloak.logout({ redirectUri: window.location.origin }));

keycloak.init({ onLoad: 'check-sso', pkceMethod: 'S256' }).then((autenticado) => {
    if (autenticado) {
        el('btn-login').hidden = true;
        el('btn-logout').hidden = false;
        const papeis = (keycloak.realmAccess?.roles || [])
            .filter((r) => ['admin', 'avaliador', 'proponente'].includes(r)).join(', ');
        el('usuario-nome').textContent = `${keycloak.tokenParsed.preferred_username} (${papeis})`;
    }
    carregarEditais().catch((e) => mensagem(e.message, 'erro'));
});
