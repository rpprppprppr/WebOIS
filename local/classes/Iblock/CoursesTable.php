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
                    'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', Constants::COURSE_DESCRIPTION)
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
                    'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', Constants::COURSE_AUTHOR)
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
                    'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', Constants::COURSE_STUDENTS)
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

    public static function addCourse(array $fields): int
    {
        if (!\CModule::IncludeModule('iblock')) {
            throw new \Exception('Не удалось подключить модуль iblock');
        }

        $arFields = [
            'IBLOCK_ID' => Constants::IB_COURSES,
            'NAME' => $fields['NAME'] ?? '',
            'CODE' => $fields['CODE'] ?? '',
            'ACTIVE' => 'Y',
        ];

        $el = new \CIBlockElement();
        $id = $el->Add($arFields);

        if (!$id) {
            throw new \Exception('Не удалось создать элемент курса: ' . $el->LAST_ERROR);
        }

        if (!empty($fields['PROP_AUTHOR']['ID'])) {
            \CIBlockElement::SetPropertyValuesEx(
                $id,
                Constants::IB_COURSES,
                [Constants::COURSE_AUTHOR => $fields['PROP_AUTHOR']['ID']]
            );
        }

        if (!empty($fields['DESCRIPTION'])) {
            \CIBlockElement::SetPropertyValuesEx($id, Constants::IB_COURSES, [
                Constants::COURSE_DESCRIPTION => $fields['DESCRIPTION']
            ]);
        }

        if (!empty($fields['STUDENT_ID'])) {
            \CIBlockElement::SetPropertyValuesEx($id, Constants::IB_COURSES, [
                Constants::COURSE_STUDENTS => $fields['STUDENT_ID']
            ]);
        }

        return (int)$id;
    }
}