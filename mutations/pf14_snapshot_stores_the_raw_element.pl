# PF14: snapshot the custom field's own element as submitted rather than what its type says.
s{\$value = \$field \? customfield::display\(\$field, customfield::stored_value\(\$field, \(array\) \$data\)\) : '';}{\$value = \$field ? (string) (\$data->{\$field->inputname} ?? '') : '';}s;
