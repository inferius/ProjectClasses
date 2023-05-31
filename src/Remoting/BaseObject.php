<?php

namespace API;

use API\Exceptions\ValidationException;
use API\Model\DataTypes;

class BaseObject {
    /** @var string */
    protected $objectName;
    /** @var int */
    protected $currentLanguage = 2;

    protected $is_edited;

    /** @var \DateTime */
    protected $created;
    /** @var \DateTime */
    protected $edited;

    /** @var ?int */
    protected $id;

    protected $use_transaction = true;

    /** @var Model\ClassDescription $class_info */
    protected $class_info;

    /** @var IAttributeValue[]  */
    protected $values = [];

    /** @var \API\Event\EventManagerInterface */
    protected $_eventManager;

    /** @return \API\Event\EventManagerInterface */
    public function eventManager() {
        return $this->_eventManager;
    }

    public function setUseTransaction(bool $lang) {
        $this->use_transaction = $lang;
    }
    public function getUseTransaction(): bool {
        return $this->use_transaction;
    }

    /**
     * Datum a čas vytvoření objektu
     * @return \DateTime|null
     */
    public function getCreated(): ?\DateTime {
        return $this->created;
    }

    /**
     * Nastavení ID jazyka
     * @param int $lang
     * @return void
     */
    public function setLanguage(int $lang) {
        $this->currentLanguage = $lang;

        $this->reload();
        foreach ($this->values as $key => $attr) {
            if ($attr instanceof \API\ClassAttributeValue) {
                if (!empty($attr->getValue())) $attr->getValue()->setLanguage($lang);
            }
        }
    }

    /**
     * ID aktuálního jazyka použítého pro objekt
     * @return int
     */
    public function getLanguage() {
        return $this->currentLanguage;
    }

    /**
     * Informace o tom, zda je objekt rozeditovaný
     * @return true
     */
    public function isEdited() {
        return $this->is_edited;
    }

    /**
     * Informace o tom zda je objekt nový
     * @return bool
     */
    public function isNew() {
        return empty($this->id);
    }

    /**
     * Vrátí ID objektu
     * @return int
     */
    public function getId(): int {
        return $this->id;
    }

    /**
     * Vrátí název objektu
     * @return string
     */
    public function getObjectName(): string {
        return $this->objectName;
    }

    /**
     * Vráti informace o třídě
     * @return Model\ClassDescription
     */
    public function getClassInfo(): \API\Model\ClassDescription {
        return $this->class_info;
    }

    public function __construct(string $object_type, $id = null, $lang_id = null) {

        $this->_eventManager = new \API\Event\EventManager();

        $cd = \API\Model\ClassDescription::get($object_type);

        if (empty($cd)) {
            throw new \InvalidArgumentException("Object type '$object_type' not found");
        }

        $this->class_info = $cd;
        $this->objectName = $object_type;
        $this->id = $id;

        if (empty($lang_id) && !empty(\API\Configurator::$editLanguageId)) $this->currentLanguage = \API\Configurator::$editLanguageId;
        if (empty($lang_id) && empty(\API\Configurator::$editLanguageId)) $this->currentLanguage = \API\Configurator::$currentLanguageId;
        else $this->currentLanguage = $lang_id;


        if (empty($id)) {
            $this->is_edited = true;
            $this->created = new \DateTime();
            $this->edited = null;
            $this->id = null;
        }

        $this->reload();
    }

    /**
     * Vrati objekt na zaklade atributu
     * @param string $object_type
     * @param string $attrname
     * @param $value
     * @return static|null
     */
    public static function getObjectByAttr(string $object_type, string $attrname, $value): ?self {


        if (strpos($attrname, ".") !== FALSE) {
            return null;
        }

        $cd = \API\Model\ClassDescription::get($object_type);
        if (empty($cd->getAttributeInfo($attrname))) return null;

        $table_name = $cd->table()->getTableName();
        $attr = $cd->table()->getColumnNameId();

        if ($cd->getAttributeInfo($attrname)->flags()->isLocalizable()) {
            $table_name = $cd->table()->getTableName();
            $attr = $cd->table()->getColumnNameParentId();
        }

        $id = \API\Configurator::$connection->fetchField("SELECT $attr FROM $table_name WHERE $attrname = ?", $value);

        if (empty($id)) return null;

        return new self($object_type, $id);
    }

