<?php
namespace API;

interface IAttributeValue {
    
    public function getName();

    public function getDataType();

    public function getDataSubType();

    public function getAttrType(): ?AttrTypeItem;

    public function isEdited();

    public function isLocalizable();

    public function isRequired();

    public function isUnique();

    public function isEmpty();

    public function getValue();

    public function setValue($value);

    public function isValid();

    public function save();

    public function afterSave();
}