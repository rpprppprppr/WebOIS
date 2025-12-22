<?php

namespace Legacy\Iblock;

use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\ElementPropertyTable;

use Bitrix\Main\DB\SqlExpression;
use Bitrix\Main\Entity\Query;
use Bitrix\Main\Entity\ReferenceField;

use Legacy\General\Constants;

class TestBlockTable extends ElementTable
{
    public static function withSelect(Query $query): void
    {
        $query->setSelect([
            'ID',
            'NAME',
            'CODE',
            'ACTIVE',
            'ACTIVE_FROM',
            'ACTIVE_TO',
            'DATE_CREATE',
            'TIMESTAMP_X',
            'SORT',
        ]);
    }

    public static function withRuntimeProperties(Query $query): void
    {
        $query->registerRuntimeField(
            'PROPERTY',
            new ReferenceField(
                'PROPERTY',
                ElementPropertyTable::class,
                [
                    'ref.IBLOCK_ELEMENT_ID' => 'this.ID',
                    'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', Constants::PROP_TEST)
                ]
            )
        );

        $query->addSelect('PROPERTY.VALUE', 'PROPERTY_VALUE');
    }

    public static function withPage(Query $query, $limit, $page): void
    {
        $query->setLimit($limit);
        $query->setOffset(($page - 1) * $limit);
    }
}