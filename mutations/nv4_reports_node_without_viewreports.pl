# NV4: the Reports node put back for a reader core would not show reports to.
s#        if \(!has_capability\('moodle/site:viewreports', \$context\)\) \{\n            return;#        if (false) {\n            return;#;
