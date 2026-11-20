# CF2D: every type of an enabled shared category is asked, textarea included (the handler keeps every field).
s{static fn\(field_controller \$field\): bool => array_key_exists\(\$field->get\('type'\), \$available\)}{static fn(field_controller \$field): bool => true}s;
