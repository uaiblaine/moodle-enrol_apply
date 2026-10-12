# CF1C: every installed type is offered, textarea and picture included.
s{return array_intersect_key\(parent::get_available_field_types\(\), array_flip\(self::ALLOWED_TYPES\)\);}{return parent::get_available_field_types();}s;
