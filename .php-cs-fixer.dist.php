<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('vendor')
    ->exclude('var')
;

return (new PhpCsFixer\Config())
    ->setRules([
        'single_line_empty_body' => true,
        'no_unused_imports' => true,
    ])
    ->setFinder($finder)
;
