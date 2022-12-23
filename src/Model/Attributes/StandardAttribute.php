<?php

namespace API\Model;


class StandardAttribute implements IAttributeInfo {
    /** @var string $name */
    private $name;
    /** @var string $subtype */
    private $subtype;
    /** @var string $type */
    private $type;
    /** @var string $text_id */
    private $text_id;
    /** @var string $alias */
    private $alias;
    /** @var string $description */
    private $description;
    /** @var mixed $specification */
    private $specification;

    /** @var FlagsAttributeInfo $flags; */
    private $flags;
    /** @var MethodsAttributeInfo $methods */
    private $methods;

    /** @var ?string $special_id */
    private $special_id;

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     */
    public function flags(): FlagsAttributeInfo
    {
        return $this->flags;
    }

    /**
     * @inheritDoc
     */
    public function methods(): MethodsAttributeInfo
    {
        return $this->methods;
    }

    /**
     * @inheritDoc
     */
    public function getSubtype(): ?string
    {
        return $this->subtype;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getTextId(): string
    {
        return $this->text_id;
    }

    /**
     * @inheritDoc
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @inheritDoc
     */
    public function getSpecification()
    {
        return $this->specification;
    }

    /**
     * @inheritDoc
     */
    public function getSpecialId(): ?string {
        return $this->special_id;
    }

    /**
     * @inheritDoc
     */
    public function setSpecification($spec): void
    {
        $this->specification = $spec;
    }

    public function __construct($row_data) {
        $this->name = $row_data["name"];
        $this->alias = $row_data["attr_alias"];
        $this->type = $row_data["data_type"];
        $this->subtype = $row_data["data_subtype"];
        $this->description = $row_data["description"];
        $this->text_id = $row_data["text_id"];
        $this->special_id = $row_data["special_type_id"];


        $this->specification = null;
        $this->flags = new FlagsAttributeInfo($row_data);
        $this->methods = new MethodsAttributeInfo($row_data);
    }
}