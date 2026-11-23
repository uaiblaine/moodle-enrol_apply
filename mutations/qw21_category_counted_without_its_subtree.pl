# QW21: a category's count is its own courses' alone, so a parent category lists more than it counts.
s#foreach \(explode\('/', trim\(\(string\) \$category->path, '/'\)\) as \$ancestor\) \{#foreach ([(string) \$category->id] as \$ancestor) {#;
