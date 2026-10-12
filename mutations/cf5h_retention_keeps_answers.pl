# CF5H: the retention sweep deletes the record alone.
s{\n(\s*)submission::delete\(\$id\);}{\n${1}global \$DB;\n${1}\$DB->delete_records('enrol_apply_submission', ['id' => \$id]);}s;
