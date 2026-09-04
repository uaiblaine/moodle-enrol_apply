# DJ: use the identity field's own name as its wire token. Core's dynamic-table service declares a
# filter name PARAM_ALPHANUM, so a custom field - whose shortname may hold underscores - is refused
# with invalid_parameter_exception before this plugin is consulted, and the queue stops refreshing.
s{            return \$field \? self::CUSTOM_PREFIX \. \(int\) \$field->id : '';}{            return \$name;}s;
