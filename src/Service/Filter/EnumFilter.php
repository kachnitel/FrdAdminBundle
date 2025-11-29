<?php

namespace Frd\AdminBundle\Service\Filter;

use Doctrine\ORM\QueryBuilder;

/**
 * Filter for enum fields with predefined choices.
 */
class EnumFilter extends AbstractFilter
{
    public function __construct(
        string $name,
        string $label,
        private string $field,
        private string $enumClass
    ) {
        // Get enum options
        $options = [];
        if (enum_exists($enumClass)) {
            foreach ($enumClass::cases() as $case) {
                $key = $case instanceof \BackedEnum ? $case->value : $case->name;
                $options[$key] = method_exists($case, 'displayValue')
                    ? $case->displayValue()
                    : $case->name;
            }
        }

        parent::__construct($name, $label, 'select', $options);
    }

    public function apply(QueryBuilder $qb, mixed $value): void
    {
        $paramName = str_replace('.', '_', $this->field);

        $qb->andWhere('e.' . $this->field . ' = :' . $paramName)
            ->setParameter($paramName, $value);
    }
}
