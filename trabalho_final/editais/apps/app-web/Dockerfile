# SPA estática servida pelo nginx. O config.js nasce no start do container a
# partir das variáveis de ambiente (URLs visíveis do navegador).
FROM nginx:1.27-alpine
COPY public/ /usr/share/nginx/html/
ENV BFF_PUBLIC_URL=http://bff.editais.local:8000 \
    KEYCLOAK_PUBLIC_URL=http://idp.editais.local:8089
CMD ["/bin/sh", "-c", "envsubst '$BFF_PUBLIC_URL $KEYCLOAK_PUBLIC_URL' < /usr/share/nginx/html/config.js.template > /usr/share/nginx/html/config.js && exec nginx -g 'daemon off;'"]
