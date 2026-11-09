<?php

declare(strict_types=1);

// === is_safe in the options array: flagged ===
$a = new TwigFunction('render', $callable, ['is_safe' => ['html']]); // @expect Simbiat.Twig.SafeFilterOption.IsSafeUsed
$b = new TwigFilter('render', $callable, ['is_safe' => ['html']]); // @expect Simbiat.Twig.SafeFilterOption.IsSafeUsed
$e = new TwigFunction('render', $callable, ["is_safe" => ['html']]); // @expect Simbiat.Twig.SafeFilterOption.IsSafeUsed
$f = new TwigFunction('render', $callable, ['needs_environment' => true, 'is_safe' => ['html']]); // @expect Simbiat.Twig.SafeFilterOption.IsSafeUsed
$g = new TwigFunction('render', $callable, options: ['is_safe' => ['html']]); // @expect Simbiat.Twig.SafeFilterOption.IsSafeUsed
$h = new TwigFunction( // @expect Simbiat.Twig.SafeFilterOption.IsSafeUsed
    'render',
    $callable,
    [
        'is_safe' => ['html'],
    ],
);

// === One result per call, even with the option repeated ===
$i = new TwigFunction('render', $callable, ['is_safe' => ['html'], 'is_safe_callback' => 'x', 'is_safe' => ['js']]); // @expect Simbiat.Twig.SafeFilterOption.IsSafeUsed

// === Inside a nested call: the inner `new` is judged on its own ===
$j = array_map(static fn($name) => new TwigFilter($name, $callable, ['is_safe' => ['html']]), $names); // @expect Simbiat.Twig.SafeFilterOption.IsSafeUsed

// === No is_safe option: must NOT be flagged ===
$k = new TwigFunction('render', $callable);
$l = new TwigFilter('render', $callable, ['needs_environment' => true]);
$m = new TwigFunction('render', $callable, ['is_safe_callback' => $callback]);

// === Other classes, or namespaced Twig classes: must NOT be flagged ===
$o = new SomethingElse('render', $callable, ['is_safe' => ['html']]);
$p = new Twig\TwigFunction('render', $callable, ['is_safe' => ['html']]);
$q = new \Twig\TwigFilter('render', $callable, ['is_safe' => ['html']]);
$r = new TwigFunction;
$s = new TwigFunction();

// === Only `new` counts ===
TwigFunction::create('render', $callable, ['is_safe' => ['html']]);
$t = ['is_safe' => ['html']];
