-- Banco do Keycloak (Identity Provider), separado do database `editais` do
-- domínio: identidade não é dado nosso — o Keycloak simula o Gov.br, sistema
-- externo à plataforma. Mesma instância Postgres só por economia de
-- laboratório; em produção seria uma instância dedicada.
CREATE ROLE keycloak LOGIN PASSWORD 'keycloak';
CREATE DATABASE keycloak OWNER keycloak;
