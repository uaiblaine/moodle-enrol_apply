# GC6: a required chooser offers "No preference" and no empty placeholder.
s#        if \(\$required\) \{\n            \$select->addOption\(get_string\('choosedots'\)#        if (false) {\n            \$select->addOption(get_string('choosedots')#;
