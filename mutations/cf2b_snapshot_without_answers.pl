# CF2B: the snapshot freezes the profile fields only.
s{array_merge\(fields::submitted_values\(\$instance, \$data\), answers::submitted_values\(\$instance, \$data\)\)}{fields::submitted_values(\$instance, \$data)}s;
