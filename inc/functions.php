<?php

declare(strict_types=1);

use Symfony\Component\VarDumper\VarDumper;

if (
    class_exists('Symfony\\Component\\VarDumper\\VarDumper')
    && !function_exists('dump')
) {
    /**
     * Dumps variables to output
     */
    function dump(mixed $var, mixed ...$moreVars): mixed
    {
        VarDumper::dump($var);

        foreach ($moreVars as $v) {
            VarDumper::dump($v);
        }

        if (func_num_args() > 1) {
            return func_get_args();
        }

        return $var;
    }
}

if (
    class_exists('Symfony\\Component\\VarDumper\\VarDumper')
    && !function_exists('dd')
) {
    /**
     * Dumps variables to output then ends script execution
     */
    function dd(mixed ...$vars): void
    {
        foreach ($vars as $v) {
            VarDumper::dump($v);
        }

        die(1);
    }
}
