<?php

namespace API;

class AttributeManager {

    public static function get($value, $attrInfo): AttributeValue {
        global $config;
        switch ($attrInfo["data_type"]) {
            case "file": 
                require_once($config["path"]["absolute"]["framework"]["php"] . "/Remoting/AttrTypes/FileAttributeValue.new.php");
                return new FileAttributeValue($value, $attrInfo);
            case "class": 
                    require_once($config["path"]["absolute"]["framework"]["php"] . "/Remoting/AttrTypes/ClassAttributeValue.php");
                    return new ClassAttributeValue($value, $attrInfo);
                
            default:
                require_once($config["path"]["absolute"]["framework"]["php"] . "/Remoting/AttrTypes/AttributeValue.php");
                return new AttributeValue($value, $attrInfo);
        }
    }

    
}