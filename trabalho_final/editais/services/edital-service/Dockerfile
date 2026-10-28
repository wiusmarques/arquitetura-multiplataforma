# Build multi-stage: o estágio de build instala as dependências com
# toolchain completo; o runtime leva só o Python e os pacotes prontos.
FROM python:3.12-slim AS build
WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir --prefix=/install -r requirements.txt

FROM python:3.12-slim
# Nunca rodar como root.
RUN useradd --create-home appuser
WORKDIR /app
COPY --from=build /install /usr/local
COPY app/ app/
USER appuser
EXPOSE 8001
HEALTHCHECK --interval=15s --timeout=5s --retries=5 --start-period=15s \
    CMD python -c "import urllib.request;urllib.request.urlopen('http://localhost:8001/health')"
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8001"]
