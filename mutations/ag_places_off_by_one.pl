# AG: let one more approval through than there are places.
s{return self::places_taken\(\$instance\) >= \$places;}{return self::places_taken(\$instance) > \$places;};
