# CD: build the base url without the scope. Paging and sorting emit real anchors from it, so the
# second page of one method's queue would land on the site-wide one - silently, and only for the
# operator who turned a page.
#
# REPOINTED 2026-09-04. The previous pattern named `$this->baseurl = $this->scope->url;`, a line
# guess_base_url() stopped having when it was rebuilt around url_params(). It matched nothing and
# so guarded nothing, which a full `mdl mutate --dry-run` is what surfaces - the `--only` runs the
# field-filter slice was verified with never applied it.
s{        if \(\$this->scope->enrolid\) \{\n            \$params\['id'\] = \(int\) \$this->scope->enrolid;\n        \}\n}{}s;
