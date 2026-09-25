# F2E: escape a missing field's label before the template's double stash escapes it again.
s#\$fields\[\] = \['label' => \$field\['label'\]\];#\$fields[] = ['label' => s(\$field['label'])];#s;
