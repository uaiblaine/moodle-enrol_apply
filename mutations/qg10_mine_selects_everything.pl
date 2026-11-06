# QG10: `mine` selects every application rather than the reader's groups'.
s#return groupscope::where\(self::own_groups\(\$listing\)\);#return ['1 = 1', []];#;
