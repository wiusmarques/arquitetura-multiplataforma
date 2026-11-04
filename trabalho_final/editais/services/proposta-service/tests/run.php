<?php

/**
 * Runner de testes do proposta-service (lado PHP do `make test`).
 *
 * Mini-runner proposital, sem PHPUnit: o hook de pre-push roda na máquina do
 * desenvolvedor, onde o vendor/ não existe (o composer install acontece só na
 * imagem Docker). Como o domínio é PHP puro (Clean Architecture), basta um
 * autoloader PSR-4 de 5 linhas e asserções simples — qualquer `php` 8.3 roda.
 *
 * Convenção PHPUnit mantida: classes *Test.php com métodos test*.
 */

declare(strict_types=1);

spl_autoload_register(function (string $classe): void {
    if (str_starts_with($classe, 'App\\')) {
        $arquivo = __DIR__ . '/../app/' . str_replace('\\', '/', substr($classe, 4)) . '.php';
        if (is_file($arquivo)) {
            require $arquivo;
        }
    }
});

abstract class MiniTestCase
{
    public static function assertSame(mixed $esperado, mixed $obtido, string $msg = ''): void
    {
        if ($esperado !== $obtido) {
            $e = var_export($esperado, true);
            $o = var_export($obtido, true);
            throw new RuntimeException($msg !== '' ? $msg : "esperava {$e}, obteve {$o}");
        }
    }

    public static function assertNull(mixed $valor, string $msg = ''): void
    {
        self::assertSame(null, $valor, $msg !== '' ? $msg : 'esperava null');
    }

    public static function assertNotNull(mixed $valor, string $msg = ''): void
    {
        if ($valor === null) {
            throw new RuntimeException($msg !== '' ? $msg : 'não esperava null');
        }
    }

    /** Executa $acao e exige que ela lance $classeExcecao. */
    public static function assertLanca(string $classeExcecao, callable $acao, string $msg = ''): void
    {
        try {
            $acao();
        } catch (Throwable $t) {
            if ($t instanceof $classeExcecao) {
                return;
            }
            throw new RuntimeException("esperava {$classeExcecao}, veio " . $t::class . ": {$t->getMessage()}");
        }
        throw new RuntimeException($msg !== '' ? $msg : "esperava {$classeExcecao}, nada foi lançado");
    }
}

// Descobre e roda todos os *Test.php desta pasta.
$falhas = 0;
$total  = 0;
foreach (glob(__DIR__ . '/*Test.php') as $arquivo) {
    require $arquivo;
    $classe = basename($arquivo, '.php');
    foreach (get_class_methods($classe) as $metodo) {
        if (! str_starts_with($metodo, 'test')) {
            continue;
        }
        $total++;
        try {
            (new $classe())->$metodo();
            echo "  ✔ {$classe}::{$metodo}\n";
        } catch (Throwable $t) {
            $falhas++;
            echo "  ✘ {$classe}::{$metodo} — {$t->getMessage()}\n";
        }
    }
}

echo $falhas === 0
    ? "OK — {$total} teste(s) passaram.\n"
    : "FALHOU — {$falhas} de {$total} teste(s) com erro.\n";
exit($falhas === 0 ? 0 : 1);
