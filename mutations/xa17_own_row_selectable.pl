# XA17: the queue draws a selection box on the reader's own application, which every decision then skips in silence.
# Re-pointed when the checkbox and the headless reader came to share applications::selectable().
s#return \$this->can_decide\(\) && !queue::is_own_application\(\(int\) \$row->userid\);#return \$this->can_decide();#;
