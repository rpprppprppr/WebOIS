<?php
namespace Legacy\Iblock;

use Bitrix\Main\Loader;

if (!Loader::includeModule('iblock')) {
    throw new \Exception('Модуль iblock не подключён');
}

use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\ElementPropertyTable;

use Bitrix\Main\Entity\Query;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\DB\SqlExpression;

use Legacy\General\Constants;

class ModulesTable extends ElementTable
{
    public static function withSelect(Query $query)
    {
        $query->setSelect([
            'ID',
            'NAME',
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
                    'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', Constants::MODULE_DESCRIPTION)
                ]
            )
        );
        $query->addSelect('DESCRIPTION_PROP.VALUE', 'DESCRIPTION');

        $query->registerRuntimeField(
            'TYPE_PROP',
            new ReferenceField(
                'TYPE_PROP',
                ElementPropertyTable::class,
                [
                    'ref.IBLOCK_ELEMENT_ID' => 'this.ID',
                    'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', Constants::MODULE_TYPE)
                ]
            )
        );
        $query->addSelect('TYPE_PROP.VALUE', 'TYPE');

        $query->registerRuntimeField(
            'MAX_SCORE_PROP',
            new ReferenceField(
                'MAX_SCORE_PROP',
                ElementPropertyTable::class,
                [
                    'ref.IBLOCK_ELEMENT_ID' => 'this.ID',
                    'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', Constants::MODULE_MAX_SCORE)
                ]
            )
        );
        $query->addSelect('MAX_SCORE_PROP.VALUE', 'MAX_SCORE');

        $query->registerRuntimeField(
            'DEADLINE_PROP',
            new ReferenceField(
                'DEADLINE_PROP',
                ElementPropertyTable::class,
                [
                    'ref.IBLOCK_ELEMENT_ID' => 'this.ID',
                    'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', Constants::MODULE_DEADLINE)
                ]
            )
        );
        $query->addSelect('DEADLINE_PROP.VALUE', 'DEADLINE');

        $query->registerRuntimeField(
            'COURSE_PROP',
            new ReferenceField(
                'COURSE_PROP',
                ElementPropertyTable::class,
                [
                    'ref.IBLOCK_ELEMENT_ID' => 'this.ID',
                    'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', Constants::MODULE_COURSE)
                ]
            )
        );
        $query->addSelect('COURSE_PROP.VALUE', 'COURSE_ID');

        $query->registerRuntimeField(
            'FILES_PROP',
            new ReferenceField(
                'FILES_PROP',
                ElementPropertyTable::class,
                [
                    'ref.IBLOCK_ELEMENT_ID' => 'this.ID',
                    'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', Constants::MODULE_FILE),
                ]
            )
        );

        $query->addSelect('FILES_PROP.VALUE', 'FILE_ID');
    }

    public static function withFilter(Query $query, array $filter = [])
    {
        $defaultFilter = ['ACTIVE' => 'Y', 'IBLOCK_ID' => Constants::IB_MODULES];
        $query->setFilter(array_merge($defaultFilter, $filter));
    }

    public static function withOrder(Query $query, array $order = [])
    {
        if (empty($order)) $order = ['ID' => 'ASC'];
        $query->setOrder($order);
    }

    public static function withPage(Query $query, int $limit = 50, int $page = 1)
    {
        $query->setLimit($limit);
        $query->setOffset(($page - 1) * $limit);
    }

    public static function addModule(array $fields): int
    {
        if (!\CModule::IncludeModule('iblock')) {
            throw new \Exception('Не удалось подключить модуль iblock');
        }

        $arFields = [
            'IBLOCK_ID' => Constants::IB_MODULES,
            'NAME' => $fields['NAME'] ?? '',
            'ACTIVE' => 'Y',
        ];

        $el = new \CIBlockElement();
        $id = $el->Add($arFields);
        if (!$id) throw new \Exception('Не удалось создать элемент модуля: ' . $el->LAST_ERROR);

        $props = [
            Constants::MODULE_DESCRIPTION => $fields['DESCRIPTION'] ?? '',
            Constants::MODULE_TYPE => $fields['TYPE'] ?? '',
            Constants::MODULE_MAX_SCORE => $fields['MAX_SCORE'] ?? 0,
            Constants::MODULE_DEADLINE => $fields['DEADLINE'] ?? '',
            Constants::MODULE_COURSE => $fields['COURSE_ID'] ?? 0,
        ];

        \CIBlockElement::SetPropertyValuesEx($id, Constants::IB_MODULES, $props);

        if (!empty($fields['FILES'])) {
            $files = $fields['FILES'];
            \CIBlockElement::SetPropertyValuesEx($id, Constants::IB_MODULES, [Constants::MODULE_FILE => $files]);
        }

        return (int)$id;
    }

    public static function updateModule(int $moduleId, array $fields): bool
    {
        if (!$moduleId) {
            throw new \Exception('Не передан ID модуля');
        }

        if (!\CModule::IncludeModule('iblock')) {
            throw new \Exception('Не удалось подключить модуль iblock');
        }

        $el = new \CIBlockElement();

        $elementFields = [];

        if (!empty($fields['NAME'])) {
            $elementFields['NAME'] = trim($fields['NAME']);
        }

        if (!empty($elementFields)) {
            if (!$el->Update($moduleId, $elementFields)) {
                throw new \Exception('Ошибка обновления модуля: ' . $el->LAST_ERROR);
            }
        }

        $props = [];

        if (array_key_exists('DESCRIPTION', $fields)) {
            $props[Constants::MODULE_DESCRIPTION] = $fields['DESCRIPTION'];
        }

        if (array_key_exists('TYPE', $fields)) {
            $props[Constants::MODULE_TYPE] = $fields['TYPE'];
        }

        if (array_key_exists('MAX_SCORE', $fields)) {
            $props[Constants::MODULE_MAX_SCORE] = (int)$fields['MAX_SCORE'];
        }

        if (array_key_exists('DEADLINE', $fields)) {
            $props[Constants::MODULE_DEADLINE] = $fields['DEADLINE'];
        }

        if (array_key_exists('COURSE_ID', $fields)) {
            $props[Constants::MODULE_COURSE] = (int)$fields['COURSE_ID'];
        }

        if (array_key_exists('FILES', $fields)) {
            $props[Constants::MODULE_FILE] = $fields['FILES'] ?: false;
        }

        if (!empty($props)) {
            \CIBlockElement::SetPropertyValuesEx(
                $moduleId,
                Constants::IB_MODULES,
                $props
            );
        }

        return true;
    }

    public static function getModuleById(int $moduleId): ?array
    {
        if ($moduleId <= 0) {
            return null;
        }

        $query = self::query();
        self::withSelect($query);
        self::withRuntimeProperties($query);
        self::withFilter($query, ['ID' => $moduleId]);

        return $query->exec()->fetch() ?: null;
    }
}
