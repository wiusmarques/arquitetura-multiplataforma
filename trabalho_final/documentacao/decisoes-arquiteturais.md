# Decisões Arquiteturais — Plataforma de Editais Federais

## 1. Cenário

A plataforma gerencia o ciclo de vida de editais federais de fomento, nos quais pessoas físicas e jurídicas submetem propostas para solicitar recursos do Estado ou recebem auxílio financeiro em situações de calamidade pública. O padrão de uso é fortemente sazonal: o tráfego se concentra nos **cinco últimos dias de vigência de cada edital com picos extremos nos dois últimos**, quando proponentes correm para concluir submissões, avaliadores antecipam análises e cidadãos acompanham as publicações — agravado pelo fato de que, com frequência, **vários editais encerram na mesma data**, somando seus picos. Nesses períodos a plataforma precisa sustentar **até 5.000 acessos por minuto** entre proponentes, cidadãos e avaliadores, enquanto nos demais períodos o volume é médio e estável, dominado por consultas ao catálogo de editais e acompanhamento de propostas já submetidas.

Além do acesso interativo via web e mobile, a plataforma expõe uma **API pública de transparência**, consumida por sistemas externos (órgãos de controle, portais de dados abertos e ferramentas da sociedade civil), com informações de **distribuição de recursos e prestação de contas** dos editais encerrados. Esse canal tem perfil distinto do uso interativo: é exclusivamente de leitura, tolera consistência eventual, tende a consultas em lote e não pode, em hipótese alguma, degradar a experiência de quem está submetendo uma proposta perto do prazo — o que reforça a separação entre o caminho de escrita (submissão transacional) e os caminhos de leitura (consulta pública e transparência).

Para o trabalho, será montado um **cenário reduzido e representativo** desse case: um conjunto pequeno de métodos cobrindo o fluxo essencial — publicação de edital pela administração, consulta pública de editais, submissão e acompanhamento de proposta, registro de parecer pelo avaliador e endpoints de transparência (distribuição de recursos e prestação de contas por edital). O ambiente local de laboratório **não reproduzirá a carga real de pico** — a arquitetura, porém, é **desenhada para suportá-la**: as decisões que viabilizam os 5.000 acessos/minuto (leitura pública separada da escrita via CQRS, comunicação assíncrona por eventos para absorver picos, escala independente por serviço, resiliência no gateway e observabilidade ponta a ponta) estarão implementadas e demonstráveis em pequena escala, de modo que levar o sistema à volumetria-alvo seja questão de replicar instâncias, não de redesenhar a solução.


## 2. Decisões

### 2.1 Gestão interna: o monolito

No cenário ele é usado por um grupo pequeno de servidores da gestão pública (criar e publicar editais, atribuir avaliadores, acompanhar resultados), com volume de acesso **ínfimo** perto do restante da plataforma. São várias funcionalidades como relatórios e atividades gerenciais e distribuir essa parte não traria ganho algum: não há pico a absorver, não há escala a buscar e o custo de migração não se paga. A decisão é **mantê-lo como monolito**, integrando-o ao restante do sistema apenas pela publicação de eventos (Outbox → Kafka), sem que nenhum serviço novo dependa do seu banco.

### 2.2 Canais externos: serviços separados com escala independente

Já as demais camadas têm perfil oposto: o **app web**, o **aplicativo mobile** e a **API pública de transparência** (consumida por órgãos de controle e portais de dados abertos) concentram o uso **"massivo"** da plataforma — é por eles que entram os 5.000 acessos/minuto dos períodos de pico. É esse contraste que justifica o desenho: enquanto a gestão interna permanece monolítica, os caminhos de alto volume são servidos por serviços próprios (BFF como entrada única, query side dedicado para leitura pública via CQRS, serviço transacional de propostas), cada um podendo **escalar de forma independente** conforme a demanda do seu canal, sem arrastar os demais.

### 2.3 Kafka: absorver picos e disparar pagamentos em massa

A comunicação assíncrona via **Kafka** cumpre dois papéis. O primeiro é desacoplar o pós-submissão do request do usuário (avaliação e notificação não seguram a resposta) e sincronizar o read model do CQRS. 

O segundo é o caso de uso que melhor elucida mensageria: **ao fim do ciclo de avaliação de um edital, a publicação do resultado dispara os pagamentos em massa em um curto período** aos proponentes aprovados. O evento `resultado.publicado` é consumido por um fluxo de pagamentos que processa o lote de aprovados de forma assíncrona — exatamente o tipo de carga em rajada (centenas ou milhares de ordens de uma vez) que não deve passar por chamadas síncronas. 

No laboratório esse fluxo será **simulado** (nenhuma integração bancária real), mas implementado ponta a ponta para demonstrar o padrão: evento publicado, consumer processando o lote e registro do pagamento simulado de cada proposta aprovada.