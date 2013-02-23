<?php

namespace Jade;

class Filter {
    protected static function getTextOfNodes($data) {
        if (is_object($data)) {
            $new_str = '';
            foreach ($data->nodes as $n) {
                $new_str .= $n->value . "\n";
            }
            $data = $new_str;
        }
        return $data;
    }

    public static function cdata($data) {
        if (is_object($data)) {
            $new_data = '';
            foreach ($data->nodes as $n) {
                //TODO: original doing interpolation here
                $new_data .= $n->value . "\n";
            }
            $data = $new_data;
        }
        return "<!CDATA[\n" . $data . "\n]]>";
    }

    public static function css($data) {
        return '<style type="text/css">' . self::getTextOfNodes($data) . '</style>';
    }

    public static function javascript($data) {
        return '<script type="text/javascript">' . self::getTextOfNodes($data) . '</script>';
    }

    public static function php($data) {
        if (is_object($data)) {
            $new_data = '';
            foreach ($data->nodes as $n) {
                if (preg_match('/^[[:space:]]*\|(.*)/', $n->value, $m)) {
                    $new_data = $m[1];
                } else {
                    $new_data .= $n->value . "\n";
                }
            }
            $data = $new_data;
        }
        return '<?php ' . $data . ' ?>';
    }
}
