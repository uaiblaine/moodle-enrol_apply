# NV2: the course queue opened to the deciding capability alone, the viewer turned away.
s#return has_capability\(groupscope::DECIDE, \$context\) \|\| has_capability\(groupscope::VIEW, \$context\);#return has_capability(groupscope::DECIDE, \$context);#;
