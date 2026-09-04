# DN: trim the menu vocabulary on the way out. Core stores the option verbatim, so an option
# written with a leading or trailing space is offered in a spelling the column can never hold and
# the filter matches nothing, on every database, silently.
s{\$values = array_filter\(explode\("\\n", \(string\) \$field->param1\), static function \(\$v\) \{}{\$values = array_filter(array_map('trim', explode("\\n", (string) \$field->param1)), static function (\$v) \{}s;
