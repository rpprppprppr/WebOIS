<?php
namespace Legacy\Iblock;

use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\ElementPropertyTable;

use Bitrix\Main\Entity\Query;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\DB\SqlExpression;

use Legacy\General\Constants;

class CoursesTable extends ElementTable
{
    public static function withSelect(Query $query)
    {
        $query->setSelect([
            'ID',
            'NAME',
            'CODE',
        ]);
    }

    public static function withRuntimeProperties(Query $query)
    {
        $query->registerRuntimeField(
            'DESCRIPTION_PROP',
            new ReferenceField(
                'DESCRIPTION_PROP',
                ElementPropertyTable::class,
                [
                    'ref.IBLOCK_ELEMENT_ID' => 'this.ID',
                    'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', Constants::PROP_DESCRIPTION)
                ]
            )
        );
        $query->addSelect('DESCRIPTION_PROP.VALUE', 'DESCRIPTION');

        $query->registerRuntimeField(
            'AUTHOR_PROP',
            new ReferenceField(
                'AUTHOR_PROP',
                ElementPropertyTable::class,
                [
                    'ref.IBLOCK_ELEMENT_ID' => 'this.ID',
                    'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', Constants::PROP_AUTHOR)
                ]
            )
        );
        $query->addSelect('AUTHOR_PROP.VALUE', 'AUTHOR_ID');

        $query->registerRuntimeField(
            'STUDENTS_PROP',
            new ReferenceField(
                'STUDENTS_PROP',
                ElementPropertyTable::class,
                [
                    'ref.IBLOCK_ELEMENT_ID' => 'this.ID',
                    'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', Constants::PROP_STUDENTS)
                ]
            )
        );
        $query->addSelect('STUDENTS_PROP.VALUE', 'STUDENT_ID');
    }

    public static function withFilter(Query $query, array $filter = [])
    {
        $defaultFilter = ['ACTIVE' => 'Y', 'IBLOCK_ID' => Constants::IB_COURSES];
        $query->setFilter(array_merge($defaultFilter, $filter));
    }

    public static function withOrder(Query $query, array $order = [])
    {
        if (empty($order)) {
            $order = ['ID' => 'ASC'];
        }
        $query->setOrder($order);
    }

    public static function withPage(Query $query, int $limit = 50, int $page = 1)
    {
        $query->setLimit($limit);
        $query->setOffset(($page - 1) * $limit);
    }
}