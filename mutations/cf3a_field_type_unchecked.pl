# CF3A: a field of any installed type is restored, textarea included.
s{!in_array\(\$type, application_handler::ALLOWED_TYPES, true\)\n\s*\|\| !class_exists}{!class_exists}s;