    /**
     * Přenačte data objektu z db
     * @return void
     */
    public function reload() {
        $cd = \API\Model\ClassDescription::get($this->getObjectName());

        $attrs = [
            "t.id as id",
            "t.created as created",
            "t.edited as edited"
        ];

        $plain_attrs = ["id", "created", "edited"];

        $data_values = [];

        foreach ($cd->getAttributes() as $attr) {
            $key = $attr->getAlias();

            if ($attr->flags()->isLocalizable()) {
                $attrs[] = "tl.{$key} as $key";
            }
            else {
                $attrs[] = "t.{$key} as $key";
            }
            $plain_attrs[] = $key;
            $data_values[$key] = $attr;
        }

        $sql_column = join(",", $attrs);

        $data = \API\Configurator::$connection->fetch("SELECT $sql_column FROM {$cd->table()->getTableName()} as t LEFT JOIN (SELECT * FROM {$cd->table()->getTableLangName()} WHERE lang_id = ?) AS tl ON tl.parent_id = t.id WHERE t.id = ?", $this->currentLanguage, $this->id);

        foreach ($plain_attrs as $key) {
            if (in_array($key, ["id", "created", "edited"])) {

                if ($key == "id") {
                    $this->values[$key] = new ReadOnlyAttributeValue($data == null ? null : $data[$key], $key);
                }
                continue;
            }

            $this->values[$key] = AttributeManager::get($data == null ? null : $data[$key], $data_values[$key], $cd, $this);

            $this->values[$key]->eventManager()->attach("change", function() {
                $this->is_edited = true;
                $this->eventManager()->trigger("change", $this);
            });
        }

        if (!$this->isNew()) {
            $this->created = @\DateTime::createFromFormat("Y-m-d", $data["created"]);
            $this->edited = @\DateTime::createFromFormat("Y-m-d", $data["edited"]);;
        }
    }

    /**
     * Vrátí objekt typu IAttributeValue
     * @param string $attrName Název atributu
     * @return IAttributeValue|null
     */
    public function getItem(string $attrName): ?IAttributeValue {
        if (self::isBindingAttribute($attrName)) {
            $attrs = self::getFirstAttr($attrName);
            /*if (empty($this->values[$attrs["attrName"]])) {
                trigger_error("Attribute '{$attrName}' on class '{$this->objectName}' not exist!", E_USER_WARNING);
                return null;
            }*/
            if ($this->values[$attrs["attrName"]]->isEmpty()) return $this->values[$attrs["attrName"]];
            if (empty($this->values[$attrs["attrName"]]->getValue())) return null;
            return $this->values[$attrs["attrName"]]->getValue()->getItem($attrs["next"]);
        }
        /*if (empty($this->values[$attrName])) {
            trigger_error("Attribute '{$attrName}' on class '{$this->objectName}' not exist!", E_USER_WARNING);
            return null;
        }*/
        return $this->values[$attrName];
    }

    /**
     * Vrátí hodnotu atributu
     * @param string $attrName Název atributu
     * @return mixed|null
     */
    public function getValue(string $attrName) {
        $attrdata = $this->getItem($attrName);
        if (empty($attrdata)) return null;
        return $attrdata->getValue();
    }

    public function setValue($attrName, $value) {
        $this->is_edited = true;
        if (self::isBindingAttribute($attrName)) {
            $attrNames = BaseObject::getFirstAttr($attrName);
            $firstAttrName = $attrNames["attrName"];
            $nextAttrName = $attrNames["next"];

            $ai = $this->class_info->getAttributeInfo($attrNames["attrName"]);
            if ($ai->getType() !== \API\Model\DataTypes::CLASSES) {
                throw new \InvalidArgumentException("Not set binding attribute to value where is not type Class");
            }
            if ($this->values[$attrNames["attrName"]]->isEmpty()) {
                /** @var \API\Model\ClassDescription $ci */
                //$ci = $ai->getSpecification();
                $o = new \API\BaseObject($ai->getSubtype());
                $this->values[$attrNames["attrName"]]->setValue($o);

                // TODO: Vymyslet lepe, nejake objektove transformace nebo neco, neni vhodne cpat do obecne metody nejake vyjimky
                // specialita pro url
                if ($ai->getSubtype() == "url_manager") {
                    $url_object = $o;

                    $this->eventManager()->attach("saved", function() use (&$url_object) {
                        $url_object->setValue("table_name", "obj:" . $this->class_info->getTextId());
                        $url_object->setValue("table_id", $this->getId());
                        $url_object->save();
                    }, 1, true);
                }

            }
            else {
                $o = $this->values[$attrNames["attrName"]]->getValue();
            }

            $o->setValue($attrNames["next"], $value);
        }
        else {
            $this->values[$attrName]->setValue($value);
        }
    }

    public static function getFirstAttr($attrName) {
        if (self::isBindingAttribute($attrName)) {
            $r = explode(".", $attrName, 2);
            return [ "attrName" => $r[0], "next" => $r[1] ];
        }
        else return [ "attrName" => $attrName, "next" => null ];
    }

    public static function isBindingAttribute($attrName) {
        return strpos($attrName, ".");
    }

