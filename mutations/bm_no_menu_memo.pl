# BM: drop the per-course memo, so the participants menu gets one identical optgroup per enrolment
# instance again. user/index.php calls get_bulk_operations() once per instance with a url carrying
# only the plugin and the operation, so N instances give N indistinguishable groups.
s#        if \(empty\(\$manager->get_enrolment_filter\(\)\)\) \{\n            if \(\$this->bulkmenuoffered\) \{\n                return \[\];\n            \}\n            \$this->bulkmenuoffered = true;\n        \}\n\n##s;
