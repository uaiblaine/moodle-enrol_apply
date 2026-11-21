# XA45: every standard text field gets maxlength 255 again, whatever its column holds.
s#\$attributes\['maxlength'\] = fields::max_length\(\$key\);#\$attributes['maxlength'] = 255;#;
