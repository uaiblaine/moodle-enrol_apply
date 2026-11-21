# RB7: the Decided and Group cells, which name groups under their visibility rule, offer
# aggregation again, so a grouped custom report would count the rows the cells withhold.
s#\n *->set_disabled_aggregation_all\(\)(\n *->add_attributes\(\['class' => 'enrol_apply-linebreaks'\]\)\n *->add_callback\(\[formatter::class, '(?:decided|group)'\]\))#$1#g;
