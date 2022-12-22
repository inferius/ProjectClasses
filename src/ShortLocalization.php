<?php

class L {
    public static function t($key, $def = "") { $k = Localization::getText($key, is_array($def) ? $def : null); if (empty($k) && !is_array($def)) return $def; return $k; }
    public static function pt($key, $def = "") { $k = Localization::getPlainText($key, is_array($def) ? $def : null); if (empty($k) && !is_array($def)) return $def; return $k; }

    public static function et($key) { echo Localization::getText($key); }
    public static function ept($key) { echo Localization::getPlainText($key); }
}