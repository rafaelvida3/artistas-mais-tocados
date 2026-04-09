<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . "/wp-content/mu-plugins",
    ])
    ->exclude(["vendor"])
    ->name("*.php");

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        "@PSR12" => true,
        "array_syntax" => ["syntax" => "short"],
        "binary_operator_spaces" => [
            "default" => "single_space",
        ],
        "blank_line_after_opening_tag" => true,
        "blank_line_before_statement" => [
            "statements" => ["return"],
        ],
        "braces_position" => [
            "anonymous_functions_opening_brace" => "same_line",
            "anonymous_classes_opening_brace" => "same_line",
            "classes_opening_brace" => "same_line",
            "control_structures_opening_brace" => "same_line",
            "functions_opening_brace" => "same_line",
        ],
        "cast_spaces" => true,
        "class_attributes_separation" => [
            "elements" => [
                "method" => "one",
                "property" => "one",
                "trait_import" => "none",
                "case" => "none",
                "const" => "one",
            ],
        ],
        "concat_space" => ["spacing" => "one"],
        "declare_equal_normalize" => true,
        "function_declaration" => true,
        "line_ending" => true,
        "lowercase_cast" => true,
        "lowercase_keywords" => true,
        "method_argument_space" => [
            "on_multiline" => "ensure_fully_multiline",
        ],
        "new_with_parentheses" => true,
        "no_extra_blank_lines" => true,
        "no_trailing_whitespace" => true,
        "no_unused_imports" => true,
        "not_operator_with_successor_space" => true,
        "ordered_imports" => [
            "sort_algorithm" => "alpha",
        ],
        "single_blank_line_at_eof" => true,
        "single_quote" => true,
        "trailing_comma_in_multiline" => [
            "elements" => ["arrays", "arguments", "parameters"],
        ],
        "types_spaces" => [
            "space" => "single",
        ],
    ])
    ->setFinder($finder);