# L: let the custom role name be escaped, the format_string() default. The stock-role
# branch below is unaffected, which is what makes the two spellings diverge again.
s|\n                'escape' => false,\n            \]\);|\n            ]);|s;
