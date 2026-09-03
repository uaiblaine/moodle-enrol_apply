# CN: drop the comment cell's own heading. Above the breakpoint nothing changes - the thead says
# it - so this is invisible on a desktop and leaves every card below it showing a wall of values
# under no headings at all. It is markup only a stylesheet and a screen reader consume, which is
# the kind that rots unnoticed.
s{        return \$this->card_label\(\$label\) \. format_text\(\$row->applycomment, FORMAT_PLAIN\);}{        return format_text(\$row->applycomment, FORMAT_PLAIN);}s;
