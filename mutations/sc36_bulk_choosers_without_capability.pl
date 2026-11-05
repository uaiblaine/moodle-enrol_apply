# SC36: the participants page's bulk form offers the choosers without enrol/apply:choosedecision.
s#if \(!has_capability\('enrol/apply:choosedecision', \$coursecontext\)\) \{#if (false) {#;
