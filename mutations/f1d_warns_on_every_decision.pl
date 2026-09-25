# F1D: warn about applications on other methods whether or not there are any, which is the
# operation test_a_single_method_produces_no_other_methods_warning is the control against.
s#        if \(!\$others\) \{\n            return '';\n        \}\n\n        \$instance = static::dispatch_instance#        \$instance = static::dispatch_instance#s;
