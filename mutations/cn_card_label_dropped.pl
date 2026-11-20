# CN: drop the comment cell's own heading. Above the breakpoint nothing changes - the thead says
# it - so this is invisible on a desktop and leaves every card below it showing a wall of values
# under no headings at all. It is markup only a stylesheet and a screen reader consume, which is
# the kind that rots unnoticed. Repointed when the comment gained its two-line clamp (Q1).
s{        return \$this->card_label\(\$label\) \. html_writer::div\(\n            format_text\(\$comment, FORMAT_PLAIN\),}{        return html_writer::div(\n            format_text(\$comment, FORMAT_PLAIN),}s;
