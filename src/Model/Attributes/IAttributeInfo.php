<?php

namespace API\Model;


interface IAttributeInfo {
    /**
     * @return string Vrátí typ
     */
    public function getType(): string;

    /**
     * @return string|null Vrátí podtyp
     */
    public function getSubtype(): ?string;

    /**
     * @return string Vrátí název
     */
    public function getName(): string;

    /**
     * @return string Vrátí textový identifikátor
     */
    public function getTextId(): string;

    /**
     * @return string Vrátí alias
     */
    public function getAlias(): string;

    /**
     * @return string Vrátí popis
     */
    public function getDescription(): string;

    /**
     * @return mixed Vrátí specifikaci
     */
    public function getSpecification();

    /**
     * @return string|null Vrátí speciální ID
     */
    public function getSpecialId(): ?string;

    /**
     * Nastaví specifikace
     * @param mixed $spec Specifikace
     * @return void
     */
    public function setSpecification($spec): void;

    /**
     * @return FlagsAttributeInfo Vrátí flagy atributu
     */
    public function flags(): FlagsAttributeInfo;

    /**
     * @return MethodsAttributeInfo Vrátí objekt s definicí metod
     */
    public function methods(): MethodsAttributeInfo;
}