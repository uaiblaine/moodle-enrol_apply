# GF4: every group is listed as needing attention.
s#static fn\(array \$group\): bool => \(\$group\['hasseats'\] && \$group\['full'\]\)#static fn(array \$group): bool => true#;
