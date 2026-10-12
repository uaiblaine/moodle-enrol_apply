# CF2D: every type of an enabled shared category is asked, textarea included.
s{static fn\(field_controller \$field\): bool => array_key_exists\(\$field->get\('type'\), \$allowed\)}{static fn(field_controller \$field): bool => true}s;
