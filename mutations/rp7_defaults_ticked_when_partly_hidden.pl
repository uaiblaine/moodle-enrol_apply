# RP7: a keyless application ticks the visible part of defaults that hold a group the reader may
# not choose, so the explicit post drops that group from the approval (RP-D19).
s#            if \(!\$route && \$defaults && !array_diff\(\$defaults, array_keys\(\$facts\)\)\) \{#            if (!\$route && \$defaults) {#;