    /**
     * Provede smazání objektu
     * @return void
     */
    public function delete() {
        \API\Configurator::$connection->query("DELETE FROM {$this->class_info->table()->getTableName()} WHERE id = ?", $this->id);
        \API\Configurator::$connection->query("DELETE FROM {$this->class_info->table()->getTableLangName()} WHERE parent_id = ?", $this->id);

        foreach ($this->values as $val) {
            $val->delete();
        }
    }

    /**
     * @throws ValidationException
     */
    public function save() {
        if (!$this->isEdited()) return;

        try {
            if ($this->use_transaction) \API\Configurator::$connection->beginTransaction();

            $loc_table = [];
            $n_table = [];

            $error_attrs = [];

            foreach ($this->values as $key => $val) {
                if ($val->isEdited() || $this->isNew()) {
                    if ($val->isRequired() && $val->isEmpty()) {
                        $error_attrs[] = [
                            "attrName" => $key,
                            "error" => "required"
                        ];
                        continue;
                    }
                    if (!$val->isValid()) {
                        $error_attrs[] = [
                            "attrName" => $key,
                            "error" => "not_valid"
                        ];
                        continue;
                    }
                    if ($val->isUnique() && !$val->isEmpty()) {
                        $ai = $this->getClassInfo()->getAttributeInfo($key);
                        // Nektere typy nepodporuji unikatnost
                        if (!in_array($ai->getType(), [ DataTypes::CLASSES, DataTypes::FILE, DataTypes::ENUM ])) {
                            $tbl = $val->isLocalizable() ? $this->getClassInfo()->table()->getTableLangName() : $this->getClassInfo()->table()->getTableName();
                            $whr = "";
                            if (!$this->isNew()) $whr .= " AND " . ($val->isLocalizable() ? "parent_id" : "id") . " != " . $this->getId();

                            $exist = \API\Configurator::$connection->fetch("SELECT * FROM $tbl WHERE {$ai->getAlias()} = ? $whr", $val->getValue());

                            if (!is_null($exist)) {
                                $error_attrs[] = [
                                    "attrName" => $key,
                                    "error" => "not_unique"
                                ];
                                continue;
                            }
                        }
                    }

                    if ($val->isLocalizable()) {
                        $loc_table[$key] = $val->save();
                    }
                    else {
                        $n_table[$key] = $val->save();
                    }

                }
            }

            if (!empty($error_attrs)) {
                throw new \API\Exceptions\ValidationException("Object wasn't saved. Validation error", 0, $error_attrs);
            }

            $loc_table["lang_id"] = $this->getLanguage();

            if ($this->isNew()) {
                $n_table["created"] = \API\Configurator::$connection::literal("now()");
                $this->created = new \DateTime();

                \API\Configurator::$connection->query("INSERT INTO {$this->class_info->table()->getTableName()} ", $n_table);

                $i_id = \API\Configurator::$connection->getInsertId();
                $loc_table["parent_id"] = $i_id;

                \API\Configurator::$connection->query("INSERT INTO {$this->class_info->table()->getTableLangName()} ", $loc_table);

                $this->id = $i_id;
                $this->values["id"] = new ReadOnlyAttributeValue($i_id, "id");
            }
            else {
                $n_table["edited"] = \API\Configurator::$connection::literal("now()");
                $this->edited = new \DateTime();

                \API\Configurator::$connection->query("UPDATE {$this->class_info->table()->getTableName()} SET", $n_table, "WHERE id = ?", $this->id);
                // kontrola existence jazykove verze
                if (empty(\API\Configurator::$connection->fetch("SELECT id FROM {$this->class_info->table()->getTableLangName()} WHERE parent_id = ? AND lang_id = ?", $this->id, $this->getLanguage()))) {
                    $loc_table["parent_id"] = $this->id;
                    \API\Configurator::$connection->query("INSERT INTO {$this->class_info->table()->getTableLangName()} ", $loc_table);
                }
                else {
                    \API\Configurator::$connection->query("UPDATE {$this->class_info->table()->getTableLangName()} SET", $loc_table, "WHERE parent_id = ? AND lang_id = ?", $this->id, $this->getLanguage());
                }
            }
            $this->is_edited = false;

            foreach ($this->values as $key => $val) {
                $val->afterSave();
            }
        }
        catch (\API\Exceptions\ValidationException $e) {
            throw $e;
        }
        finally {
            if ($this->use_transaction) {
                if ($this->is_edited == false) \API\Configurator::$connection->commit();
                else \API\Configurator::$connection->rollBack();
            }
            if ($this->is_edited == false) {
                $this->eventManager()->trigger("saved", $this);
            }

        }
    }

    public function __destruct() {
        $this->eventManager()->clearListeners("change");
    }
}