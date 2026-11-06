# RP7: a keyless application ticks the method's groups only when the reader may choose every one
# of them, the rule from before confirm_enrolment() kept the rest (RP-D19).
s#            if \(!\$route\) \{#            if (!\$route && !array_diff(\$defaults, array_keys(\$facts))) {#;
