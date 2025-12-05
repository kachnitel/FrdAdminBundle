<?php

namespace Kachnitel\AdminBundle\Service\Filter;

use Doctrine\ORM\QueryBuilder;

/**
 * Simple field equality filter.
 */
class FieldFilter extends AbstractFilter
{
    public function __construct(
        string $name,
        string $label,
        private string $field,
        private string $operator = '=',
        string $type = 'text',
        array $options = []
    ) {
        parent::__construct($name, $label, $type, $options);
    }

    public function apply(QueryBuilder $qb, mixed $value): void
    {
        $paramName = str_replace('.', '_', $this->field);

        switch ($this->operator) {
            case 'LIKE':
                $qb->andWhere($qb->expr()->like('e.' . $this->field, ':' . $paramName))
                    ->setParameter($paramName, '%' . $value . '%');
                break;

            case 'IN':
                $qb->andWhere($qb->expr()->in('e.' . $this->field, ':' . $paramName))
                    ->setParameter($paramName, is_array($value) ? $value : [$value]);
                break;

            case '>=':
            case '<=':
            case '>':
            case '<':
                $qb->andWhere('e.' . $this->field . ' ' . $this->operator . ' :' . $paramName)
                    ->setParameter($paramName, $value);
                break;

            default:
                $qb->andWhere('e.' . $this->field . ' = :' . $paramName)
                    ->setParameter($paramName, $value);
        }
    }
}
