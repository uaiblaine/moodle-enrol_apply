# RQ1: the profile fields' per-instance required flag is ignored, so no profile field reads as required.
s#            if \(\$profile->is_required\(\$key\)\) \{#            if (false) {#;
