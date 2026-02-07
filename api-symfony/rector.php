<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

$basePath = __DIR__;

return RectorConfig::configure()
    ->withParallel(240)
    ->withPaths([
        $basePath . '/config',
        $basePath . '/src',
        $basePath . '/tests',
    ])
    ->withRootFiles()
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        instanceOf: true,
        earlyReturn: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
    )
    ->withAttributesSets(
        symfony: true,
        doctrine: true,
    )
    ->withImportNames(
        importShortClasses: false,
        removeUnusedImports: true,
    )
    ->withComposerBased(
        doctrine: true,
        symfony: true,
    );
