# DH: treat country as a text field. {user}.country holds a two-letter code, so a LIKE over it
# answers about "BR" and never about "Brazil" - which is the only thing an operator would type into
# a text box, and what the select would have offered them.
s{        if \(\$name === 'country'\) \{\n            return \(object\) \[\n                'control' => 'select',}{        if (false) \{\n            return (object) [\n                'control' => 'select',}s;
