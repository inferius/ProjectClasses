<?php
namespace API;

interface IAttributeValue {
    /**
     * Vrátí název
     * @return string
     */
    public function getName(): string;

    /**
     * Vrátí datový typ
     * @return string
     */
    public function getDataType(): string;

    /**
     * Vrátí rozšiřující datový typ
     * @return string
     */
    public function getDataSubType(): ?string;

    /**
     * Vrátí typovou třídu
     * @return AttrTypeItem|null
     */
    public function getAttrType(): ?AttrTypeItem;

    /**
     * Je rozeditovaný
     * @return bool
     */
    public function isEdited(): bool;

    /**
     * Je lokalizovatelný
     * @return bool
     */
    public function isLocalizable(): bool;

    /**
     * Je povinný
     * @return bool
     */
    public function isRequired(): bool;

    /**
     * Je unikátní
     * @return bool
     */
    public function isUnique(): bool;

    /**
     * Je prázdny
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Vrátí hodnotu atributu
     * @return mixed
     */
    public function getValue();

    /**
     * Nastaví hodnotu atributu
     * @param mixed $value
     * @return void
     */
    public function setValue($value): void;

    /**
     * Je validni
     * @return bool
     */
    public function isValid(): bool;

    /**
     * Uloží hodnot
     * @return mixed
     */
    public function save();

    /**
     * Metoda zavolaná po uložení objektu
     * @return void
     */
    public function afterSave(): void;

    /**
     * Metoda zavolána při smazání objektu
     * @return void
     */
    public function delete(): void;
}