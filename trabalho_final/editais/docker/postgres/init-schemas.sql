-- Database per service dentro de um único PostgreSQL de laboratório:
-- cada serviço tem a própria role e o próprio schema, e a role de um serviço
-- não enxerga o schema dos outros. Integração entre domínios só por REST ou
-- evento Kafka — nunca por JOIN entre schemas.
--
-- Em produção cada serviço teria sua instância (ou ao menos seu database);
-- aqui um Postgres só com schemas isolados mantém o mesmo contrato
-- arquitetural gastando uma fração dos recursos da máquina do avaliador.

CREATE ROLE gestao      LOGIN PASSWORD 'gestao';
CREATE ROLE edital      LOGIN PASSWORD 'edital';
CREATE ROLE proposta    LOGIN PASSWORD 'proposta';
CREATE ROLE avaliacao   LOGIN PASSWORD 'avaliacao';
CREATE ROLE notificacao LOGIN PASSWORD 'notificacao';

CREATE SCHEMA gestao       AUTHORIZATION gestao;      -- monolito gestao-interna (command side)
CREATE SCHEMA editais_read AUTHORIZATION edital;      -- edital-service (query side / CQRS)
CREATE SCHEMA propostas    AUTHORIZATION proposta;    -- proposta-service (aggregate + outbox)
CREATE SCHEMA avaliacao    AUTHORIZATION avaliacao;   -- avaliacao-service
CREATE SCHEMA notificacao  AUTHORIZATION notificacao; -- notification-service

-- Cada role enxerga só o próprio schema por padrão.
ALTER ROLE gestao      SET search_path = gestao;
ALTER ROLE edital      SET search_path = editais_read;
ALTER ROLE proposta    SET search_path = propostas;
ALTER ROLE avaliacao   SET search_path = avaliacao;
ALTER ROLE notificacao SET search_path = notificacao;

-- Fecha a porta dos fundos: sem acesso via PUBLIC nem ao schema public.
REVOKE ALL ON DATABASE editais FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT CONNECT ON DATABASE editais TO gestao, edital, proposta, avaliacao, notificacao;
