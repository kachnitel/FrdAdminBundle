<?php

namespace Kachnitel\AdminBundle\Service\Filter;

/**
 * Base implementation of FilterInterface with common functionality.
 */
abstract class AbstractFilter implements FilterInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        protected string $name,
        protected string $label,
        protected string $type = 'text',
        protected array $options = []
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
