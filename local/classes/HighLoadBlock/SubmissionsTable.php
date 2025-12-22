<?php
namespace Legacy\HighLoadBlock;

use Legacy\General\Constants;

class SubmissionsTable
{
    protected static function getHLId(): int
    {
        return Constants::HLBLOCK_SUBMISSIONS;
    }

    public static function withSelect(array &$select): void
    {
        if (empty($select)) {
            $select = [
                'ID',
                'UF_STUDENT',
                'UF_MODULE',
                'UF_SCORE',
                'UF_STATUS',
                'UF_ANSWER',
                'UF_LINK',
                'UF_REVIEW_COMMENT',
                'UF_DATE_SUBMITTED',
                'UF_FILE'
            ];
        }
    }

    public static function withFilter(array &$filter, array $customFilter = []): void
    {
        $filter = array_merge($filter, $customFilter);
    }

    public static function withOrder(array &$order, array $customOrder = []): void
    {
        if (empty($customOrder)) {
            $order = ['ID' => 'ASC'];
        } else {
            $order = $customOrder;
        }
    }

    public static function withPage(array &$params, int $limit = 50, int $page = 1): void
    {
        $params['limit'] = $limit;
        $params['offset'] = ($page - 1) * $limit;
    }

    public static function getList(array $params = []): array
    {
        $filter = $params['filter'] ?? [];
        self::withFilter($filter, $params['custom_filter'] ?? []);

        $order = $params['order'] ?? [];
        self::withOrder($order, $params['custom_order'] ?? []);

        self::withPage($params, $params['limit'] ?? 50, $params['page'] ?? 1);

        $hlId = self::getHLId();
        $hlId = self::getHLId();
        $res = Entity::getInstance()->getList($hlId, [
            'filter' => $filter,
            'limit' => $params['limit'] ?? 50,
            'offset' => $params['offset'] ?? 0,
        ]);

        return $res ?: [];
    }

    public static function getRow(array $params): ?array
    {
        $params['limit'] = 1;
        $rows = self::getList($params);
        return $rows ? current($rows) : null;
    }

    public static function add(array $fields): int
    {
        $hlId = self::getHLId();
        return Entity::getInstance()->add($hlId, $fields);
    }

    public static function update(int $id, array $fields): bool
    {
        $hlId = self::getHLId();
        return Entity::getInstance()->update($hlId, $id, $fields);
    }

    public static function delete(int $id): bool
    {
        $hlId = self::getHLId();
        return Entity::getInstance()->delete($hlId, $id);
    }

    public static function getFields(): array
    {
        $hlId = self::getHLId();
        return Entity::getInstance()->getFields($hlId) ?: [];
    }
}