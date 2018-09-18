<?php
namespace AppBundle\Util;

class RecordUtil
{
    public static function getPreferredTerm($terms)
    {
        foreach($terms as $term) {
            if($term['pref'] === 'preferred') {
                return $term['term'];
            }
        }
        return null;
    }

    public static function getFieldLabel($field, $dataDef)
    {
        $label = '';
        if (strpos($field, '/')) {
            $parts = explode('/', $field);
            $label = $dataDef[$parts[0]][$parts[1]]['label'];
        } else {
            $def = $dataDef[$field];
            if (array_key_exists('label', $def)) {
                $label = $def['label'];
            }
            elseif (array_key_exists('term', $def)) {
                $label = $def['term']['label'];
            }
        }
        return $label;
    }
}
